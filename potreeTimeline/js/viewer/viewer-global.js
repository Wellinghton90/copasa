/**
 * Funções globais da página de nuvem (timeline).
 * Depende de window.viewer e window.currentPointcloud (definidos por viewer/timeline.js).
 */

function setPointSize(button) {
    const size = parseFloat(button.getAttribute('data-param'));
    const pointcloud = window.currentPointcloud;
    const viewer = window.viewer;
    if (pointcloud && viewer) {
        pointcloud.material.size = size;
        viewer.render();
    } else {
        console.warn('Nuvem de pontos ainda não carregada!');
    }
}

if (typeof window !== 'undefined') {
    window.setPointSize = setPointSize;
    window.mudaPonto = setPointSize;
}
