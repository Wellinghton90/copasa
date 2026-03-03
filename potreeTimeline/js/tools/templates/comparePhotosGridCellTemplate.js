/**
 * Template HTML para uma célula do grid de comparação de fotos.
 * @param {string} photoName - Nome da foto
 * @param {string} projectId - ID do projeto
 * @param {string} imageUrl - URL da imagem
 * @returns {string} HTML da célula
 */

export function comparePhotosGridCellTemplate(photoName, projectId, imageUrl) {
    return `
        <div class="compare-photos-cell-header">
            <span class="compare-photos-cell-title">${photoName}</span>
            <span class="compare-photos-cell-project">${projectId}</span>
            <button type="button" class="compare-photos-cell-remove">×</button>
        </div>
        <div class="compare-photos-cell-image">
            <img src="${imageUrl}" alt="${photoName}">
        </div>
    `;
}

/**
 * Template HTML para um item do modal de seleção de foto.
 * @param {string} photoName - Nome da foto
 * @param {string} imageUrl - URL da imagem
 * @returns {string} HTML do item
 */

export function comparePhotosModalItemTemplate(photoName, imageUrl) {
    return `
        <img src="${imageUrl}" alt="${photoName}" loading="lazy">
        <button type="button" class="compare-photos-modal-select-btn">Selecionar</button>
    `;
}
