/**
 * Template HTML para um slot de foto na comparação.
 */

export const comparePhotosSlotTemplate = `
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
