/**
 * Comparar Fotos: ferramenta para comparar entre 2, 3 ou 4 fotos de nuvens diferentes
 * Permite seleção via ponto na nuvem ou lista completa de fotos
 */

import * as THREE from "../../potree/libs/three.js/build/three.module.js";
import { getBestCamerasForPoint, loadCamerasForProject, loadOffsetForFotos, setFotosToolActive } from "./photos-at-point.js";
import { getImageUrlForProject } from "../Frustum/imagePath.js";
import { getConfig, PHOTO_LIMITS } from "../config/viewer-config.js";

// Estado da comparação
let comparePhotosActive = false;
let selectedPhotos = [];
let currentSlot = 0;
let selectionMode = null;
let pointSelectionActive = false;

let selectionPanelEl = null;
let comparisonPanelEl = null;
let clickHandler = null;
let photoSelectionModalEl = null;
/** Dados do modal de seleção por ponto (para "Carregar mais fotos") */
let modalAllPhotos = [];
let modalProjectId = "";
let modalSlotIndex = 0;
let modalLimitIndex = 0;

/**
 * Inicializa a ferramenta de comparação de fotos.
 * O botão da barra esquerda é tratado por timeline.js; o painel de seleção é criado em #left_panel_compare.
 */
export function initComparePhotos() {
    createSelectionPanel();
    createComparisonPanel();
}

/**
 * Ativa ou desativa o modo de comparação de fotos
 * @param {boolean} active
 */
export function setComparePhotosActive(active) {
    comparePhotosActive = active;
    const btn = document.getElementById("btn_sidebar_compare_photos");
    if (btn) btn.classList.toggle("active", active);

    if (active) {
        setFotosToolActive(false);
    }

    if (!active) {
        hideComparisonPanel();
        resetSelection();
    }
}

if (typeof window !== 'undefined') {
    window.setComparePhotosActive = setComparePhotosActive;
}

/**
 * Verifica se o modo de comparação está ativo
 * @returns {boolean}
 */
export function isComparePhotosActive() {
    return comparePhotosActive;
}

/**
 * Cria o painel de seleção de fotos
 */
function createSelectionPanel() {
    selectionPanelEl = document.createElement("div");
    selectionPanelEl.className = "compare-photos-selection-panel";
    selectionPanelEl.innerHTML = `
        <div class="compare-photos-selection-header">
            <span class="compare-photos-selection-title">Comparar Fotos</span>
            <button type="button" class="compare-photos-close-btn" title="Fechar">×</button>
        </div>
        <div class="compare-photos-slots-container">
            <div class="photo-slot" data-slot="0">
                <div class="photo-slot-header">
                    <select class="photo-slot-project-select">
                        <option value="">Selecione a nuvem</option>
                    </select>
                </div>
                <div class="photo-slot-controls">
                    <button type="button" class="photo-slot-select-point-btn">Selecionar ponto</button>
                    <button type="button" class="photo-slot-choose-photo-btn">Escolher foto área</button>
                </div>
                <div class="photo-slot-preview"></div>
                <button type="button" class="photo-slot-remove-btn" style="display: none;">Remover</button>
            </div>
            <div class="photo-slot" data-slot="1">
                <div class="photo-slot-header">
                    <select class="photo-slot-project-select">
                        <option value="">Selecione a nuvem</option>
                    </select>
                </div>
                <div class="photo-slot-controls">
                    <button type="button" class="photo-slot-select-point-btn">Selecionar ponto</button>
                    <button type="button" class="photo-slot-choose-photo-btn">Escolher foto área</button>
                </div>
                <div class="photo-slot-preview"></div>
                <button type="button" class="photo-slot-remove-btn" style="display: none;">Remover</button>
            </div>
        </div>
        <div class="compare-photos-selection-actions">
            <button type="button" class="compare-photos-add-slot-btn" id="btn_add_slot_3">+ Adicionar 3ª foto</button>
            <button type="button" class="compare-photos-compare-btn" id="btn_compare_photos_panel" style="display: none;">Comparar</button>
        </div>
    `;

    const config = getConfig();
    const projetos = config.projetosDisponiveis || [];
    const selects = selectionPanelEl.querySelectorAll(".photo-slot-project-select");
    selects.forEach(select => {
        projetos.forEach(proj => {
            const option = document.createElement("option");
            option.value = proj.id;
            option.textContent = proj.label || proj.id;
            select.appendChild(option);
        });
    });

    const closeBtn = selectionPanelEl.querySelector(".compare-photos-close-btn");
    closeBtn.addEventListener("click", () => {
        setComparePhotosActive(false);
        if (typeof window.setLeftPanelMode === "function") window.setLeftPanelMode(null);
    });

    const addSlot3Btn = selectionPanelEl.querySelector("#btn_add_slot_3");
    addSlot3Btn.addEventListener("click", () => addSlot(2));

    const compareBtn = selectionPanelEl.querySelector("#btn_compare_photos_panel");
    compareBtn.addEventListener("click", () => showComparison());

    const slots = selectionPanelEl.querySelectorAll(".photo-slot");
    slots.forEach((slot, index) => {
        const selectPointBtn = slot.querySelector(".photo-slot-select-point-btn");
        const choosePhotoBtn = slot.querySelector(".photo-slot-choose-photo-btn");
        const removeBtn = slot.querySelector(".photo-slot-remove-btn");
        const projectSelect = slot.querySelector(".photo-slot-project-select");

        selectPointBtn.addEventListener("click", () => activatePointSelection(index));
        choosePhotoBtn.addEventListener("click", () => activateFrustumPickForSlot(index));
        removeBtn.addEventListener("click", () => removePhotoFromSlot(index));
        projectSelect.addEventListener("change", () => {
            removePhotoFromSlot(index);
        });
    });

    const container = document.getElementById("left_panel_compare");
    if (container) {
        container.appendChild(selectionPanelEl);
        selectionPanelEl.style.display = "flex";
    } else {
        document.querySelector(".potree_container").appendChild(selectionPanelEl);
    }
    updateSlotsGridLayout();
}

