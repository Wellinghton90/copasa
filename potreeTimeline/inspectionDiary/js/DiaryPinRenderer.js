/**
 * Renderiza o pin do diário na nuvem de pontos.
 * Converte UTM → cena, mostra/esconde mesh e permite reposicionar por arraste.
 */

import * as THREE from '../../potree/libs/three.js/build/three.module.js';
import { loadPix4dOffset } from '../../js/config/viewer-config.js';

function getConfig() {
    return typeof window !== 'undefined' && window.NUVEM_CONFIG ? window.NUVEM_CONFIG : {};
}

const DEFAULT_PIN_COLOR = 0x025e73;

/**
 * Converte coordenada UTM para posição na cena: pCena = utm - pix4dOffset + offsetProjeto.
 * @param {{ x: number, y: number, z: number }} utm
 * @param {[number, number, number]} pix4dOffset
 * @param {[number, number, number]} offsetProjeto
 * @returns {THREE.Vector3}
 */
function utmToScene(utm, pix4dOffset, offsetProjeto) {
    const pix = pix4dOffset || [0, 0, 0];
    const proj = offsetProjeto && offsetProjeto.length >= 3 ? offsetProjeto : [0, 0, 0];
    return new THREE.Vector3(
        utm.x - pix[0] + proj[0],
        utm.y - pix[1] + proj[1],
        utm.z - pix[2] + proj[2]
    );
}

export class DiaryPinRenderer {
    /**
     * @param {object} viewer - Potree.Viewer
     * @param {object} configService - ConfigService ou objeto com getConfig()
     * @param {OffsetService} offsetService - getOffsetForProject(projectId, availableProjects)
     */
    constructor(viewer, configService, offsetService) {
        this.viewer = viewer;
        this.configService = configService;
        this.offsetService = offsetService;
        /** @type {THREE.Group|null} */
        this.pinsGroup = null;
        /** @type {THREE.Mesh|null} - mantido para compatibilidade; uso preferencial: pinMeshes */
        this.pinMesh = null;
        /** @type {THREE.Mesh[]} - um mesh por pin (ordem = índice). */
        this.pinMeshes = [];
        /** Lista de pins UTM atuais [{ x, y, z, color? }] (para refresh ao mudar projeto). */
        this._currentPins = [];
        this._currentProjectId = null;
        /** Em arraste: mesh e índice do pin sendo arrastado. */
        this._dragging = false;
        this._draggedMesh = null;
        this._draggedPinIndex = null;
        this._onPointerMove = null;
        this._onPointerUp = null;
        /** Índice do pin selecionado (modo edição: contorno + caixa de cor). null = nenhum. */
        this._selectedPinIndex = null;
        /** Mesh de contorno (wireframe) do pin selecionado. */
        this._outlineMesh = null;
        /** Ao clicar no pin já selecionado: aguardar movimento para iniciar arraste. */
        this._dragPending = null;
        this._onPointerMovePending = null;
        this._onPointerUpPending = null;
        /** Geometria compartilhada para todos os pins. */
        this._pinGeometry = null;
    }

    _getConfig() {
        if (this.configService && typeof this.configService.getConfig === 'function') {
            return this.configService.getConfig();
        }
        return getConfig();
    }

    _ensurePinsGroup() {
        const scene = this.viewer && this.viewer.scene;
        if (!scene) return;
        if (!scene.diaryPinsGroup) {
            scene.diaryPinsGroup = new THREE.Group();
            scene.diaryPinsGroup.name = 'diaryPins';
            scene.diaryPinsGroup.renderOrder = 1001;
            scene.scene.add(scene.diaryPinsGroup);
        }
        this.pinsGroup = scene.diaryPinsGroup;
        if (!this._pinGeometry) {
            this._pinGeometry = new THREE.ConeGeometry(0.8, 2.5, 8);
        }
    }

    _colorToHex(color) {
        if (typeof color === 'number') return color;
        if (typeof color === 'string' && color.trim()) {
            try {
                return new THREE.Color(color).getHex();
            } catch {
                return DEFAULT_PIN_COLOR;
            }
        }
        return DEFAULT_PIN_COLOR;
    }

