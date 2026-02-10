/**
 * Módulo: viewer Potree + timeline de nuvens.
 * Orquestra config, cache, offset e UI. Expõe window.viewer e window.currentPointcloud.
 */

import { getConfig, getCloudJsUrl, getCameraParamsUrl, getOffsetXyzUrl, loadPix4dOffset } from '../config/viewer-config.js';
import { createCloudCache } from './cloud-cache.js';
import { getOffsetForProject, setOffsetForProject } from './cloud-offset.js';
import { loadCameraFrustumsAsync } from '../Frustum/index.js';
import { applyOffsetDeltaToFrustums } from '../Frustum/frustumRenderer.js';
import { updateFotosForNewCloud } from '../tools/photos-at-point.js';

const CONFIG = getConfig();
const {
    projetosDisponiveis: availableProjects = [],
    projetoInicial: initialProjectId = '',
    developerMode = false
} = CONFIG;

const MAX_CACHED_CLOUDS = 3;
/** Largura da barra lateral (deve coincidir com .sidebar-left { width } no CSS). */
const SIDEBAR_LEFT_WIDTH = 88;
const STATUS_BAR_HEIGHT = 40;

let viewer;
let cache;
let currentPointcloud = null;
let currentIndex = 0;
let currentProjectId = '';
let currentLeftPanelMode = null;

// -----------------------------------------------------------------------------
// URLs e câmera (usam config centralizado)
// -----------------------------------------------------------------------------

async function loadFrustumsForProject(projectId) {
    const url = getCameraParamsUrl(projectId);
    const pix4dOffset = await loadPix4dOffset(projectId);
    const manualOffset = getOffsetForProject(projectId, availableProjects);
    const pix4d = pix4dOffset != null ? pix4dOffset : [0, 0, 0];
    loadCameraFrustumsAsync(url, pix4d, manualOffset, projectId, 0).catch((err) => {
        console.warn("Frustums não carregados:", err.message);
    });
}

function saveCameraState() {
    const view = viewer.scene.view;
    return {
        position: view.position.clone(),
        pivot: view.getPivot().clone()
    };
}

function restoreCameraState(state) {
    if (!state || !state.position || !state.pivot) return;
    viewer.scene.view.position.copy(state.position);
    viewer.scene.view.lookAt(state.pivot);
}

// -----------------------------------------------------------------------------
// Material e definição da nuvem atual
// -----------------------------------------------------------------------------

function applyDefaultMaterial(pointcloud) {
    const material = pointcloud.material;
    material.size = 1;
    material.pointSizeType = Potree.PointSizeType.ADAPTIVE;
    material.shape = Potree.PointShape.SQUARE;
}

function setAsCurrent(pointcloud, projectId) {
    currentPointcloud = pointcloud;
    window.currentPointcloud = pointcloud;
    currentProjectId = projectId;
    window.currentProjectId = projectId;
    const idx = availableProjects.findIndex((p) => p.id === projectId);
    if (idx >= 0) currentIndex = idx;
    const sel = document.getElementById('seletor_projeto');
    if (sel) sel.value = projectId;
    updateArrowButtons();
    updateOffsetUI();
    updateFotosForNewCloud();
}

// -----------------------------------------------------------------------------
// Carregamento de nuvens (cache hit/miss)
// -----------------------------------------------------------------------------

function loadCloud(projectId, keepCamera) {
    if (!projectId || !viewer || !cache) return;
    if (projectId === currentProjectId) return;

    const cached = cache.get(projectId);
    if (cached) {
        if (currentPointcloud) currentPointcloud.visible = false;
        cached.visible = true;
        cache.touch(projectId);
        setAsCurrent(cached, projectId);
        loadFrustumsForProject(projectId);
        viewer.render();
        return;
    }

    if (currentPointcloud) currentPointcloud.visible = false;
    const cameraState = (currentPointcloud && keepCamera) ? saveCameraState() : null;

    if (cache.size >= MAX_CACHED_CLOUDS) cache.evictLRU();

    const url = getCloudJsUrl(projectId);
    try {
        Potree.loadPointCloud(url, 'DSM', (e) => {
            if (!e || !e.pointcloud) {
                console.warn('Falha ao carregar nuvem:', projectId);
                if (currentPointcloud) currentPointcloud.visible = true;
                return;
            }
            const scene = viewer.scene;
            const pointcloud = e.pointcloud;
            pointcloud.userData.initialPosition = pointcloud.position.clone();
            const offset = getOffsetForProject(projectId, availableProjects);
            pointcloud.position.x += offset[0];
            pointcloud.position.y += offset[1];
            pointcloud.position.z += offset[2];
            applyDefaultMaterial(pointcloud);
            scene.addPointCloud(pointcloud);
            cache.add(projectId, pointcloud);
            pointcloud.visible = true;
            setAsCurrent(pointcloud, projectId);
            loadFrustumsForProject(projectId);
            if (cameraState) {
                restoreCameraState(cameraState);
            } else {
                viewer.fitToScreen();
            }
            viewer.render();
        });
    } catch (err) {
        console.warn('Erro ao carregar nuvem:', projectId, err);
        if (currentPointcloud) currentPointcloud.visible = true;
    }
}

