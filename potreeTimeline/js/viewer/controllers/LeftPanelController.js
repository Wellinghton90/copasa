/**
 * Controlador do painel lateral esquerdo.
 * Gerencia abertura/fechamento de painéis e mensagens de status.
 * 
 * @class LeftPanelController
 */
export class LeftPanelController {
    /**
     * Cria uma instância de LeftPanelController.
     */
    constructor() {
        /** Largura da barra lateral (deve coincidir com .sidebar-left { width } no CSS). */
        this.SIDEBAR_LEFT_WIDTH = 88;
        
        /** Mensagens de status para cada modo do painel. */
        this.STATUS_MESSAGES = {
            config: 'Ferramentas (medição, câmera, clipping)',
            camadas: 'Visibilidade de camadas',
            compare: 'Selecione as nuvens e as fotos para comparar',
            compare_clouds: 'Selecione 2 nuvens para comparar',
            fotos: 'Clique em um ponto para visualizar as fotos',
            photo_area: 'Selecione uma foto área (frustum)',
            select_point: 'Clique nos pontos desejados. Esc para cancelar',
            default: 'Nuvem de pontos'
        };
        
        /** Modo atual do painel lateral. */
        this.currentMode = null;
    }

    /**
     * Define a mensagem de status na barra inferior.
     * @param {string} msg - Mensagem a exibir (ou null para mensagem padrão)
     */
    setStatusMessage(msg) {
        const el = document.getElementById('status_bar_message');
        if (el) {
            el.textContent = msg || this.STATUS_MESSAGES.default;
        }
    }

    /**
     * Atualiza a posição left da área de render para compensar a barra lateral.
     * A área de render fica com left fixo; o painel sobrepõe por cima (z-index).
     */
    updateRenderAreaLeft() {
        const renderArea = document.getElementById('potree_render_area');
        if (renderArea) {
            renderArea.style.left = this.SIDEBAR_LEFT_WIDTH + 'px';
        }
    }

    /**
     * Define o modo ativo do painel lateral esquerdo.
     * @param {string|null} mode - Modo a ativar ('config', 'camadas', 'compare', etc.) ou null para fechar
     * @returns {string|null} Novo modo ativo
     */
    setMode(mode) {
        const sameMode = this.currentMode === mode;
        // Garantir toggle: ao clicar em "Comparar nuvens" com a ferramenta já ativa, fechar o painel
        const compareCloudsAlreadyActive = (mode === 'compare_clouds' && typeof window.isCompareCloudsActive === 'function' && window.isCompareCloudsActive());
        const newMode = (sameMode || compareCloudsAlreadyActive) ? null : mode;
        this.currentMode = newMode;
        
        const leftPanel = document.getElementById('left_panel');
        const contents = document.querySelectorAll('.left-panel-content');
        const buttons = document.querySelectorAll('.sidebar-btn');
        
        if (!leftPanel || !contents.length) {
            return newMode;
        }
        
        if (newMode) {
            // Abrir painel
            leftPanel.classList.add('open');
            leftPanel.setAttribute('data-mode', newMode);
            
            contents.forEach((el) => {
                el.classList.toggle('active', el.getAttribute('data-mode') === newMode);
            });
            
            buttons.forEach((btn) => {
                btn.classList.toggle('active', btn.getAttribute('data-mode') === newMode);
            });
            
            this.setStatusMessage(this.STATUS_MESSAGES[newMode] || this.STATUS_MESSAGES.default);
            
            // Ações específicas por modo
            const sidebar = document.getElementById('potree_sidebar_container');
            if (sidebar && newMode === 'config') {
                sidebar.style.display = 'block';
            }
            
            if (newMode === 'camadas' && typeof window.refreshCamadasCheckboxes === 'function') {
                window.refreshCamadasCheckboxes();
            }
            
            if (newMode === 'compare' && typeof window.setComparePhotosActive === 'function') {
                window.setComparePhotosActive(true);
            }
            
            if (newMode === 'fotos' && typeof window.setFotosToolActive === 'function') {
                window.setFotosToolActive(true);
            }
            
            if (newMode === 'compare_clouds' && typeof window.setCompareCloudsActive === 'function') {
                window.setCompareCloudsActive(true);
            }
        } else {
            // Fechar painel
            if (typeof window.setComparePhotosActive === 'function') {
                window.setComparePhotosActive(false);
            }
            if (typeof window.setCompareCloudsActive === 'function') {
                window.setCompareCloudsActive(false);
            }
            if (typeof window.setFotosToolActive === 'function') {
                window.setFotosToolActive(false);
            }
            
            leftPanel.classList.remove('open');
            leftPanel.removeAttribute('data-mode');
            contents.forEach((el) => el.classList.remove('active'));
            buttons.forEach((btn) => btn.classList.remove('active'));
            this.setStatusMessage(this.STATUS_MESSAGES.default);
            
            const sidebar = document.getElementById('potree_sidebar_container');
            if (sidebar) {
                sidebar.style.display = 'none';
            }
        }
        
        this.updateRenderAreaLeft();
        return newMode;
    }

    /**
     * Inicializa o painel lateral esquerdo e registra event listeners.
     */
    init() {
        const leftPanel = document.getElementById('left_panel');
        const sidebar = document.getElementById('potree_sidebar_container');
        
        if (sidebar) {
            sidebar.style.display = 'none';
        }
        
        // Registra handlers para os botões da sidebar
        document.querySelectorAll('.sidebar-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const mode = btn.getAttribute('data-mode');
                if (mode) {
                    this.setMode(mode);
                }
            });
        });
        
        this.updateRenderAreaLeft();
        
        // Expõe funções globalmente para compatibilidade
        if (typeof window.setStatusMessage === 'undefined') {
            window.setStatusMessage = (msg) => this.setStatusMessage(msg);
        }
        if (typeof window.restoreStatusMessage === 'undefined') {
            window.restoreStatusMessage = () => this.setStatusMessage(this.STATUS_MESSAGES[this.currentMode] || this.STATUS_MESSAGES.default);
        }
        if (typeof window.STATUS_MESSAGE_PHOTO_AREA === 'undefined') {
            window.STATUS_MESSAGE_PHOTO_AREA = this.STATUS_MESSAGES.photo_area;
        }
        if (typeof window.STATUS_MESSAGE_SELECT_POINT === 'undefined') {
            window.STATUS_MESSAGE_SELECT_POINT = this.STATUS_MESSAGES.select_point;
        }
        if (typeof window.setLeftPanelMode === 'undefined') {
            window.setLeftPanelMode = (mode) => this.setMode(mode);
        }
    }
}