    /**
     * Define todos os pins na nuvem. pins = [{ x, y, z, color? }, ...]. Se vazio, remove todos.
     * @param {{ x: number, y: number, z: number, color?: string|number }[]} pins
     * @param {string} [projectId]
     */
    async setPins(pins, projectId) {
        this._currentPins = Array.isArray(pins) ? pins.map((p) => ({ ...p })) : [];
        this._currentProjectId = projectId || null;

        this._ensurePinsGroup();
        if (!this.pinsGroup) return;

        // Remove meshes antigos (geometria é compartilhada, não fazer dispose)
        for (const mesh of this.pinMeshes) {
            this.pinsGroup.remove(mesh);
            if (mesh.material) mesh.material.dispose();
        }
        this.pinMeshes = [];
        this.pinMesh = null;

        if (this._currentPins.length === 0) {
            this._removeOutline();
            this._selectedPinIndex = null;
            if (this.viewer && typeof this.viewer.render === 'function') this.viewer.render();
            return;
        }

        const config = this._getConfig();
        const pid = projectId || (config.projetoInicial || (config.projetosDisponiveis && config.projetosDisponiveis[0] && config.projetosDisponiveis[0].id));
        if (!pid) return;
        const availableProjects = config.projetosDisponiveis || [];
        const offsetProjeto = this.offsetService
            ? this.offsetService.getOffsetForProject(pid, availableProjects)
            : ((availableProjects.find((p) => p.id === pid) || {}).offset || [0, 0, 0]);
        const pix4dOffset = await loadPix4dOffset(pid);
        const pix = pix4dOffset != null ? pix4dOffset : [0, 0, 0];

        for (let i = 0; i < this._currentPins.length; i++) {
            const pin = this._currentPins[i];
            const pos = utmToScene(pin, pix, offsetProjeto);
            const material = new THREE.MeshBasicMaterial({
                color: this._colorToHex(pin.color),
                depthTest: true,
                depthWrite: true
            });
            const mesh = new THREE.Mesh(this._pinGeometry, material);
            mesh.rotation.x = -Math.PI / 2;
            mesh.renderOrder = 1001;
            mesh.position.copy(pos);
            mesh.userData.isDiaryPin = true;
            mesh.userData.pinIndex = i;
            this.pinsGroup.add(mesh);
            this.pinMeshes.push(mesh);
        }
        this.pinMesh = this.pinMeshes[0] || null;

        // Reaplicar contorno no pin selecionado após recriar meshes
        if (this._selectedPinIndex != null && this.pinMeshes[this._selectedPinIndex]) {
            this._addOutlineToMesh(this.pinMeshes[this._selectedPinIndex]);
        }

        if (this.viewer && typeof this.viewer.render === 'function') this.viewer.render();
    }

    _removeOutline() {
        if (this._outlineMesh && this._outlineMesh.parent) {
            this._outlineMesh.parent.remove(this._outlineMesh);
            if (this._outlineMesh.geometry) this._outlineMesh.geometry.dispose();
            if (this._outlineMesh.material) this._outlineMesh.material.dispose();
            this._outlineMesh = null;
        }
    }

    _addOutlineToMesh(pinMesh) {
        this._removeOutline();
        if (!pinMesh) return;
        // Outline como filho do pin: mesma orientação local (não rotacionar de novo).
        const outlineGeom = new THREE.ConeGeometry(0.8 * 1.05, 2.5 * 1.05, 8);
        const outlineMat = new THREE.MeshBasicMaterial({
            color: 0xffffff,
            wireframe: true,
            depthTest: true,
            depthWrite: false
        });
        const outline = new THREE.Mesh(outlineGeom, outlineMat);
        outline.renderOrder = 1002;
        pinMesh.add(outline);
        this._outlineMesh = outline;
    }