// -----------------------------------------------------------------------------
// UI: timeline (setas, seletor)
// -----------------------------------------------------------------------------

function updateArrowButtons() {
    const prev = document.getElementById('btn_anterior');
    const next = document.getElementById('btn_proximo');
    if (prev) prev.disabled = availableProjects.length === 0 || currentIndex <= 0;
    if (next) next.disabled = availableProjects.length === 0 || currentIndex >= availableProjects.length - 1;
}

function goToProject(index) {
    if (index < 0 || index >= availableProjects.length) return;
    currentIndex = index;
    loadCloud(availableProjects[index].id, true);
}

function initTimeline() {
    const sel = document.getElementById('seletor_projeto');
    if (sel) {
        sel.innerHTML = '';
        availableProjects.forEach((p) => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.label || p.id;
            sel.appendChild(opt);
        });
    }
    const initial = initialProjectId || (availableProjects[0] && availableProjects[0].id);
    const idx = availableProjects.findIndex((p) => p.id === initial);
    currentIndex = idx >= 0 ? idx : 0;
    if (sel) sel.value = availableProjects[currentIndex] ? availableProjects[currentIndex].id : '';
    updateArrowButtons();
    if (initial && availableProjects.length > 0) loadCloud(initial, false);
}

function onSelectChange() {
    const id = this.value;
    const idx = availableProjects.findIndex((p) => p.id === id);
    if (idx >= 0) {
        currentIndex = idx;
        loadCloud(id, true);
    }
}

function onKeydown(e) {
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
    if (availableProjects.length === 0) return;
    if (e.key === 'ArrowLeft') {
        if (currentIndex <= 0) return;
        e.preventDefault();
        goToProject(currentIndex - 1);
    } else {
        if (currentIndex >= availableProjects.length - 1) return;
        e.preventDefault();
        goToProject(currentIndex + 1);
    }
}

// -----------------------------------------------------------------------------
// UI: barra lateral esquerda e painel único
// -----------------------------------------------------------------------------

const STATUS_MESSAGES = {
    config: 'Ferramentas Potree (medição, câmera, clipping)',
    camadas: 'Visibilidade de camadas',
    compare: 'Selecione 2–4 fotos para comparar',
    fotos: 'Clique em um ponto para ver fotos',
    default: 'Nuvem de pontos'
};

function setStatusMessage(msg) {
    const el = document.getElementById('status_bar_message');
    if (el) el.textContent = msg || STATUS_MESSAGES.default;
}

/** Área de render fica com left fixo; o painel sobrepõe por cima (z-index), sem redimensionar a nuvem. */
function updateRenderAreaLeft() {
    const renderArea = document.getElementById('potree_render_area');
    if (!renderArea) return;
    renderArea.style.left = SIDEBAR_LEFT_WIDTH + 'px';
}

function setLeftPanelMode(mode) {
    const sameMode = currentLeftPanelMode === mode;
    currentLeftPanelMode = sameMode ? null : mode;

    const leftPanel = document.getElementById('left_panel');
    const contents = document.querySelectorAll('.left-panel-content');
    const buttons = document.querySelectorAll('.sidebar-btn');

    if (!leftPanel || !contents.length) return;

    if (currentLeftPanelMode) {
        leftPanel.classList.add('open');
        leftPanel.setAttribute('data-mode', currentLeftPanelMode);
        contents.forEach((el) => {
            el.classList.toggle('active', el.getAttribute('data-mode') === currentLeftPanelMode);
        });
        buttons.forEach((btn) => {
            btn.classList.toggle('active', btn.getAttribute('data-mode') === currentLeftPanelMode);
        });
        setStatusMessage(STATUS_MESSAGES[currentLeftPanelMode] || STATUS_MESSAGES.default);
        const sidebar = document.getElementById('potree_sidebar_container');
        if (sidebar && currentLeftPanelMode === 'config') sidebar.style.display = 'block';
        if (currentLeftPanelMode === 'camadas' && typeof window.refreshCamadasCheckboxes === 'function') window.refreshCamadasCheckboxes();
        if (currentLeftPanelMode === 'compare' && typeof window.setComparePhotosActive === 'function') window.setComparePhotosActive(true);
        if (currentLeftPanelMode === 'fotos' && typeof window.setFotosToolActive === 'function') window.setFotosToolActive(true);
    } else {
        if (typeof window.setComparePhotosActive === 'function') window.setComparePhotosActive(false);
        if (typeof window.setFotosToolActive === 'function') window.setFotosToolActive(false);
        leftPanel.classList.remove('open');
        leftPanel.removeAttribute('data-mode');
        contents.forEach((el) => el.classList.remove('active'));
        buttons.forEach((btn) => btn.classList.remove('active'));
        setStatusMessage(STATUS_MESSAGES.default);
        const sidebar = document.getElementById('potree_sidebar_container');
        if (sidebar) sidebar.style.display = 'none';
    }

    updateRenderAreaLeft();
}

