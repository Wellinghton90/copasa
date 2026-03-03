/**
 * Template HTML para o painel de seleção de comparação de fotos.
 */

export const comparePhotosSelectionTemplate = `
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
