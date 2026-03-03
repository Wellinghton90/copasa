/**
 * Ferramenta de comparação de fotos.
 * Permite comparar entre 2, 3 ou 4 fotos de nuvens diferentes.
 * Permite seleção via ponto na nuvem ou lista completa de fotos.
 * 
 * @class ComparePhotosTool
 */

import * as THREE from "../../potree/libs/three.js/build/three.module.js";
import { getImageUrlForProject } from "../Frustum/imagePath.js";
import { comparePhotosSelectionTemplate } from "./templates/comparePhotosSelectionTemplate.js";
import { comparePhotosComparisonTemplate } from "./templates/comparePhotosComparisonTemplate.js";
import { comparePhotosSlotTemplate } from "./templates/comparePhotosSlotTemplate.js";
import { comparePhotosModalTemplate } from "./templates/comparePhotosModalTemplate.js";
import { comparePhotosGridCellTemplate, comparePhotosModalItemTemplate } from "./templates/comparePhotosGridCellTemplate.js";
import { getConfig, PHOTO_LIMITS } from "../config/viewer-config.js";

export class ComparePhotosTool {
    /**
     * Cria uma instância de ComparePhotosTool.
     * @param {object} viewer - Instância Potree.Viewer
     * @param {PhotosAtPointTool} photosTool - Ferramenta de fotos no ponto
     */
    constructor(viewer, photosTool) {
        this.viewer = viewer;
        this.photosTool = photosTool;
        this.active = false;
        this.selectedPhotos = [];
        this.currentSlot = 0;
        this.selectionMode = null;
        this.pointSelectionActive = false;
        this.selectionPanelEl = null;
        this.comparisonPanelEl = null;
        this.clickHandler = null;
        this.photoSelectionModalEl = null;
        /** Dados do modal de seleção por ponto */
        this.modalAllPhotos = [];
        this.modalProjectId = "";
        this.modalSlotIndex = 0;
        this.modalLimitIndex = 0;
    }

    /**
     * Inicializa a ferramenta de comparação de fotos.
     */
    init() {
        if (typeof window !== "undefined") {
            window.setComparePhotosActive = (active) => this.setActive(active);
        }
        this._createSelectionPanel();
        this._createComparisonPanel();
    }

    /**
     * Ativa ou desativa o modo de comparação de fotos.
     * @param {boolean} active - Se true, ativa o modo
     */
    setActive(active) {
        this.active = active;
        const btn = document.getElementById("btn_sidebar_compare_photos");
        if (btn) {
            btn.classList.toggle("active", active);
        }

        if (active) {
            if (this.photosTool) {
                this.photosTool.setActive(false);
            } else if (typeof window.setFotosToolActive === "function") {
                window.setFotosToolActive(false);
            }
        }

        if (!active) {
            this._hideComparisonPanel();
            this._resetSelection();
        }
        
        // Expõe função globalmente para compatibilidade
        if (typeof window !== "undefined") {
            window.setComparePhotosActive = (active) => this.setActive(active);
        }
    }

    /**
     * Verifica se o modo de comparação está ativo.
     * @returns {boolean}
     */
    isActive() {
        return this.active;
    }

