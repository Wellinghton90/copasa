/**
 * Ferramenta de camadas (Layers).
 * Gerencia checkboxes para mostrar/ocultar nuvem e frustums.
 * 
 * @class LayersTool
 */

import { layersPanelTemplate } from "./templates/layersPanelTemplate.js";

export class LayersTool {
    /**
     * Cria uma instância de LayersTool.
     * @param {object} viewer - Instância Potree.Viewer
     */
    constructor(viewer) {
        this.viewer = viewer;
        this.panelEl = null;
    }

    /**
     * Obtém o grupo de frustums da cena.
     * @private
     * @returns {THREE.Group|null}
     */
    _getFrustumsGroup() {
        return this.viewer && this.viewer.scene && this.viewer.scene.cameraFrustumsGroup
            ? this.viewer.scene.cameraFrustumsGroup
            : null;
    }

    /**
     * Define a visibilidade da nuvem de pontos.
     * @param {boolean} visible - Se true, mostra a nuvem
     */
    setPointCloudVisible(visible) {
        const pointcloud = window.currentPointcloud;
        if (pointcloud) {
            pointcloud.visible = !!visible;
        }
        if (this.viewer && typeof this.viewer.render === "function") {
            this.viewer.render();
        }
    }

    /**
     * Define a visibilidade dos frustums.
     * @param {boolean} visible - Se true, mostra os frustums
     */
    setFrustumsVisible(visible) {
        const group = this._getFrustumsGroup();
        if (group) {
            group.visible = !!visible;
        }
        if (this.viewer && typeof this.viewer.render === "function") {
            this.viewer.render();
        }
    }

    /**
     * Atualiza os checkboxes com os valores atuais de visibilidade.
     */
    refreshCheckboxes() {
        if (!this.panelEl) {
            return;
        }
        
        const cbNuvem = this.panelEl.querySelector("#camadas_nuvem");
        const cbFrustums = this.panelEl.querySelector("#camadas_frustums");
        
        if (cbNuvem && window.currentPointcloud) {
            cbNuvem.checked = window.currentPointcloud.visible;
        }
        
        if (cbFrustums) {
            const group = this._getFrustumsGroup();
            cbFrustums.checked = group ? group.visible : true;
        }
    }

    /**
     * Cria o painel de camadas.
     * @private
     */
    _createPanel() {
        const container = document.getElementById("left_panel_camadas");
        if (!container || this.panelEl) {
            return;
        }

        this.panelEl = document.createElement("div");
        this.panelEl.id = "camadasSettingsPanel";
        this.panelEl.className = "camadas-settings-panel camadas-settings-panel-embedded";
        this.panelEl.innerHTML = layersPanelTemplate;

        const closeBtn = this.panelEl.querySelector(".camadas-close-btn");
        closeBtn.addEventListener("click", () => {
            if (typeof window.setLeftPanelMode === "function") {
                window.setLeftPanelMode(null);
            }
        });

        const cbNuvem = this.panelEl.querySelector("#camadas_nuvem");
        const cbFrustums = this.panelEl.querySelector("#camadas_frustums");
        
        cbNuvem.addEventListener("change", () => {
            this.setPointCloudVisible(cbNuvem.checked);
        });
        
        cbFrustums.addEventListener("change", () => {
            this.setFrustumsVisible(cbFrustums.checked);
        });

        container.appendChild(this.panelEl);
    }

    /**
     * Inicializa a ferramenta de camadas.
     */
    init() {
        if (!this.viewer || !document.querySelector(".potree_container")) {
            return;
        }
        
        if (this.panelEl) {
            return;
        }

        this._createPanel();
        this.refreshCheckboxes();
        
        // Expõe função globalmente para compatibilidade
        if (typeof window !== "undefined") {
            window.refreshCamadasCheckboxes = () => this.refreshCheckboxes();
        }
    }
}
