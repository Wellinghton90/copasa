/**
 * Painel Camadas (Layers): checkboxes para mostrar/ocultar nuvem e frustums.
 * Conteúdo injetado em #left_panel_camadas (painel lateral esquerdo, modo "camadas").
 */

let panelCamadasEl = null;

function getFrustumsGroup() {
    return window.viewer && window.viewer.scene && window.viewer.scene.cameraFrustumsGroup
        ? window.viewer.scene.cameraFrustumsGroup
        : null;
}

function setPointCloudVisible(visible) {
    const pointcloud = window.currentPointcloud;
    if (pointcloud) pointcloud.visible = !!visible;
    if (window.viewer && typeof window.viewer.render === "function") window.viewer.render();
}

function setFrustumsVisible(visible) {
    const group = getFrustumsGroup();
    if (group) group.visible = !!visible;
    if (window.viewer && typeof window.viewer.render === "function") window.viewer.render();
}

function refreshCamadasCheckboxes() {
    if (!panelCamadasEl) return;
    const cbNuvem = panelCamadasEl.querySelector("#camadas_nuvem");
    const cbFrustums = panelCamadasEl.querySelector("#camadas_frustums");
    if (cbNuvem && window.currentPointcloud) cbNuvem.checked = window.currentPointcloud.visible;
    if (cbFrustums) {
        const group = getFrustumsGroup();
        cbFrustums.checked = group ? group.visible : true;
    }
}

function createCamadasPanel() {
    const container = document.getElementById("left_panel_camadas");
    if (!container || panelCamadasEl) return;

    panelCamadasEl = document.createElement("div");
    panelCamadasEl.id = "camadasSettingsPanel";
    panelCamadasEl.className = "camadas-settings-panel camadas-settings-panel-embedded";
    panelCamadasEl.innerHTML = `
        <div class="camadas-panel-header">
            <h3>Camadas</h3>
            <button type="button" class="camadas-close-btn" title="Fechar">×</button>
        </div>
        <div class="camadas-panel-content">
            <div class="setting-group">
                <h4>Visibilidade</h4>
                <label class="checkbox-label">
                    <input type="checkbox" id="camadas_nuvem" checked>
                    <span class="checkmark"></span>
                    Nuvem de pontos
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" id="camadas_frustums" checked>
                    <span class="checkmark"></span>
                    Fotos aéreas
                </label>
            </div>
        </div>
    `;

    const closeBtn = panelCamadasEl.querySelector(".camadas-close-btn");
    closeBtn.addEventListener("click", () => {
        if (typeof window.setLeftPanelMode === "function") window.setLeftPanelMode(null);
    });

    const cbNuvem = panelCamadasEl.querySelector("#camadas_nuvem");
    const cbFrustums = panelCamadasEl.querySelector("#camadas_frustums");
    cbNuvem.addEventListener("change", () => setPointCloudVisible(cbNuvem.checked));
    cbFrustums.addEventListener("change", () => setFrustumsVisible(cbFrustums.checked));

    container.appendChild(panelCamadasEl);
}

function initCamadas() {
    if (!window.viewer || !document.querySelector(".potree_container")) return;
    if (panelCamadasEl) return;

    createCamadasPanel();
    refreshCamadasCheckboxes();
}

function tryInitCamadas() {
    if (window.viewer && document.querySelector("#left_panel_camadas")) {
        initCamadas();
    } else {
        setTimeout(tryInitCamadas, 100);
    }
}

if (typeof window !== "undefined") {
    window.refreshCamadasCheckboxes = refreshCamadasCheckboxes;
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", tryInitCamadas);
} else {
    tryInitCamadas();
}