    /**
     * Cria o painel de seleção de fotos.
     * @private
     */
    _createSelectionPanel() {
        this.selectionPanelEl = document.createElement("div");
        this.selectionPanelEl.className = "compare-photos-selection-panel";
        this.selectionPanelEl.innerHTML = comparePhotosSelectionTemplate;

        const config = getConfig();
        const projetos = config.projetosDisponiveis || [];
        const selects = this.selectionPanelEl.querySelectorAll(".photo-slot-project-select");
        selects.forEach(select => {
            projetos.forEach(proj => {
                const option = document.createElement("option");
                option.value = proj.id;
                option.textContent = proj.label || proj.id;
                select.appendChild(option);
            });
        });

        const closeBtn = this.selectionPanelEl.querySelector(".compare-photos-close-btn");
        closeBtn.addEventListener("click", () => {
            this.setActive(false);
            if (typeof window.setLeftPanelMode === "function") {
                window.setLeftPanelMode(null);
            }
        });

        const addSlot3Btn = this.selectionPanelEl.querySelector("#btn_add_slot_3");
        addSlot3Btn.addEventListener("click", () => this._addSlot(2));

        const compareBtn = this.selectionPanelEl.querySelector("#btn_compare_photos_panel");
        compareBtn.addEventListener("click", () => this._showComparison());

        const slots = this.selectionPanelEl.querySelectorAll(".photo-slot");
        slots.forEach((slot, index) => {
            const selectPointBtn = slot.querySelector(".photo-slot-select-point-btn");
            const choosePhotoBtn = slot.querySelector(".photo-slot-choose-photo-btn");
            const removeBtn = slot.querySelector(".photo-slot-remove-btn");
            const projectSelect = slot.querySelector(".photo-slot-project-select");

            selectPointBtn.addEventListener("click", () => this._activatePointSelection(index));
            choosePhotoBtn.addEventListener("click", () => this._activateFrustumPickForSlot(index));
            removeBtn.addEventListener("click", () => this._removePhotoFromSlot(index));
            projectSelect.addEventListener("change", () => {
                this._removePhotoFromSlot(index);
            });
        });

        const container = document.getElementById("left_panel_compare");
        if (container) {
            container.appendChild(this.selectionPanelEl);
            this.selectionPanelEl.style.display = "flex";
        } else {
            const potreeContainer = document.querySelector(".potree_container");
            if (potreeContainer) {
                potreeContainer.appendChild(this.selectionPanelEl);
            }
        }
        this._updateSlotsGridLayout();
    }

    /**
     * Cria o painel de visualização de comparação.
     * @private
     */
    _createComparisonPanel() {
        this.comparisonPanelEl = document.createElement("div");
        this.comparisonPanelEl.className = "compare-photos-comparison-panel";
        this.comparisonPanelEl.style.display = "none";
        this.comparisonPanelEl.innerHTML = comparePhotosComparisonTemplate;

        const backBtn = this.comparisonPanelEl.querySelector(".compare-photos-back-btn");
        backBtn.addEventListener("click", () => {
            this._exitCompareFullscreen();
            this._hideComparisonPanel();
            this._showSelectionPanel();
        });

        const potreeContainer = document.querySelector(".potree_container");
        if (potreeContainer) {
            potreeContainer.appendChild(this.comparisonPanelEl);
        }
    }

    /**
     * Mostra/esconde painéis.
     * @private
     */
    _showSelectionPanel() {
        if (this.selectionPanelEl) {
            this.selectionPanelEl.style.display = "block";
        }
    }

    _hideSelectionPanel() {
        if (this.selectionPanelEl) {
            this.selectionPanelEl.style.display = "none";
        }
    }

    _showComparisonPanel() {
        if (this.comparisonPanelEl) {
            this.comparisonPanelEl.style.display = "block";
            this._updateComparisonGrid();
        }
    }

    _hideComparisonPanel() {
        if (this.comparisonPanelEl) {
            this.comparisonPanelEl.style.display = "none";
        }
        this._exitCompareFullscreen();
    }

    /**
     * Adiciona um slot de foto.
     * @private
     */
    _addSlot(slotIndex) {
        const slotsContainer = this.selectionPanelEl.querySelector(".compare-photos-slots-container");
        const config = getConfig();
        const projetos = config.projetosDisponiveis || [];

        const slot = document.createElement("div");
        slot.className = "photo-slot";
        slot.setAttribute("data-slot", slotIndex);
        slot.innerHTML = comparePhotosSlotTemplate;

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

        selectPointBtn.addEventListener("click", () => this._activatePointSelection(slotIndex));
        choosePhotoBtn.addEventListener("click", () => this._activateFrustumPickForSlot(slotIndex));
        removeBtn.addEventListener("click", () => this._removePhotoFromSlot(slotIndex));

        slotsContainer.appendChild(slot);
        this._updateSlotsGridLayout();
        this._updateAddSlotButtons();
    }

