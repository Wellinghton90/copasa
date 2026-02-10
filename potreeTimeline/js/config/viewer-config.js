/**
 * Configuração do viewer (lida de window.NUVEM_CONFIG injetado pelo PHP).
 * URLs de projeto, constantes compartilhadas e carregamento de offset Pix4D.
 */

export function getConfig() {
    const raw = window.NUVEM_CONFIG || {};
    return {
        projetosDisponiveis: raw.projetosDisponiveis || [],
        projetoInicial: raw.projetoInicial || '',
        baseProjetos: raw.baseProjetos || 'projetos',
        suffixPotree: raw.suffixPotree || 'potree',
        obra: raw.obra || '',
        developerMode: !!raw.developerMode
    };
}

/** Limites progressivos para "Carregar mais fotos": 4 → 10 → 20 → 50 → 100 */
export const PHOTO_LIMITS = [4, 10, 20, 50, 100];

/**
 * @param {string} projectId
 * @returns {string}
 */
export function getCloudJsUrl(projectId) {
    const { baseProjetos, suffixPotree } = getConfig();
    return `${baseProjetos}/${projectId}/${suffixPotree}/cloud.js`;
}

/**
 * @param {string} projectId
 * @returns {string}
 */
export function getCameraParamsUrl(projectId) {
    const { baseProjetos } = getConfig();
    return `${baseProjetos}/${projectId}/1_initial/params/${projectId}_calibrated_external_camera_parameters.txt`;
}

/**
 * @param {string} projectId
 * @returns {string}
 */
export function getOffsetXyzUrl(projectId) {
    const { baseProjetos } = getConfig();
    return `${baseProjetos}/${projectId}/1_initial/params/${projectId}_offset.xyz`;
}

/**
 * Carrega o offset Pix4D (offset.xyz) do projeto. Retorna [x,y,z] ou null.
 * Formato do arquivo: uma linha "x y z".
 * @param {string} projectId
 * @returns {Promise<[number, number, number]|null>}
 */
export async function loadPix4dOffset(projectId) {
    const url = getOffsetXyzUrl(projectId);
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
