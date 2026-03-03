/**
 * Cache LRU de frustums de câmera (mantém no máximo maxSize frustums em memória).
 * Usado para evitar recarregar frustums de projetos já visualizados.
 * 
 * @class FrustumCache
 */

import { clearAllFrustums, addFrustumToScene, removeFrustumFromScene } from '../../Frustum/frustumRenderer.js';
import { CameraDataStore } from '../../Frustum/CameraDataStore.js';

export class FrustumCache {
    /**
     * Cria uma instância de FrustumCache.
     * @param {object} viewer - Instância Potree.Viewer
     * @param {number} maxSize - Número máximo de frustums em cache (padrão: 3)
     */
    constructor(viewer, maxSize = 3) {
        this.viewer = viewer;
        this.maxSize = maxSize;
        /** Map: projectId -> { frustums: Array, cameraDataStore: CameraDataStore, pix4dOffset: Array, manualOffset: Array } */
        this.frustumsByProject = new Map();
        this.lruOrder = [];
        /** IDs fixados durante modo de comparação (não sofrem eviction) */
        this.pinnedForCompare = new Set();
    }

    /**
     * Descarrega recursos de frustums (geometrias, materiais) para liberar memória GPU/CPU.
     * @private
     * @param {Array} frustums - Array de frustums a descarregar
     */
    _disposeFrustumResources(frustums) {
        if (!frustums || !Array.isArray(frustums)) return;
        try {
            for (const frustum of frustums) {
                if (!frustum) continue;
                frustum.traverse((child) => {
                    if (child.geometry) {
                        try {
                            child.geometry.dispose();
                        } catch (e) {
                            // contexto WebGL pode estar perdido
                        }
                    }
                    if (child.material) {
                        if (Array.isArray(child.material)) {
                            child.material.forEach(mat => {
                                if (mat && typeof mat.dispose === 'function') {
                                    mat.dispose();
                                }
                            });
                        } else if (typeof child.material.dispose === 'function') {
                            child.material.dispose();
                        }
                    }
                });
            }
        } catch (e) {
            console.warn('Dispose frustums:', e);
        }
    }

    /**
     * Remove frustums da cena do viewer e libera recursos.
     * @private
     * @param {Array} frustums - Array de frustums a remover
     */
    _removeFromScene(frustums) {
        if (!frustums || !Array.isArray(frustums)) return;
        for (const frustum of frustums) {
            if (frustum) {
                removeFrustumFromScene(frustum);
            }
        }
        this._disposeFrustumResources(frustums);
    }

    /**
     * Adiciona frustums ao cache.
     * @param {string} projectId - ID do projeto
     * @param {Array} frustums - Array de frustums (objetos Three.js)
     * @param {CameraDataStore} cameraDataStore - Store com dados das câmeras
     * @param {[number, number, number]} pix4dOffset - Offset Pix4D usado
     * @param {[number, number, number]} manualOffset - Offset manual usado
     */
    add(projectId, frustums, cameraDataStore, pix4dOffset, manualOffset) {
        // Remove entrada antiga se existir
        if (this.frustumsByProject.has(projectId)) {
            const old = this.frustumsByProject.get(projectId);
            this._removeFromScene(old.frustums);
        }

        this.frustumsByProject.set(projectId, {
            frustums: frustums,
            cameraDataStore: cameraDataStore,
            pix4dOffset: pix4dOffset ? [...pix4dOffset] : [0, 0, 0],
            manualOffset: manualOffset ? [...manualOffset] : [0, 0, 0]
        });
        
        // Atualiza ordem LRU
        const i = this.lruOrder.indexOf(projectId);
        if (i !== -1) {
            this.lruOrder.splice(i, 1);
        }
        this.lruOrder.push(projectId);
    }

    /**
     * Obtém frustums do cache.
     * @param {string} projectId - ID do projeto
     * @returns {object|null} { frustums, cameraDataStore, pix4dOffset, manualOffset } ou null se não encontrado
     */
    get(projectId) {
        return this.frustumsByProject.get(projectId) || null;
    }

