/**
 * Serviço de configuração do viewer.
 * Gerencia configurações lidas de window.NUVEM_CONFIG injetado pelo PHP.
 * 
 * @class ConfigService
 */

/** Limites progressivos para "Carregar mais fotos": 4 → 10 → 20 → 50 → 100 */
export const PHOTO_LIMITS = [4, 10, 20, 50, 100];

export class ConfigService {
    /**
     * Cria uma instância de ConfigService.
     * @param {object} [rawConfig] - Configuração raw (default: window.NUVEM_CONFIG)
     */
    constructor(rawConfig = null) {
        this._rawConfig = rawConfig || (typeof window !== 'undefined' ? window.NUVEM_CONFIG : {});
        this._config = null;
        this._loadConfig();
    }

    /**
     * Carrega e normaliza a configuração.
     * @private
     */
    _loadConfig() {
        const raw = this._rawConfig || {};
        this._config = {
            projetosDisponiveis: raw.projetosDisponiveis || [],
            projetoInicial: raw.projetoInicial || '',
            baseProjetos: raw.baseProjetos || 'projetos',
            suffixPotree: raw.suffixPotree || 'potree',
            obra: raw.obra || '',
            developerMode: !!raw.developerMode,
            /** Máximo de nuvens em memória; ao exceder, a menos usada é descarregada (LRU). */
            maxCachedClouds: raw.maxCachedClouds || 3
        };
    }

    /**
     * Retorna a configuração completa.
     * @returns {object} Objeto de configuração
     */
    getConfig() {
        return { ...this._config };
    }

    /**
     * Retorna um valor específico da configuração.
     * @param {string} key - Chave da configuração
     * @returns {*} Valor da configuração
     */
    get(key) {
        return this._config[key];
    }

    /**
     * Retorna a URL do arquivo cloud.js de um projeto.
     * @param {string} projectId - ID do projeto
     * @returns {string} URL do arquivo cloud.js
     */
    getCloudJsUrl(projectId) {
        const { baseProjetos, suffixPotree } = this._config;
        return `${baseProjetos}/${projectId}/${suffixPotree}/cloud.js`;
    }

    /**
     * Retorna a URL do arquivo de parâmetros de câmera de um projeto.
     * @param {string} projectId - ID do projeto
     * @returns {string} URL do arquivo de parâmetros
     */
    getCameraParamsUrl(projectId) {
        const { baseProjetos } = this._config;
        return `${baseProjetos}/${projectId}/1_initial/params/${projectId}_calibrated_external_camera_parameters.txt`;
    }

    /**
     * Retorna a URL do arquivo offset.xyz de um projeto.
     * @param {string} projectId - ID do projeto
     * @returns {string} URL do arquivo offset.xyz
     */
    getOffsetXyzUrl(projectId) {
        const { baseProjetos } = this._config;
        return `${baseProjetos}/${projectId}/1_initial/params/${projectId}_offset.xyz`;
    }

    /**
     * Carrega o offset Pix4D (offset.xyz) do projeto. Retorna [x,y,z] ou null.
     * Formato do arquivo: uma linha "x y z".
     * @param {string} projectId - ID do projeto
     * @returns {Promise<[number, number, number]|null>}
     */
    async loadPix4dOffset(projectId) {
        const url = this.getOffsetXyzUrl(projectId);
        try {
            const res = await fetch(url);
            if (!res.ok) return null;
            const text = await res.text();
            const parts = text.trim().split(/\s+/);
            if (parts.length < 3) return null;
            return [
                parseFloat(parts[0]),
                parseFloat(parts[1]),
                parseFloat(parts[2])
            ];
        } catch (_) {
            return null;
        }
    }
}
