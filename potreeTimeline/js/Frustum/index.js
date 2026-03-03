/**
 * Módulo principal de Frustums
 * Exporta classes e funções para gerenciar frustums de câmera.
 */

// Classes
export { FrustumManager } from './FrustumManager.js';
export { CameraDataStore } from './CameraDataStore.js';
export { FrustumInteraction } from './FrustumInteraction.js';

// Funções puras
export * from './frustumRenderer.js';
export * from './cameraLoader.js';
export * from './imageModal.js';
export * from './imagePath.js';

// Compatibilidade: mantém função antiga para código legado
import * as THREE from "../../potree/libs/three.js/build/three.module.js";
import { CameraDataStore } from "./CameraDataStore.js";
import { FrustumInteraction } from "./FrustumInteraction.js";
import { createCameraFrustum, addFrustumToScene, clearAllFrustums } from "./frustumRenderer.js";
import { loadExternalCameraParameters } from "./cameraLoader.js";

// Instâncias globais para compatibilidade
let globalCameraDataStore = null;
let globalFrustumInteraction = null;

/**
 * Carrega frustums de câmera de forma assíncrona e desenha na cena.
 * Posição do frustum = cam - pix4dOffset + manualOffset
 * (pix4d do .xyz sempre aplicado; manual do JSON/UI mantém alinhamento com a nuvem).
 * @param {string} filePath - URL do arquivo de parâmetros
 * @param {[number, number, number]} pix4dOffset - Offset Pix4D [x, y, z] (offset.xyz; subtraído da posição da câmera)
 * @param {[number, number, number]} [manualOffset] - Offset manual (JSON/UI); somado para alinhar com a nuvem
 * @param {string} projectId - ID do projeto (para clique abrir imagem)
 * @param {number} limit - 0 = todas as câmeras
 * @param {object} [frustumCache] - Cache opcional de frustums (FrustumCache)
 * @returns {Promise<{frustums: Array, cameraDataStore: CameraDataStore}>} Objetos criados para cache
 */
export async function loadCameraFrustumsAsync(filePath, pix4dOffset, manualOffset, projectId = "", limit = 0, frustumCache = null) {
    if (!filePath || filePath.endsWith("/")) {
        console.warn("⚠️ Arquivo de câmera não informado ou é pasta");
        return null;
    }
    
    // Inicializa instâncias globais se necessário
    if (!globalCameraDataStore) {
        globalCameraDataStore = new CameraDataStore();
        // Mantém compatibilidade com window.cameraData
        Object.defineProperty(window, 'cameraData', {
            get: () => globalCameraDataStore.getAll(),
            configurable: true
        });
    }
    
    // Expõe cameraDataStore globalmente para o cache poder restaurar
    if (!window.cameraDataStore) {
        window.cameraDataStore = globalCameraDataStore;
    }
    
    if (!globalFrustumInteraction && window.viewer) {
        globalFrustumInteraction = new FrustumInteraction(window.viewer, globalCameraDataStore);
        globalFrustumInteraction.init();
    }
    
    const pix4dV = Array.isArray(pix4dOffset) && pix4dOffset.length >= 3
        ? new THREE.Vector3(pix4dOffset[0], pix4dOffset[1], pix4dOffset[2])
        : new THREE.Vector3(0, 0, 0);
    const manualV = Array.isArray(manualOffset) && manualOffset.length >= 3
        ? new THREE.Vector3(manualOffset[0], manualOffset[1], manualOffset[2])
        : new THREE.Vector3(0, 0, 0);
    
    try {
        const cameras = await loadExternalCameraParameters(filePath, limit);
        
        // Cria um novo CameraDataStore para este projeto (para cache)
        const projectCameraDataStore = new CameraDataStore();
        const frustums = [];
        
        globalCameraDataStore.clear();
        clearAllFrustums();

        for (const cam of cameras) {
            const frustumPosition = new THREE.Vector3(
                cam.position.x - pix4dV.x + manualV.x,
                cam.position.y - pix4dV.y + manualV.y,
                cam.position.z - pix4dV.z + manualV.z
            );
            
            const cameraData = {
                name: cam.name,
                position: frustumPosition,
                quaternion: cam.quaternion,
                projectId: projectId,
                imagePath: null
            };
            
            // Adiciona ao store global (para compatibilidade)
            globalCameraDataStore.add(cameraData);
            // Adiciona ao store do projeto (para cache)
            projectCameraDataStore.add(cameraData);
            
            const frustumHelper = createCameraFrustum(frustumPosition, cam.quaternion, cam.name);
            addFrustumToScene(frustumHelper);
            frustums.push(frustumHelper);
        }

        // Inicializa interação se ainda não foi inicializada
        if (!globalFrustumInteraction && window.viewer) {
            globalFrustumInteraction = new FrustumInteraction(window.viewer, globalCameraDataStore);
            globalFrustumInteraction.init();
        }
        
        console.log("✅ Frustums carregados:", cameras.length);

        if (window.viewer && typeof window.viewer.render === "function") {
            window.viewer.render();
        }
        
        // Retorna objetos para cache
        return {
            frustums: frustums,
            cameraDataStore: projectCameraDataStore
        };
    } catch (err) {
        console.error("❌ Erro ao carregar frustums Pix4D:", err.message);
        return null;
    }
}
