/**
 * Fotos no ponto: dado um ponto na nuvem (clique), exibe as 4 melhores fotos
 * que contêm esse ponto em um painel lateral à direita.
 */

import * as THREE from "../../potree/libs/three.js/build/three.module.js";
import { loadExternalCameraParameters } from "../Frustum/cameraLoader.js";
import { getImageUrlForProject } from "../Frustum/imagePath.js";
import { getConfig, getCameraParamsUrl, loadPix4dOffset, PHOTO_LIMITS } from "../config/viewer-config.js";

const FOV_DEG = 60;
const ASPECT = 4 / 3;
const NEAR = 1;
const FAR = 100000;

let cameraCache = {};
let photosToolActive = false;
let panelEl = null;
let clickHandler = null;
let lastClickedPoint = null;
let currentMaxPhotosIndex = 0;
let allAvailablePhotos = [];
let currentProjectIdForFotos = null;

/**
 * Testa se o ponto está no frustum. Aceita convenção +Z (câmera olha para +Z)
 * ou -Z (câmera olha para -Z, padrão Three.js).
 */
export function pointInFrustum(pointWorld, cameraPosWorld, cameraQuat, fovDeg = FOV_DEG, aspect = ASPECT, near = NEAR, far = FAR) {
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
 * Usa offset.xyz do Pix4D quando existir, senão proj.offset do config.
 */
export async function loadOffsetForFotos(projectId, baseProjetos, projetos = []) {
    const pix4d = await loadPix4dOffset(projectId);
    if (pix4d) return pix4d;
    const proj = projetos.find((p) => p.id === projectId);
    return (proj && Array.isArray(proj.offset) && proj.offset.length >= 3) ? proj.offset : [0, 0, 0];
}

/**
 * Carrega câmeras do projeto (com cache).
 * @param {string} projectId
 * @param {string} [baseProjetos] - Ignorado; a URL vem de viewer-config.
 */
export async function loadCamerasForProject(projectId, baseProjetos = "projetos") {
    if (cameraCache[projectId]) return cameraCache[projectId];
    const url = getCameraParamsUrl(projectId);
    const cameras = await loadExternalCameraParameters(url, 0);
    cameraCache[projectId] = cameras;
    return cameras;
}

/**
 * Dado ponto em world e câmeras, retorna as câmeras que veem o ponto, ordenadas por distância.
 */
export function getBestCamerasForPoint(pointWorld, cameras, maxCount) {
    const withDistance = [];
    for (const cam of cameras) {
        if (!pointInFrustum(pointWorld, cam.position, cam.quaternion, FOV_DEG, ASPECT, NEAR, FAR)) continue;
        const d = pointWorld.distanceTo(cam.position);
        withDistance.push({ ...cam, distance: d });
    }
    withDistance.sort((a, b) => a.distance - b.distance);
    if (maxCount == null) return withDistance;
    return withDistance.slice(0, maxCount);
}

function setPhotosPanelHeaderCount(n) {
    const titleEl = panelEl && panelEl.querySelector(".fotos-panel-title");
    if (titleEl) titleEl.textContent = "Fotos (" + n + ")";
}

/**
 * Atualiza o painel lateral com as fotos (metadados apenas; imagens carregadas pelo browser via img.src).
 */
export function updateFotosPanel(projectId, photos) {
    if (!panelEl) createFotosPanel();
    const list = panelEl.querySelector(".fotos-panel-list");
    const loadMoreWrap = panelEl.querySelector(".fotos-panel-load-more-wrap");
    list.innerHTML = "";
    setPhotosPanelHeaderCount(photos ? photos.length : 0);
    if (!photos || photos.length === 0) {
        list.innerHTML = "<p class=\"fotos-panel-empty\">Nenhuma foto contém este ponto.</p>";
        if (loadMoreWrap) loadMoreWrap.style.display = "none";
        panelEl.classList.add("fotos-panel-open");
        return;
    }
    const config = getConfig();
    const baseProjetos = config.baseProjetos || "projetos";
    for (const p of photos) {
        const url = p.imagePath || getImageUrlForProject(projectId, p.name + ".JPG");
        const item = document.createElement("div");
        item.className = "fotos-panel-item";
        const img = document.createElement("img");
        img.src = url;
        img.alt = p.name;
        img.loading = "lazy";
        img.addEventListener("error", () => { img.style.background = "#333"; img.alt = p.name + " (não encontrada)"; });
        const label = document.createElement("span");
        label.className = "fotos-panel-label";
        label.textContent = p.name + (p.distance != null ? ` (${Math.round(p.distance)} m)` : "");
        item.appendChild(img);
        item.appendChild(label);
        img.addEventListener("click", () => openImageModal(url, p.name));
        list.appendChild(item);
    }
    if (loadMoreWrap) {
        const atMaxLimit = currentMaxPhotosIndex >= PHOTO_LIMITS.length - 1;
        const hasMoreToShow = allAvailablePhotos.length > PHOTO_LIMITS[currentMaxPhotosIndex];
        const canLoadMore = !atMaxLimit && hasMoreToShow;
        loadMoreWrap.style.display = canLoadMore ? "block" : "none";
    }
    panelEl.classList.add("fotos-panel-open");
}

function openImageModal(src, name) {
    const modal = document.createElement("div");
    modal.className = "fotos-modal-overlay";
    modal.style.cssText = "position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:10000;display:flex;align-items:center;justify-content:center;";
    const box = document.createElement("div");
    box.style.cssText = "max-width:95vw;max-height:95vh;position:relative;width:100%;height:100%;display:flex;align-items:center;justify-content:center;";
    const img = document.createElement("img");
    img.src = src;
    img.alt = name;
    img.style.cssText = "max-width:100%;max-height:95vh;object-fit:contain;pointer-events:none;";
    const close = document.createElement("button");
    close.textContent = "×";
    close.style.cssText = "position:absolute;top:16px;right:16px;width:40px;height:40px;border:none;background:rgba(2,94,115,0.9);color:#fff;font-size:28px;cursor:pointer;border-radius:8px;z-index:10001;display:flex;align-items:center;justify-content:center;line-height:1;";
    close.addEventListener("click", (e) => { e.stopPropagation(); modal.remove(); });
    modal.addEventListener("click", (e) => {
        if (e.target === modal || e.target === box) {
            modal.remove();
        }
    });
    img.addEventListener("click", (e) => e.stopPropagation());
    document.addEventListener("keydown", function esc(e) { if (e.key === "Escape") { modal.remove(); document.removeEventListener("keydown", esc); } });
    box.appendChild(img);
    box.appendChild(close);
    modal.appendChild(box);
    document.body.appendChild(modal);
}

function createFotosPanel() {
    panelEl = document.createElement("div");
    panelEl.className = "fotos-panel";
    panelEl.innerHTML = `
        <div class="fotos-panel-header">
            <span class="fotos-panel-title">Fotos (0)</span>
            <button type="button" class="fotos-panel-close-btn left-panel-close-btn" title="Fechar">×</button>
        </div>
        <div class="fotos-panel-list"></div>
        <div class="fotos-panel-load-more-wrap" style="display: none;">
            <button type="button" class="fotos-panel-load-more" id="btn_fotos_carregar_mais">Carregar mais fotos</button>
        </div>
    `;
    const closeBtn = panelEl.querySelector(".fotos-panel-close-btn");
    closeBtn.addEventListener("click", () => {
        if (typeof window.setFotosToolActive === "function") window.setFotosToolActive(false);
        if (typeof window.setLeftPanelMode === "function") window.setLeftPanelMode(null);
    });
    const loadMoreBtn = panelEl.querySelector("#btn_fotos_carregar_mais");
    if (loadMoreBtn) loadMoreBtn.addEventListener("click", expandPhotos);

    const container = document.getElementById("left_panel_fotos");
    if (container) {
        panelEl.classList.add("fotos-panel-embedded");
        container.insertBefore(panelEl, container.firstChild);
    } else {
        document.querySelector(".potree_container").appendChild(panelEl);
    }
}

function expandPhotos() {
    if (currentMaxPhotosIndex >= PHOTO_LIMITS.length - 1) return;
    if (!currentProjectIdForFotos || !allAvailablePhotos.length) return;
    currentMaxPhotosIndex += 1;
    const limit = PHOTO_LIMITS[currentMaxPhotosIndex];
    const photosToShow = allAvailablePhotos.slice(0, Math.min(limit, allAvailablePhotos.length));
    updateFotosPanel(currentProjectIdForFotos, photosToShow);
    if (window.viewer) window.viewer.render();
}

/**
 * Ativa ou desativa a ferramenta Fotos no ponto.
 * O listener de clique já está registrado na inicialização; aqui só alternamos o "modo ativo".
 */
export function setFotosToolActive(active) {
    photosToolActive = active;
    const btn = document.getElementById("btn_sidebar_fotos_no_ponto");
    if (btn) btn.classList.toggle("active", active);

    if (active) {
        if (!panelEl) createFotosPanel();
        setTimeout(() => {
            if (window.setComparePhotosActive) {
                window.setComparePhotosActive(false);
            }
        }, 0);
    }
}

if (typeof window !== "undefined") {
    window.setFotosToolActive = setFotosToolActive;
}

/** Garante que o painel de fotos está dentro de #left_panel_fotos (evita fotos fora do painel). */
function ensurePanelInLeftPanelFotos() {
    if (!panelEl) return;
    const container = document.getElementById("left_panel_fotos");
    if (!container || panelEl.parentNode === container) return;
    panelEl.classList.add("fotos-panel-embedded");
    container.insertBefore(panelEl, container.firstChild);
}

function isPhotosToolActive() {
    return photosToolActive;
}

/**
 * Atualiza as fotos do painel quando a nuvem muda, usando o último ponto clicado.
 */
export async function updateFotosForNewCloud() {
    if (!lastClickedPoint) return;
    if (!panelEl || !panelEl.classList.contains("fotos-panel-open")) return;

    const viewer = window.viewer;
    if (!viewer) return;

    const config = getConfig();
    const projectId = config.projetoInicial || (config.projetosDisponiveis && config.projetosDisponiveis[0] && config.projetosDisponiveis[0].id);
    const currentProjectId = window.currentProjectId != null ? window.currentProjectId : projectId;
    if (!currentProjectId) return;

    const baseProjetos = config.baseProjetos || "projetos";
    const projetos = config.projetosDisponiveis || [];

    const cameras = await loadCamerasForProject(currentProjectId, baseProjetos);
    const offset = await loadOffsetForFotos(currentProjectId, baseProjetos, projetos);
    const offsetV = new THREE.Vector3(offset[0], offset[1], offset[2]);

    const camerasWorld = cameras.map((c) => ({ ...c, position: c.position.clone().sub(offsetV) }));

    const allBest = getBestCamerasForPoint(lastClickedPoint, camerasWorld, null);
    allAvailablePhotos = allBest.map((c) => ({ name: c.name, distance: c.distance }));
    currentProjectIdForFotos = currentProjectId;
    currentMaxPhotosIndex = 0;
    const limit = PHOTO_LIMITS[0];
    const photosToShow = allAvailablePhotos.slice(0, Math.min(limit, allAvailablePhotos.length));

    updateFotosPanel(currentProjectId, photosToShow);
    viewer.render();
}

/**
 * Inicializa a ferramenta: cria o painel e registra o listener de clique na área de render.
 * Ao abrir "Fotos do Ponto", setFotosToolActive(true) apenas ativa o modo; o clique já está ouvindo.
 */
export function initFotosNoPonto() {
    if (!panelEl) createFotosPanel();
    ensurePanelInLeftPanelFotos();

    const renderArea = document.getElementById("potree_render_area");
    if (!renderArea) return;

    clickHandler = async (e) => {
        if (!isPhotosToolActive()) return;
        const viewer = window.viewer;
        const pointcloud = window.currentPointcloud;
        if (!viewer || !pointcloud) return;
        const rect = viewer.renderer.domElement.getBoundingClientRect();
        const mouse = { x: e.clientX - rect.left, y: e.clientY - rect.top };
        const camera = viewer.scene.getActiveCamera();
        const pointclouds = [pointcloud];
        const result = Potree.Utils.getMousePointCloudIntersection(mouse, camera, viewer, pointclouds, {});
        if (!result || !result.location) return;
        const worldPoint = result.location.clone();
        lastClickedPoint = worldPoint.clone();
        const config = getConfig();
        const projectId = config.projetoInicial || (config.projetosDisponiveis && config.projetosDisponiveis[0] && config.projetosDisponiveis[0].id);
        const currentProjectId = window.currentProjectId != null ? window.currentProjectId : projectId;
        if (!currentProjectId) return;
        const baseProjetos = config.baseProjetos || "projetos";
        const cameras = await loadCamerasForProject(currentProjectId, baseProjetos);
        const projetos = config.projetosDisponiveis || [];
        const offset = await loadOffsetForFotos(currentProjectId, baseProjetos, projetos);
        const offsetV = new THREE.Vector3(offset[0], offset[1], offset[2]);
        const camerasWorld = cameras.map((c) => ({ ...c, position: c.position.clone().sub(offsetV) }));
        const allBest = getBestCamerasForPoint(worldPoint, camerasWorld, null);
        allAvailablePhotos = allBest.map((c) => ({ name: c.name, distance: c.distance }));
        currentProjectIdForFotos = currentProjectId;
        currentMaxPhotosIndex = 0;
        const limit = PHOTO_LIMITS[0];
        const photosToShow = allAvailablePhotos.slice(0, Math.min(limit, allAvailablePhotos.length));
        updateFotosPanel(currentProjectId, photosToShow);
        viewer.render();
    };

    renderArea.addEventListener("click", clickHandler);
}

function tryInit() {
    if (window.viewer && document.getElementById("potree_render_area")) {
        initFotosNoPonto();
    } else {
        setTimeout(tryInit, 100);
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", tryInit);
} else {
    tryInit();
}
