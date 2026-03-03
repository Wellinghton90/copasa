/**
 * Template HTML para o modal de seleção de foto.
 */

export const comparePhotosModalTemplate = `
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
