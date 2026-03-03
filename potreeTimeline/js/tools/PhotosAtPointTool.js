/**
 * Ferramenta de fotos no ponto.
 * Dado um ponto na nuvem (clique), exibe as melhores fotos que contêm esse ponto.
 * 
 * @class PhotosAtPointTool
 */

import * as THREE from "../../potree/libs/three.js/build/three.module.js";
import { loadExternalCameraParameters } from "../Frustum/cameraLoader.js";
import { getImageUrlForProject } from "../Frustum/imagePath.js";
import { photosAtPointPanelTemplate } from "./templates/photosAtPointPanelTemplate.js";
import { showCameraImage } from "../Frustum/imageModal.js";
import { getConfig, getCameraParamsUrl, loadPix4dOffset, PHOTO_LIMITS } from "../config/viewer-config.js";

const FOV_DEG = 60;
const ASPECT = 4 / 3;
const NEAR = 1;
const FAR = 100000;

export class PhotosAtPointTool {
    /**
     * Cria uma instância de PhotosAtPointTool.
     * @param {object} viewer - Instância Potree.Viewer
     * @param {object} configService - Serviço de configuração (ou objeto com getConfig)
     */
    constructor(viewer, configService) {
        this.viewer = viewer;
        this.configService = configService;
        this.cameraCache = {};
        this.active = false;
        this.panelEl = null;
        this.clickHandler = null;
        this.lastClickedPoint = null;
        this.currentMaxPhotosIndex = 0;
        this.allAvailablePhotos = [];
        this.currentProjectIdForFotos = null;
    }

    /**
     * Testa se o ponto está no frustum. Aceita convenção +Z (câmera olha para +Z)
     * ou -Z (câmera olha para -Z, padrão Three.js).
     * @param {THREE.Vector3} pointWorld - Ponto em coordenadas de mundo
     * @param {THREE.Vector3} cameraPosWorld - Posição da câmera
     * @param {THREE.Quaternion} cameraQuat - Rotação da câmera
     * @param {number} [fovDeg] - Campo de visão em graus
     * @param {number} [aspect] - Aspecto
     * @param {number} [near] - Plano próximo
     * @param {number} [far] - Plano distante
     * @returns {boolean}
     */
    pointInFrustum(pointWorld, cameraPosWorld, cameraQuat, fovDeg = FOV_DEG, aspect = ASPECT, near = NEAR, far = FAR) {
        const p = pointWorld.clone().sub(cameraPosWorld);
        const qInv = cameraQuat.clone().invert();
        p.applyQuaternion(qInv);
        const tanHalfFov = Math.tan((fovDeg * Math.PI / 180) / 2);
        let depth;
        if (p.z > near && p.z < far) {
            depth = p.z;
        } else if (p.z < -near && p.z > -far) {
            depth = -p.z;
        } else {
            return false;
        }
        const halfW = depth * tanHalfFov * aspect;
        const halfH = depth * tanHalfFov;
        return Math.abs(p.x) <= halfW && Math.abs(p.y) <= halfH;
    }

    /**
     * Carrega o offset para alinhar câmeras à cena (mesma lógica dos frustums).
     * @param {string} projectId - ID do projeto
     * @param {Array} projetos - Lista de projetos
     * @returns {Promise<[number, number, number]>}
     */
    async loadOffsetForFotos(projectId, projetos = []) {
        const pix4d = await loadPix4dOffset(projectId);
        if (pix4d) return pix4d;
        const proj = projetos.find((p) => p.id === projectId);
        return (proj && Array.isArray(proj.offset) && proj.offset.length >= 3) ? proj.offset : [0, 0, 0];
    }

    /**
     * Carrega câmeras do projeto (com cache).
     * @param {string} projectId - ID do projeto
     * @returns {Promise<Array>}
     */
    async loadCamerasForProject(projectId) {
        if (this.cameraCache[projectId]) {
            return this.cameraCache[projectId];
        }
        const url = getCameraParamsUrl(projectId);
        const cameras = await loadExternalCameraParameters(url, 0);
        this.cameraCache[projectId] = cameras;
        return cameras;
    }

