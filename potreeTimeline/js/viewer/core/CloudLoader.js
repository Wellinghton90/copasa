/**
 * Carregador de nuvens de pontos.
 * Gerencia carregamento, cache hit/miss, e aplicação de offsets.
 * 
 * @class CloudLoader
 */

import { getCloudJsUrl } from '../../config/viewer-config.js';
import { ConfigService } from '../../config/ConfigService.js';
import { applyOffsetDeltaToFrustums } from '../../Frustum/frustumRenderer.js';

export class CloudLoader {
    /**
     * Cria uma instância de CloudLoader.
     * @param {object} viewer - Instância Potree.Viewer
     * @param {CloudCache} cache - Cache de nuvens
     * @param {object|ConfigService} configService - Serviço de configuração (objeto ou instância de ConfigService)
     * @param {OffsetService} offsetService - Serviço de offset (obrigatório para aplicar offsets)
     */
    constructor(viewer, cache, configService, offsetService) {
        this.viewer = viewer;
        this.cache = cache;
        this.configService = configService instanceof ConfigService ? configService : null;
        this.config = configService instanceof ConfigService ? configService.getConfig() : configService;
        this.offsetService = offsetService;
    }

    /**
     * Aplica configurações padrão de material em uma nuvem de pontos.
     * @param {object} pointcloud - Nuvem de pontos Potree
     */
    applyDefaultMaterial(pointcloud) {
        const material = pointcloud.material;
        material.size = 1;
        material.pointSizeType = Potree.PointSizeType.ADAPTIVE;
        material.shape = Potree.PointShape.SQUARE;
    }

    /**
     * Aplica offset a uma nuvem de pontos baseado no projeto.
     * @param {object} pointcloud - Nuvem de pontos
     * @param {string} projectId - ID do projeto
     * @param {Array} availableProjects - Lista de projetos disponíveis
     */
    applyOffsetToPointcloud(pointcloud, projectId, availableProjects) {
        if (!pointcloud || !projectId || !this.offsetService) return;
        
        // Salva posição inicial se ainda não foi salva
        if (!pointcloud.userData.initialPosition) {
            pointcloud.userData.initialPosition = pointcloud.position.clone();
        }
        
        const offset = this.offsetService.getOffsetForProject(projectId, availableProjects);
        pointcloud.position.x += offset[0];
        pointcloud.position.y += offset[1];
        pointcloud.position.z += offset[2];
    }

    /**
     * Carrega uma nuvem de pontos do servidor.
     * @param {string} projectId - ID do projeto
     * @param {Array} availableProjects - Lista de projetos disponíveis
     * @param {Function} onLoadCallback - Callback chamado quando a nuvem é carregada
     * @returns {Promise<object|null>} Promise que resolve com a nuvem ou null em caso de erro
     */
    async loadPointCloud(projectId, availableProjects, onLoadCallback = null) {
        if (!projectId || !this.viewer || !this.cache) {
            return Promise.resolve(null);
        }

        // Usa ConfigService se disponível, senão usa função importada
        const url = this.configService 
            ? this.configService.getCloudJsUrl(projectId)
            : getCloudJsUrl(projectId);
        
        return new Promise((resolve) => {
            try {
                Potree.loadPointCloud(url, 'DSM', (e) => {
                    if (!e || !e.pointcloud) {
                        console.warn('Falha ao carregar nuvem:', projectId);
                        resolve(null);
                        if (onLoadCallback) onLoadCallback(null);
                        return;
                    }
                    
                    const scene = this.viewer.scene;
                    const pointcloud = e.pointcloud;
                    
                    this.applyOffsetToPointcloud(pointcloud, projectId, availableProjects);
                    this.applyDefaultMaterial(pointcloud);
                    
                    scene.addPointCloud(pointcloud);
                    this.cache.add(projectId, pointcloud);
                    pointcloud.visible = true;
                    
                    if (onLoadCallback) onLoadCallback(pointcloud);
                    resolve(pointcloud);
                });
            } catch (err) {
                console.warn('Erro ao carregar nuvem:', projectId, err);
                resolve(null);
                if (onLoadCallback) onLoadCallback(null);
            }
        });
    }

    /**
     * Carrega uma nuvem para modo de comparação: não esconde outras, retorna Promise.
     * @param {string} projectId - ID do projeto
     * @param {Array} availableProjects - Lista de projetos disponíveis
     * @returns {Promise<object|null>} Promise que resolve com a nuvem ou null
     */
    async loadForCompare(projectId, availableProjects) {
        if (!projectId || !this.viewer || !this.cache) {
            return Promise.resolve(null);
        }

        const cached = this.cache.get(projectId);
        if (cached) {
            this.cache.touch(projectId);
            return Promise.resolve(cached);
        }

        if (this.cache.size >= 3) { // MAX_CACHED_CLOUDS
            this.cache.evictLRU();
        }

        return this.loadPointCloud(projectId, availableProjects, (pointcloud) => {
            if (pointcloud) {
                pointcloud.updateMatrixWorld(true);
                this.viewer.render();
            }
        });
    }

    /**
     * Aplica um delta de offset a uma nuvem e seus frustums associados.
     * @param {object} pointcloud - Nuvem de pontos
     * @param {number} dx - Delta X
     * @param {number} dy - Delta Y
     * @param {number} dz - Delta Z
     */
    applyOffsetDelta(pointcloud, dx, dy, dz) {
        if (!pointcloud || !this.viewer) return;
        
        const initial = pointcloud.userData.initialPosition;
        if (initial) {
            pointcloud.position.x += dx;
            pointcloud.position.y += dy;
            pointcloud.position.z += dz;
        }
        
        if (dx !== 0 || dy !== 0 || dz !== 0) {
            applyOffsetDeltaToFrustums(dx, dy, dz);
        }
        
        this.viewer.render();
    }
}