function initLeftPanel() {
    const leftPanel = document.getElementById('left_panel');
    const sidebar = document.getElementById('potree_sidebar_container');
    if (sidebar) sidebar.style.display = 'none';

    document.querySelectorAll('.sidebar-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const mode = btn.getAttribute('data-mode');
            if (mode) setLeftPanelMode(mode);
        });
    });

    updateRenderAreaLeft();
    if (typeof window.setStatusMessage === 'undefined') window.setStatusMessage = setStatusMessage;
    if (typeof window.setLeftPanelMode === 'undefined') window.setLeftPanelMode = setLeftPanelMode;
}

// -----------------------------------------------------------------------------
// UI: offset (developerMode) – dentro do painel Fotos no ponto
// -----------------------------------------------------------------------------

function roundOffset(num, decimals = 2) {
    const factor = 10 ** decimals;
    return Math.round(num * factor) / factor;
}

function updateOffsetUI() {
    if (!developerMode) return;
    const inpX = document.getElementById('offset_x');
    const inpY = document.getElementById('offset_y');
    const inpZ = document.getElementById('offset_z');
    if (!inpX || !inpY || !inpZ) return;
    if (currentPointcloud) {
        const initial = currentPointcloud.userData.initialPosition;
        const p = currentPointcloud.position;
        inpX.value = roundOffset(p.x - (initial ? initial.x : 0));
        inpY.value = roundOffset(p.y - (initial ? initial.y : 0));
        inpZ.value = roundOffset(p.z - (initial ? initial.z : 0));
    } else {
        inpX.value = inpY.value = inpZ.value = '0';
    }
}

function applyOffsetFromUI() {
    if (!currentPointcloud || !viewer) return;
    const inpX = document.getElementById('offset_x');
    const inpY = document.getElementById('offset_y');
    const inpZ = document.getElementById('offset_z');
    if (!inpX || !inpY || !inpZ) return;
    const initial = currentPointcloud.userData.initialPosition;
    const oldOx = (initial ? currentPointcloud.position.x - initial.x : currentPointcloud.position.x) || 0;
    const oldOy = (initial ? currentPointcloud.position.y - initial.y : currentPointcloud.position.y) || 0;
    const oldOz = (initial ? currentPointcloud.position.z - initial.z : currentPointcloud.position.z) || 0;
    const ox = parseFloat(inpX.value) || 0;
    const oy = parseFloat(inpY.value) || 0;
    const oz = parseFloat(inpZ.value) || 0;
    currentPointcloud.position.x = (initial ? initial.x : 0) + ox;
    currentPointcloud.position.y = (initial ? initial.y : 0) + oy;
    currentPointcloud.position.z = (initial ? initial.z : 0) + oz;
    const projectId = availableProjects[currentIndex]?.id;
    if (projectId) setOffsetForProject(projectId, [roundOffset(ox), roundOffset(oy), roundOffset(oz)]);
    const dx = ox - oldOx;
    const dy = oy - oldOy;
    const dz = oz - oldOz;
    if (dx !== 0 || dy !== 0 || dz !== 0) {
        applyOffsetDeltaToFrustums(dx, dy, dz);
    }
    viewer.render();
}

function copyOffsetToClipboard() {
    if (!currentPointcloud) return;
    const initial = currentPointcloud.userData.initialPosition;
    const p = currentPointcloud.position;
    const x = roundOffset(p.x - (initial ? initial.x : 0));
    const y = roundOffset(p.y - (initial ? initial.y : 0));
    const z = roundOffset(p.z - (initial ? initial.z : 0));
    const json = JSON.stringify([x, y, z]);
    navigator.clipboard.writeText(json).then(() => {
        if (typeof console !== 'undefined' && console.log) console.log('Offset copiado: ' + json);
    }).catch(() => {});
}

