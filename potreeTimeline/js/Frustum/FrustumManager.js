/**
 * Gerenciador de frustums de câmera.
 * Responsável por carregar, renderizar e gerenciar frustums na cena.
 * 
 * @class FrustumManager
 */

import * as THREE from "../../potree/libs/three.js/build/three.module.js";
import { createCameraFrustum, addFrustumToScene, clearAllFrustums, applyOffsetDeltaToFrustums } from "./frustumRenderer.js";
import { loadExternalCameraParameters } from "./cameraLoader.js";

export class FrustumManager {
    /**
     * Cria uma instância de FrustumManager.
     * @param {object} viewer - Instância Potree.Viewer
     * @param {CameraDataStore} cameraDataStore - Armazenamento de dados de câmeras
     * @param {OffsetService} [offsetService] - Serviço de offset (opcional)
     */
    constructor(viewer, cameraDataStore, offsetService = null) {
        this.viewer = viewer;
        this.cameraDataStore = cameraDataStore;
        this.offsetService = offsetService;
    }

    /**
     * Carrega frustums de câmera de forma assíncrona e desenha na cena.
     * Posição do frustum = cam - pix4dOffset + manualOffset
     * (pix4d do .xyz sempre aplicado; manual do JSON/UI mantém alinhamento com a nuvem).
     * 
     * @param {string} filePath - URL do arquivo de parâmetros
     * @param {[number, number, number]} pix4dOffset - Offset Pix4D [x, y, z] (offset.xyz; subtraído da posição da câmera)
     * @param {[number, number, number]} [manualOffset] - Offset manual (JSON/UI); somado para alinhar com a nuvem
     * @param {string} [projectId] - ID do projeto (para clique abrir imagem)
     * @param {number} [limit] - Limite de câmeras (0 = todas)
     */
    async loadFrustums(filePath, pix4dOffset, manualOffset = [0, 0, 0], projectId = "", limit = 0) {
        if (!filePath || filePath.endsWith("/")) {
            console.warn("⚠️ Arquivo de câmera não informado ou é pasta");
            return;
        }

        const pix4dV = Array.isArray(pix4dOffset) && pix4dOffset.length >= 3
            ? new THREE.Vector3(pix4dOffset[0], pix4dOffset[1], pix4dOffset[2])
            : new THREE.Vector3(0, 0, 0);
        
        const manualV = Array.isArray(manualOffset) && manualOffset.length >= 3
            ? new THREE.Vector3(manualOffset[0], manualOffset[1], manualOffset[2])
            : new THREE.Vector3(0, 0, 0);

        try {
            const cameras = await loadExternalCameraParameters(filePath, limit);
            this.cameraDataStore.clear();
            clearAllFrustums();

            for (const cam of cameras) {
                const frustumPosition = new THREE.Vector3(
                    cam.position.x - pix4dV.x + manualV.x,
                    cam.position.y - pix4dV.y + manualV.y,
                    cam.position.z - pix4dV.z + manualV.z
                );
                
                this.cameraDataStore.add({
                    name: cam.name,
                    position: frustumPosition,
                    quaternion: cam.quaternion,
                    projectId: projectId,
                    imagePath: null
                });
                
                const frustumHelper = createCameraFrustum(frustumPosition, cam.quaternion, cam.name);
                addFrustumToScene(frustumHelper);
            }

            console.log("✅ Frustums carregados:", cameras.length);

            if (this.viewer && typeof this.viewer.render === "function") {
                this.viewer.render();
            }
        } catch (err) {
            console.error("❌ Erro ao carregar frustums Pix4D:", err.message);
            throw err;
        }
    }

    /**
     * Limpa todos os frustums da cena e os dados de câmeras.
     */
    clearAll() {
        this.cameraDataStore.clear();
        clearAllFrustums();
        
        if (this.viewer && typeof this.viewer.render === "function") {
            this.viewer.render();
        }
    }

    /**
     * Aplica um deslocamento (delta) a todos os frustums e aos dados de câmera.
     * Usado quando o offset manual da nuvem é alterado na UI.
     * 
     * @param {number} dx - Delta em X
     * @param {number} dy - Delta em Y
     * @param {number} dz - Delta em Z
     */
    applyOffsetDelta(dx, dy, dz) {
        // Atualiza frustums na cena
        applyOffsetDeltaToFrustums(dx, dy, dz);
        
        // Atualiza dados de câmeras no store
        const cameras = this.cameraDataStore.getAll();
        for (const cam of cameras) {
            if (cam.position) {
                cam.position.x += dx;
                cam.position.y += dy;
                cam.position.z += dz;
            }
        }
        
        if (this.viewer && typeof this.viewer.render === "function") {
            this.viewer.render();
        }
    }
}
