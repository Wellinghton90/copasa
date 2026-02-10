/**
 * Gerenciamento de dados das câmeras
 * Armazena informações das câmeras para acesso posterior
 */

// Armazenar informações das câmeras para acesso posterior
window.cameraData = [];

/**
 * Adiciona dados de uma câmera ao armazenamento global
 * @param {Object} cameraInfo - Informações da câmera
 * @param {string} cameraInfo.name - Nome da câmera
 * @param {THREE.Vector3} cameraInfo.position - Posição da câmera
 * @param {THREE.Quaternion} cameraInfo.quaternion - Rotação da câmera
 * @param {string} cameraInfo.imagePath - Caminho da imagem
 */
export function addCameraData(cameraInfo) {
    window.cameraData.push(cameraInfo);
}

/**
 * Limpa todos os dados das câmeras
 */
export function clearCameraData() {
    window.cameraData = [];
}

/**
 * Busca dados de uma câmera pelo nome
 * @param {string} cameraName - Nome da câmera
 * @returns {Object|null} Dados da câmera ou null se não encontrada
 */
export function getCameraData(cameraName) {
    return window.cameraData.find(cam => cam.name === cameraName) || null;
}

/**
 * Retorna todos os dados das câmeras
 * @returns {Array} Array com todos os dados das câmeras
 */
export function getAllCameraData() {
    return window.cameraData;
}
