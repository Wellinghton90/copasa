/**
 * Renderizador de frustums
 * Cria e gerencia os frustums das câmeras no Three.js
 */

import * as THREE from "../../potree/libs/three.js/build/three.module.js";

/** Tamanho do frustum (distância far). Proporcional à cena (~50 u; nuvem–frustum ~186 u). */
const FRUSTUM_FAR_SCALE = 1;

/**
 * Cria a geometria do frustum (apontando para +Z, igual ao CameraFrustumHelper)
 */
function createFrustumHelperMesh(fov = 60, aspect = 1.5, near = 1, far = 50) {
    const tan = Math.tan((fov / 2) * Math.PI / 180);
    const h = tan * far;
    const w = h * aspect;
    const origin = new THREE.Vector3(0, 0, 0);
    const corners = [
        new THREE.Vector3(-w, -h, far),
        new THREE.Vector3(w, -h, far),
        new THREE.Vector3(w, h, far),
        new THREE.Vector3(-w, h, far)
    ];
    const vertices = [
        ...origin.toArray(), ...corners[0].toArray(), ...corners[1].toArray(),
        ...origin.toArray(), ...corners[1].toArray(), ...corners[2].toArray(),
        ...origin.toArray(), ...corners[2].toArray(), ...corners[3].toArray(),
        ...origin.toArray(), ...corners[3].toArray(), ...corners[0].toArray(),
        ...corners[0].toArray(), ...corners[1].toArray(), ...corners[2].toArray(),
        ...corners[2].toArray(), ...corners[3].toArray(), ...corners[0].toArray()
    ];
    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute("position", new THREE.Float32BufferAttribute(vertices, 3));
    geometry.computeVertexNormals();
    const solidMaterial = new THREE.MeshBasicMaterial({
        color: 0x00FFFF,
        transparent: true,
        opacity: 0.35,
        side: THREE.DoubleSide,
        depthWrite: false,
        depthTest: true
    });
    const solidMesh = new THREE.Mesh(geometry, solidMaterial);
    const wireframeMaterial = new THREE.LineBasicMaterial({
        color: 0x00FFFF,
        transparent: true,
        opacity: 0.85,
        depthTest: true
    });
    const edges = new THREE.EdgesGeometry(geometry);
    const wireframe = new THREE.LineSegments(edges, wireframeMaterial);
    const group = new THREE.Group();
    group.add(solidMesh);
    group.add(wireframe);
    // Desenhar frustums após a nuvem para ficarem visíveis (evitar oclusão pela nuvem)
    // Usar renderOrder maior para garantir que frustums sejam renderizados por cima
    group.renderOrder = 1000;
    solidMesh.renderOrder = 1000;
    wireframe.renderOrder = 1000;
    // Garantir que frustums sejam renderizados mesmo quando há sobreposição
    solidMaterial.depthTest = true;
    solidMaterial.depthWrite = false;
    wireframeMaterial.depthTest = true;
    return group;
}

/**
 * Cria um frustum de câmera a partir de posição e rotação
 * @param {THREE.Vector3} position - Posição do frustum
 * @param {THREE.Quaternion} quaternion - Rotação do frustum
 * @param {string} cameraName - Nome da câmera
 * @param {number} fov - Campo de visão (padrão: 50)
 * @param {number} aspect - Proporção (padrão: 1.5)
 * @param {number} near - Plano próximo (padrão: 1)
 * @param {number} far - Plano distante (padrão: 50)
 * @returns {THREE.Object3D} Helper do frustum criado
 */
export function createCameraFrustum(position, quaternion, cameraName, fov = 60, aspect = 1.5, near = 1, far = FRUSTUM_FAR_SCALE) {
    const helper = createFrustumHelperMesh(fov, aspect, near, far);
    helper.quaternion.copy(quaternion);
    helper.position.copy(position);
    
    // Adicionar propriedades customizadas para identificação
    helper.userData = {
        cameraName: cameraName,
        isClickable: true
    };
    
    // console.log('🔧 Frustum criado para:', cameraName, 'com userData:', helper.userData);
    
    // Verificar se o userData foi definido corretamente
    if (!helper.userData.cameraName) {
        console.error('❌ ERRO: cameraName não foi definido no userData!');
    }
    
    return helper;
}

