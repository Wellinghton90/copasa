/**
 * Offset por projeto (editado em memória; ao carregar nuvem usa o valor do JSON ou o editado).
 */

const editedOffsets = {};

/**
 * @param {string} projectId
 * @param {Array<{ id: string, offset?: number[] }>} availableProjects
 * @returns {[number, number, number]}
 */
export function getOffsetForProject(projectId, availableProjects) {
    if (editedOffsets[projectId]) return editedOffsets[projectId];
    const project = availableProjects.find((p) => p.id === projectId);
    return (project && Array.isArray(project.offset) && project.offset.length >= 3)
        ? project.offset
        : [0, 0, 0];
}

/**
 * @param {string} projectId
 * @param {[number, number, number]} offset
 */
export function setOffsetForProject(projectId, offset) {
    editedOffsets[projectId] = offset;
}
