/**
 * Armazenamento de dados das câmeras.
 * Gerencia informações das câmeras para acesso posterior.
 * 
 * @class CameraDataStore
 */
export class CameraDataStore {
    /**
     * Cria uma instância de CameraDataStore.
     */
    constructor() {
        /** Array com dados de todas as câmeras */
        this.cameras = [];
    }

    /**
     * Adiciona dados de uma câmera ao armazenamento.
     * @param {Object} cameraInfo - Informações da câmera
     * @param {string} cameraInfo.name - Nome da câmera
     * @param {THREE.Vector3} cameraInfo.position - Posição da câmera
     * @param {THREE.Quaternion} cameraInfo.quaternion - Rotação da câmera
     * @param {string} [cameraInfo.imagePath] - Caminho da imagem
     * @param {string} [cameraInfo.projectId] - ID do projeto
     */
    add(cameraInfo) {
        this.cameras.push(cameraInfo);
    }

    /**
     * Limpa todos os dados das câmeras.
     */
    clear() {
        this.cameras = [];
    }

    /**
     * Busca dados de uma câmera pelo nome.
     * @param {string} cameraName - Nome da câmera
     * @returns {Object|null} Dados da câmera ou null se não encontrada
     */
    get(cameraName) {
        return this.cameras.find(cam => cam.name === cameraName) || null;
    }

    /**
     * Retorna todos os dados das câmeras.
     * @returns {Array} Array com todos os dados das câmeras
     */
    getAll() {
        return this.cameras;
    }

    /**
     * Retorna o número de câmeras armazenadas.
     * @returns {number}
     */
    get size() {
        return this.cameras.length;
    }
}
