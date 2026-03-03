/**
 * Controlador de tamanho de pontos.
 * Gerencia slider e input numérico para ajustar o tamanho dos pontos.
 * 
 * @class PointSizeController
 */

import { pointSizeControlsTemplate } from "../templates/pointSizeControlsTemplate.js";

export class PointSizeController {
    /**
     * Cria uma instância de PointSizeController.
     * @param {object} viewer - Instância Potree.Viewer
     */
    constructor(viewer) {
        this.viewer = viewer;
        this.currentPointcloud = null;
        this.slider = null;
        this.input = null;
        this.initialized = false;
    }

    /**
     * Atualiza o tamanho dos pontos da nuvem atual.
     * @param {number} value - Novo tamanho dos pontos
     */
    updatePointSize(value) {
        if (!this.currentPointcloud || !this.viewer) {
            return;
        }
        
        if (this.currentPointcloud.material) {
            this.currentPointcloud.material.size = value;
        }
        
        if (typeof this.viewer.render === 'function') {
            this.viewer.render();
        }
    }

    /**
     * Define a nuvem de pontos atual e atualiza os controles.
     * @param {object} pointcloud - Nuvem de pontos (pode ser null)
     */
    setPointcloud(pointcloud) {
        this.currentPointcloud = pointcloud;
        this.updateControls();
    }

    /**
     * Atualiza os controles de tamanho de pontos quando a nuvem muda.
     */
    updateControls() {
        if (!this.slider || !this.input) {
            return;
        }
        
        if (this.currentPointcloud && this.currentPointcloud.material) {
            const currentSize = this.currentPointcloud.material.size || 1;
            this.slider.value = currentSize;
            this.input.value = currentSize.toFixed(1);
        }
    }

    /**
     * Define o tamanho dos pontos a partir de um botão com data-param.
     * @param {HTMLElement} button - Botão com atributo data-param
     */
    setPointSizeFromButton(button) {
        if (!button || !this.currentPointcloud || !this.viewer) {
            console.warn('setPointSizeFromButton: parâmetros inválidos');
            return;
        }
        
        const size = parseFloat(button.getAttribute('data-param'));
        if (isNaN(size)) {
            console.warn('setPointSizeFromButton: data-param inválido');
            return;
        }
        
        this.updatePointSize(size);
    }

    /**
     * Inicializa os controles de tamanho de pontos na barra de status.
     */
    init() {
        if (this.initialized) {
            return;
        }

        const container = document.getElementById('status_bar_extra');
        if (!container) {
            return;
        }
        
        // Verifica se já foi inicializado
        if (document.getElementById('pointSizeSlider')) {
            this.slider = document.getElementById('pointSizeSlider');
            this.input = document.getElementById('pointSizeInput');
            this.initialized = true;
            return;
        }
        
        const ajustesWrap = document.createElement('div');
        ajustesWrap.className = 'status-bar-ajustes';
        ajustesWrap.innerHTML = pointSizeControlsTemplate;
        container.appendChild(ajustesWrap);
        
        this.slider = document.getElementById('pointSizeSlider');
        this.input = document.getElementById('pointSizeInput');
        
        if (!this.slider || !this.input) {
            return;
        }
        
        // Função auxiliar para atualizar o tamanho
        const applySize = (value) => {
            const numValue = parseFloat(value);
            if (!isNaN(numValue) && numValue >= 0.1 && numValue <= 5) {
                this.slider.value = numValue;
                this.input.value = numValue.toFixed(1);
                this.updatePointSize(numValue);
            }
        };
        
        // Event listeners
        this.slider.addEventListener('input', () => {
            const value = parseFloat(this.slider.value);
            this.input.value = value.toFixed(1);
            this.updatePointSize(value);
        });
        
        this.input.addEventListener('input', () => {
            const value = parseFloat(this.input.value);
            if (!isNaN(value) && value >= 0.1 && value <= 5) {
                this.slider.value = value;
                this.updatePointSize(value);
            }
        });
        
        this.input.addEventListener('blur', () => {
            const value = parseFloat(this.input.value);
            if (isNaN(value) || value < 0.1) {
                applySize(0.1);
            } else if (value > 5) {
                applySize(5);
            }
        });
        
        // Atualiza controles com valores iniciais
        this.updateControls();
        
        this.initialized = true;
        
        // Expõe funções globalmente para compatibilidade com código legado
        if (typeof window !== 'undefined') {
            window.updatePointSizeControls = (pointcloud) => {
                this.setPointcloud(pointcloud);
            };
            
            // Função global setPointSize para compatibilidade (usada por viewer-global.js)
            window.setPointSize = (button) => {
                if (!button || !this.currentPointcloud || !this.viewer) {
                    console.warn('setPointSize: parâmetros inválidos');
                    return;
                }
                
                const size = parseFloat(button.getAttribute('data-param'));
                if (!isNaN(size)) {
                    this.updatePointSize(size);
                }
            };
            
            // Mantém alias legado
            window.mudaPonto = window.setPointSize;
        }
    }
}
