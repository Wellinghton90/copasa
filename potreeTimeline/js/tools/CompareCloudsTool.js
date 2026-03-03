/**
 * Ferramenta de comparação de nuvens.
 * Permite visualizar duas nuvens de pontos sobrepostas com cores diferenciadas.
 * 
 * @class CompareCloudsTool
 */

import { getConfig } from "../config/viewer-config.js";
import { compareCloudsPanelTemplate } from "./templates/compareCloudsPanelTemplate.js";
import { BlinkController } from "./BlinkController.js";
import { TintController } from "./TintController.js";

/** Se true, aplica o tom apenas via uniforms no shader (leve, GPU). Requer que o shader do Potree use uTintAmount e uTintColor. */
const USE_TINT_SHADER_ONLY = true;

export class CompareCloudsTool {
    /**
     * Cria uma instância de CompareCloudsTool.
     * @param {object} viewer - Instância Potree.Viewer
     * @param {CloudLoader} cloudLoader - Loader de nuvens (deve ter método loadCloudForCompare)
     */
    constructor(viewer, cloudLoader) {
        this.viewer = viewer;
        this.cloudLoader = cloudLoader;
        this.active = false;
        this.selectionPanelEl = null;
        /** Armazena estado original (activeAttributeName, color, opacity, tintAmount) para restaurar ao sair */
        this.originalMaterialState = new Map();
        /** Cópias originais das cores (rgba) por nó, para restaurar ao sair e reaplicar tint */
        this.originalRgbaByPointcloud = new Map();
        /** Mapeia projectId -> pointcloud para atualizar em tempo real ao mudar sliders */
        this.comparePointclouds = new Map();
        /** projectIds das nuvens em comparação (para restaurar ao sair) */
        this.compareCloudIds = [];
        /** Controlador do modo blink (alternância de visibilidade entre as duas nuvens) */
        this._blinkController = new BlinkController();
        /** Controlador do modo tint (reaplicação periódica e atrasada do tom) */
        this._tintController = new TintController();
        /** Intervalo em ms do loop que verifica novos nós (1s); passado ao TintController */
        this.tintRefreshInterval = 1000;
        /** Mínimo de ms entre reaplicações de tint (throttle); passado ao TintController */
        this.tintReapplyThrottleMs = 2000;
        /** Usar apenas shader/GPU para tint (sem modificar buffers); depende do Potree usar uTintAmount/uTintColor no shader */
        this.useTintShaderOnly = USE_TINT_SHADER_ONLY;
        /** Nuvens escondidas ao entrar no modo piscar (para restaurar visibilidade ao sair) */
        this._blinkHiddenPointclouds = new Set();
    }

    /**
     * Inicializa a ferramenta de comparação de nuvens.
     */
    init() {
        this._createSelectionPanel();
    }