/**
 * Cria o painel de visualização de comparação
 */
function createComparisonPanel() {
    comparisonPanelEl = document.createElement("div");
    comparisonPanelEl.className = "compare-photos-comparison-panel";
    comparisonPanelEl.style.display = "none";
    comparisonPanelEl.innerHTML = `
        <div class="compare-photos-comparison-header">
            <span class="compare-photos-comparison-title">Comparação de Fotos</span>
            <button type="button" class="compare-photos-back-btn">← Voltar</button>
        </div>
        <div class="compare-photos-grid"></div>
    `;

    const backBtn = comparisonPanelEl.querySelector(".compare-photos-back-btn");
    backBtn.addEventListener("click", () => {
        exitCompareFullscreen();
        hideComparisonPanel();
        showSelectionPanel();
    });

    document.querySelector(".potree_container").appendChild(comparisonPanelEl);
}

function showSelectionPanel() {
    if (selectionPanelEl) {
        selectionPanelEl.style.display = "block";
    }
}

function hideSelectionPanel() {
    if (selectionPanelEl) {
        selectionPanelEl.style.display = "none";
    }
}

function showComparisonPanel() {
    if (comparisonPanelEl) {
        comparisonPanelEl.style.display = "block";
        updateComparisonGrid();
    }
}

function hideComparisonPanel() {
    if (comparisonPanelEl) {
        comparisonPanelEl.style.display = "none";
    }
    exitCompareFullscreen();
}

function addSlot(slotIndex) {
    const slotsContainer = selectionPanelEl.querySelector(".compare-photos-slots-container");
    const config = getConfig();
    const projetos = config.projetosDisponiveis || [];

    const slot = document.createElement("div");
    slot.className = "photo-slot";
    slot.setAttribute("data-slot", slotIndex);
    slot.innerHTML = `
        <div class="photo-slot-header">
            <select class="photo-slot-project-select">
                <option value="">Selecione a nuvem</option>
            </select>
        </div>
        <div class="photo-slot-controls">
            <button type="button" class="photo-slot-select-point-btn">Selecionar ponto</button>
            <button type="button" class="photo-slot-choose-photo-btn">Escolher foto área</button>
        </div>
        <div class="photo-slot-preview"></div>
        <button type="button" class="photo-slot-remove-btn" style="display: none;">Remover</button>
    `;

    const select = slot.querySelector(".photo-slot-project-select");
    projetos.forEach(proj => {
        const option = document.createElement("option");
        option.value = proj.id;
        option.textContent = proj.label || proj.id;
        select.appendChild(option);
    });

    const selectPointBtn = slot.querySelector(".photo-slot-select-point-btn");
    const choosePhotoBtn = slot.querySelector(".photo-slot-choose-photo-btn");
    const removeBtn = slot.querySelector(".photo-slot-remove-btn");

    selectPointBtn.addEventListener("click", () => activatePointSelection(slotIndex));
    choosePhotoBtn.addEventListener("click", () => activateFrustumPickForSlot(slotIndex));
    removeBtn.addEventListener("click", () => removePhotoFromSlot(slotIndex));

    slotsContainer.appendChild(slot);
    updateSlotsGridLayout();
    updateAddSlotButtons();
}

