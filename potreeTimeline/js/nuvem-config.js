/**
 * Configuração da página de nuvem (lida de window.NUVEM_CONFIG injetado por nuvem.php).
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
