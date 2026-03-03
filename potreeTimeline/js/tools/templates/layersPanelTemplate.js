/**
 * Template HTML para o painel de camadas.
 */

export const layersPanelTemplate = `
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
