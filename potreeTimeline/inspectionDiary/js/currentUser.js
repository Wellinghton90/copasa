/**
 * Ponto único para obter o usuário atual do Diário de fiscalização.
 * Por enquanto retorna "default"; no futuro ler window.INSPECTION_DIARY_USER (injetado pelo PHP/sessão).
 * @returns {string} Identificador do usuário atual
 */
export function getCurrentUserId() {
    if (typeof window !== 'undefined' && window.INSPECTION_DIARY_USER != null) {
        return String(window.INSPECTION_DIARY_USER);
    }
    return 'MatheusPrates';
}

/**
 * Retorna o cargo do usuário: admin (editar e excluir), editor (só editar), leitor (só visualizar).
 * @returns {'admin'|'editor'|'leitor'}
 */
export function getCurrentUserRole() {
    const role = (typeof window !== 'undefined' && window.INSPECTION_DIARY_USER_ROLE)
        ? String(window.INSPECTION_DIARY_USER_ROLE).toLowerCase()
        : 'leitor';
    if (role === 'admin' || role === 'editor' || role === 'leitor') return role;
    return 'leitor';
}

/**
 * Retorna nome para tooltip e iniciais para o badge.
 * Iniciais: primeiro e último nome → uma letra de cada (ex.: "Matheus Prates" → "MP");
 * um só nome → duas primeiras letras (ex.: "Matheus" → "MA").
 * @returns {{ display: string, initials: string }}
 */
export function getCurrentUserDisplay() {
    const display = (typeof window !== 'undefined' && window.INSPECTION_DIARY_USER_DISPLAY)
        ? String(window.INSPECTION_DIARY_USER_DISPLAY).trim()
        : '';
    const userId = getCurrentUserId();
    if (display) {
        const parts = display.split(/\s+/).filter(Boolean);
        const initials = parts.length >= 2
            ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
            : (display.slice(0, 2)).toUpperCase();
        return { display, initials: initials.slice(0, 2) };
    }
    const initials = (userId.slice(0, 2)).toUpperCase() || '?';
    return { display: userId, initials };
}