    /**
     * Verifica se frustums estão no cache e se os offsets são compatíveis.
     * @param {string} projectId - ID do projeto
     * @param {[number, number, number]} pix4dOffset - Offset Pix4D atual
     * @param {[number, number, number]} manualOffset - Offset manual atual
     * @returns {boolean} true se está no cache e offsets são compatíveis
     */
    has(projectId, pix4dOffset, manualOffset) {
        const cached = this.frustumsByProject.get(projectId);
        if (!cached) return false;

        // Verifica se os offsets são compatíveis
        const pix4dMatch = this._arraysEqual(cached.pix4dOffset, pix4dOffset || [0, 0, 0]);
        const manualMatch = this._arraysEqual(cached.manualOffset, manualOffset || [0, 0, 0]);
        
        return pix4dMatch && manualMatch;
    }

    /**
     * Compara dois arrays numericamente.
     * @private
     * @param {Array} a - Primeiro array
     * @param {Array} b - Segundo array
     * @returns {boolean} true se são iguais
     */
    _arraysEqual(a, b) {
        if (!a || !b || a.length !== b.length) return false;
        const epsilon = 0.0001; // Tolerância para comparação de floats
        for (let i = 0; i < a.length; i++) {
            if (Math.abs(a[i] - b[i]) > epsilon) return false;
        }
        return true;
    }

    /**
     * Marca frustums como recentemente usados (atualiza ordem LRU).
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
     * Remove os frustums menos recentemente usados do cache (LRU eviction).
     */
    evictLRU() {
        while (this.frustumsByProject.size >= this.maxSize && this.lruOrder.length > 0) {
            let evicted = false;
            for (let i = 0; i < this.lruOrder.length; i++) {
                const projectId = this.lruOrder[i];
                if (this.pinnedForCompare.has(projectId)) continue;
                this.lruOrder.splice(i, 1);
                const cached = this.frustumsByProject.get(projectId);
                if (cached) {
                    this._removeFromScene(cached.frustums);
                    this.frustumsByProject.delete(projectId);
                    evicted = true;
                }
                break;
            }
            if (!evicted) break;
        }
    }

    /**
     * Restaura frustums do cache para a cena.
     * @param {string} projectId - ID do projeto
     * @returns {boolean} true se restaurou com sucesso
     */
    restore(projectId) {
        const cached = this.frustumsByProject.get(projectId);
        if (!cached) return false;

        // Limpa frustums atuais da cena
        clearAllFrustums();

        // Adiciona frustums do cache de volta à cena
        for (const frustum of cached.frustums) {
            if (frustum) {
                addFrustumToScene(frustum);
            }
        }

        // Restaura dados das câmeras no store global
        if (window.cameraDataStore) {
            window.cameraDataStore.clear();
            const allCameras = cached.cameraDataStore.getAll();
            for (const cam of allCameras) {
                window.cameraDataStore.add(cam);
            }
        }

        // Atualiza ordem LRU
        this.touch(projectId);

        return true;
    }

    /**
     * Invalida o cache de um projeto específico (remove e libera recursos).
     * @param {string} projectId - ID do projeto
     */
    invalidate(projectId) {
        const cached = this.frustumsByProject.get(projectId);
        if (cached) {
            this._removeFromScene(cached.frustums);
            this.frustumsByProject.delete(projectId);
            const i = this.lruOrder.indexOf(projectId);
            if (i !== -1) {
                this.lruOrder.splice(i, 1);
            }
        }
    }

    /**
     * Limpa todo o cache e remove todos os frustums da cena.
     */
    clear() {
        for (const [projectId, cached] of this.frustumsByProject.entries()) {
            this._removeFromScene(cached.frustums);
        }
        this.frustumsByProject.clear();
        this.lruOrder = [];
        clearAllFrustums();
    }

    /**
     * Fixa frustums no cache durante modo de comparação (não sofrem eviction).
     * @param {string[]} projectIds - IDs dos projetos a fixar
     */
    pinForCompare(projectIds) {
        this.pinnedForCompare = new Set(projectIds || []);
    }

    /**
     * Retorna o número de frustums em cache.
     * @returns {number}
     */
    get size() {
        return this.frustumsByProject.size;
    }
}