    /**
     * Dado ponto em world e câmeras, retorna as câmeras que veem o ponto, ordenadas por distância.
     * @param {THREE.Vector3} pointWorld - Ponto em coordenadas de mundo
     * @param {Array} cameras - Lista de câmeras
     * @param {number|null} maxCount - Número máximo de câmeras (null = todas)
     * @returns {Array}
     */
    getBestCamerasForPoint(pointWorld, cameras, maxCount) {
        const withDistance = [];
        for (const cam of cameras) {
            if (!this.pointInFrustum(pointWorld, cam.position, cam.quaternion, FOV_DEG, ASPECT, NEAR, FAR)) {
                continue;
            }
            const d = pointWorld.distanceTo(cam.position);
            withDistance.push({ ...cam, distance: d });
        }
        withDistance.sort((a, b) => a.distance - b.distance);
        if (maxCount == null) return withDistance;
        return withDistance.slice(0, maxCount);
    }

    /**
     * Atualiza o contador no cabeçalho do painel.
     * @private
     */
    _setPhotosPanelHeaderCount(n) {
        const titleEl = this.panelEl && this.panelEl.querySelector(".fotos-panel-title");
        if (titleEl) {
            titleEl.textContent = "Fotos (" + n + ")";
        }
    }

    /**
     * Atualiza o painel lateral com as fotos.
     * @param {string} projectId - ID do projeto
     * @param {Array} photos - Lista de fotos
     */
    updateFotosPanel(projectId, photos) {
        if (!this.panelEl) {
            this._createPanel();
        }
        
        const list = this.panelEl.querySelector(".fotos-panel-list");
        const loadMoreWrap = this.panelEl.querySelector(".fotos-panel-load-more-wrap");
        list.innerHTML = "";
        this._setPhotosPanelHeaderCount(photos ? photos.length : 0);
        
        if (!photos || photos.length === 0) {
            list.innerHTML = "<p class=\"fotos-panel-empty\">Nenhuma foto contém este ponto.</p>";
            if (loadMoreWrap) loadMoreWrap.style.display = "none";
            this.panelEl.classList.add("fotos-panel-open");
            return;
        }
        
        const config = this.configService.getConfig ? this.configService.getConfig() : getConfig();
        
        const diaryRefMode = typeof window !== "undefined" && window.diaryReferenceMode === "photoPoint";

        for (const p of photos) {
            const url = p.imagePath || getImageUrlForProject(projectId, p.name + ".JPG");
            const item = document.createElement("div");
            item.className = "fotos-panel-item";
            const imgWrap = document.createElement("div");
            imgWrap.className = "fotos-panel-item-img-wrap";
            const img = document.createElement("img");
            img.src = url;
            img.alt = p.name;
            img.loading = "lazy";
            img.addEventListener("error", () => {
                img.style.background = "#333";
                img.alt = p.name + " (não encontrada)";
            });
            img.addEventListener("click", () => {
                showCameraImage({ name: p.name, imagePath: url });
            });
            imgWrap.appendChild(img);
            if (diaryRefMode) {
                const addRefBtn = document.createElement("button");
                addRefBtn.type = "button";
                addRefBtn.className = "fotos-panel-add-ref-plus";
                addRefBtn.innerHTML = "+";
                addRefBtn.title = "Adicionar como referência";
                addRefBtn.setAttribute("aria-label", "Adicionar como referência");
                addRefBtn.addEventListener("click", (e) => {
                    e.stopPropagation();
                    e.preventDefault();
                    if (typeof window.onDiaryReferencePhotoPointSelected === "function") {
                        window.onDiaryReferencePhotoPointSelected(p.name);
                    }
                });
                imgWrap.appendChild(addRefBtn);
            }
            item.appendChild(imgWrap);
            const label = document.createElement("span");
            label.className = "fotos-panel-label";
            label.textContent = p.name + (p.distance != null ? ` (${Math.round(p.distance)} m)` : "");
            item.appendChild(label);
            list.appendChild(item);
        }
        
        if (loadMoreWrap) {
            const atMaxLimit = this.currentMaxPhotosIndex >= PHOTO_LIMITS.length - 1;
            const hasMoreToShow = this.allAvailablePhotos.length > PHOTO_LIMITS[this.currentMaxPhotosIndex];
            const canLoadMore = !atMaxLimit && hasMoreToShow;
            loadMoreWrap.style.display = canLoadMore ? "block" : "none";
        }
        
        this.panelEl.classList.add("fotos-panel-open");
    }


