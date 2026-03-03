/**
 * Controla o loop de piscar (blink) entre duas nuvens de pontos.
 * Alterna a visibilidade das nuvens em um intervalo configurável.
 *
 * @class BlinkController
 */
export class BlinkController {
    constructor() {
        /** @type {number | null} ID do requestAnimationFrame (para cancelar ao parar) */
        this._rafId = null;
        /** @type {boolean} true = mostrar primeira nuvem, false = mostrar segunda */
        this._showA = true;
        /** @type {number} Timestamp da última alternância (performance.now()) */
        this._lastToggleTime = 0;
    }

    /**
     * Inicia o modo blink.
     * @param {Object} options
     * @param {() => { pcA: object, pcB: object } | null} options.getPair - Função que retorna o par atual de pointclouds (A e B)
     * @param {number} options.intervalMs - Intervalo em ms entre cada alternância
     * @param {object} [options.viewer] - Viewer Potree (para chamar render())
     * @param {() => boolean} options.isActive - Função que indica se o modo ainda está ativo (para encerrar o loop)
     */
    start({ getPair, intervalMs, viewer, isActive }) {
        this.stop();

        const pair = getPair();
        if (!pair || !pair.pcA || !pair.pcB) return;

        pair.pcA.visible = true;
        pair.pcB.visible = false;
        this._showA = true;
        this._lastToggleTime = 0;
        this._getPair = getPair;
        this._intervalMs = intervalMs;
        this._viewer = viewer;
        this._isActive = isActive;

        const self = this;
        function loop() {
            if (!self._isActive()) {
                self._rafId = null;
                return;
            }
            const p = self._getPair();
            if (!p || !p.pcA || !p.pcB) {
                self._rafId = null;
                return;
            }
            const now = performance.now();
            if (self._lastToggleTime === 0) self._lastToggleTime = now;
            if ((now - self._lastToggleTime) >= self._intervalMs) {
                self._lastToggleTime = now;
                self._showA = !self._showA;
            }
            p.pcA.visible = self._showA;
            p.pcB.visible = !self._showA;
            if (self._viewer && typeof self._viewer.render === "function") {
                self._viewer.render();
            }
            self._rafId = requestAnimationFrame(loop);
        }
        this._rafId = requestAnimationFrame(loop);
    }

    /**
     * Para o loop de blink.
     */
    stop() {
        if (this._rafId != null) {
            cancelAnimationFrame(this._rafId);
            this._rafId = null;
        }
    }

    /**
     * Indica se o blink está rodando.
     * @returns {boolean}
     */
    isRunning() {
        return this._rafId != null;
    }
}
