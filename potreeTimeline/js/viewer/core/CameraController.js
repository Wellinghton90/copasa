/**
 * Controlador de câmera do viewer Potree.
 * Gerencia estado da câmera, salvamento e restauração de posições.
 * 
 * @class CameraController
 */
export class CameraController {
    /**
     * Cria uma instância de CameraController.
     * @param {object} viewer - Instância Potree.Viewer
     */
    constructor(viewer) {
        this.viewer = viewer;
    }

    /**
     * Salva o estado atual da câmera (posição e pivot).
     * @returns {object|null} Estado da câmera ou null se inválido
     */
    saveState() {
        if (!this.viewer || !this.viewer.scene || !this.viewer.scene.view) {
            return null;
        }
        
        const view = this.viewer.scene.view;
        return {
            position: view.position.clone(),
            pivot: view.getPivot().clone()
        };
    }

    /**
     * Restaura o estado da câmera a partir de um estado salvo.
     * @param {object} state - Estado da câmera (com position e pivot)
     */
    restoreState(state) {
        if (!this.viewer || !this.viewer.scene || !this.viewer.scene.view) {
            return;
        }
        
        if (!state || !state.position || !state.pivot) {
            return;
        }
        
        const view = this.viewer.scene.view;
        view.position.copy(state.position);
        view.lookAt(state.pivot);
    }

    /**
     * Atualiza o pivot da view para a posição de uma nuvem de pontos.
     * @param {object} pointcloud - Nuvem de pontos
     */
    updatePivotToPointcloud(pointcloud) {
        if (!this.viewer || !this.viewer.scene || !this.viewer.scene.view || !pointcloud) {
            return;
        }
        
        try {
            const pivot = pointcloud.position.clone();
            const view = this.viewer.scene.view;
            
            if (typeof view.setPivot === 'function') {
                view.setPivot(pivot);
            } else if (view.pivot) {
                view.pivot.copy(pivot);
            }
        } catch (err) {
            console.warn('Erro ao atualizar pivot:', err);
        }
    }

    /**
     * Ajusta a câmera para visualizar toda a cena.
     */
    fitToScreen() {
        if (this.viewer && typeof this.viewer.fitToScreen === 'function') {
            this.viewer.fitToScreen();
        }
    }
}
