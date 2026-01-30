/**
 * Módulo: viewer Potree + timeline de nuvens.
 * Orquestra config, cache, offset e UI. Expõe window.viewer e window.currentPointcloud.
 */

import { getConfig } from './nuvem-config.js';
import { createCloudCache } from './nuvem-cache.js';
import { getOffsetForProject, setOffsetForProject } from './nuvem-offset.js';

const CONFIG = getConfig();
const {
    projetosDisponiveis = [],
    projetoInicial = '',
    baseProjetos = 'projetos',
    suffixPotree = 'potree',
    obra = '',
    developerMode = false
} = CONFIG;

const MAX_CACHED_CLOUDS = 3;

let viewer;
let cache;
let currentPointcloud = null;
let indiceAtual = 0;
let currentProjectId = '';

// -----------------------------------------------------------------------------
// URLs e câmera
// -----------------------------------------------------------------------------

function getUrlCloudJs(id) {
    return `${baseProjetos}/${id}/${suffixPotree}/cloud.js`;
}

function salvarEstadoCamera() {
    const view = viewer.scene.view;
    return {
        position: view.position.clone(),
        pivot: view.getPivot().clone()
    };
}

function restaurarEstadoCamera(estado) {
    if (!estado || !estado.position || !estado.pivot) return;
    viewer.scene.view.position.copy(estado.position);
    viewer.scene.view.lookAt(estado.pivot);
}

// -----------------------------------------------------------------------------
// Material e definição da nuvem atual
// -----------------------------------------------------------------------------

function aplicarMaterialPadrao(pointcloud) {
    const material = pointcloud.material;
    material.size = 1;
    material.pointSizeType = Potree.PointSizeType.ADAPTIVE;
    material.shape = Potree.PointShape.SQUARE;
}

function definirComoAtual(pointcloud, identificadorProjeto) {
    currentPointcloud = pointcloud;
    window.currentPointcloud = pointcloud;
    currentProjectId = identificadorProjeto;
    const idx = projetosDisponiveis.findIndex((p) => p.id === identificadorProjeto);
    if (idx >= 0) indiceAtual = idx;
    const sel = document.getElementById('seletor_projeto');
    if (sel) sel.value = identificadorProjeto;
    atualizarBotoesSetas();
    atualizarUIOffset();
}

// -----------------------------------------------------------------------------
// Carregamento de nuvens (cache hit/miss)
// -----------------------------------------------------------------------------

function carregarNuvem(identificadorProjeto, manterCamera) {
    if (!identificadorProjeto || !viewer || !cache) return;
    if (identificadorProjeto === currentProjectId) return;

    const cached = cache.get(identificadorProjeto);
    if (cached) {
        if (currentPointcloud) currentPointcloud.visible = false;
        cached.visible = true;
        cache.touch(identificadorProjeto);
        definirComoAtual(cached, identificadorProjeto);
        viewer.render();
        return;
    }

    if (currentPointcloud) currentPointcloud.visible = false;
    const estadoCamera = (currentPointcloud && manterCamera) ? salvarEstadoCamera() : null;

    if (cache.size >= MAX_CACHED_CLOUDS) cache.evictLRU();

    const url = getUrlCloudJs(identificadorProjeto);
    try {
        Potree.loadPointCloud(url, 'DSM', (e) => {
            if (!e || !e.pointcloud) {
                console.warn('Falha ao carregar nuvem:', identificadorProjeto);
                if (currentPointcloud) currentPointcloud.visible = true;
                return;
            }
            const scene = viewer.scene;
            const pointcloud = e.pointcloud;
            pointcloud.userData.initialPosition = pointcloud.position.clone();
            const offset = getOffsetForProject(identificadorProjeto, projetosDisponiveis);
            pointcloud.position.x += offset[0];
            pointcloud.position.y += offset[1];
            pointcloud.position.z += offset[2];
            aplicarMaterialPadrao(pointcloud);
            scene.addPointCloud(pointcloud);
            cache.add(identificadorProjeto, pointcloud);
            pointcloud.visible = true;
            definirComoAtual(pointcloud, identificadorProjeto);
            if (estadoCamera) {
                restaurarEstadoCamera(estadoCamera);
            } else {
                viewer.fitToScreen();
            }
            viewer.render();
        });
    } catch (err) {
        console.warn('Erro ao carregar nuvem:', identificadorProjeto, err);
        if (currentPointcloud) currentPointcloud.visible = true;
    }
}

// -----------------------------------------------------------------------------
// UI: timeline (setas, seletor)
// -----------------------------------------------------------------------------

function atualizarBotoesSetas() {
    const prev = document.getElementById('btn_anterior');
    const next = document.getElementById('btn_proximo');
    if (prev) prev.disabled = projetosDisponiveis.length === 0 || indiceAtual <= 0;
    if (next) next.disabled = projetosDisponiveis.length === 0 || indiceAtual >= projetosDisponiveis.length - 1;
}

