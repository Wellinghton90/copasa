/**
 * Controlador do painel lateral direito (Diário de fiscalização).
 * Um único modo: diario. O painel abre por cima da nuvem (overlay), sem redimensionar a área de render.
 */
import { getCurrentUserDisplay } from './currentUser.js';
import { PLATFORM_OPTIONS, DEFAULT_PLATFORM_VALUE } from './platformConfig.js';

export class RightPanelController {
    constructor() {
        /** Modo atual do painel (diario ou null) */
        this.currentMode = null;
    }

    /**
     * Garante que a área de render não tem right definido pelo painel direito,
     * para o painel abrir em overlay (como o painel esquerdo) sem dar refresh na nuvem.
     */
    ensureRenderAreaNotShrunk() {
        const renderArea = document.getElementById('potree_render_area');
        if (renderArea) {
            renderArea.style.right = '';
        }
    }

    /**
     * Define o modo ativo do painel direito.
     * @param {string|null} mode - 'diario' para abrir, null para fechar
     * @returns {string|null} Novo modo ativo
     */
    setMode(mode) {
        const sameMode = this.currentMode === mode;
        const newMode = sameMode ? null : mode;
        this.currentMode = newMode;

        const rightPanel = document.getElementById('right_panel');
        const contents = document.querySelectorAll('.right-panel-content');
        const diaryBtn = document.getElementById('btn_diario');

        if (!rightPanel || !contents.length) {
            this.ensureRenderAreaNotShrunk();
            return newMode;
        }

        if (newMode) {
            rightPanel.classList.add('open');
            rightPanel.setAttribute('data-mode', newMode);
            contents.forEach((el) => {
                el.classList.toggle('active', el.getAttribute('data-mode') === newMode);
            });
            if (diaryBtn) diaryBtn.classList.add('active');
            if (typeof window.onInspectionDiaryPanelOpen === 'function') {
                window.onInspectionDiaryPanelOpen();
            }
        } else {
            rightPanel.classList.remove('open');
            rightPanel.removeAttribute('data-mode');
            contents.forEach((el) => el.classList.remove('active'));
            if (diaryBtn) diaryBtn.classList.remove('active');
        }

        this.ensureRenderAreaNotShrunk();
        return newMode;
    }

    /**
     * Inicializa o painel direito, registra o botão "Diário" e preenche o badge do usuário na barra superior.
     */
    init() {
        const diaryBtn = document.getElementById('btn_diario');
        if (diaryBtn) {
            diaryBtn.addEventListener('click', () => this.setMode('diario'));
        }

        const rightPanel = document.getElementById('right_panel');
        if (rightPanel) {
            rightPanel.addEventListener('click', (e) => {
                if (e.target.closest('.diary-panel-close-btn')) {
                    this.setMode(null);
                }
            });
        }

        this._updateUserBadge();
        this._initPlatformDropdown();
        this.ensureRenderAreaNotShrunk();

        if (typeof window.setRightPanelMode === 'undefined') {
            window.setRightPanelMode = (mode) => this.setMode(mode);
        }
    }

    /**
     * Preenche o dropdown de plataforma e expõe valor atual em window.currentPlatformValue / currentPlatformLabel.
     * @private
     */
    _initPlatformDropdown() {
        const select = document.getElementById('top_bar_platform');
        if (!select) return;
        PLATFORM_OPTIONS.forEach((opt) => {
            const option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.label;
            select.appendChild(option);
        });
        select.value = DEFAULT_PLATFORM_VALUE;
        window.currentPlatformValue = DEFAULT_PLATFORM_VALUE;
        window.currentPlatformLabel = PLATFORM_OPTIONS.find((o) => o.value === DEFAULT_PLATFORM_VALUE)?.label ?? '';
        select.addEventListener('change', () => {
            window.currentPlatformValue = select.value;
            window.currentPlatformLabel = PLATFORM_OPTIONS.find((o) => o.value === select.value)?.label ?? '';
        });
    }

    /**
     * Preenche o badge do usuário na barra superior (nome completo ou iniciais em círculo).
     * @private
     */
    _updateUserBadge() {
        const badge = document.getElementById('top_bar_user_badge');
        if (!badge) return;
        const { display, initials } = getCurrentUserDisplay();
        badge.textContent = initials;
        badge.classList.remove('has-name');
        badge.title = display || 'Usuário atual';
    }
}
