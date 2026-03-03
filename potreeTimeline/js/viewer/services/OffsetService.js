/**
 * Serviço de gerenciamento de offset (modo desenvolvedor).
 * Gerencia controles de offset manual para alinhamento de nuvens.
 * 
 * @class OffsetService
 */

import { applyOffsetDeltaToFrustums } from '../../Frustum/frustumRenderer.js';
import { offsetControlsTemplate } from '../templates/offsetControlsTemplate.js';

export class OffsetService {
    /**
     * Cria uma instância de OffsetService.
     * @param {object} configService - Serviço de configuração
     * @param {object} cloudLoader - Instância de CloudLoader (opcional)
     */
    constructor(configService, cloudLoader = null) {
        this.configService = configService;
        this.cloudLoader = cloudLoader;
        this.initialized = false;
        /** Offsets editados em memória por projeto */
        this.editedOffsets = {};
    }

    /**
     * Arredonda um número para um número específico de casas decimais.
     * @param {number} num - Número a arredondar
     * @param {number} decimals - Número de casas decimais (padrão: 2)
     * @returns {number} Número arredondado
     */
    roundOffset(num, decimals = 2) {
        const factor = 10 ** decimals;
        return Math.round(num * factor) / factor;
    }

    /**
     * Obtém o offset de um projeto.
     * Retorna o offset editado se existir, senão retorna o offset do projeto ou [0,0,0].
     * @param {string} projectId - ID do projeto
     * @param {Array} availableProjects - Lista de projetos disponíveis
     * @returns {[number, number, number]} Offset [x, y, z]
     */
    getOffsetForProject(projectId, availableProjects) {
        // Retorna offset editado se existir
        if (this.editedOffsets[projectId]) {
            return this.editedOffsets[projectId];
        }
        
        // Busca offset do projeto na lista
        const project = availableProjects.find((p) => p.id === projectId);
        return (project && Array.isArray(project.offset) && project.offset.length >= 3)
            ? project.offset
            : [0, 0, 0];
    }

    /**
     * Define o offset de um projeto (editado em memória).
     * @param {string} projectId - ID do projeto
     * @param {[number, number, number]} offset - Offset [x, y, z]
     */
    setOffsetForProject(projectId, offset) {
        this.editedOffsets[projectId] = offset;
    }

    /**
     * Atualiza os campos de input de offset com os valores atuais da nuvem.
     * @param {object} pointcloud - Nuvem de pontos atual
     * @param {string} projectId - ID do projeto atual
     * @param {Array} availableProjects - Lista de projetos disponíveis
     */
    updateUI(pointcloud, projectId, availableProjects) {
        const inpX = document.getElementById('offset_x');
        const inpY = document.getElementById('offset_y');
        const inpZ = document.getElementById('offset_z');
        
        if (!inpX || !inpY || !inpZ) {
            return;
        }
        
        if (pointcloud) {
            const initial = pointcloud.userData.initialPosition;
            const p = pointcloud.position;
            inpX.value = this.roundOffset(p.x - (initial ? initial.x : 0));
            inpY.value = this.roundOffset(p.y - (initial ? initial.y : 0));
            inpZ.value = this.roundOffset(p.z - (initial ? initial.z : 0));
        } else {
            inpX.value = inpY.value = inpZ.value = '0';
        }
    }

    /**
     * Aplica o offset a partir dos campos de input da UI.
     * @param {object} pointcloud - Nuvem de pontos atual
     * @param {object} viewer - Instância Potree.Viewer
     * @param {string} projectId - ID do projeto atual
     * @param {Array} availableProjects - Lista de projetos disponíveis
     */
    applyOffsetFromUI(pointcloud, viewer, projectId, availableProjects) {
        if (!pointcloud || !viewer) {
            return;
        }
        
        const inpX = document.getElementById('offset_x');
        const inpY = document.getElementById('offset_y');
        const inpZ = document.getElementById('offset_z');
        
        if (!inpX || !inpY || !inpZ) {
            return;
        }
        
        const initial = pointcloud.userData.initialPosition;
        const oldOx = (initial ? pointcloud.position.x - initial.x : pointcloud.position.x) || 0;
        const oldOy = (initial ? pointcloud.position.y - initial.y : pointcloud.position.y) || 0;
        const oldOz = (initial ? pointcloud.position.z - initial.z : pointcloud.position.z) || 0;
        
        const ox = parseFloat(inpX.value) || 0;
        const oy = parseFloat(inpY.value) || 0;
        const oz = parseFloat(inpZ.value) || 0;
        
        // Aplica novo offset
        pointcloud.position.x = (initial ? initial.x : 0) + ox;
        pointcloud.position.y = (initial ? initial.y : 0) + oy;
        pointcloud.position.z = (initial ? initial.z : 0) + oz;
        
        // Salva offset editado
        if (projectId) {
            this.setOffsetForProject(projectId, [this.roundOffset(ox), this.roundOffset(oy), this.roundOffset(oz)]);
        }
        
        // Aplica delta aos frustums
        const dx = ox - oldOx;
        const dy = oy - oldOy;
        const dz = oz - oldOz;
        
        if (dx !== 0 || dy !== 0 || dz !== 0) {
            applyOffsetDeltaToFrustums(dx, dy, dz);
        }
        
        viewer.render();
    }

