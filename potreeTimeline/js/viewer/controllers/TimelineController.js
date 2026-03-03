/**
 * Controlador principal da timeline de nuvens.
 * Orquestra todos os subsistemas: cache, loader, câmera, UI, etc.
 * 
 * @class TimelineController
 */

import { getCameraParamsUrl, loadPix4dOffset } from '../../config/viewer-config.js';
import { ConfigService } from '../../config/ConfigService.js';
import { loadCameraFrustumsAsync } from '../../Frustum/index.js';

// Classes core
import { CloudCache } from '../core/CloudCache.js';
import { FrustumCache } from '../core/FrustumCache.js';
import { CloudLoader } from '../core/CloudLoader.js';
import { CameraController } from '../core/CameraController.js';

// Controllers
import { LeftPanelController } from './LeftPanelController.js';
import { PointSizeController } from './PointSizeController.js';

// Services
import { OffsetService } from '../services/OffsetService.js';

// Tools
import { CompareCloudsTool } from '../../tools/index.js';
import { ComparePhotosTool } from '../../tools/index.js';
import { PhotosAtPointTool } from '../../tools/index.js';
import { SelectPointTool } from '../../tools/index.js';
import { LayersTool } from '../../tools/index.js';

// Inspection Diary (painel direito)
import { RightPanelController } from '../../../inspectionDiary/js/RightPanelController.js';
import { InspectionDiaryTool } from '../../../inspectionDiary/js/InspectionDiaryTool.js';
import { DiaryPinRenderer } from '../../../inspectionDiary/js/DiaryPinRenderer.js';

// UI (funções puras)
import {
    updateArrowButtons,
    initProjectSelector,
    updateProjectSelector
} from '../timeline-ui.js';

export class TimelineController {
    /**
     * Cria uma instância de TimelineController.
     * @param {object|ConfigService} config - Configuração (objeto ou instância de ConfigService)
     */
    constructor(config) {
        // Aceita ConfigService ou objeto simples
        if (config instanceof ConfigService) {
            this.configService = config;
            this.config = config.getConfig();
        } else {
            this.config = config;
            this.configService = null;
        }
        
        // Estado
        this.viewer = null;
        this.currentPointcloud = null;
        this.currentIndex = 0;
        this.currentProjectId = '';
        this.availableProjects = this.config.projetosDisponiveis || [];
        this.initialProjectId = this.config.projetoInicial || '';
        this.developerMode = this.config.developerMode || false;
        this.maxCachedClouds = this.config.maxCachedClouds || 3;
        
        // Serviços (serão inicializados em init)
        this.cache = null;
        this.frustumCache = null;
        this.cloudLoader = null;
        this.cameraController = null;
        this.leftPanelController = null;
        this.pointSizeController = null;
        this.offsetService = null;
        
        // Tools (serão inicializados em init)
        this.compareCloudsTool = null;
        this.comparePhotosTool = null;
        this.photosTool = null;
        this.selectPointTool = null;
        this.layersTool = null;

        // Inspection Diary (painel direito)
        this.rightPanelController = null;
        this.inspectionDiaryTool = null;
        this.diaryPinRenderer = null;
    }

