/**
 * Template HTML para o painel de fotos no ponto.
 */

export const photosAtPointPanelTemplate = `
    <div class="fotos-panel-header">
        <span class="fotos-panel-title">Fotos (0)</span>
        <button type="button" class="fotos-panel-close-btn left-panel-close-btn" title="Fechar">×</button>
    </div>
    <div class="fotos-panel-list"></div>
    <div class="fotos-panel-load-more-wrap" style="display: none;">
        <button type="button" class="fotos-panel-load-more" id="btn_fotos_carregar_mais">Carregar mais fotos</button>
    </div>
`;