function updateSlotsGridLayout() {
    const slotsContainer = selectionPanelEl.querySelector(".compare-photos-slots-container");
    const slots = slotsContainer.querySelectorAll(".photo-slot");

    slotsContainer.classList.remove("slots-2", "slots-3", "slots-4");

    if (slots.length === 2) {
        slotsContainer.classList.add("slots-2");
    } else if (slots.length === 3) {
        slotsContainer.classList.add("slots-3");
    } else if (slots.length === 4) {
        slotsContainer.classList.add("slots-4");
    }
}

function updateAddSlotButtons() {
    const slotsContainer = selectionPanelEl.querySelector(".compare-photos-slots-container");
    const slots = slotsContainer.querySelectorAll(".photo-slot");
    const actionsContainer = selectionPanelEl.querySelector(".compare-photos-selection-actions");

    const existingAddBtns = actionsContainer.querySelectorAll(".compare-photos-add-slot-btn");
    existingAddBtns.forEach(btn => btn.remove());

    if (slots.length === 2) {
        const btn3 = document.createElement("button");
        btn3.type = "button";
        btn3.className = "compare-photos-add-slot-btn";
        btn3.id = "btn_add_slot_3";
        btn3.textContent = "+ Adicionar 3ª foto";
        btn3.addEventListener("click", () => addSlot(2));
        actionsContainer.insertBefore(btn3, actionsContainer.querySelector(".compare-photos-compare-btn"));
    } else if (slots.length === 3) {
        const btn4 = document.createElement("button");
        btn4.type = "button";
        btn4.className = "compare-photos-add-slot-btn";
        btn4.id = "btn_add_slot_4";
        btn4.textContent = "+ Adicionar 4ª foto";
        btn4.addEventListener("click", () => addSlot(3));
        actionsContainer.insertBefore(btn4, actionsContainer.querySelector(".compare-photos-compare-btn"));
    }
}

/**
 * Limpa o modo "Escolher foto área" (frustum pick) e o callback global.
 */
function clearFrustumPickMode() {
    const mode = window.comparePhotosFrustumPickMode;
    if (mode && selectionPanelEl) {
        const slot = selectionPanelEl.querySelector(`[data-slot="${mode.slotIndex}"]`);
        if (slot) slot.classList.remove("photo-slot-selecting");
    }
    window.comparePhotosFrustumPickMode = null;
    window.onFrustumClickAddToCompareSlot = null;
}

/**
 * Ativa o modo de seleção por clique em frustum para o slot (Escolher foto área).
 * Sincroniza a nuvem do viewer com o dropdown do slot se forem diferentes.
 */
