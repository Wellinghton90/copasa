/**
 * Cache LRU de nuvens de pontos (mantém no máximo maxSize nuvens em memória).
 * Usado para troca instantânea entre as últimas nuvens visualizadas.
 */

/**
 * Remove um pointcloud da cena do viewer e libera recursos.
 * @param {object} viewer - Instância Potree.Viewer
 * @param {object} pointcloud - Pointcloud a remover
 */
function removeFromScene(viewer, pointcloud) {
    if (!pointcloud || !viewer) return;
    const scene = viewer.scene;
    scene.scenePointCloud.remove(pointcloud);
    const idx = scene.pointclouds.indexOf(pointcloud);
    if (idx !== -1) scene.pointclouds.splice(idx, 1);
    if (typeof pointcloud.dispose === 'function') pointcloud.dispose();
}

/**
 * Cria um cache LRU de nuvens.
 * @param {object} viewer - Instância Potree.Viewer
 * @param {number} maxSize - Número máximo de nuvens em cache (ex.: 3)
 * @returns {{ add: Function, get: Function, has: Function, evictLRU: Function, touch: Function, size: number }}
 */
export function createCloudCache(viewer, maxSize) {
    const pointcloudsByProject = new Map();
    const lruOrder = [];

    function touch(projectId) {
        const i = lruOrder.indexOf(projectId);
        if (i !== -1) lruOrder.splice(i, 1);
        lruOrder.push(projectId);
    }

    function evictLRU() {
        if (lruOrder.length === 0) return;
        const projectId = lruOrder.shift();
        const pointcloud = pointcloudsByProject.get(projectId);
        if (pointcloud) {
            removeFromScene(viewer, pointcloud);
            pointcloudsByProject.delete(projectId);
        }
    }

    return {
        add(projectId, pointcloud) {
            pointcloudsByProject.set(projectId, pointcloud);
            lruOrder.push(projectId);
        },
        get(projectId) {
            return pointcloudsByProject.get(projectId);
        },
        has(projectId) {
            return pointcloudsByProject.has(projectId);
        },
        evictLRU,
        touch,
        get size() {
            return pointcloudsByProject.size;
        }
    };
}