/**
 * Garante que a cena Potree tenha o grupo de frustums e retorna-o.
 * Os frustums são adicionados a scene.scene (cena principal) para serem desenhados
 * pelo renderer Three.js padrão; o pRenderer do Potree só desenha point clouds e ignora meshes.
 * @returns {THREE.Group|null}
 */
function ensureCameraFrustumsGroup() {
    if (!window.viewer || !window.viewer.scene) return null;
    const scene = window.viewer.scene;
    if (!scene.cameraFrustumsGroup) {
        scene.cameraFrustumsGroup = new THREE.Group();
        scene.cameraFrustumsGroup.name = "cameraFrustums";
        scene.cameraFrustumsGroup.renderOrder = 1000;
        scene.scene.add(scene.cameraFrustumsGroup);
    } else if (scene.cameraFrustumsGroup.parent !== scene.scene) {
        if (scene.cameraFrustumsGroup.parent) scene.cameraFrustumsGroup.parent.remove(scene.cameraFrustumsGroup);
        scene.scene.add(scene.cameraFrustumsGroup);
    }
    return scene.cameraFrustumsGroup;
}

/**
 * Adiciona um frustum ao grupo de frustums da cena
 * @param {THREE.Object3D} frustumHelper - Helper do frustum
 */
export function addFrustumToScene(frustumHelper) {
    const group = ensureCameraFrustumsGroup();
    if (group) group.add(frustumHelper);
}

/**
 * Remove um frustum do grupo de frustums da cena
 * @param {Object} frustumHelper - Helper do frustum
 */
export function removeFrustumFromScene(frustumHelper) {
    if (window.viewer && window.viewer.scene && window.viewer.scene.cameraFrustumsGroup) {
        window.viewer.scene.cameraFrustumsGroup.remove(frustumHelper);
    }
}

/**
 * Limpa todos os frustums da cena
 */
export function clearAllFrustums() {
    const group = window.viewer && window.viewer.scene && window.viewer.scene.cameraFrustumsGroup;
    if (group) {
        while (group.children.length > 0) group.remove(group.children[0]);
    }
}

/**
 * Aplica um deslocamento (delta) a todos os frustums e aos dados de câmera.
 * Usado quando o offset manual da nuvem é alterado na UI.
 * @param {number} dx - Delta em X
 * @param {number} dy - Delta em Y
 * @param {number} dz - Delta em Z
 */
export function applyOffsetDeltaToFrustums(dx, dy, dz) {
    const group = window.viewer && window.viewer.scene && window.viewer.scene.cameraFrustumsGroup;
    if (group) {
        group.traverse((child) => {
            if (child.userData && child.userData.isClickable) {
                child.position.x += dx;
                child.position.y += dy;
                child.position.z += dz;
            }
        });
    }
    if (window.cameraData && Array.isArray(window.cameraData)) {
        for (const cam of window.cameraData) {
            if (cam.position) {
                cam.position.x += dx;
                cam.position.y += dy;
                cam.position.z += dz;
            }
        }
    }
}

/**
 * Obtém todos os frustums clicáveis da cena
 * @returns {Array} Array com todos os frustums clicáveis
 */
export function getClickableFrustums() {
    const frustums = [];
    if (window.viewer && window.viewer.scene && window.viewer.scene.cameraFrustumsGroup) {
        window.viewer.scene.cameraFrustumsGroup.traverse((child) => {
            // console.log('🔍 Verificando child:', child.type, 'userData:', child.userData);
            if (child.userData && child.userData.isClickable) {
                // console.log('✅ Frustum clicável encontrado:', child.userData.cameraName);
                frustums.push(child);
            }
        });
    }
    // console.log('📊 Total de frustums clicáveis:', frustums.length); // Removido para evitar spam no mousemove
    return frustums;
}