    /**
     * Atualiza layout dos slots.
     * @private
     */
    _updateSlotsGridLayout() {
        const slotsContainer = this.selectionPanelEl.querySelector(".compare-photos-slots-container");
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

    /**
     * Atualiza botões de adicionar slot.
     * @private
     */
    _updateAddSlotButtons() {
        const slotsContainer = this.selectionPanelEl.querySelector(".compare-photos-slots-container");
        const slots = slotsContainer.querySelectorAll(".photo-slot");
        const actionsContainer = this.selectionPanelEl.querySelector(".compare-photos-selection-actions");

        const existingAddBtns = actionsContainer.querySelectorAll(".compare-photos-add-slot-btn");
        existingAddBtns.forEach(btn => btn.remove());

        if (slots.length === 2) {
            const btn3 = document.createElement("button");
            btn3.type = "button";
            btn3.className = "compare-photos-add-slot-btn";
            btn3.id = "btn_add_slot_3";
            btn3.textContent = "+ Adicionar 3ª foto";
            btn3.addEventListener("click", () => this._addSlot(2));
            actionsContainer.insertBefore(btn3, actionsContainer.querySelector(".compare-photos-compare-btn"));
        } else if (slots.length === 3) {
            const btn4 = document.createElement("button");
            btn4.type = "button";
            btn4.className = "compare-photos-add-slot-btn";
            btn4.id = "btn_add_slot_4";
            btn4.textContent = "+ Adicionar 4ª foto";
            btn4.addEventListener("click", () => this._addSlot(3));
            actionsContainer.insertBefore(btn4, actionsContainer.querySelector(".compare-photos-compare-btn"));
        }
    }

    /**
     * Limpa o modo "Escolher foto área" (frustum pick).
     * @private
     */
    _clearFrustumPickMode() {
        const mode = window.comparePhotosFrustumPickMode;
        if (mode && this.selectionPanelEl) {
            const slot = this.selectionPanelEl.querySelector(`[data-slot="${mode.slotIndex}"]`);
            if (slot) slot.classList.remove("photo-slot-selecting");
        }
        window.comparePhotosFrustumPickMode = null;
        window.onFrustumClickAddToCompareSlot = null;
        if (typeof window.restoreStatusMessage === "function") {
            window.restoreStatusMessage();
        }
    }

    /**
     * Ativa o modo de seleção por clique em frustum para o slot.
     * @private
     */
    _activateFrustumPickForSlot(slotIndex) {
        if (!this.active) return;

        const slot = this.selectionPanelEl.querySelector(`[data-slot="${slotIndex}"]`);
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

        this._clearFrustumPickMode();
        window.comparePhotosFrustumPickMode = { active: true, slotIndex, projectIdSlot };
        if (typeof window.setStatusMessage === "function" && window.STATUS_MESSAGE_PHOTO_AREA) {
            window.setStatusMessage(window.STATUS_MESSAGE_PHOTO_AREA);
        }

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
            this._selectPhoto(projectId, photoName, imageUrl, slotIndexFromMode);
            this._clearFrustumPickMode();
        };

        if (slot) slot.classList.add("photo-slot-selecting");
    }

    /**
     * Ativa seleção por ponto.
     * @private
     */
    _activatePointSelection(slotIndex) {
        if (!this.active) return;

        this._clearFrustumPickMode();
        this.currentSlot = slotIndex;
        this.pointSelectionActive = true;
        this.selectionMode = 'point';

        if (this.photosTool) {
            this.photosTool.setActive(false);
        } else if (typeof window.setFotosToolActive === "function") {
            window.setFotosToolActive(false);
        }

        const renderArea = document.getElementById("potree_render_area");
        if (!renderArea) return;

        if (this.clickHandler) {
            renderArea.removeEventListener("click", this.clickHandler);
        }

        this.clickHandler = async (e) => {
            if (!this.pointSelectionActive) return;

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
            await this._showPhotosForPoint(worldPoint, slotIndex);
        };

        renderArea.addEventListener("click", this.clickHandler);

        const slot = this.selectionPanelEl.querySelector(`[data-slot="${slotIndex}"]`);
        if (slot) {
            slot.classList.add("photo-slot-selecting");
        }
    }

