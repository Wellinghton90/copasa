/**
 * Template HTML para o painel de comparação de nuvens.
 */

export const compareCloudsPanelTemplate = `
    <div class="compare-clouds-selection-header">
        <span class="compare-clouds-selection-title">Comparar Nuvens</span>
        <button type="button" class="compare-clouds-close-btn left-panel-close-btn" title="Fechar">×</button>
    </div>
    <div class="compare-clouds-content">
        <div class="compare-clouds-slots-container">
            <div class="cloud-slot" data-slot="0">
                <div class="cloud-slot-header">
                    <select class="cloud-slot-project-select"><option value="">Selecione a nuvem</option></select>
                </div>
                <div class="cloud-slot-color">
                    <label>Cor do tom:</label>
                    <input type="color" class="cloud-slot-color-picker" value="#ff3333" title="Tom da nuvem">
                </div>
                <div class="cloud-slot-tint">
                    <label>Intensidade: <span class="cloud-slot-tint-value">30%</span></label>
                    <input type="range" class="cloud-slot-tint-slider" min="0" max="100" value="30" title="0 = cor original, 100 = totalmente a cor">
                </div>
            </div>
            <div class="cloud-slot" data-slot="1">
                <div class="cloud-slot-header">
                    <select class="cloud-slot-project-select"><option value="">Selecione a nuvem</option></select>
                </div>
                <div class="cloud-slot-color">
                    <label>Cor do tom:</label>
                    <input type="color" class="cloud-slot-color-picker" value="#3366ff" title="Tom da nuvem">
                </div>
                <div class="cloud-slot-tint">
                    <label>Intensidade: <span class="cloud-slot-tint-value">30%</span></label>
                    <input type="range" class="cloud-slot-tint-slider" min="0" max="100" value="30" title="0 = cor original, 100 = totalmente a cor">
                </div>
            </div>
        </div>
        <div class="compare-clouds-view-selector">
            <span class="compare-clouds-view-selector-label">Modo:</span>
            <div class="compare-clouds-view-selector-options">
                <label><input type="radio" name="compare_mode" value="tint" checked> As duas juntas</label>
                <label><input type="radio" name="compare_mode" value="blink"> Piscar</label>
            </div>
        </div>
        <div class="compare-clouds-blink-speed-wrap" style="display:none">
            <div class="compare-clouds-blink-speed">
                <label>Intervalo: <span class="compare-clouds-blink-value">500</span> ms</label>
                <input type="range" class="compare-clouds-blink-slider" min="300" max="3000" value="500" step="100">
            </div>
        </div>
    </div>
    <div class="compare-clouds-selection-actions">
        <button type="button" class="compare-clouds-compare-btn" id="btn_compare_clouds_panel">Comparar</button>
        <button type="button" class="compare-clouds-stop-btn" id="btn_compare_clouds_stop" style="display:none">Parar</button>
    </div>
`;
