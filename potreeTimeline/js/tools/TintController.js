/**
 * Controla a re-aplicação periódica do tint nas nuvens em comparação.
 * Mantém o tom aplicado quando novos nós são carregados (LOD) e agenda
 * re-aplicações atrasadas para garantir que a segunda nuvem receba o tint.
 *
 * @class TintController
 */
export class TintController {
    constructor() {
        /** @type {number | null} ID do setInterval do loop de refresh */
        this._intervalId = null;
        /** @type {number[]} IDs dos timeouts de re-aplicação atrasada */
        this._timeoutIds = [];
        /** Total de nós visíveis na última reaplicação (reaplica só quando mudar) */
        this._lastNodeCount = 0;
        /** Timestamp da última reaplicação (throttle) */
        this._lastReapplyTime = 0;
    }

    /**
     * Inicia o controle de tint: loop de refresh e re-aplicações atrasadas.
     * @param {Object} options
     * @param {() => void} options.applyTint - Callback que reaplica o tint (ex.: lendo painel e aplicando às nuvens)
     * @param {() => boolean} options.getShouldRun - Retorna true se o modo tint deve continuar (ex.: pointclouds.size > 0 && mode === 'tint')
     * @param {() => number} options.getTotalVisibleNodes - Retorna o total de nós visíveis nas nuvens em comparação
     * @param {number} [options.refreshIntervalMs=1000] - Intervalo do setInterval (ms)
     * @param {number} [options.throttleMs=2000] - Mínimo de ms entre reaplicações no loop
     * @param {number[]} [options.delayedDelays] - Lista de ms para agendar reaplicações (ex.: [0, 30, 80, 150, 300, 350, 700, 1200])
     * @param {object} [options.viewer] - Viewer para chamar render() após aplicar
     */
    start({
        applyTint,
        getShouldRun,
        getTotalVisibleNodes,
        refreshIntervalMs = 1000,
        throttleMs = 2000,
        delayedDelays = [],
        viewer
    }) {
        this.stop();

        this._applyTint = applyTint;
        this._getShouldRun = getShouldRun;
        this._getTotalVisibleNodes = getTotalVisibleNodes;
        this._refreshIntervalMs = refreshIntervalMs;
        this._throttleMs = throttleMs;
        this._viewer = viewer;
        this._lastNodeCount = 0;
        this._lastReapplyTime = 0;

        const self = this;

        // Loop periódico: reaplica quando o total de nós visíveis mudar (novos nós carregados)
        this._intervalId = setInterval(() => {
            if (!self._getShouldRun()) {
                self.stop();
                return;
            }
            const totalNodes = self._getTotalVisibleNodes();
            const now = Date.now();
            const throttleOk = (now - self._lastReapplyTime) >= self._throttleMs;
            if (totalNodes !== self._lastNodeCount && throttleOk) {
                self._lastNodeCount = totalNodes;
                self._lastReapplyTime = now;
                self._applyTint();
                if (self._viewer && typeof self._viewer.render === "function") {
                    self._viewer.render();
                }
            }
        }, this._refreshIntervalMs);

        // Re-aplicações atrasadas (para segunda nuvem quando visibleNodes for preenchido)
        for (const delayMs of delayedDelays) {
            const id = setTimeout(() => {
                if (!self._getShouldRun()) return;
                self._applyTint();
                if (self._viewer && typeof self._viewer.render === "function") {
                    self._viewer.render();
                }
            }, delayMs);
            this._timeoutIds.push(id);
        }
    }

    /**
     * Para o loop e cancela todos os timeouts.
     */
    stop() {
        if (this._intervalId != null) {
            clearInterval(this._intervalId);
            this._intervalId = null;
        }
        for (const id of this._timeoutIds) {
            clearTimeout(id);
        }
        this._timeoutIds = [];
    }

    /**
     * Indica se o controle está ativo (interval ou timeouts pendentes).
     * @returns {boolean}
     */
    isRunning() {
        return this._intervalId != null || this._timeoutIds.length > 0;
    }
}