    /**
     * Atualiza apenas a cor do pin no índice (sem recriar meshes).
     * @param {number} pinIndex
     * @param {string|number} color
     */
    setPinColor(pinIndex, color) {
        if (!this.pinMeshes[pinIndex] || !this.pinMeshes[pinIndex].material) return;
        this.pinMeshes[pinIndex].material.color.setHex(this._colorToHex(color));
        if (this._currentPins[pinIndex]) this._currentPins[pinIndex].color = color;
        if (this.viewer && typeof this.viewer.render === 'function') this.viewer.render();
    }

    /**
     * Define um único pin (compatibilidade). Equivale a setPins(utmPoint ? [{ ...utmPoint, color }] : [], projectId).
     * @param {{ x: number, y: number, z: number }|null} utmPoint
     * @param {string} [projectId]
     * @param {string|number} [color]
     */
    async setPinPosition(utmPoint, projectId, color) {
        if (!utmPoint) {
            await this.setPins([], projectId);
            return;
        }
        await this.setPins(
            [{ x: utmPoint.x, y: utmPoint.y, z: utmPoint.z, color: color || null }],
            projectId
        );
    }

    /**
     * Recalcula posições dos pins na cena (ex.: após mudança de projeto/offset).
     */
    async refreshPosition(projectId) {
        if (this._currentPins.length === 0) return;
        await this.setPins(this._currentPins, projectId !== undefined ? projectId : this._currentProjectId);
    }

    getPinMesh() {
        return this.pinMesh || (this.pinMeshes && this.pinMeshes[0]) || null;
    }

    /**
     * Registra listeners de arraste para um pin. onUtmUpdated(pinIndex, utm) ao soltar.
     * @param {THREE.Mesh} mesh - Mesh do pin sendo arrastado
     * @param {number} pinIndex - Índice do pin
     * @param {function(number, { x: number, y: number, z: number }): void} onUtmUpdated
     */
    enableDrag(mesh, pinIndex, onUtmUpdated) {
        const renderArea = document.getElementById('potree_render_area');
        if (!renderArea || !this.viewer || !mesh) return;
        const viewer = this.viewer;
        const pointcloud = window.currentPointcloud;
        const projectId = this._currentProjectId || window.currentProjectId;
        const config = this._getConfig();
        const availableProjects = config.projetosDisponiveis || [];
        const offsetProjeto = this.offsetService
            ? this.offsetService.getOffsetForProject(projectId, availableProjects)
            : [0, 0, 0];

        const getMouse = (e) => {
            const rect = viewer.renderer.domElement.getBoundingClientRect();
            return { x: e.clientX - rect.left, y: e.clientY - rect.top };
        };

        const toUTM = (pCena, pix4dOffset) => {
            const pix = pix4dOffset || [0, 0, 0];
            return {
                x: pCena.x + pix[0] - offsetProjeto[0],
                y: pCena.y + pix[1] - offsetProjeto[1],
                z: pCena.z + pix[2] - offsetProjeto[2]
            };
        };

        this._onPointerMove = async (e) => {
            if (!this._dragging || !pointcloud) return;
            const mouse = getMouse(e);
            const camera = viewer.scene.getActiveCamera();
            const result = Potree.Utils.getMousePointCloudIntersection(mouse, camera, viewer, [pointcloud], {});
            if (result && result.location) {
                mesh.position.copy(result.location);
                viewer.render();
            }
        };

        this._onPointerUp = async (e) => {
            if (!this._dragging) return;
            this._dragging = false;
            this._draggedMesh = null;
            this._draggedPinIndex = null;
            renderArea.removeEventListener('pointermove', this._onPointerMove);
            renderArea.removeEventListener('pointerup', this._onPointerUp);
            renderArea.releasePointerCapture && renderArea.releasePointerCapture(e.pointerId);
            const mouse = getMouse(e);
            const camera = viewer.scene.getActiveCamera();
            const result = Potree.Utils.getMousePointCloudIntersection(mouse, camera, viewer, [pointcloud], {});
            if (result && result.location && typeof onUtmUpdated === 'function') {
                const pix4dOffset = await loadPix4dOffset(projectId);
                const utm = toUTM(result.location, pix4dOffset);
                onUtmUpdated(pinIndex, utm);
            }
        };

        renderArea.addEventListener('pointermove', this._onPointerMove);
        renderArea.addEventListener('pointerup', this._onPointerUp);
    }

