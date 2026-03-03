/**
 * Template HTML para os controles de offset.
 */

export const offsetControlsTemplate = `
    <span>Offset</span>
    <div class="offset-controls">
        <input type="number" id="offset_x" step="any" placeholder="X" title="X">
        <input type="number" id="offset_y" step="any" placeholder="Y" title="Y">
        <input type="number" id="offset_z" step="any" placeholder="Z" title="Z">
        <button type="button" id="btn_salvar_offset" title="Copiar offset em JSON">Copiar Offset</button>
    </div>
`;