    /**
     * Mostra fotos para um ponto.
     * @private
     */
    async _showPhotosForPoint(worldPoint, slotIndex) {
        const slot = this.selectionPanelEl.querySelector(`[data-slot="${slotIndex}"]`);
        const projectSelect = slot.querySelector(".photo-slot-project-select");
        const projectId = projectSelect.value;

        if (!projectId) {
            alert("Por favor, selecione uma nuvem primeiro.");
            return;
        }

        const config = getConfig();
        const projetos = config.projetosDisponiveis || [];

        try {
            const cameras = await this.photosTool.loadCamerasForProject(projectId);
            const offset = await this.photosTool.loadOffsetForFotos(projectId, projetos);
            const offsetV = new THREE.Vector3(offset[0], offset[1], offset[2]);

            const camerasWorld = cameras.map((c) => ({
                ...c,
                position: c.position.clone().sub(offsetV)
            }));
            const allBest = this.photosTool.getBestCamerasForPoint(worldPoint, camerasWorld, null);

            if (allBest.length === 0) {
                alert("Nenhuma foto contém este ponto.");
                return;
            }

            this._showPhotoSelectionModal(allBest, projectId, slotIndex);

        } catch (error) {
            console.error("Erro ao carregar fotos:", error);
            alert("Erro ao carregar fotos do projeto.");
        }
    }

    /**
     * Mostra modal de seleção de fotos.
     * @private
     */
    _showPhotoSelectionModal(photos, projectId, slotIndex) {
        if (this.photoSelectionModalEl) {
            this.photoSelectionModalEl.remove();
        }

        this.modalAllPhotos = photos;
        this.modalProjectId = projectId;
        this.modalSlotIndex = slotIndex;
        this.modalLimitIndex = 0;

        this.photoSelectionModalEl = document.createElement("div");
        this.photoSelectionModalEl.className = "compare-photos-selection-modal";
        this.photoSelectionModalEl.innerHTML = comparePhotosModalTemplate;

        const loadMoreBtn = this.photoSelectionModalEl.querySelector("#compare_modal_load_more");
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener("click", () => {
                if (this.modalLimitIndex < PHOTO_LIMITS.length - 1) {
                    this.modalLimitIndex += 1;
                    this._fillModalPhotoGrid();
                }
            });
        }

        this._fillModalPhotoGrid();

        const closeBtn = this.photoSelectionModalEl.querySelector(".compare-photos-modal-close");
        closeBtn.addEventListener("click", () => this._closePhotoSelectionModal());

        this.photoSelectionModalEl.addEventListener("click", (e) => {
            if (e.target === this.photoSelectionModalEl) {
                this._closePhotoSelectionModal();
            }
        });