    /**
     * Ativa ou desativa o modo de comparação de nuvens.
     * Para parar o modo programaticamente: setCompareCloudsActive(false) ou compareCloudsTool.setActive(false).
     * Ao sair é disparado o evento "compare_clouds_exited" no viewer (detail.projectIds).
     * @param {boolean} active - Se true, ativa o modo
     */
    setActive(active) {
        this.active = active;
        const btn = document.getElementById("btn_sidebar_compare_clouds");
        if (btn) {
            btn.classList.toggle("active", active);
        }

        if (active) {
            try {
                if (!this.selectionPanelEl) {
                    this.init();
                }
                if (this.selectionPanelEl) {
                    this._ensurePanelInLeftPanel();
                    this.selectionPanelEl.style.display = "flex";
                    this.selectionPanelEl.style.visibility = "visible";
                    this._refreshProjectOptions();
                    this._setupPanelFromExisting();
                    this._updateActionsVisibility();
                }
            } catch (e) {
                // Ignorar erros
            }
        } else {
            this.exitCompareMode();
        }
        
        // Expõe funções globalmente para compatibilidade
        if (typeof window !== "undefined") {
            window.setCompareCloudsActive = (active) => this.setActive(active);
            window.isCompareCloudsActive = () => this.isActive();
            window.initCompareClouds = () => this.init();
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
     * Restaura o material original das nuvens e esconde a que não é a principal.
     */
    exitCompareMode() {
        this._tintController.stop();
        this._blinkController.stop();
        this._updateActionsVisibility();

        for (const [pointcloud, state] of this.originalMaterialState.entries()) {
            try {
                this._restoreOriginalColors(pointcloud);
            } catch (e) {
                // Ignorar erros
            }
            if (pointcloud?.material) {
                pointcloud.material.activeAttributeName = state.activeAttributeName;
                pointcloud.material.color.setHex(state.color);
                pointcloud.material.opacity = state.opacity;
                if (pointcloud.material.uniforms?.uTintAmount) {
                    pointcloud.material.uniforms.uTintAmount.value = state.tintAmount;
                }
                if (pointcloud.material.uniforms?.uTintColor && state.tintColor) {
                    const v = pointcloud.material.uniforms.uTintColor.value;
                    v.x = state.tintColor.x;
                    v.y = state.tintColor.y;
                    v.z = state.tintColor.z;
                }
            }
            if (pointcloud._compareTint !== undefined) pointcloud._compareTint = null;
        }
        
        this.originalMaterialState.clear();
        this.originalRgbaByPointcloud.clear();
        this.comparePointclouds.clear();

        for (const pc of this._blinkHiddenPointclouds) {
            if (pc && typeof pc.visible !== "undefined") pc.visible = true;
        }
        this._blinkHiddenPointclouds.clear();

        if (this.compareCloudIds.length > 0) {
            if (typeof window.unpinCompareClouds === "function") {
                window.unpinCompareClouds();
            }
            const finishExitCompareClouds = window.finishExitCompareClouds;
            if (typeof finishExitCompareClouds === "function") {
                finishExitCompareClouds(this.compareCloudIds[0], this.compareCloudIds);
            }
        }
        const exitedIds = [...this.compareCloudIds];
        this.compareCloudIds = [];

        const viewerExit = this.viewer || (typeof window !== "undefined" ? window.viewer : null);
        if (viewerExit && typeof viewerExit.dispatchEvent === "function") {
            viewerExit.dispatchEvent(new CustomEvent("compare_clouds_exited", { detail: { projectIds: exitedIds } }));
        }
    }

    /**
     * Converte hex (0xff0000) para RGB 0-255.
     * @private
     */
    _hexToRgb(hex) {
        return [(hex >> 16) & 255, (hex >> 8) & 255, hex & 255];
    }

    /**
     * Aplica tint modificando diretamente o buffer de cores da geometria.
     * @private
     */
    _applyTintToBuffer(pointcloud, hexColor, tintAmount) {
        if (!pointcloud) return;
        const nodes = pointcloud.visibleNodes || [];
        // Usa visibleNodes - o loop periódico garantirá que novos nós sejam processados quando ficarem visíveis
        const tintRgb = this._hexToRgb(hexColor);
        const t = Math.max(0, Math.min(1, tintAmount));
        let store = this.originalRgbaByPointcloud.get(pointcloud);
        if (!store) {
            store = new Map();
            this.originalRgbaByPointcloud.set(pointcloud, store);
        }

        for (const node of nodes) {
            const geoNode = node.geometryNode;
            if (!geoNode?.geometry) continue;

            const geom = geoNode.geometry;
            const attr = geom.attributes.rgba || geom.attributes.color;
            if (!attr || !attr.array || attr.itemSize !== 4) continue;

            const arr = attr.array;
            const key = geom.uuid || geom.id || node.name || String(node.pcIndex != null ? node.pcIndex : Math.random());

            // Salva cores originais apenas na primeira vez (quando o nó é carregado)
            if (!store.has(key)) {
                store.set(key, new Uint8Array(arr));
            }
            const orig = store.get(key);

            // Aplica o tint
            for (let i = 0; i < arr.length; i += 4) {
                const r0 = orig[i] / 255, g0 = orig[i + 1] / 255, b0 = orig[i + 2] / 255;
                const r1 = tintRgb[0] / 255, g1 = tintRgb[1] / 255, b1 = tintRgb[2] / 255;
                arr[i] = Math.round(255 * ((1 - t) * r0 + t * r1));
                arr[i + 1] = Math.round(255 * ((1 - t) * g0 + t * g1));
                arr[i + 2] = Math.round(255 * ((1 - t) * b0 + t * b1));
            }
            attr.needsUpdate = true;
            if (typeof attr.version !== "undefined") attr.version++;
        }

        // Invalida cache do renderer para que as alterações nos buffers sejam refletidas na tela
        // (necessário quando o tint é aplicado pelo loop de refresh, pois a segunda nuvem pode
        // ter tido 0 nós na primeira aplicação e nunca ter invalidado o cache)
        const viewer = this.viewer || window.viewer;
        const pRenderer = viewer && (viewer.pRenderer != null ? viewer.pRenderer : (typeof viewer.getPRenderer === "function" ? viewer.getPRenderer() : undefined));
        if (pRenderer && pointcloud.material) {
            if (pRenderer.shaders?.has?.(pointcloud.material)) {
                pRenderer.shaders.delete(pointcloud.material);
            }
            if (pRenderer.buffers && nodes.length > 0) {
                for (const node of nodes) {
                    const geom = node.geometryNode?.geometry;
                    if (geom && pRenderer.buffers.has(geom)) {
                        pRenderer.buffers.delete(geom);
                    }
                }
            }
        }
    }

    /**
     * Restaura cores originais nos buffers da geometria.
     * @private
     */
    _restoreOriginalColors(pointcloud) {
        const store = this.originalRgbaByPointcloud.get(pointcloud);
        if (!store) return;

        const nodes = pointcloud.visibleNodes || [];
        for (const node of nodes) {
            const geoNode = node.geometryNode;
            if (!geoNode?.geometry) continue;

            const geom = geoNode.geometry;
            const attr = geom.attributes.rgba || geom.attributes.color;
            if (!attr || !attr.array) continue;

            const key = geom.uuid || geom.id || node.name || String(node.pcIndex != null ? node.pcIndex : Math.random());
            const orig = store.get(key);
            if (!orig) continue;

            attr.array.set(orig);
            attr.needsUpdate = true;
            if (typeof attr.version !== "undefined") attr.version++;
        }
    }

    /**
     * Aplica tonalidade (tint) à nuvem mantendo a cor original.
     * @private
     */
    _applyCompareColor(pointcloud, hexColor, tintAmount) {
        if (!pointcloud || !pointcloud.material) return;
        const mat = pointcloud.material;

        if (!this.originalMaterialState.has(pointcloud)) {
            const prevTintColor = mat.uniforms?.uTintColor?.value;
            this.originalMaterialState.set(pointcloud, {
                activeAttributeName: mat.activeAttributeName,
                color: mat.color ? mat.color.getHex() : 0xffffff,
                opacity: mat.opacity,
                tintAmount: (mat.uniforms && mat.uniforms.uTintAmount && mat.uniforms.uTintAmount.value != null) 
                    ? mat.uniforms.uTintAmount.value : 0,
                tintColor: prevTintColor ? { x: prevTintColor.x, y: prevTintColor.y, z: prevTintColor.z } : { x: 1, y: 1, z: 1 }
            });
        }

        mat.activeAttributeName = "rgba";
        mat.color.setHex(hexColor);
        if (!mat.uniforms.uTintAmount) {
            mat.uniforms.uTintAmount = { type: "f", value: 0 };
        }
        mat.uniforms.uTintAmount.value = Math.max(0, Math.min(1, tintAmount));

        const [r, g, b] = this._hexToRgb(hexColor);
        if (!mat.uniforms.uTintColor) {
            mat.uniforms.uTintColor = { type: "v3", value: { x: r / 255, y: g / 255, z: b / 255 } };
        } else {
            mat.uniforms.uTintColor.value.x = r / 255;
            mat.uniforms.uTintColor.value.y = g / 255;
            mat.uniforms.uTintColor.value.z = b / 255;
        }

        mat.opacity = 1.0;
        mat.needsUpdate = true;

        // Guarda no point cloud para o renderer usar (octree.material pode ser outra referência)
        pointcloud._compareTint = { amount: Math.max(0, Math.min(1, tintAmount)), color: { x: r / 255, y: g / 255, z: b / 255 } };

        if (this.useTintShaderOnly) {
            // Caminho leve: só uniforms; o shader deve fazer: color.rgb = mix(color.rgb, uTintColor, uTintAmount)
            const viewer = this.viewer || window.viewer;
            const pRenderer = viewer && (viewer.pRenderer != null ? viewer.pRenderer : (typeof viewer.getPRenderer === "function" ? viewer.getPRenderer() : undefined));
            if (pRenderer && pRenderer.shaders?.has?.(mat)) {
                pRenderer.shaders.delete(mat);
            }
        } else {
            this._applyTintToBuffer(pointcloud, hexColor, tintAmount);
            const viewer = this.viewer || window.viewer;
            const pRenderer = viewer && (viewer.pRenderer != null ? viewer.pRenderer : (typeof viewer.getPRenderer === "function" ? viewer.getPRenderer() : undefined));
            if (pRenderer) {
                if (pRenderer.shaders?.has?.(mat)) pRenderer.shaders.delete(mat);
                if (pRenderer.buffers) {
                    for (const node of pointcloud.visibleNodes || []) {
                        const geom = node.geometryNode?.geometry;
                        if (geom && pRenderer.buffers.has(geom)) pRenderer.buffers.delete(geom);
                    }
                }
            }
        }
    }

    /**
     * Cria o painel de seleção dinamicamente usando o template.
     * @private
     */
    _createSelectionPanel() {
        // Verifica se já existe (não deveria, mas por segurança)
        const existing = document.getElementById("compare_clouds_panel");
        if (existing) {
            existing.remove();
        }
        
        // Cria o painel usando o template
        this.selectionPanelEl = document.createElement("div");
        this.selectionPanelEl.className = "compare-clouds-selection-panel";
        this.selectionPanelEl.id = "compare_clouds_panel";
        this.selectionPanelEl.innerHTML = compareCloudsPanelTemplate;

        // Adiciona ao container
        const containerEl = document.getElementById("left_panel_compare_clouds");
        if (containerEl) {
            containerEl.appendChild(this.selectionPanelEl);
            this.selectionPanelEl.style.display = "flex";
        } else {
            // Fallback: adiciona ao container principal do Potree
            const potreeContainer = document.querySelector(".potree_container");
            if (potreeContainer) {
                potreeContainer.appendChild(this.selectionPanelEl);
            }
        }

        this._setupPanelFromExisting();
    }

    /**
     * Garante que o painel está dentro de #left_panel_compare_clouds (como Fotos do Ponto).
     * @private
     */
    _ensurePanelInLeftPanel() {
        if (!this.selectionPanelEl) return;
        const container = document.getElementById("left_panel_compare_clouds");
        if (!container || this.selectionPanelEl.parentNode === container) return;
        this.selectionPanelEl.classList.add("compare-clouds-panel-embedded");
        container.insertBefore(this.selectionPanelEl, container.firstChild);
    }

    /**
     * Preenche os dropdowns de nuvens com a lista atual de projetos.
     * @private
     */
    _refreshProjectOptions() {
        if (!this.selectionPanelEl) return;
        const config = getConfig();
        const projetos = config.projetosDisponiveis || [];
        const refreshSelect = (select) => {
            const currentValue = select.value;
            select.innerHTML = "";
            const placeholder = document.createElement("option");
            placeholder.value = "";
            placeholder.textContent = "Selecione a nuvem";
            select.appendChild(placeholder);
            projetos.forEach((proj) => {
                const option = document.createElement("option");
                option.value = proj.id;
                option.textContent = proj.label || proj.id;
                select.appendChild(option);
            });
            if (currentValue && projetos.some((p) => p.id === currentValue)) {
                select.value = currentValue;
            }
        };
        this.selectionPanelEl.querySelectorAll(".cloud-slot-project-select").forEach(refreshSelect);
    }

    /**
     * Retorna o modo atual selecionado: 'tint' ou 'blink'.
     * @private
     */
    _getCompareMode() {
        const radio = this.selectionPanelEl?.querySelector('input[name="compare_mode"]:checked');
        return radio?.value === "blink" ? "blink" : "tint";
    }

    /**
     * Atualiza a visibilidade do bloco do slider de velocidade (só visível no modo piscar).
     * @private
     */
    _updateModeVisibility() {
        const mode = this._getCompareMode();
        const speedWrap = this.selectionPanelEl?.querySelector(".compare-clouds-blink-speed-wrap");
        if (speedWrap) speedWrap.style.display = mode === "blink" ? "" : "none";
    }

    /**
     * Atualiza a visibilidade do botão Parar (visível quando a comparação está ativa).
     * @private
     */
    _updateActionsVisibility() {
        const stopBtn = this.selectionPanelEl?.querySelector("#btn_compare_clouds_stop");
        if (!stopBtn) return;
        stopBtn.style.display = this.active && this.comparePointclouds.size > 0 ? "" : "none";
    }

    /**
     * Alterna entre os modos "as duas juntas" e "piscar" quando a comparação já está ativa.
     * Usa as nuvens já carregadas e os valores atuais do painel.
     * @private
     */
    _switchCompareMode() {
        if (this.comparePointclouds.size !== 2 || this.compareCloudIds.length !== 2) return;
        const slot0 = this.selectionPanelEl?.querySelector('.cloud-slot[data-slot="0"]');
        const slot1 = this.selectionPanelEl?.querySelector('.cloud-slot[data-slot="1"]');
        if (!slot0 || !slot1) return;
        const pointcloud0 = this.comparePointclouds.get(this.compareCloudIds[0]);
        const pointcloud1 = this.comparePointclouds.get(this.compareCloudIds[1]);
        if (!pointcloud0 || !pointcloud1) return;

        const color0 = this._parseColorHex(slot0?.querySelector(".cloud-slot-color-picker")?.value);
        const color1 = this._parseColorHex(slot1?.querySelector(".cloud-slot-color-picker")?.value);
        const tint0 = (parseFloat(slot0?.querySelector(".cloud-slot-tint-slider")?.value) || 30) / 100;
        const tint1 = (parseFloat(slot1?.querySelector(".cloud-slot-tint-slider")?.value) || 30) / 100;
        const mode = this._getCompareMode();
        const v = this.viewer || window.viewer;

        if (mode === "tint") {
            this._blinkController.stop();
            this._applyCompareColor(pointcloud0, color0, tint0);
            this._applyCompareColor(pointcloud1, color1, tint1);
            pointcloud0.visible = true;
            pointcloud1.visible = true;
            if (!this.useTintShaderOnly) this._startTintRefreshLoop();
        } else {
            this._tintController.stop();
            this._applyCompareColor(pointcloud0, color0, tint0);
            this._applyCompareColor(pointcloud1, color1, tint1);
            this._blinkHiddenPointclouds.clear();
            const scene = v?.scene;
            if (scene && Array.isArray(scene.pointclouds)) {
                for (const pc of scene.pointclouds) {
                    if (pc !== pointcloud0 && pc !== pointcloud1) {
                        pc.visible = false;
                        this._blinkHiddenPointclouds.add(pc);
                    }
                }
            }
            pointcloud0.visible = true;
            pointcloud1.visible = false;
            this._startBlink();
        }
        if (v) v.render();
    }

    /**
     * Remove o tom das nuvens (usado apenas se for necessário em outro fluxo).
     * @private
     */
    _clearTintForBlinkMode() {
        this._tintController.stop();
        const clearTint = (pointcloud) => {
            if (!pointcloud) return;
            if (pointcloud?.material?.uniforms) {
                if (pointcloud.material.uniforms.uTintAmount) pointcloud.material.uniforms.uTintAmount.value = 0;
                if (pointcloud.material.uniforms.uTintColor?.value) {
                    const v = pointcloud.material.uniforms.uTintColor.value;
                    v.x = 1; v.y = 1; v.z = 1;
                }
            }
            if (pointcloud._compareTint !== undefined) pointcloud._compareTint = null;
        };
        for (const [pointcloud] of this.originalMaterialState.entries()) clearTint(pointcloud);
        for (const pointcloud of this.comparePointclouds.values()) clearTint(pointcloud);
        const v = this.viewer || window.viewer;
        if (v) v.render();
    }

    /**
     * Configura listeners do painel.
     * @private
     */
    _setupPanelFromExisting() {
        if (!this.selectionPanelEl) return;
        this._refreshProjectOptions();
        this._updateModeVisibility();

        const modeRadios = this.selectionPanelEl.querySelectorAll('input[name="compare_mode"]');
        modeRadios.forEach((r) => {
            if (!r.dataset.initialized) {
                r.dataset.initialized = "1";
                r.addEventListener("change", () => {
                    this._updateModeVisibility();
                    this._blinkController.stop();
                    if (this.comparePointclouds.size === 2) {
                        this._switchCompareMode();
                    }
                });
            }
        });

        const closeBtn = this.selectionPanelEl.querySelector(".compare-clouds-close-btn");
        if (closeBtn && !closeBtn.dataset.initialized) {
            closeBtn.dataset.initialized = "1";
            closeBtn.addEventListener("click", () => {
                this.setActive(false);
                if (typeof window.setLeftPanelMode === "function") {
                    window.setLeftPanelMode(null);
                }
            });
        }
        
        const compareBtn = this.selectionPanelEl.querySelector("#btn_compare_clouds_panel");
        if (compareBtn && !compareBtn.dataset.initialized) {
            compareBtn.dataset.initialized = "1";
            compareBtn.addEventListener("click", () => this._applyCompare());
        }

        const stopBtn = this.selectionPanelEl.querySelector("#btn_compare_clouds_stop");
        if (stopBtn && !stopBtn.dataset.initialized) {
            stopBtn.dataset.initialized = "1";
            stopBtn.addEventListener("click", () => this.setActive(false));
        }
        
        this._setupSlotSliderListeners();
        this._setupViewControlListeners();
    }

    /**
     * Configura listeners nos sliders para atualizar as nuvens em tempo real.
     * Usa event delegation no painel.
     * @private
     */
    _setupSlotSliderListeners() {
        if (!this.selectionPanelEl) return;
        if (this.selectionPanelEl.dataset.slotListenersSetup === "1") return;
        this.selectionPanelEl.dataset.slotListenersSetup = "1";

        const handleSlotChange = (target) => {
            const slot = target?.closest(".cloud-slot");
            if (!slot) return;
            const projectId = slot.querySelector(".cloud-slot-project-select")?.value;
            const pc = projectId ? this.comparePointclouds.get(projectId) : null;
            if (!pc) return;
            const colorPicker = slot.querySelector(".cloud-slot-color-picker");
            const tintSlider = slot.querySelector(".cloud-slot-tint-slider");
            const tintValue = slot.querySelector(".cloud-slot-tint-value");
            const color = this._parseColorHex(colorPicker?.value);
            const tintAmount = (parseFloat(tintSlider?.value) || 0) / 100;
            this._applyCompareColor(pc, color, tintAmount);
            if (!this.useTintShaderOnly) this._applyTintToBuffer(pc, color, tintAmount);
            if (tintValue) tintValue.textContent = Math.round(tintAmount * 100) + "%";
            const v = this.viewer || window.viewer;
            if (v) v.render();
        };

        this.selectionPanelEl.addEventListener("input", (e) => {
            const target = e.target;
            if (!target?.closest(".cloud-slot")) return;
            if (target?.classList?.contains("cloud-slot-color-picker") ||
                target?.classList?.contains("cloud-slot-tint-slider")) {
                handleSlotChange(target);
            }
        });
    }

    /**
     * Configura listeners dos controles de visualização (blink).
     * Usa event delegation no painel para garantir funcionamento.
     * @private
     */
    _setupViewControlListeners() {
        if (!this.selectionPanelEl) return;
        if (this.selectionPanelEl.dataset.viewListenersSetup === "1") return;
        this.selectionPanelEl.dataset.viewListenersSetup = "1";

        const panel = this.selectionPanelEl;

        panel.addEventListener("input", (e) => {
            const target = e.target;
            if (target?.classList?.contains("compare-clouds-blink-slider")) {
                const blinkValueEl = panel.querySelector(".compare-clouds-blink-value");
                if (blinkValueEl) blinkValueEl.textContent = target.value;
                this._startBlink();
            }
        });
    }

    /**
     * Obtém a cor em hex a partir do input color.
     * @private
     */
    _parseColorHex(colorStr) {
        if (!colorStr || colorStr.charAt(0) !== "#") return 0xffffff;
        return parseInt(colorStr.slice(1), 16);
    }

    /**
     * Re-aplica o tint às nuvens em comparação lendo parâmetros do painel.
     * @private
     */
    _reapplyCompareTintFromPanel() {
        // Não usa isActive() - chamado pelo loop de refresh; o loop é cancelado em exitCompareMode()
        if (this.comparePointclouds.size === 0 || !this.selectionPanelEl) return;
        const slots = this.selectionPanelEl.querySelectorAll(".cloud-slot");
        for (const slot of slots) {
            const projectId = slot.querySelector(".cloud-slot-project-select")?.value;
            if (!projectId) continue;
            const pc = this.comparePointclouds.get(projectId);
            if (!pc) continue;
            const color = this._parseColorHex(slot.querySelector(".cloud-slot-color-picker")?.value);
            const tintAmount = (parseFloat(slot.querySelector(".cloud-slot-tint-slider")?.value) || 0) / 100;
            // Usa _applyCompareColor (como o slider) para garantir material + buffer + invalidação completa
            this._applyCompareColor(pc, color, tintAmount);
        }
    }


    /**
     * Retorna o par de nuvens em comparação para o modo blink, ou null se não houver duas.
     * @private
     * @returns {{ pcA: object, pcB: object } | null}
     */
    _getBlinkPair() {
        if (this.comparePointclouds.size !== 2 || this.compareCloudIds.length !== 2) return null;
        const pcA = this.comparePointclouds.get(this.compareCloudIds[0]);
        const pcB = this.comparePointclouds.get(this.compareCloudIds[1]);
        if (!pcA || !pcB) return null;
        return { pcA, pcB };
    }

    /**
     * Inicia o modo blink (alternância de visibilidade entre as duas nuvens).
     * @private
     */
    _startBlink() {
        if (this.comparePointclouds.size !== 2 || this.compareCloudIds.length !== 2) return;
        const pair = this._getBlinkPair();
        if (!pair) return;

        const intervalMs = parseFloat(this.selectionPanelEl.querySelector(".compare-clouds-blink-slider")?.value) || 500;
        const viewer = this.viewer || window.viewer;

        this._blinkController.start({
            getPair: () => this._getBlinkPair(),
            intervalMs,
            viewer,
            isActive: () => this.isActive()
        });
    }

    /**
     * Inicia o controle de tint: loop de re-aplicação e reaplicações atrasadas.
     * CompareCloudsTool apenas orquestra; a lógica fica no TintController.
     * @private
     */
    _startTintRefreshLoop() {
        const viewer = this.viewer || window.viewer;
        this._tintController.start({
            applyTint: () => this._reapplyCompareTintFromPanel(),
            getShouldRun: () => this.comparePointclouds.size > 0 && this._getCompareMode() === "tint",
            getTotalVisibleNodes: () => {
                let n = 0;
                for (const pc of this.comparePointclouds.values()) {
                    n += (pc.visibleNodes || []).length;
                }
                return n;
            },
            refreshIntervalMs: this.tintRefreshInterval,
            throttleMs: this.tintReapplyThrottleMs || 2000,
            delayedDelays: [0, 30, 80, 150, 300, 350, 700, 1200],
            viewer
        });
    }

    /**
     * Carrega as duas nuvens, aplica cores e exibe ambas visíveis.
     * @private
     */
    async _applyCompare() {
        this._refreshProjectOptions();
        // Garante modo ativo quando o usuário clica Comparar (painel pode ter sido aberto sem setActive(true)).
        this.active = true;
        const mode = this._getCompareMode();
        const slot0 = this.selectionPanelEl?.querySelector('.cloud-slot[data-slot="0"]');
        const slot1 = this.selectionPanelEl?.querySelector('.cloud-slot[data-slot="1"]');
        if (!slot0 || !slot1) return;
        const projectId0 = slot0.querySelector(".cloud-slot-project-select")?.value;
        const projectId1 = slot1.querySelector(".cloud-slot-project-select")?.value;

        if (!projectId0 || !projectId1) {
            alert("Selecione as duas nuvens.");
            return;
        }
        if (projectId0 === projectId1) {
            alert("Selecione duas nuvens diferentes.");
            return;
        }

        const loadCloudForCompare = this.cloudLoader?.loadCloudForCompare || window.loadCloudForCompare;
        if (typeof loadCloudForCompare !== "function") {
            alert("Erro: timeline não carregou. Recarregue a página.");
            return;
        }

        try {
            const [pointcloud0, pointcloud1] = await Promise.all([
                loadCloudForCompare(projectId0),
                loadCloudForCompare(projectId1)
            ]);

            if (!pointcloud0 || !pointcloud1) {
                alert("Erro ao carregar uma ou mais nuvens.");
                return;
            }

            this.compareCloudIds = [projectId0, projectId1];
            this.comparePointclouds.set(projectId0, pointcloud0);
            this.comparePointclouds.set(projectId1, pointcloud1);

            if (typeof window.pinCompareClouds === "function") {
                window.pinCompareClouds(this.compareCloudIds);
            }

            const slot0 = this.selectionPanelEl?.querySelector('.cloud-slot[data-slot="0"]');
            const slot1 = this.selectionPanelEl?.querySelector('.cloud-slot[data-slot="1"]');
            const color0 = this._parseColorHex(slot0?.querySelector(".cloud-slot-color-picker")?.value);
            const color1 = this._parseColorHex(slot1?.querySelector(".cloud-slot-color-picker")?.value);
            const tint0 = (parseFloat(slot0?.querySelector(".cloud-slot-tint-slider")?.value) || 30) / 100;
            const tint1 = (parseFloat(slot1?.querySelector(".cloud-slot-tint-slider")?.value) || 30) / 100;

            if (mode === "tint") {
                this._applyCompareColor(pointcloud0, color0, tint0);
                this._applyCompareColor(pointcloud1, color1, tint1);
                pointcloud0.visible = true;
                pointcloud1.visible = true;

                if (!this.useTintShaderOnly) {
                    this._startTintRefreshLoop();
                }
            } else {
                this._tintController.stop();
                this._applyCompareColor(pointcloud0, color0, tint0);
                this._applyCompareColor(pointcloud1, color1, tint1);
                this._blinkHiddenPointclouds.clear();
                const scene = (this.viewer || window.viewer)?.scene;
                if (scene && Array.isArray(scene.pointclouds)) {
                    for (const pc of scene.pointclouds) {
                        if (pc !== pointcloud0 && pc !== pointcloud1) {
                            pc.visible = false;
                            this._blinkHiddenPointclouds.add(pc);
                        }
                    }
                }
                pointcloud0.visible = true;
                pointcloud1.visible = false;
                this._startBlink();
            }

            const v = this.viewer || window.viewer;
            if (v) v.render();
            this._updateActionsVisibility();
        } catch (err) {
            alert("Erro ao carregar nuvens.");
        }
    }
}
