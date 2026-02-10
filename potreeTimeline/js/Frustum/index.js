/**
 * Módulo principal de Frustums
 * Exporta loadCameraFrustumsAsync para desenhar frustums e clicar para abrir foto.
 */

import * as THREE from "../../potree/libs/three.js/build/three.module.js";
import { clearCameraData, addCameraData } from "./cameraData.js";
import { createCameraFrustum, addFrustumToScene, clearAllFrustums } from "./frustumRenderer.js";
import { initializeInteraction } from "./interaction.js";
import { loadExternalCameraParameters } from "./cameraLoader.js";

/**
 * Carrega frustums de câmera de forma assíncrona e desenha na cena.
 * Posição do frustum = cam - pix4dOffset + manualOffset
 * (pix4d do .xyz sempre aplicado; manual do JSON/UI mantém alinhamento com a nuvem).
 * @param {string} filePath - URL do arquivo de parâmetros
 * @param {[number, number, number]} pix4dOffset - Offset Pix4D [x, y, z] (offset.xyz; subtraído da posição da câmera)
 * @param {[number, number, number]} [manualOffset] - Offset manual (JSON/UI); somado para alinhar com a nuvem
 * @param {string} projectId - ID do projeto (para clique abrir imagem)
 * @param {number} limit - 0 = todas as câmeras
 */
export async function loadCameraFrustumsAsync(filePath, pix4dOffset, manualOffset, projectId = "", limit = 0) {
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
    await addCameraFrustumsAsync(filePath, pix4dV, manualV, projectId, limit);
}

/**
 * @param {string} filePath
 * @param {THREE.Vector3} pix4dOffset - offset Pix4D (subtraído da posição da câmera)
 * @param {THREE.Vector3} manualOffset - offset manual (somado para alinhar com a nuvem)
 * @param {string} projectId
 * @param {number} limit
 */
async function addCameraFrustumsAsync(filePath, pix4dOffset, manualOffset, projectId, limit = 0) {
    try {
        const cameras = await loadExternalCameraParameters(filePath, limit);
        clearCameraData();
        clearAllFrustums();

        for (const cam of cameras) {
            const frustumPosition = new THREE.Vector3(
                cam.position.x - pix4dOffset.x + manualOffset.x,
                cam.position.y - pix4dOffset.y + manualOffset.y,
                cam.position.z - pix4dOffset.z + manualOffset.z
            );
            addCameraData({
                name: cam.name,
                position: frustumPosition,
                quaternion: cam.quaternion,
                projectId: projectId,
                imagePath: null
            });
            const frustumHelper = createCameraFrustum(frustumPosition, cam.quaternion, cam.name);
            addFrustumToScene(frustumHelper);
        }

        initializeInteraction();
        console.log("✅ Frustums carregados:", cameras.length);

        if (window.viewer && typeof window.viewer.render === "function") {
            window.viewer.render();
        }
    } catch (err) {
        console.error("❌ Erro ao carregar frustums Pix4D:", err.message);
    }
}
