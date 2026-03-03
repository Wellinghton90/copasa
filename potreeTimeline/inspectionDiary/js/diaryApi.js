/**
 * API do Diário de fiscalização: GET (ler) e POST (gravar) JSON por usuário.
 */

const DEFAULT_API_BASE = 'inspectionDiary/php';

/**
 * Retorna a URL base da API (configurável via window.INSPECTION_DIARY_API_BASE).
 * @returns {string}
 */
function getApiBase() {
    if (typeof window !== 'undefined' && window.INSPECTION_DIARY_API_BASE) {
        return window.INSPECTION_DIARY_API_BASE;
    }
    return DEFAULT_API_BASE;
}

/**
 * Carrega os dados do diário do usuário.
 * @param {string} userId - Identificador do usuário
 * @returns {Promise<{ user: string, entries: Array }>}
 */
export async function get(userId) {
    const url = `${getApiBase()}/api.php?user=${encodeURIComponent(userId)}`;
    const res = await fetch(url);
    if (!res.ok) {
        throw new Error(`Diário: falha ao carregar (${res.status})`);
    }
    const data = await res.json();
    if (data.error) {
        throw new Error(data.error);
    }
    return {
        user: data.user || userId,
        entries: Array.isArray(data.entries) ? data.entries : []
    };
}

/**
 * Salva os dados do diário do usuário.
 * @param {string} userId - Identificador do usuário
 * @param {{ user: string, entries: Array }} payload - Objeto com user e entries
 * @returns {Promise<void>}
 */
export async function save(userId, payload) {
    const url = `${getApiBase()}/api.php?user=${encodeURIComponent(userId)}`;
    const body = {
        user: payload.user ?? userId,
        entries: Array.isArray(payload.entries) ? payload.entries : []
    };
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    });
    if (!res.ok) {
        throw new Error(`Diário: falha ao salvar (${res.status})`);
    }
    const data = await res.json();
    if (data.error) {
        throw new Error(data.error);
    }
}