    /**
     * Copia o offset atual para a área de transferência em formato JSON.
     * @param {object} pointcloud - Nuvem de pontos atual
     */
    copyOffsetToClipboard(pointcloud) {
        if (!pointcloud) {
            return;
        }
        
        const initial = pointcloud.userData.initialPosition;
        const p = pointcloud.position;
        const x = this.roundOffset(p.x - (initial ? initial.x : 0));
        const y = this.roundOffset(p.y - (initial ? initial.y : 0));
        const z = this.roundOffset(p.z - (initial ? initial.z : 0));
        const json = JSON.stringify([x, y, z]);
        
        navigator.clipboard.writeText(json).then(() => {
            if (typeof console !== 'undefined' && console.log) {
                console.log('Offset copiado: ' + json);
            }
        }).catch((err) => {
            console.warn('Erro ao copiar offset:', err);
        });
    }

    /**
     * Inicializa a UI de offset.
     * @param {object} pointcloud - Nuvem de pontos atual (pode ser null)
     * @param {object} viewer - Instância Potree.Viewer
     * @param {string} projectId - ID do projeto atual
     * @param {Array} availableProjects - Lista de projetos disponíveis
     * @param {{ containerId?: string }} [options] - containerId: id do elemento onde inserir (ex: 'developer_offset_container'); se omitido, usa 'left_panel_fotos'
     */
    initUI(pointcloud, viewer, projectId, availableProjects, options = {}) {
        const containerId = options.containerId || 'left_panel_fotos';
        const container = document.getElementById(containerId);
        if (!container) {
            return;
        }
        
        // Verifica se já foi inicializado
        if (document.getElementById('offset_x')) {
            this.updateUI(pointcloud, projectId, availableProjects);
            return;
        }
        
        const block = document.createElement('div');
        block.className = 'offset-tool offset-tool-visible';
        block.innerHTML = offsetControlsTemplate;
        container.appendChild(block);
        
        const inpX = document.getElementById('offset_x');
        const inpY = document.getElementById('offset_y');
        const inpZ = document.getElementById('offset_z');
        const saveOffsetBtn = document.getElementById('btn_salvar_offset');
        
        // Usa nuvem/projeto atuais (window) para aplicar/copiar, pois init pode ser antes de carregar nuvem
        const applyHandler = () => {
            const pc = typeof window.currentPointcloud !== 'undefined' ? window.currentPointcloud : pointcloud;
            const pid = typeof window.currentProjectId !== 'undefined' ? window.currentProjectId : projectId;
            if (pc && viewer) {
                this.applyOffsetFromUI(pc, viewer, pid, availableProjects);
            }
        };
        
        if (inpX) {
            inpX.addEventListener('input', applyHandler);
            inpX.addEventListener('change', applyHandler);
        }
        if (inpY) {
            inpY.addEventListener('input', applyHandler);
            inpY.addEventListener('change', applyHandler);
        }
        if (inpZ) {
            inpZ.addEventListener('input', applyHandler);
            inpZ.addEventListener('change', applyHandler);
        }
        
        if (saveOffsetBtn) {
            saveOffsetBtn.addEventListener('click', () => {
                const pc = typeof window.currentPointcloud !== 'undefined' ? window.currentPointcloud : pointcloud;
                if (pc) this.copyOffsetToClipboard(pc);
            });
        }
        
        // Atualiza UI com valores iniciais
        if (pointcloud) {
            this.updateUI(pointcloud, projectId, availableProjects);
        }
        
        this.initialized = true;
    }
}