function activateFrustumPickForSlot(slotIndex) {
    if (!comparePhotosActive) return;

    const slot = selectionPanelEl.querySelector(`[data-slot="${slotIndex}"]`);
    const projectSelect = slot?.querySelector(".photo-slot-project-select");
    const projectIdSlot = projectSelect?.value;

    if (!projectIdSlot) {
        alert("Por favor, selecione uma nuvem primeiro.");
        return;
    }

    const currentProjectId = window.currentProjectId ?? document.getElementById("seletor_projeto")?.value ?? "";
    if (projectIdSlot !== currentProjectId) {
        const sel = document.getElementById("seletor_projeto");
        if (sel) {
            sel.value = projectIdSlot;
            sel.dispatchEvent(new Event("change"));
        }
    }

    clearFrustumPickMode();
    window.comparePhotosFrustumPickMode = { active: true, slotIndex, projectIdSlot };

    window.onFrustumClickAddToCompareSlot = (cameraInfoWithImage, slotIndexFromMode) => {
        const m = window.comparePhotosFrustumPickMode;
        const projectIdSlotExpected = m?.projectIdSlot;
        if (cameraInfoWithImage.projectId !== projectIdSlotExpected) {
            alert("Aguarde a nuvem do slot carregar.");
            return;
        }
        const projectId = cameraInfoWithImage.projectId;
        const photoName = cameraInfoWithImage.name;
        const imageUrl = cameraInfoWithImage.imagePath;
        selectPhoto(projectId, photoName, imageUrl, slotIndexFromMode);
        clearFrustumPickMode();
    };

    if (slot) slot.classList.add("photo-slot-selecting");
}

function activatePointSelection(slotIndex) {
    if (!comparePhotosActive) return;

    clearFrustumPickMode();
    currentSlot = slotIndex;
    pointSelectionActive = true;
    selectionMode = 'point';

    setFotosToolActive(false);

    const renderArea = document.getElementById("potree_render_area");
    if (!renderArea) return;

    if (clickHandler) {
        renderArea.removeEventListener("click", clickHandler);
    }

    clickHandler = async (e) => {
        if (!pointSelectionActive) return;

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
        await showPhotosForPoint(worldPoint, slotIndex);
    };

    renderArea.addEventListener("click", clickHandler);

    const slot = selectionPanelEl.querySelector(`[data-slot="${slotIndex}"]`);
    if (slot) {
        slot.classList.add("photo-slot-selecting");
    }
}

async function showPhotosForPoint(worldPoint, slotIndex) {
    const slot = selectionPanelEl.querySelector(`[data-slot="${slotIndex}"]`);
    const projectSelect = slot.querySelector(".photo-slot-project-select");
    const projectId = projectSelect.value;

    if (!projectId) {
        alert("Por favor, selecione uma nuvem primeiro.");
        return;
    }

    const config = getConfig();
    const baseProjetos = config.baseProjetos || "projetos";
    const projetos = config.projetosDisponiveis || [];

    try {
        const cameras = await loadCamerasForProject(projectId, baseProjetos);
        const offset = await loadOffsetForFotos(projectId, baseProjetos, projetos);
        const offsetV = new THREE.Vector3(offset[0], offset[1], offset[2]);

        const camerasWorld = cameras.map((c) => ({ ...c, position: c.position.clone().sub(offsetV) }));
        const allBest = getBestCamerasForPoint(worldPoint, camerasWorld, null);

        if (allBest.length === 0) {
            alert("Nenhuma foto contém este ponto.");
            return;
        }

        showPhotoSelectionModal(allBest, projectId, slotIndex);

    } catch (error) {
        console.error("Erro ao carregar fotos:", error);
        alert("Erro ao carregar fotos do projeto.");
    }
}

async function openPhotoListModal(slotIndex) {
    const slot = selectionPanelEl.querySelector(`[data-slot="${slotIndex}"]`);
    const projectSelect = slot.querySelector(".photo-slot-project-select");
    const projectId = projectSelect.value;

    if (!projectId) {
        alert("Por favor, selecione uma nuvem primeiro.");
        return;
    }

    currentSlot = slotIndex;
    selectionMode = 'list';

    const config = getConfig();
    const baseProjetos = config.baseProjetos || "projetos";

    try {
        const cameras = await loadCamerasForProject(projectId, baseProjetos);
        showPhotoListModalContent(cameras, projectId, slotIndex);
    } catch (error) {
        console.error("Erro ao carregar fotos:", error);
        alert("Erro ao carregar fotos do projeto.");
    }
}