    startDrag(mesh, pinIndex, onUtmUpdated) {
        const renderArea = document.getElementById('potree_render_area');
        if (!renderArea || !mesh) return;
        this._dragging = true;
        this._draggedMesh = mesh;
        this._draggedPinIndex = pinIndex;
        this.enableDrag(mesh, pinIndex, onUtmUpdated);
    }

    /**
     * pointerdown: primeiro clique = seleciona pin (contorno + caixa de cor);
     * segundo clique no mesmo pin + arrastar = move o pin.
     */
    init() {
        const renderArea = document.getElementById('potree_render_area');
        if (!renderArea || !this.viewer) return;
        const raycaster = new THREE.Raycaster();
        const mouse = new THREE.Vector2();

        renderArea.addEventListener('pointerdown', (e) => {
            if (this._dragging) return;
            const meshes = this.pinMeshes && this.pinMeshes.length > 0 ? this.pinMeshes : (this.pinMesh ? [this.pinMesh] : []);
            if (meshes.length === 0) return;
            const rect = this.viewer.renderer.domElement.getBoundingClientRect();
            mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
            mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
            raycaster.setFromCamera(mouse, this.viewer.scene.getActiveCamera());
            const hits = raycaster.intersectObjects(meshes, true);
            if (hits.length === 0) {
                // Clique fora: deseleciona
                if (this._selectedPinIndex != null) {
                    this._removeOutline();
                    this._selectedPinIndex = null;
                    if (typeof window.onDiaryPinDeselected === 'function') window.onDiaryPinDeselected();
                }
                return;
            }
            // Outline é filho do pin e também é atingido; usar o primeiro hit que seja o mesh do pin (tem userData.pinIndex).
            const pinHit = hits.find((h) => typeof h.object.userData.pinIndex === 'number');
            if (!pinHit) return;
            const pinIndex = pinHit.object.userData.pinIndex;

            if (this._selectedPinIndex === null) {
                // Primeiro clique: seleciona e mostra contorno + caixa de cor
                e.preventDefault();
                this._selectedPinIndex = pinIndex;
                this._addOutlineToMesh(pinHit.object);
                if (this.viewer && typeof this.viewer.render === 'function') this.viewer.render();
                if (typeof window.onDiaryPinSelected === 'function') window.onDiaryPinSelected(pinIndex);
                return;
            }

            if (this._selectedPinIndex === pinIndex) {
                // Segundo clique no mesmo pin: aguardar movimento para iniciar arraste
                e.preventDefault();
                renderArea.setPointerCapture && renderArea.setPointerCapture(e.pointerId);
                const mesh = pinHit.object;
                const onMove = (e2) => {
                    renderArea.removeEventListener('pointermove', onMove);
                    renderArea.removeEventListener('pointerup', onUp);
                    this._dragPending = null;
                    this._onPointerMovePending = null;
                    this._onPointerUpPending = null;
                    if (typeof window.onDiaryPinPositionUpdated === 'function') {
                        this.startDrag(mesh, pinIndex, (idx, utm) => {
                            window.onDiaryPinPositionUpdated(idx, utm);
                        });
                    }
                };
                const onUp = () => {
                    renderArea.removeEventListener('pointermove', onMove);
                    renderArea.removeEventListener('pointerup', onUp);
                    this._dragPending = null;
                    this._onPointerMovePending = null;
                    this._onPointerUpPending = null;
                };
                this._dragPending = { pinIndex, mesh };
                this._onPointerMovePending = onMove;
                this._onPointerUpPending = onUp;
                renderArea.addEventListener('pointermove', onMove);
                renderArea.addEventListener('pointerup', onUp);
                return;
            }

            // Clique em outro pin: seleciona o novo
            e.preventDefault();
            this._removeOutline();
            this._selectedPinIndex = pinIndex;
            this._addOutlineToMesh(pinHit.object);
            if (this.viewer && typeof this.viewer.render === 'function') this.viewer.render();
            if (typeof window.onDiaryPinSelected === 'function') window.onDiaryPinSelected(pinIndex);
        });
    }
}