    /**
     * Cria o painel de fotos.
     * @private
     */
    _createPanel() {
        this.panelEl = document.createElement("div");
        this.panelEl.className = "fotos-panel";
        this.panelEl.innerHTML = photosAtPointPanelTemplate;
        
        const closeBtn = this.panelEl.querySelector(".fotos-panel-close-btn");
        closeBtn.addEventListener("click", () => {
            this.setActive(false);
            if (typeof window.setLeftPanelMode === "function") {
                window.setLeftPanelMode(null);
            }
        });
        
        const loadMoreBtn = this.panelEl.querySelector("#btn_fotos_carregar_mais");
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener("click", () => this._expandPhotos());
        }

        const container = document.getElementById("left_panel_fotos");
        if (container) {
            this.panelEl.classList.add("fotos-panel-embedded");
            container.insertBefore(this.panelEl, container.firstChild);
        } else {
            const potreeContainer = document.querySelector(".potree_container");
            if (potreeContainer) {
                potreeContainer.appendChild(this.panelEl);
            }
        }
    }

    /**
     * Expande a lista de fotos carregando mais.
     * @private
     */
    _expandPhotos() {
        if (this.currentMaxPhotosIndex >= PHOTO_LIMITS.length - 1) return;
        if (!this.currentProjectIdForFotos || !this.allAvailablePhotos.length) return;
        
        this.currentMaxPhotosIndex += 1;
        const limit = PHOTO_LIMITS[this.currentMaxPhotosIndex];
        const photosToShow = this.allAvailablePhotos.slice(0, Math.min(limit, this.allAvailablePhotos.length));
        this.updateFotosPanel(this.currentProjectIdForFotos, photosToShow);
        
        if (this.viewer) {
            this.viewer.render();
        }
    }

    /**
     * Ativa ou desativa a ferramenta.
     * @param {boolean} active - Se true, ativa a ferramenta
     */
    setActive(active) {
        this.active = active;
        const btn = document.getElementById("btn_sidebar_fotos_no_ponto");
        if (btn) {
            btn.classList.toggle("active", active);
        }

        if (active) {
            if (!this.panelEl) {
                this._createPanel();
            }
            this._ensurePanelInLeftPanelFotos();
            
            setTimeout(() => {
                if (window.setComparePhotosActive) {
                    window.setComparePhotosActive(false);
                }
            }, 0);
        } else {
            // Ao fechar o painel de fotos, limpa o modo de referência do diário (se estiver ativo).
            if (typeof window !== "undefined" && window.diaryReferenceMode === "photoPoint") {
                window.diaryReferenceMode = null;
                window.onDiaryReferencePhotoPointSelected = null;
            }
        }
        
        // Expõe função globalmente para compatibilidade
        if (typeof window !== "undefined") {
            window.setFotosToolActive = (active) => this.setActive(active);
        }
    }

    /**
     * Garante que o painel de fotos está dentro de #left_panel_fotos.
     * @private
     */
    _ensurePanelInLeftPanelFotos() {
        if (!this.panelEl) return;
        const container = document.getElementById("left_panel_fotos");
        if (!container || this.panelEl.parentNode === container) return;
        this.panelEl.classList.add("fotos-panel-embedded");
        container.insertBefore(this.panelEl, container.firstChild);
    }

    /**
     * Atualiza as fotos do painel quando a nuvem muda, usando o último ponto clicado.
     */
    async updateForNewCloud() {
        if (!this.lastClickedPoint) return;
        if (!this.panelEl || !this.panelEl.classList.contains("fotos-panel-open")) return;

        const viewer = this.viewer || window.viewer;
        if (!viewer) return;

        const config = this.configService.getConfig ? this.configService.getConfig() : getConfig();
        const projectId = config.projetoInicial || (config.projetosDisponiveis && config.projetosDisponiveis[0] && config.projetosDisponiveis[0].id);
        const currentProjectId = window.currentProjectId != null ? window.currentProjectId : projectId;
        if (!currentProjectId) return;

        const projetos = config.projetosDisponiveis || [];
        const cameras = await this.loadCamerasForProject(currentProjectId);
        const offset = await this.loadOffsetForFotos(currentProjectId, projetos);
        const offsetV = new THREE.Vector3(offset[0], offset[1], offset[2]);

        const camerasWorld = cameras.map((c) => ({
            ...c,
            position: c.position.clone().sub(offsetV)
        }));

        const allBest = this.getBestCamerasForPoint(this.lastClickedPoint, camerasWorld, null);
        this.allAvailablePhotos = allBest.map((c) => ({
            name: c.name,
            distance: c.distance
        }));
        this.currentProjectIdForFotos = currentProjectId;
        this.currentMaxPhotosIndex = 0;
        const limit = PHOTO_LIMITS[0];
        const photosToShow = this.allAvailablePhotos.slice(0, Math.min(limit, this.allAvailablePhotos.length));

        this.updateFotosPanel(currentProjectId, photosToShow);
        viewer.render();
    }

    /**
     * Inicializa a ferramenta: cria o painel e registra o listener de clique.
     */
    init() {
        if (typeof window !== "undefined") {
            window.setFotosToolActive = (active) => this.setActive(active);
        }
        if (!this.panelEl) {
            this._createPanel();
        }
        this._ensurePanelInLeftPanelFotos();

        const renderArea = document.getElementById("potree_render_area");
        if (!renderArea) {
            return;
        }

        // Remove listener anterior se existir
        if (this.clickHandler) {
            renderArea.removeEventListener("click", this.clickHandler);
        }

        this.clickHandler = async (e) => {
            if (!this.active) return;
            
            const viewer = this.viewer || window.viewer;
            const pointcloud = window.currentPointcloud;
            if (!viewer || !pointcloud) return;
            
            const rect = viewer.renderer.domElement.getBoundingClientRect();
            const mouse = { x: e.clientX - rect.left, y: e.clientY - rect.top };
            const camera = viewer.scene.getActiveCamera();
            const pointclouds = [pointcloud];
            const result = Potree.Utils.getMousePointCloudIntersection(mouse, camera, viewer, pointclouds, {});
            
            if (!result || !result.location) return;
            
            const worldPoint = result.location.clone();
            this.lastClickedPoint = worldPoint.clone();
            
            const config = this.configService.getConfig ? this.configService.getConfig() : getConfig();
            const projectId = config.projetoInicial || (config.projetosDisponiveis && config.projetosDisponiveis[0] && config.projetosDisponiveis[0].id);
            const currentProjectId = window.currentProjectId != null ? window.currentProjectId : projectId;
            if (!currentProjectId) return;
            
            const cameras = await this.loadCamerasForProject(currentProjectId);
            const projetos = config.projetosDisponiveis || [];
            const offset = await this.loadOffsetForFotos(currentProjectId, projetos);
            const offsetV = new THREE.Vector3(offset[0], offset[1], offset[2]);
            const camerasWorld = cameras.map((c) => ({
                ...c,
                position: c.position.clone().sub(offsetV)
            }));
            
            const allBest = this.getBestCamerasForPoint(worldPoint, camerasWorld, null);
            this.allAvailablePhotos = allBest.map((c) => ({
                name: c.name,
                distance: c.distance
            }));
            this.currentProjectIdForFotos = currentProjectId;
            this.currentMaxPhotosIndex = 0;
            const limit = PHOTO_LIMITS[0];
            const photosToShow = this.allAvailablePhotos.slice(0, Math.min(limit, this.allAvailablePhotos.length));
            
            this.updateFotosPanel(currentProjectId, photosToShow);
            viewer.render();
        };

        renderArea.addEventListener("click", this.clickHandler);
    }
}
