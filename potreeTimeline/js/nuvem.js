/**
 * Funções globais da página de nuvem (nuvem.php).
 * Depende de window.viewer e window.currentPointcloud (definidos por nuvem-timeline.js).
 */

function mudaPonto(botao) {
    const tamanho = parseFloat(botao.getAttribute('data-param'));
    const pointcloud = window.currentPointcloud;
    const viewer = window.viewer;
    if (pointcloud && viewer) {
        pointcloud.material.size = tamanho;
        viewer.render();
    } else {
        console.warn('Nuvem de pontos ainda não carregada!');
    }
}