function fillModalPhotoGrid() {
    if (!photoSelectionModalEl) return;
    const grid = photoSelectionModalEl.querySelector(".compare-photos-modal-grid");
    const loadMoreWrap = photoSelectionModalEl.querySelector(".compare-photos-modal-load-more-wrap");
    if (!grid) return;

    const limit = PHOTO_LIMITS[modalLimitIndex];
    const photosToShow = modalAllPhotos.slice(0, Math.min(limit, modalAllPhotos.length));
    grid.innerHTML = "";

    photosToShow.forEach((photo) => {
        const photoName = photo.name || photo;
        const url = getImageUrlForProject(modalProjectId, photoName + ".JPG");

        const item = document.createElement("div");
        item.className = "compare-photos-modal-item";
        item.innerHTML = `
            <img src="${url}" alt="${photoName}" loading="lazy">
            <button type="button" class="compare-photos-modal-select-btn">Selecionar</button>
        `;

        const selectBtn = item.querySelector(".compare-photos-modal-select-btn");
        selectBtn.addEventListener("click", () => {
            selectPhoto(modalProjectId, photoName, url, modalSlotIndex);
            closePhotoSelectionModal();
        });
        grid.appendChild(item);
    });

    if (loadMoreWrap) {
        const atMaxLimit = modalLimitIndex >= PHOTO_LIMITS.length - 1;
        const hasMoreToShow = modalAllPhotos.length > PHOTO_LIMITS[modalLimitIndex];
        loadMoreWrap.style.display = !atMaxLimit && hasMoreToShow ? "block" : "none";
    }
}

function closePhotoSelectionModal() {
    if (photoSelectionModalEl) {
        photoSelectionModalEl.remove();
        photoSelectionModalEl = null;
    }
    pointSelectionActive = false;
    const renderArea = document.getElementById("potree_render_area");
    if (renderArea && clickHandler) {
        renderArea.removeEventListener("click", clickHandler);
        clickHandler = null;
    }
}

function showPhotoSelectionModal(photos, projectId, slotIndex) {
    if (photoSelectionModalEl) {
        photoSelectionModalEl.remove();
    }

    modalAllPhotos = photos;
    modalProjectId = projectId;
    modalSlotIndex = slotIndex;
    modalLimitIndex = 0;

    photoSelectionModalEl = document.createElement("div");
    photoSelectionModalEl.className = "compare-photos-selection-modal";
    photoSelectionModalEl.innerHTML = `
        <div class="compare-photos-modal-content">
            <div class="compare-photos-modal-header">
                <span>Selecione uma foto</span>
                <button type="button" class="compare-photos-modal-close">×</button>
            </div>
            <div class="compare-photos-modal-grid"></div>
            <div class="compare-photos-modal-load-more-wrap" style="display: none;">
                <button type="button" class="compare-photos-modal-load-more" id="compare_modal_load_more">Carregar mais fotos</button>
            </div>
        </div>
    `;

    const loadMoreBtn = photoSelectionModalEl.querySelector("#compare_modal_load_more");
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener("click", () => {
            if (modalLimitIndex < PHOTO_LIMITS.length - 1) {
                modalLimitIndex += 1;
                fillModalPhotoGrid();
            }
        });
    }

    fillModalPhotoGrid();

    const closeBtn = photoSelectionModalEl.querySelector(".compare-photos-modal-close");
    closeBtn.addEventListener("click", () => closePhotoSelectionModal());

    photoSelectionModalEl.addEventListener("click", (e) => {
        if (e.target === photoSelectionModalEl) closePhotoSelectionModal();
    });

    document.body.appendChild(photoSelectionModalEl);
}

function showPhotoListModalContent(cameras, projectId, slotIndex) {
    showPhotoSelectionModal(cameras, projectId, slotIndex);
}

function selectPhoto(projectId, photoName, imageUrl, slotIndex) {
    selectedPhotos = selectedPhotos.filter(p => p.slotIndex !== slotIndex);
    selectedPhotos.push({ projectId, photoName, imageUrl, slotIndex });
    updateSlotUI(slotIndex);
    updateCompareButton();
}

function updateSlotUI(slotIndex) {
    const slot = selectionPanelEl.querySelector(`[data-slot="${slotIndex}"]`);
    if (!slot) return;

    const photo = selectedPhotos.find(p => p.slotIndex === slotIndex);
    const preview = slot.querySelector(".photo-slot-preview");
    const removeBtn = slot.querySelector(".photo-slot-remove-btn");

    if (photo) {
        preview.innerHTML = `<img src="${photo.imageUrl}" alt="${photo.photoName}">`;
        removeBtn.style.display = "block";
        slot.classList.remove("photo-slot-selecting");
    } else {
        preview.innerHTML = "";
        removeBtn.style.display = "none";
    }
}

