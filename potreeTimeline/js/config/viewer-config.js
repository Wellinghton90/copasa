/**
 * Configuração do viewer (lida de window.NUVEM_CONFIG injetado pelo PHP).
 * URLs de projeto, constantes compartilhadas e carregamento de offset Pix4D.
 * 
 * Mantém funções de compatibilidade que delegam para ConfigService.
 */

import { ConfigService, PHOTO_LIMITS } from './ConfigService.js';

// Instância singleton para compatibilidade
let _defaultConfigService = null;

/**
 * Obtém a instância padrão do ConfigService.
 * @private
 */
function getDefaultConfigService() {
    if (!_defaultConfigService) {
        _defaultConfigService = new ConfigService();
    }
    return _defaultConfigService;
}

/**
 * Retorna a configuração completa.
 * @returns {object} Objeto de configuração
 */
export function getConfig() {
    return getDefaultConfigService().getConfig();
}

/** Limites progressivos para "Carregar mais fotos": 4 → 10 → 20 → 50 → 100 */
export { PHOTO_LIMITS };

/**
 * Retorna a URL do arquivo cloud.js de um projeto.
 * @param {string} projectId - ID do projeto
 * @returns {string} URL do arquivo cloud.js
 */
export function getCloudJsUrl(projectId) {
    return getDefaultConfigService().getCloudJsUrl(projectId);
}

/**
 * Retorna a URL do arquivo de parâmetros de câmera de um projeto.
 * @param {string} projectId - ID do projeto
 * @returns {string} URL do arquivo de parâmetros
 */
export function getCameraParamsUrl(projectId) {
    return getDefaultConfigService().getCameraParamsUrl(projectId);
}

/**
 * Retorna a URL do arquivo offset.xyz de um projeto.
 * @param {string} projectId - ID do projeto
 * @returns {string} URL do arquivo offset.xyz
 */
export function getOffsetXyzUrl(projectId) {
    return getDefaultConfigService().getOffsetXyzUrl(projectId);
}

/**
 * Carrega o offset Pix4D (offset.xyz) do projeto. Retorna [x,y,z] ou null.
 * Formato do arquivo: uma linha "x y z".
 * @param {string} projectId - ID do projeto
 * @returns {Promise<[number, number, number]|null>}
 */
export async function loadPix4dOffset(projectId) {
    return getDefaultConfigService().loadPix4dOffset(projectId);
}

// Exporta a classe também
export { ConfigService };
