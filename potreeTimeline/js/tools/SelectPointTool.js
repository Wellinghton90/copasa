/**
 * Ferramenta de seleção de pontos na nuvem para o diário.
 * Permite múltiplos cliques; exibe coordenada UTM na barra de status.
 * Esc desativa a ferramenta.
 *
 * @class SelectPointTool
 */

/** Mensagem exibida na status bar quando a ferramenta está ativa (um único pin). */
export const STATUS_MESSAGE_SELECT_POINT = 'Clique na nuvem para posicionar o pin. Esc para cancelar';

export class SelectPointTool {
    /**
     * Cria uma instância de SelectPointTool.
     * @param {object} viewer - Instância Potree.Viewer
     * @param {object|ConfigService} configService - Serviço de configuração
     * @param {OffsetService} offsetService - Serviço de offset (getOffsetForProject)
     */
    constructor(viewer, configService, offsetService) {
        this.viewer = viewer;
        this.configService = configService;
        this.offsetService = offsetService;
        this.active = false;
        /** @type {{ x: number, y: number, z: number }[]} Pontos UTM desta sessão. */
        this.points = [];
        this.clickHandler = null;
        this.escapeHandler = null;
    }

    /**
     * Obtém a configuração atual (com projetos e projeto inicial).
     * @returns {object}
     */
    _getConfig() {
        if (this.configService && typeof this.configService.getConfig === 'function') {
            return this.configService.getConfig();
        }
        return typeof window !== 'undefined' && window.NUVEM_CONFIG ? window.NUVEM_CONFIG : {};
    }

    /**
     * Carrega o offset Pix4D do projeto (offset.xyz). Retorna null se não existir.
     * @param {string} projectId - ID do projeto
     * @returns {Promise<[number, number, number]|null>}
     */
    async _loadPix4dOffset(projectId) {
        if (this.configService && typeof this.configService.loadPix4dOffset === 'function') {
            return this.configService.loadPix4dOffset(projectId);
        }
        return null;
    }

    /**
     * Atualiza a mensagem da barra de status.
     * @param {string} msg - Mensagem a exibir
     */
    _setStatusMessage(msg) {
        if (typeof window.setStatusMessage === 'function') {
            window.setStatusMessage(msg);
        } else {
            const el = document.getElementById('status_bar_message');
            if (el) el.textContent = msg;
        }
    }

    /**
     * Restaura a mensagem padrão da barra de status.
     */
    _restoreStatusMessage() {
        if (typeof window.restoreStatusMessage === 'function') {
            window.restoreStatusMessage();
        } else {
            this._setStatusMessage('Nuvem de pontos');
        }
    }

    /**
     * Formata um ponto UTM para exibição na status bar.
     * @param {{ x: number, y: number, z: number }} pt
     * @param {number} decimals
     * @returns {string}
     */
    _formatPoint(pt, decimals = 2) {
        const f = (n) => Number(n).toFixed(decimals);
        return `UTM: ${f(pt.x)}, ${f(pt.y)}, ${f(pt.z)}`;
    }

    /**
     * Calcula coordenada UTM: P_cena + pix4dOffset - offsetProjeto.
     * @param {THREE.Vector3} pCena - Ponto em coordenadas de cena (result.location)
     * @param {[number, number, number]} pix4dOffset - Offset Pix4D ou [0,0,0]
     * @param {[number, number, number]} offsetProjeto - Offset do projeto (alinhamento)
     * @returns {{ x: number, y: number, z: number }}
     */
    _toUTM(pCena, pix4dOffset, offsetProjeto) {
        const pix = pix4dOffset || [0, 0, 0];
        const proj = offsetProjeto && offsetProjeto.length >= 3 ? offsetProjeto : [0, 0, 0];
        return {
            x: pCena.x + pix[0] - proj[0],
            y: pCena.y + pix[1] - proj[1],
            z: pCena.z + pix[2] - proj[2]
        };
    }

    /**
     * Handler de clique na área de render: obtém ponto na nuvem, calcula UTM e atualiza status bar.
     * @private
     */
    _onClick = async (e) => {
        if (!this.active) return;

        const viewer = this.viewer || window.viewer;
        const pointcloud = window.currentPointcloud;
        if (!viewer || !pointcloud) return;

        const rect = viewer.renderer.domElement.getBoundingClientRect();
        const mouse = { x: e.clientX - rect.left, y: e.clientY - rect.top };
        const camera = viewer.scene.getActiveCamera();
        const pointclouds = [pointcloud];
        const result = Potree.Utils.getMousePointCloudIntersection(mouse, camera, viewer, pointclouds, {});

        if (!result || !result.location) return;

        const pCena = result.location.clone();
        const config = this._getConfig();
        const projectId = window.currentProjectId != null
            ? window.currentProjectId
            : (config.projetoInicial || (config.projetosDisponiveis && config.projetosDisponiveis[0] && config.projetosDisponiveis[0].id));
        if (!projectId) return;

        const availableProjects = config.projetosDisponiveis || [];
        const offsetProjeto = this.offsetService
            ? this.offsetService.getOffsetForProject(projectId, availableProjects)
            : ((availableProjects.find((p) => p.id === projectId) || {}).offset || [0, 0, 0]);
        const pix4dOffset = await this._loadPix4dOffset(projectId);

        const utm = this._toUTM(pCena, pix4dOffset, offsetProjeto);
        this.points = [utm];

        if (typeof window.onSelectPointPointAdded === 'function') {
            window.onSelectPointPointAdded(utm);
        }

        const suffix = pix4dOffset ? '' : ' (sem offset.xyz)';
        this._setStatusMessage(`Pin: ${this._formatPoint(utm)}${suffix}`);
        viewer.render();

        // Um único pin: desativa a ferramenta após o primeiro clique
        this.setActive(false);
    };

    /**
     * Handler da tecla Esc: desativa a ferramenta.
     * @private
     */
    _onEscape = (e) => {
        if (e.key === 'Escape' && this.active) {
            e.preventDefault();
            this.setActive(false);
        }
    };

    /**
     * Ativa ou desativa a ferramenta. Liga/desliga listeners de clique e Esc.
     * @param {boolean} active - true para ativar, false para desativar
     */
    setActive(active) {
        const renderArea = document.getElementById('potree_render_area');
        if (!renderArea) return;

        if (this.clickHandler) {
            renderArea.removeEventListener('click', this.clickHandler);
            this.clickHandler = null;
        }
        if (this.escapeHandler) {
            document.removeEventListener('keydown', this.escapeHandler);
            this.escapeHandler = null;
        }

        this.active = !!active;

        if (this.active) {
            this.points = [];
            this.clickHandler = this._onClick;
            renderArea.addEventListener('click', this.clickHandler);
            this.escapeHandler = this._onEscape;
            document.addEventListener('keydown', this.escapeHandler);
            this._setStatusMessage(STATUS_MESSAGE_SELECT_POINT);
            if (typeof window !== 'undefined') {
                window.diaryReferenceMode = 'selectPoint';
            }
        } else {
            if (typeof window !== 'undefined') {
                window.diaryReferenceMode = null;
                if (typeof window.onSelectPointToolDeactivate === 'function') {
                    window.onSelectPointToolDeactivate(this.points);
                }
            }
            this._restoreStatusMessage();
        }
    }

    /**
     * Inicialização (sem UI própria; ativação é feita pelo diário).
     */
    init() {
        // Nada a montar no DOM; setActive(true) é chamado pelo InspectionDiaryTool
    }
}
