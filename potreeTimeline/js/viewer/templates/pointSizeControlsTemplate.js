/**
 * Template HTML para os controles de tamanho de pontos.
 */

export const pointSizeControlsTemplate = `
    <div class="point-size-control">
        <label for="pointSizeSlider">Tamanho dos pontos:</label>
        <div class="slider-container">
            <input type="range" id="pointSizeSlider" min="0.1" max="5" step="0.1" value="1" class="slider">
            <input type="number" id="pointSizeInput" min="0.1" max="5" step="0.1" value="1" class="size-input">
        </div>
    </div>
`;