function removePhotoFromSlot(slotIndex) {
    selectedPhotos = selectedPhotos.filter(p => p.slotIndex !== slotIndex);
    updateSlotUI(slotIndex);
    updateCompareButton();
}

function updateCompareButton() {
    const compareBtn = selectionPanelEl.querySelector("#btn_compare_photos_panel");
    if (compareBtn) {
        const hasAtLeastTwo = selectedPhotos.length >= 2;
        compareBtn.style.display = hasAtLeastTwo ? "block" : "none";
    }
}

function showComparison() {
    if (selectedPhotos.length < 2) return;
    hideSelectionPanel();
    showComparisonPanel();
    enterCompareFullscreen();
}

function enterCompareFullscreen() {
    const container = document.querySelector(".potree_container");
    if (container) container.classList.add("compare-fullscreen");
}

function exitCompareFullscreen() {
    const container = document.querySelector(".potree_container");
    if (container) container.classList.remove("compare-fullscreen");
}

function updateComparisonGrid() {
    const grid = comparisonPanelEl.querySelector(".compare-photos-grid");
    if (!grid) return;

    grid.innerHTML = "";

    const count = selectedPhotos.length;

    if (count === 2) {
        grid.style.gridTemplateColumns = "1fr 1fr";
        grid.style.gridTemplateRows = "1fr";
    } else if (count === 3) {
        grid.style.gridTemplateColumns = "1fr 1fr 1fr";
        grid.style.gridTemplateRows = "1fr";
    } else if (count === 4) {
        grid.style.gridTemplateColumns = "1fr 1fr";
        grid.style.gridTemplateRows = "1fr 1fr";
    }

    selectedPhotos.forEach((photo) => {
        const cell = document.createElement("div");
        cell.className = "compare-photos-grid-cell";
        cell.innerHTML = `
            <div class="compare-photos-cell-header">
                <span class="compare-photos-cell-title">${photo.photoName}</span>
                <span class="compare-photos-cell-project">${photo.projectId}</span>
                <button type="button" class="compare-photos-cell-remove">×</button>
            </div>
            <div class="compare-photos-cell-image">
                <img src="${photo.imageUrl}" alt="${photo.photoName}">
            </div>
        `;

        const removeBtn = cell.querySelector(".compare-photos-cell-remove");
        removeBtn.addEventListener("click", () => {
            removePhotoFromSlot(photo.slotIndex);
            if (selectedPhotos.length < 2) {
                hideComparisonPanel();
                showSelectionPanel();
            } else {
                updateComparisonGrid();
            }
        });

        grid.appendChild(cell);
    });
}

function resetSelection() {
    clearFrustumPickMode();
    selectedPhotos = [];
    currentSlot = 0;
    selectionMode = null;
    pointSelectionActive = false;

    const renderArea = document.getElementById("potree_render_area");
    if (renderArea && clickHandler) {
        renderArea.removeEventListener("click", clickHandler);
        clickHandler = null;
    }

    if (photoSelectionModalEl) {
        photoSelectionModalEl.remove();
        photoSelectionModalEl = null;
    }

    const slotsContainer = selectionPanelEl.querySelector(".compare-photos-slots-container");
    const slots = slotsContainer.querySelectorAll(".photo-slot");
    for (let i = 2; i < slots.length; i++) {
        slots[i].remove();
    }

    const allSlots = slotsContainer.querySelectorAll(".photo-slot");
    allSlots.forEach(slot => {
        const preview = slot.querySelector(".photo-slot-preview");
        preview.innerHTML = "";
        const removeBtn = slot.querySelector(".photo-slot-remove-btn");
        removeBtn.style.display = "none";
        slot.classList.remove("photo-slot-selecting");
        const projectSelect = slot.querySelector(".photo-slot-project-select");
        if (projectSelect) projectSelect.value = "";
    });

    updateSlotsGridLayout();
    updateAddSlotButtons();
    updateCompareButton();
}

function tryInit() {
    if (window.viewer && document.getElementById("potree_render_area")) {
        initComparePhotos();
    } else {
        setTimeout(tryInit, 100);
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", tryInit);
} else {
    tryInit();
}