function initOffsetUI() {
    if (!developerMode) return;
    const container = document.getElementById('left_panel_fotos');
    if (!container) return;
    const block = document.createElement('div');
    block.className = 'offset-tool offset-tool-visible';
    block.innerHTML = '<span>Offset</span><div class="offset-controls">' +
        '<input type="number" id="offset_x" step="any" placeholder="X" title="X">' +
        '<input type="number" id="offset_y" step="any" placeholder="Y" title="Y">' +
        '<input type="number" id="offset_z" step="any" placeholder="Z" title="Z">' +
        '<button type="button" id="btn_salvar_offset" title="Copiar offset em JSON">Copiar Offset</button>' +
        '</div>';
    container.appendChild(block);
    const inpX = document.getElementById('offset_x');
    const inpY = document.getElementById('offset_y');
    const inpZ = document.getElementById('offset_z');
    const saveOffsetBtn = document.getElementById('btn_salvar_offset');
    if (inpX) inpX.addEventListener('input', applyOffsetFromUI);
    if (inpY) inpY.addEventListener('input', applyOffsetFromUI);
    if (inpZ) inpZ.addEventListener('input', applyOffsetFromUI);
    if (inpX) inpX.addEventListener('change', applyOffsetFromUI);
    if (inpY) inpY.addEventListener('change', applyOffsetFromUI);
    if (inpZ) inpZ.addEventListener('change', applyOffsetFromUI);
    if (saveOffsetBtn) saveOffsetBtn.addEventListener('click', copyOffsetToClipboard);
}

// -----------------------------------------------------------------------------
// Inicialização do viewer e da timeline
// -----------------------------------------------------------------------------

function init() {
    const renderArea = document.getElementById('potree_render_area');
    if (!renderArea) return;

    viewer = new Potree.Viewer(renderArea);
    window.viewer = viewer;

    cache = createCloudCache(viewer, MAX_CACHED_CLOUDS);

    viewer.setBackground('gradient');
    viewer.setEDLEnabled(false);
    viewer.setFOV(60);
    viewer.setPointBudget(10_000_000);
    viewer.loadSettingsFromURL();
    viewer.setDescription('');

    viewer.loadGUI(() => {
        viewer.setLanguage('pt');
        $('#menu_tools').next().show();
        $('#menu_clipping').next().show();
        const sidebar = document.getElementById('potree_sidebar_container');
        if (sidebar) sidebar.style.display = 'none';
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTimeline);
    } else {
        initTimeline();
    }

    const sel = document.getElementById('seletor_projeto');
    const btnPrev = document.getElementById('btn_anterior');
    const btnNext = document.getElementById('btn_proximo');

    if (sel) sel.addEventListener('change', onSelectChange);
    if (btnPrev) btnPrev.addEventListener('click', () => goToProject(currentIndex - 1));
    if (btnNext) btnNext.addEventListener('click', () => goToProject(currentIndex + 1));
    document.addEventListener('keydown', onKeydown);

    initLeftPanel();
    initFotosPanelAjustes();
    initOffsetUI();
}

function updatePointSize(value) {
    if (window.currentPointcloud) {
        window.currentPointcloud.material.size = value;
        if (viewer && typeof viewer.render === 'function') viewer.render();
    }
}

function initFotosPanelAjustes() {
    const container = document.getElementById('status_bar_extra');
    if (!container) return;

    const ajustesWrap = document.createElement('div');
    ajustesWrap.className = 'status-bar-ajustes';
    ajustesWrap.innerHTML = `
        <div class="point-size-control">
            <label for="pointSizeSlider">Tamanho dos pontos:</label>
            <div class="slider-container">
                <input type="range" id="pointSizeSlider" min="0.1" max="5" step="0.1" value="1" class="slider">
                <input type="number" id="pointSizeInput" min="0.1" max="5" step="0.1" value="1" class="size-input">
            </div>
        </div>
    `;
    container.appendChild(ajustesWrap);

    const slider = document.getElementById('pointSizeSlider');
    const input = document.getElementById('pointSizeInput');
    if (slider && input) {
        slider.addEventListener('input', () => {
            const value = parseFloat(slider.value);
            input.value = value.toFixed(1);
            updatePointSize(value);
        });
        input.addEventListener('input', () => {
            const value = parseFloat(input.value);
            if (!isNaN(value) && value >= 0.1 && value <= 5) {
                slider.value = value;
                updatePointSize(value);
            }
        });
        input.addEventListener('blur', () => {
            const value = parseFloat(input.value);
            if (isNaN(value) || value < 0.1) { input.value = '0.1'; slider.value = 0.1; updatePointSize(0.1); }
            else if (value > 5) { input.value = '5'; slider.value = 5; updatePointSize(5); }
        });
    }
}

init();
