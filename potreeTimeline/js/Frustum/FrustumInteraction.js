/**
 * Sistema de interação com frustums.
 * Gerencia cliques e hover nos frustums das câmeras.
 * 
 * @class FrustumInteraction
 */

import * as THREE from "../../potree/libs/three.js/build/three.module.js";
import { getClickableFrustums } from "./frustumRenderer.js";
import { showCameraImage } from "./imageModal.js";
import { getImageUrlForProject, generateImagePath } from "./imagePath.js";

/** Limite de pixels para distinguir clique de arraste. */
const DRAG_THRESHOLD_PX = 6;

export class FrustumInteraction {
    /**
     * Cria uma instância de FrustumInteraction.
     * @param {object} viewer - Instância Potree.Viewer
     * @param {CameraDataStore} cameraDataStore - Armazenamento de dados de câmeras
     */
    constructor(viewer, cameraDataStore) {
        this.viewer = viewer;
        this.cameraDataStore = cameraDataStore;
        this.raycaster = null;
        this.mouse = null;
        this.hoveredFrustum = null;
        this.pointerDownAt = null;
        this.initialized = false;
    }

    /**
     * Inicializa o sistema de interação.
     */
    init() {
        if (this.initialized) {
            return;
        }

        this.raycaster = new THREE.Raycaster();
        this.mouse = new THREE.Vector2();
        
        if (this.viewer && this.viewer.renderArea) {
            const el = this.viewer.renderArea;
            el.addEventListener('mousedown', this._onPointerDown.bind(this));
            el.addEventListener('click', this._onFrustumClick.bind(this));
            el.addEventListener('mousemove', this._onMouseMove.bind(this));
            this.initialized = true;
        } else {
            console.warn('⚠️ Viewer ou renderArea não encontrado para interação');
        }
    }

    /**
     * Remove os event listeners de interação.
     */
    cleanup() {
        if (this.viewer && this.viewer.renderArea && this.initialized) {
            const el = this.viewer.renderArea;
            el.removeEventListener('mousedown', this._onPointerDown.bind(this));
            el.removeEventListener('click', this._onFrustumClick.bind(this));
            el.removeEventListener('mousemove', this._onMouseMove.bind(this));
            this.initialized = false;
        }
    }

    /**
     * Guarda a posição do pointer no mousedown para distinguir clique de arraste.
     * @private
     */
    _onPointerDown(event) {
        this.pointerDownAt = { x: event.clientX, y: event.clientY };
    }

    /**
     * Verifica se a camada "Fotos aéreas" (frustums) está visível.
     * Quando desabilitada, não deve haver hover nem clique nos frustums.
     * @returns {boolean}
     */
    _areFrustumsVisible() {
        const group = this.viewer?.scene?.cameraFrustumsGroup;
        return !!(group && group.visible);
    }

    /**
     * Manipula o movimento do mouse para detectar hover.
     * @private
     */
    _onMouseMove(event) {
        if (!this.viewer || !this.viewer.renderArea) {
            return;
        }

        if (!this._areFrustumsVisible()) {
            if (this.hoveredFrustum) {
                this.hoveredFrustum.material.opacity = 0.3;
                this.hoveredFrustum = null;
            }
            this.viewer.renderArea.style.cursor = "default";
            this.viewer.render();
            return;
        }
        
        // Calcular posição do mouse normalizada
        const rect = this.viewer.renderArea.getBoundingClientRect();
        this.mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        this.mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
        
        // Configurar raycaster
        this.raycaster.setFromCamera(this.mouse, this.viewer.scene.getActiveCamera());
        
        // Verificar interseção com frustums
        const frustums = getClickableFrustums();
        const intersects = this.raycaster.intersectObjects(frustums, true);
        
        // Resetar frustum anterior se existir
        if (this.hoveredFrustum) {
            this.hoveredFrustum.material.opacity = 0.3;
            this.viewer.renderArea.style.cursor = 'default';
        }
        
        // Destacar frustum atual
        if (intersects.length > 0) {
            this.hoveredFrustum = intersects[0].object;
            this.hoveredFrustum.material.opacity = 0.7;
            this.viewer.renderArea.style.cursor = 'pointer';
        } else {
            this.hoveredFrustum = null;
        }
        
        this.viewer.render();
    }

    /**
     * Manipula cliques nos frustums. Só abre a foto se não houve arraste (movimento da câmera).
     * @private
     */
    _onFrustumClick(event) {
        if (!this.viewer || !this.viewer.renderArea) {
            return;
        }

        if (!this._areFrustumsVisible()) {
            this.pointerDownAt = null;
            return;
        }

        const movedTooMuch = this.pointerDownAt &&
            (Math.abs(event.clientX - this.pointerDownAt.x) > DRAG_THRESHOLD_PX ||
             Math.abs(event.clientY - this.pointerDownAt.y) > DRAG_THRESHOLD_PX);
        this.pointerDownAt = null;
        
        if (movedTooMuch) {
            return;
        }

        const rect = this.viewer.renderArea.getBoundingClientRect();
        this.mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        this.mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

        this.raycaster.setFromCamera(this.mouse, this.viewer.scene.getActiveCamera());
        const frustums = getClickableFrustums();
        const intersects = this.raycaster.intersectObjects(frustums, true);

        if (intersects.length > 0) {
            const clickedObject = intersects[0].object;
            
            // Procurar userData na hierarquia (objeto atual ou parent)
            let cameraName = null;
            let currentObject = clickedObject;
            
            while (currentObject && !cameraName) {
                if (currentObject.userData && currentObject.userData.cameraName) {
                    cameraName = currentObject.userData.cameraName;
                    break;
                }
                currentObject = currentObject.parent;
            }
            
            if (!cameraName) {
                console.warn('⚠️ cameraName não encontrado na hierarquia do objeto clicado');
                return;
            }
            
            // Encontrar dados da câmera correspondente
            const cameraInfo = this.cameraDataStore.get(cameraName);
            
            if (cameraInfo) {
                const imagePath = cameraInfo.projectId
                    ? getImageUrlForProject(cameraInfo.projectId, cameraInfo.name + ".JPG")
                    : generateImagePath(cameraName);
                const cameraInfoWithImage = { ...cameraInfo, imagePath };

                // Verificar se está em modo de comparação de fotos
                const mode = window.comparePhotosFrustumPickMode;
                if (mode?.active && typeof window.onFrustumClickAddToCompareSlot === "function") {
                    window.onFrustumClickAddToCompareSlot(cameraInfoWithImage, mode.slotIndex);
                    return;
                }

                // Verificar se está em modo de referência do diário (Foto área)
                if (window.diaryReferenceMode === 'photoArea' && typeof window.onFrustumClickAddToDiaryReference === 'function') {
                    window.onFrustumClickAddToDiaryReference(cameraInfoWithImage);
                    return;
                }

                showCameraImage(cameraInfoWithImage);
            } else {
                console.warn('⚠️ Dados da câmera não encontrados:', cameraName);
            }
        }
    }
}
