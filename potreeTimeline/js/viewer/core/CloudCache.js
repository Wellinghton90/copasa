/**
 * Cache LRU de nuvens de pontos (mantém no máximo maxSize nuvens em memória).
 * Usado para troca instantânea entre as últimas nuvens visualizadas.
 * 
 * @class CloudCache
 */
export class CloudCache {
    /**
     * Cria uma instância de CloudCache.
     * @param {object} viewer - Instância Potree.Viewer
     * @param {number} maxSize - Número máximo de nuvens em cache (padrão: 3)
     */
    constructor(viewer, maxSize = 3) {
        this.viewer = viewer;
        this.maxSize = maxSize;
        this.pointcloudsByProject = new Map();
        this.lruOrder = [];
        /** IDs fixados durante modo de comparação (não sofrem eviction) */
        this.pinnedForCompare = new Set();
    }

    /**
     * Descarrega geometrias e material de um pointcloud para liberar memória GPU/CPU.
     * @private
     * @param {object} pointcloud - Pointcloud (PointCloudOctree) a descarregar
     */
    _disposePointCloudResources(pointcloud) {
        if (!pointcloud || !this.viewer) return;
        try {
            const pRenderer = this.viewer.pRenderer;
            if (pRenderer && typeof pRenderer.releaseMaterial === 'function' && pointcloud.material) {
                pRenderer.releaseMaterial(pointcloud.material);
            }
            pointcloud.traverse((obj) => {
                if (obj.geometry) {
                    try {
                        obj.geometry.dispose();
                    } catch (e) {
                        // contexto WebGL pode estar perdido
                    }
                }
            });
            if (pointcloud.material && typeof pointcloud.material.dispose === 'function') {
                pointcloud.material.dispose();
            }
        } catch (e) {
            console.warn('Dispose pointcloud:', e);
        }
    }

    /**
     * Remove um pointcloud da cena do viewer e libera recursos.
     * @private
     * @param {object} pointcloud - Pointcloud a remover
     */
    _removeFromScene(pointcloud) {
        if (!pointcloud || !this.viewer) return;
        const scene = this.viewer.scene;
        scene.scenePointCloud.remove(pointcloud);
        const idx = scene.pointclouds.indexOf(pointcloud);
        if (idx !== -1) scene.pointclouds.splice(idx, 1);
        this._disposePointCloudResources(pointcloud);
        if (typeof pointcloud.dispose === 'function') {
            pointcloud.dispose();
        }
    }

    /**
     * Adiciona uma nuvem ao cache.
     * @param {string} projectId - ID do projeto
     * @param {object} pointcloud - Nuvem de pontos
     */
    add(projectId, pointcloud) {
        this.pointcloudsByProject.set(projectId, pointcloud);
        this.lruOrder.push(projectId);
    }

    /**
     * Obtém uma nuvem do cache.
     * @param {string} projectId - ID do projeto
     * @returns {object|null} Nuvem de pontos ou null se não encontrada
     */
    get(projectId) {
        return this.pointcloudsByProject.get(projectId) || null;
    }

    /**
     * Verifica se uma nuvem está no cache.
     * @param {string} projectId - ID do projeto
     * @returns {boolean}
     */
    has(projectId) {
        return this.pointcloudsByProject.has(projectId);
    }

    /**
     * Marca uma nuvem como recentemente usada (atualiza ordem LRU).
     * @param {string} projectId - ID do projeto
     */
    touch(projectId) {
        const i = this.lruOrder.indexOf(projectId);
        if (i !== -1) {
            this.lruOrder.splice(i, 1);
        }
        this.lruOrder.push(projectId);
    }

    /**
     * Remove a nuvem menos recentemente usada do cache (LRU eviction).
     */
    evictLRU() {
        while (this.pointcloudsByProject.size >= this.maxSize && this.lruOrder.length > 0) {
            let evicted = false;
            for (let i = 0; i < this.lruOrder.length; i++) {
                const projectId = this.lruOrder[i];
                if (this.pinnedForCompare.has(projectId)) continue;
                this.lruOrder.splice(i, 1);
                const pointcloud = this.pointcloudsByProject.get(projectId);
                if (pointcloud) {
                    this._removeFromScene(pointcloud);
                    this.pointcloudsByProject.delete(projectId);
                    evicted = true;
                }
                break;
            }
            if (!evicted) break;
        }
    }

    /**
     * Fixa nuvens no cache durante modo de comparação (não sofrem eviction).
     * @param {string[]} projectIds - IDs dos projetos a fixar
     */
    pinForCompare(projectIds) {
        this.pinnedForCompare = new Set(projectIds || []);
    }

    /**
     * Retorna o número de nuvens em cache.
     * @returns {number}
     */
    get size() {
        return this.pointcloudsByProject.size;
    }
}