function irParaProjeto(index) {
    if (index < 0 || index >= projetosDisponiveis.length) return;
    indiceAtual = index;
    carregarNuvem(projetosDisponiveis[index].id, true);
}

function initTimeline() {
    const sel = document.getElementById('seletor_projeto');
    if (sel) {
        sel.innerHTML = '';
        projetosDisponiveis.forEach((p) => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.label || p.id;
            sel.appendChild(opt);
        });
    }
    const inicial = projetoInicial || (projetosDisponiveis[0] && projetosDisponiveis[0].id);
    const idx = projetosDisponiveis.findIndex((p) => p.id === inicial);
    indiceAtual = idx >= 0 ? idx : 0;
    if (sel) sel.value = projetosDisponiveis[indiceAtual] ? projetosDisponiveis[indiceAtual].id : '';
    atualizarBotoesSetas();
    if (inicial && projetosDisponiveis.length > 0) carregarNuvem(inicial, false);
}

function onSelectChange() {
    const id = this.value;
    const idx = projetosDisponiveis.findIndex((p) => p.id === id);
    if (idx >= 0) {
        indiceAtual = idx;
        carregarNuvem(id, true);
    }
}

function onKeydown(e) {
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
    if (projetosDisponiveis.length === 0) return;
    if (e.key === 'ArrowLeft') {
        if (indiceAtual <= 0) return;
        e.preventDefault();
        irParaProjeto(indiceAtual - 1);
    } else {
        if (indiceAtual >= projetosDisponiveis.length - 1) return;
        e.preventDefault();
        irParaProjeto(indiceAtual + 1);
    }
}

// -----------------------------------------------------------------------------
// UI: offset (developerMode)
// -----------------------------------------------------------------------------

/** Arredonda para N casas decimais (evita ruído de ponto flutuante na UI e no clipboard). */
function roundOffset(num, decimals = 2) {
    const factor = 10 ** decimals;
    return Math.round(num * factor) / factor;
}

function atualizarUIOffset() {
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

function aplicarOffsetFromUI() {
    if (!currentPointcloud || !viewer) return;
    const inpX = document.getElementById('offset_x');
    const inpY = document.getElementById('offset_y');
    const inpZ = document.getElementById('offset_z');
    if (!inpX || !inpY || !inpZ) return;
    const initial = currentPointcloud.userData.initialPosition;
    const ox = parseFloat(inpX.value) || 0;
    const oy = parseFloat(inpY.value) || 0;
    const oz = parseFloat(inpZ.value) || 0;
    currentPointcloud.position.x = (initial ? initial.x : 0) + ox;
    currentPointcloud.position.y = (initial ? initial.y : 0) + oy;
    currentPointcloud.position.z = (initial ? initial.z : 0) + oz;
    const projectId = projetosDisponiveis[indiceAtual]?.id;
    if (projectId) setOffsetForProject(projectId, [roundOffset(ox), roundOffset(oy), roundOffset(oz)]);
    viewer.render();
}

function salvarOffsetClipboard() {
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
    const toolbar = document.querySelector('.viewer-toolbar');
    if (!toolbar) return;
    const block = document.createElement('div');
    block.className = 'offset-tool';
    block.innerHTML = '<span>Offset</span><div class="offset-controls">' +
        '<input type="number" id="offset_x" step="any" placeholder="X" title="X">' +
        '<input type="number" id="offset_y" step="any" placeholder="Y" title="Y">' +
        '<input type="number" id="offset_z" step="any" placeholder="Z" title="Z">' +
        '<button type="button" id="btn_salvar_offset" title="Copiar offset em JSON">Copiar Offset</button>' +
        '</div>';
    toolbar.appendChild(block);
    const inpX = document.getElementById('offset_x');
    const inpY = document.getElementById('offset_y');
    const inpZ = document.getElementById('offset_z');
    const btnSalvar = document.getElementById('btn_salvar_offset');
    if (inpX) inpX.addEventListener('input', aplicarOffsetFromUI);
    if (inpY) inpY.addEventListener('input', aplicarOffsetFromUI);
    if (inpZ) inpZ.addEventListener('input', aplicarOffsetFromUI);
    if (inpX) inpX.addEventListener('change', aplicarOffsetFromUI);
    if (inpY) inpY.addEventListener('change', aplicarOffsetFromUI);
    if (inpZ) inpZ.addEventListener('change', aplicarOffsetFromUI);
    if (btnSalvar) btnSalvar.addEventListener('click', salvarOffsetClipboard);
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
    viewer.setDescription('Visualizador 3D - Obra: ' + obra);

    viewer.loadGUI(() => {
        viewer.setLanguage('pt');
        $('#menu_tools').next().show();
        $('#menu_clipping').next().show();
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
    if (btnPrev) btnPrev.addEventListener('click', () => irParaProjeto(indiceAtual - 1));
    if (btnNext) btnNext.addEventListener('click', () => irParaProjeto(indiceAtual + 1));
    document.addEventListener('keydown', onKeydown);

    if (developerMode) initOffsetUI();
}

init();