    /**
     * Carrega frustums de câmera para um projeto específico.
     * Usa cache quando disponível para evitar recarregar frustums já carregados.
     * @private
     * @param {string} projectId - ID do projeto
     */
    async _loadFrustumsForProject(projectId) {
        try {
            const url = getCameraParamsUrl(projectId);
            const pix4dOffset = await loadPix4dOffset(projectId);
            
            // Usar o pix4dOffset da nuvem atual para que os frustums fiquem alinhados com ela
            // O manualOffset garante que os frustums fiquem na mesma posição relativa à nuvem
            const manualOffset = this.offsetService 
                ? this.offsetService.getOffsetForProject(projectId, this.availableProjects)
                : [0, 0, 0];
            
            const pix4d = pix4dOffset != null ? pix4dOffset : [0, 0, 0];
            
            // Verifica se está no cache e se os offsets são compatíveis
            if (this.frustumCache && this.frustumCache.has(projectId, pix4d, manualOffset)) {
                // Restaura do cache (muito mais rápido)
                const restored = this.frustumCache.restore(projectId);
                if (restored) {
                    // console.log("✅ Frustums restaurados do cache:", projectId);
                    if (this.viewer && typeof this.viewer.render === "function") {
                        this.viewer.render();
                    }
                    return;
                }
            }
            
            // Carrega do servidor e armazena no cache
            const result = await loadCameraFrustumsAsync(url, pix4d, manualOffset, projectId, 0, this.frustumCache);
            
            if (result && this.frustumCache) {
                // Evict LRU se necessário
                if (this.frustumCache.size >= this.maxCachedClouds) {
                    this.frustumCache.evictLRU();
                }
                
                // Armazena no cache
                this.frustumCache.add(projectId, result.frustums, result.cameraDataStore, pix4d, manualOffset);
                console.log("💾 Frustums armazenados no cache:", projectId);
            }
        } catch (err) {
            console.error("Erro ao carregar frustums:", err);
        }
    }

    /**
     * Define uma nuvem como a atual e atualiza toda a UI relacionada.
     * @param {object} pointcloud - Nuvem de pontos
     * @param {string} projectId - ID do projeto
     */
    setAsCurrent(pointcloud, projectId) {
        if (!pointcloud) {
            console.error('setAsCurrent: pointcloud é null!', projectId);
            return;
        }
        
        this.currentPointcloud = pointcloud;
        window.currentPointcloud = pointcloud;
        this.currentProjectId = projectId;
        window.currentProjectId = projectId;
        
        const idx = this.availableProjects.findIndex((p) => p.id === projectId);
        if (idx >= 0) {
            this.currentIndex = idx;
        }
        
        // Atualiza UI
        updateProjectSelector(projectId);
        updateArrowButtons(this.currentIndex, this.availableProjects.length);
        
        if (this.pointSizeController) {
            this.pointSizeController.setPointcloud(pointcloud);
        }
        
        if (this.developerMode && this.offsetService) {
            this.offsetService.updateUI(pointcloud, projectId, this.availableProjects);
        }
        
        // Atualiza fotos para nova nuvem
        if (this.photosTool) {
            this.photosTool.updateForNewCloud();
        }
        
        // Garantir que a nuvem está visível
        if (!pointcloud.visible) {
            console.warn('setAsCurrent: nuvem não estava visível, corrigindo...', projectId);
            pointcloud.visible = true;
        }

        // Notificar mudança de projeto (ex.: diário de fiscalização atualiza a lista)
        if (typeof window.dispatchEvent === 'function') {
            window.dispatchEvent(new CustomEvent('projectchange', { detail: { projectId } }));
        }

        // Atualizar pivot da view para a posição da nuvem atual
        if (this.cameraController) {
            this.cameraController.updatePivotToPointcloud(pointcloud);
        }
    }

    /**
     * Carrega uma nuvem de pontos para visualização.
     * @param {string} projectId - ID do projeto
     * @param {boolean} keepCamera - Se true, mantém a posição da câmera atual
     */
    async loadCloud(projectId, keepCamera = false) {
        if (!projectId || !this.viewer || !this.cache || !this.cloudLoader) {
            return;
        }
        
        if (projectId === this.currentProjectId) {
            return;
        }
        
        // Verifica cache
        const cached = this.cache.get(projectId);
        if (cached) {
            if (this.currentPointcloud) {
                this.currentPointcloud.visible = false;
            }
            cached.visible = true;
            this.cache.touch(projectId);
            this.setAsCurrent(cached, projectId);
            await this._loadFrustumsForProject(projectId);
            this.viewer.render();
            return;
        }
        
        // Esconde nuvem atual e salva estado da câmera se necessário
        if (this.currentPointcloud) {
            this.currentPointcloud.visible = false;
        }
        const cameraState = (this.currentPointcloud && keepCamera && this.cameraController) 
            ? this.cameraController.saveState() 
            : null;
        
        // Evict LRU se necessário
        if (this.cache.size >= this.maxCachedClouds) {
            this.cache.evictLRU();
        }
        
        // Carrega nova nuvem
        const pointcloud = await this.cloudLoader.loadPointCloud(projectId, this.availableProjects);
        
        if (!pointcloud) {
            console.warn('Falha ao carregar nuvem:', projectId);
            if (this.currentPointcloud) {
                this.currentPointcloud.visible = true;
            }
            return;
        }
        
        pointcloud.visible = true;
        this.setAsCurrent(pointcloud, projectId);
        await this._loadFrustumsForProject(projectId);
        
        if (cameraState && this.cameraController) {
            this.cameraController.restoreState(cameraState);
        } else {
            this.cameraController?.fitToScreen();
        }
        
        this.viewer.render();
    }

