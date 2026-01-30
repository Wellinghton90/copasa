/**
 * Offset por projeto (editado em memória; ao carregar nuvem usa o valor do JSON ou o editado).
 */

const editedOffsets = {};

/**
 * @param {string} projectId
 * @param {Array<{ id: string, offset?: number[] }>} projetosDisponiveis
 * @returns {[number, number, number]}
 */
export function getOffsetForProject(projectId, projetosDisponiveis) {
    if (editedOffsets[projectId]) return editedOffsets[projectId];
    const projeto = projetosDisponiveis.find((p) => p.id === projectId);
    return (projeto && Array.isArray(projeto.offset) && projeto.offset.length >= 3)
        ? projeto.offset
        : [0, 0, 0];
}

/**
 * @param {string} projectId
 * @param {[number, number, number]} offset
 */
export function setOffsetForProject(projectId, offset) {
    editedOffsets[projectId] = offset;
}