        document.body.appendChild(this.photoSelectionModalEl);
    }

    /**
     * Preenche grid do modal com fotos.
     * @private
     */
    _fillModalPhotoGrid() {
        if (!this.photoSelectionModalEl) return;
        const grid = this.photoSelectionModalEl.querySelector(".compare-photos-modal-grid");
        const loadMoreWrap = this.photoSelectionModalEl.querySelector(".compare-photos-modal-load-more-wrap");
        if (!grid) return;

        const limit = PHOTO_LIMITS[this.modalLimitIndex];
        const photosToShow = this.modalAllPhotos.slice(0, Math.min(limit, this.modalAllPhotos.length));
        grid.innerHTML = "";

        photosToShow.forEach((photo) => {
            const photoName = photo.name || photo;
            const url = getImageUrlForProject(this.modalProjectId, photoName + ".JPG");

            const item = document.createElement("div");
            item.className = "compare-photos-modal-item";
            item.innerHTML = comparePhotosModalItemTemplate(photoName, url);

            const selectBtn = item.querySelector(".compare-photos-modal-select-btn");
            selectBtn.addEventListener("click", () => {
                this._selectPhoto(this.modalProjectId, photoName, url, this.modalSlotIndex);
                this._closePhotoSelectionModal();
            });
            grid.appendChild(item);
        });

        if (loadMoreWrap) {
            const atMaxLimit = this.modalLimitIndex >= PHOTO_LIMITS.length - 1;
            const hasMoreToShow = this.modalAllPhotos.length > PHOTO_LIMITS[this.modalLimitIndex];
            loadMoreWrap.style.display = !atMaxLimit && hasMoreToShow ? "block" : "none";
        }
    }

    /**
     * Fecha modal de seleção de fotos.
     * @private
     */
    _closePhotoSelectionModal() {
        if (this.photoSelectionModalEl) {
            this.photoSelectionModalEl.remove();
            this.photoSelectionModalEl = null;
        }
        this.pointSelectionActive = false;
        const renderArea = document.getElementById("potree_render_area");
        if (renderArea && this.clickHandler) {
            renderArea.removeEventListener("click", this.clickHandler);
            this.clickHandler = null;
        }
    }

    /**
     * Seleciona uma foto para um slot.
     * @private
     */
    _selectPhoto(projectId, photoName, imageUrl, slotIndex) {
        this.selectedPhotos = this.selectedPhotos.filter(p => p.slotIndex !== slotIndex);
        this.selectedPhotos.push({ projectId, photoName, imageUrl, slotIndex });
        this._updateSlotUI(slotIndex);
        this._updateCompareButton();
    }

    /**
     * Atualiza UI de um slot.
     * @private
     */
    _updateSlotUI(slotIndex) {
        const slot = this.selectionPanelEl.querySelector(`[data-slot="${slotIndex}"]`);
        if (!slot) return;

        const photo = this.selectedPhotos.find(p => p.slotIndex === slotIndex);
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

    /**
     * Remove foto de um slot.
     * @private
     */
    _removePhotoFromSlot(slotIndex) {
        this.selectedPhotos = this.selectedPhotos.filter(p => p.slotIndex !== slotIndex);
        this._updateSlotUI(slotIndex);
        this._updateCompareButton();
    }

    /**
     * Atualiza botão de comparar.
     * @private
     */
    _updateCompareButton() {
        const compareBtn = this.selectionPanelEl.querySelector("#btn_compare_photos_panel");
        if (compareBtn) {
            const hasAtLeastTwo = this.selectedPhotos.length >= 2;
            compareBtn.style.display = hasAtLeastTwo ? "block" : "none";
        }
    }

    /**
     * Mostra painel de comparação.
     * @private
     */
    _showComparison() {
        if (this.selectedPhotos.length < 2) return;
        this._hideSelectionPanel();
        this._showComparisonPanel();
        this._enterCompareFullscreen();
    }

    /**
     * Entra/sai do modo fullscreen de comparação.
     * @private
     */
    _enterCompareFullscreen() {
        const container = document.querySelector(".potree_container");
        if (container) container.classList.add("compare-fullscreen");
    }

    _exitCompareFullscreen() {
        const container = document.querySelector(".potree_container");
        if (container) container.classList.remove("compare-fullscreen");
    }

    /**
     * Atualiza grid de comparação.
     * @private
     */
    _updateComparisonGrid() {
        const grid = this.comparisonPanelEl.querySelector(".compare-photos-grid");
        if (!grid) return;

        grid.innerHTML = "";

        const count = this.selectedPhotos.length;

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

        this.selectedPhotos.forEach((photo) => {
            const cell = document.createElement("div");
            cell.className = "compare-photos-grid-cell";
            cell.innerHTML = comparePhotosGridCellTemplate(photo.photoName, photo.projectId, photo.imageUrl);

            const removeBtn = cell.querySelector(".compare-photos-cell-remove");
            removeBtn.addEventListener("click", () => {
                this._removePhotoFromSlot(photo.slotIndex);
                if (this.selectedPhotos.length < 2) {
                    this._hideComparisonPanel();
                    this._showSelectionPanel();
                } else {
                    this._updateComparisonGrid();
                }
            });

            grid.appendChild(cell);
        });
    }

    /**
     * Reseta seleção.
     * @private
     */
    _resetSelection() {
        this._clearFrustumPickMode();
        this.selectedPhotos = [];
        this.currentSlot = 0;
        this.selectionMode = null;
        this.pointSelectionActive = false;

        const renderArea = document.getElementById("potree_render_area");
        if (renderArea && this.clickHandler) {
            renderArea.removeEventListener("click", this.clickHandler);
            this.clickHandler = null;
        }

        if (this.photoSelectionModalEl) {
            this.photoSelectionModalEl.remove();
            this.photoSelectionModalEl = null;
        }

        const slotsContainer = this.selectionPanelEl.querySelector(".compare-photos-slots-container");
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

        this._updateSlotsGridLayout();
        this._updateAddSlotButtons();
        this._updateCompareButton();
    }
}
