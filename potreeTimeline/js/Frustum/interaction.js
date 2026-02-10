/**
 * Sistema de interação com frustums
 * Gerencia cliques e hover nos frustums das câmeras
 */

import * as THREE from "../../potree/libs/three.js/build/three.module.js";
import { getClickableFrustums } from "./frustumRenderer.js";
import { getCameraData } from "./cameraData.js";
import { showCameraImage } from "./imageModal.js";
import { getImageUrlForProject, generateImagePath } from "./imagePath.js";

let raycaster = null;
let mouse = null;
let hoveredFrustum = null;

/** Posição do pointer no mousedown; usado para ignorar "clique" após arrastar a câmera. */
let pointerDownAt = null;
const DRAG_THRESHOLD_PX = 6;

/**
 * Inicializa o sistema de interação
 */
export function initializeInteraction() {
    raycaster = new THREE.Raycaster();
    mouse = new THREE.Vector2();
    
    if (window.viewer && window.viewer.renderArea) {
        const el = window.viewer.renderArea;
        el.addEventListener('mousedown', onPointerDown);
        el.addEventListener('click', onFrustumClick);
        el.addEventListener('mousemove', onMouseMove);
    } else {
        console.warn('⚠️ Viewer ou renderArea não encontrado para interação');
    }
}

/**
 * Remove os event listeners de interação
 */
export function cleanupInteraction() {
    if (window.viewer && window.viewer.renderArea) {
        const el = window.viewer.renderArea;
        el.removeEventListener('mousedown', onPointerDown);
        el.removeEventListener('click', onFrustumClick);
        el.removeEventListener('mousemove', onMouseMove);
    }
}

/**
 * Guarda a posição do pointer no mousedown para distinguir clique de arraste.
 */
function onPointerDown(event) {
    pointerDownAt = { x: event.clientX, y: event.clientY };
}

/**
 * Manipula o movimento do mouse para detectar hover
 * @param {MouseEvent} event - Evento de movimento do mouse
 */
function onMouseMove(event) {
    if (!window.viewer || !window.viewer.renderArea) return;
    
    // Calcular posição do mouse normalizada
    const rect = window.viewer.renderArea.getBoundingClientRect();
    mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
    mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
    
    // Configurar raycaster
    raycaster.setFromCamera(mouse, window.viewer.scene.getActiveCamera());
    
    // Verificar interseção com frustums
    const frustums = getClickableFrustums();
    const intersects = raycaster.intersectObjects(frustums, true);
    
    // Resetar frustum anterior se existir
    if (hoveredFrustum) {
        hoveredFrustum.material.opacity = 0.3;
        window.viewer.renderArea.style.cursor = 'default';
    }
    
    // Destacar frustum atual
    if (intersects.length > 0) {
        hoveredFrustum = intersects[0].object;
        hoveredFrustum.material.opacity = 0.7;
        window.viewer.renderArea.style.cursor = 'pointer';
    } else {
        hoveredFrustum = null;
    }
    
    window.viewer.render();
}

/**
 * Manipula cliques nos frustums. Só abre a foto se não houve arraste (movimento da câmera).
 */
function onFrustumClick(event) {
    if (!window.viewer || !window.viewer.renderArea) return;

    const movedTooMuch = pointerDownAt &&
        (Math.abs(event.clientX - pointerDownAt.x) > DRAG_THRESHOLD_PX ||
         Math.abs(event.clientY - pointerDownAt.y) > DRAG_THRESHOLD_PX);
    pointerDownAt = null;
    if (movedTooMuch) return;

    const rect = window.viewer.renderArea.getBoundingClientRect();
    mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
    mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

    raycaster.setFromCamera(mouse, window.viewer.scene.getActiveCamera());
    const frustums = getClickableFrustums();
    const intersects = raycaster.intersectObjects(frustums, true);

    if (intersects.length > 0) {
        const clickedObject = intersects[0].object;
        // console.log('🖱️ Clique detectado no objeto:', clickedObject);
        // console.log('📋 userData do objeto:', clickedObject.userData);
        
        // Procurar userData na hierarquia (objeto atual ou parent)
        let cameraName = null;
        let currentObject = clickedObject;
        
        while (currentObject && !cameraName) {
            if (currentObject.userData && currentObject.userData.cameraName) {
                cameraName = currentObject.userData.cameraName;
                // console.log('📷 Nome da câmera encontrado em:', currentObject.type, '-> Nome:', cameraName);
                break;
            }
            currentObject = currentObject.parent;
        }
        
        if (!cameraName) {
            console.warn('⚠️ cameraName não encontrado na hierarquia do objeto clicado');
            return;
        }
        
        // Encontrar dados da câmera correspondente
        const cameraInfo = getCameraData(cameraName);
        // console.log('ℹ️ Dados da câmera encontrados:', cameraInfo);
        
        if (cameraInfo) {
            const imagePath = cameraInfo.projectId
                ? getImageUrlForProject(cameraInfo.projectId, cameraInfo.name + ".JPG")
                : generateImagePath(cameraName);
            const cameraInfoWithImage = { ...cameraInfo, imagePath };

            const mode = window.comparePhotosFrustumPickMode;
            if (mode?.active && typeof window.onFrustumClickAddToCompareSlot === "function") {
                window.onFrustumClickAddToCompareSlot(cameraInfoWithImage, mode.slotIndex);
                return;
            }

            showCameraImage(cameraInfoWithImage);
        } else {
            console.warn('⚠️ Dados da câmera não encontrados:', cameraName);
            console.log('📊 Dados de câmeras disponíveis:', window.cameraData);
        }
    }
}