    /**
     * Finaliza saída do modo de comparação: esconde nuvens exceto a indicada e define como current.
     * @param {string} projectIdToShow - Projeto a exibir
     * @param {string[]} projectIdsInCompare - IDs das nuvens que estavam em comparação
     */
    finishExitCompareClouds(projectIdToShow, projectIdsInCompare = []) {
        if (!this.viewer || !this.cache) {
            return;
        }
        
        for (const id of projectIdsInCompare) {
            const pc = this.cache.get(id);
            if (pc) {
                pc.visible = id === projectIdToShow;
            }
        }
        
        const toShow = this.cache.get(projectIdToShow);
        if (toShow) {
            toShow.visible = true;
            this.setAsCurrent(toShow, projectIdToShow);
        }
        
        this.viewer.render();
    }

    /**
     * Navega para um projeto específico pelo índice.
     * @param {number} index - Índice do projeto na lista
     */
    goToProject(index) {
        if (index < 0 || index >= this.availableProjects.length) {
            return;
        }
        
        this.currentIndex = index;
        this.loadCloud(this.availableProjects[index].id, true);
    }

    /**
     * Inicializa o viewer Potree e todos os subsistemas.
     */
    async init() {
        const renderArea = document.getElementById('potree_render_area');
        if (!renderArea) {
            return;
        }
        
        // Cria viewer
        this.viewer = new Potree.Viewer(renderArea);
        window.viewer = this.viewer;
        
        // Cria caches
        this.cache = new CloudCache(this.viewer, this.maxCachedClouds);
        this.frustumCache = new FrustumCache(this.viewer, this.maxCachedClouds);
        
        // Cria serviços
        this.cameraController = new CameraController(this.viewer);
        
        // OffsetService deve ser criado antes do CloudLoader (que depende dele)
        this.offsetService = new OffsetService(this.configService || this.config);
        this.cloudLoader = new CloudLoader(this.viewer, this.cache, this.configService || this.config, this.offsetService);
        
        this.leftPanelController = new LeftPanelController();
        this.rightPanelController = new RightPanelController();
        this.pointSizeController = new PointSizeController(this.viewer);
        
        // Cria ferramentas
        this.compareCloudsTool = new CompareCloudsTool(this.viewer, this.cloudLoader);
        // Passa ConfigService se disponível, senão passa o objeto config
        this.photosTool = new PhotosAtPointTool(this.viewer, this.configService || this.config);
        this.comparePhotosTool = new ComparePhotosTool(this.viewer, this.photosTool);
        this.selectPointTool = new SelectPointTool(this.viewer, this.configService || this.config, this.offsetService);
        this.layersTool = new LayersTool(this.viewer);
        this.diaryPinRenderer = new DiaryPinRenderer(this.viewer, this.configService || this.config, this.offsetService);
        this.inspectionDiaryTool = new InspectionDiaryTool();
        
        // Configura viewer
        this.viewer.setBackground('gradient');
        this.viewer.setEDLEnabled(false);
        this.viewer.setFOV(60);
        /** Orçamento total de pontos (todas as nuvens); menor = menos memória e mais fluido ao trocar nuvens. */
        this.viewer.setPointBudget(6_000_000);
        this.viewer.loadSettingsFromURL();
        this.viewer.setDescription('');
        
        // Carrega GUI do Potree
        this.viewer.loadGUI(() => {
            this.viewer.setLanguage('pt');
            $('#menu_tools').next().show();
            $('#menu_clipping').next().show();
            const sidebar = document.getElementById('potree_sidebar_container');
            if (sidebar) {
                sidebar.style.display = 'none';
            }
        });
        
        // Inicializa timeline quando DOM estiver pronto
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this._initTimeline());
        } else {
            this._initTimeline();
        }
        
        // Inicializa UI do painel lateral
        this.leftPanelController.init();
        
        // Inicializa controles de tamanho de pontos
        this.pointSizeController.init();
        
        // Inicializa ferramentas
        this.compareCloudsTool.init();
        this.photosTool.init();
        this.comparePhotosTool.init();
        this.selectPointTool.init();
        this.layersTool.init();
        this.rightPanelController.init();
        this.diaryPinRenderer.init();
        this.inspectionDiaryTool.init();
        
        // Inicializa UI de offset (developer mode) — canto inferior direito, acima da barra de status
        if (this.developerMode) {
            this.offsetService.initUI(
                this.currentPointcloud,
                this.viewer,
                this.currentProjectId,
                this.availableProjects,
                { containerId: 'developer_offset_container' }
            );
        }
        
        // Expõe funções globalmente para compatibilidade
        window.loadCloudForCompare = (projectId) => {
            return this.cloudLoader.loadForCompare(projectId, this.availableProjects);
        };
        window.finishExitCompareClouds = this.finishExitCompareClouds.bind(this);
        window.pinCompareClouds = (ids) => {
            this.cache?.pinForCompare?.(ids);
            this.frustumCache?.pinForCompare?.(ids);
        };
        window.unpinCompareClouds = () => {
            this.cache?.pinForCompare?.([]);
            this.frustumCache?.pinForCompare?.([]);
        };
        window.setSelectPointToolActive = (active) => this.selectPointTool.setActive(active);
        window.setDiaryPinPosition = (utm, projectId, color) => this.diaryPinRenderer.setPinPosition(utm, projectId, color);
        window.setDiaryPins = (pins, projectId) => this.diaryPinRenderer.setPins(pins, projectId);
        window.setDiaryPinColor = (pinIndex, color) => this.diaryPinRenderer.setPinColor(pinIndex, color);

        window.addEventListener('projectchange', (e) => {
            const projectId = e.detail && e.detail.projectId;
            if (this.diaryPinRenderer && projectId != null) {
                this.diaryPinRenderer.refreshPosition(projectId);
            }
        });
    }

    /**
     * Inicializa a timeline (seletor e controles de navegação).
     * @private
     */
    _initTimeline() {
        this.currentIndex = initProjectSelector(this.availableProjects, this.initialProjectId);
        updateArrowButtons(this.currentIndex, this.availableProjects.length);
        
        const initial = this.initialProjectId || (this.availableProjects[0] && this.availableProjects[0].id);
        if (initial && this.availableProjects.length > 0) {
            this.loadCloud(initial, false);
        }
        
        // Registra event listeners
        const sel = document.getElementById('seletor_projeto');
        const btnPrev = document.getElementById('btn_anterior');
        const btnNext = document.getElementById('btn_proximo');
        
        if (sel) {
            sel.addEventListener('change', () => {
                const id = sel.value;
                const idx = this.availableProjects.findIndex((p) => p.id === id);
                if (idx >= 0) {
                    this.goToProject(idx);
                }
            });
        }
        
        if (btnPrev) {
            btnPrev.addEventListener('click', () => {
                if (this.currentIndex > 0) {
                    this.goToProject(this.currentIndex - 1);
                }
            });
        }
        
        if (btnNext) {
            btnNext.addEventListener('click', () => {
                if (this.currentIndex < this.availableProjects.length - 1) {
                    this.goToProject(this.currentIndex + 1);
                }
            });
        }
        
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') {
                return;
            }
            
            if (this.availableProjects.length === 0) {
                return;
            }
            
            if (e.key === 'ArrowLeft') {
                if (this.currentIndex <= 0) return;
                e.preventDefault();
                this.goToProject(this.currentIndex - 1);
            } else {
                if (this.currentIndex >= this.availableProjects.length - 1) return;
                e.preventDefault();
                this.goToProject(this.currentIndex + 1);
            }
        });
    }
}
