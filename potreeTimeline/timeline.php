<?php
/**
 * Página de visualização de nuvem de pontos (timeline).
 * Configuração em config/timeline.php.
 */

session_start();

if (file_exists('connection.php')) {
    include 'connection.php';
}

require_once __DIR__ . '/config/timeline.php';

// Debug quando nenhum projeto carrega: mensagem só no console (não na tela)
$jsonPath = (isset($CONFIG) && isset($CONFIG['jsonProjetos'])) ? $CONFIG['jsonProjetos'] : (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'projetos.json');
$jsonExists = file_exists($jsonPath);
$NUVEM_DEBUG_MSG = null;
if (empty($projetosDisponiveis)) {
    $NUVEM_DEBUG_MSG = 'Nenhuma nuvem no dropdown. Verifique: (1) O arquivo data/projetos.json existe no servidor? (2) config/timeline.php está configurado? Caminho usado: ' . $jsonPath . ' — Arquivo existe? ' . ($jsonExists ? 'sim' : 'NAO');
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="description" content="Carrega Nuvem de Pontos">
    <meta name="author" content="Wellinghton Gomes">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>VIEWER</title>

    <!-- Potree -->
    <link rel="stylesheet" type="text/css" href="potree/build/potree/potree.css">
    <link rel="stylesheet" type="text/css" href="potree/libs/jquery-ui/jquery-ui.min.css">
    <link rel="stylesheet" type="text/css" href="potree/libs/openlayers3/ol.css">
    <link rel="stylesheet" type="text/css" href="potree/libs/spectrum/spectrum.css">
    <link rel="stylesheet" type="text/css" href="potree/libs/jstree/themes/mixed/style.css">

    <link rel="stylesheet" type="text/css" href="css/nuvem.css">

    <script src="potree/libs/jquery/jquery-3.1.1.min.js"></script>
    <script src="potree/libs/spectrum/spectrum.js"></script>
    <script src="potree/libs/jquery-ui/jquery-ui.min.js"></script>
    <script src="potree/libs/other/BinaryHeap.js"></script>
    <script src="potree/libs/tween/tween.min.js"></script>
    <script src="potree/libs/d3/d3.js"></script>
    <script src="potree/libs/proj4/proj4.js"></script>
    <script src="potree/libs/openlayers3/ol.js"></script>
    <script src="potree/libs/i18next/i18next.js"></script>
    <script src="potree/libs/jstree/jstree.js"></script>
    <script src="potree/build/potree/potree.js"></script>
    <script src="potree/libs/plasio/js/laslaz.js"></script>
</head>
<body>

    <div class="potree_container">
        <aside class="sidebar-left" id="sidebar_left">
            <button type="button" id="btn_sidebar_config" class="sidebar-btn" data-mode="config" title="Ferramentas Potree (medição, câmera, clipping)">
                <span class="sidebar-icon">🔧</span>
                <span class="sidebar-label">Ferramentas</span>
            </button>
            <button type="button" id="btn_sidebar_camadas" class="sidebar-btn" data-mode="camadas" title="Camadas">
                <span class="sidebar-icon">📑</span>
                <span class="sidebar-label">Camadas</span>
            </button>
            <button type="button" id="btn_sidebar_compare_photos" class="sidebar-btn" data-mode="compare" title="Comparar fotos">
                <span class="sidebar-icon">↔️</span>
                <span class="sidebar-label">Comparar Fotos</span>
            </button>
            <button type="button" id="btn_sidebar_fotos_no_ponto" class="sidebar-btn" data-mode="fotos" title="Fotos no ponto">
                <span class="sidebar-icon">🎯</span>
                <span class="sidebar-label">Fotos do Ponto</span>
            </button>
        </aside>

        <div class="left-panel" id="left_panel">
            <div class="left-panel-content" data-mode="config" id="left_panel_config">
                <div id="potree_sidebar_container"></div>
            </div>
            <div class="left-panel-content" data-mode="camadas" id="left_panel_camadas"></div>
            <div class="left-panel-content" data-mode="compare" id="left_panel_compare"></div>
            <div class="left-panel-content" data-mode="fotos" id="left_panel_fotos"></div>
        </div>

        <div id="potree_render_area"></div>
    </div>

    <footer class="status-bar" id="status_bar">
        <div class="status-bar-left" id="status_bar_message">
            Nuvem de pontos
        </div>
        <div class="status-bar-center">
            <div class="timeline-controls">
                <button type="button" id="btn_anterior" title="Projeto anterior">←</button>
                <select id="seletor_projeto" title="Nuvem / Data"></select>
                <button type="button" id="btn_proximo" title="Próximo projeto">→</button>
            </div>
        </div>
        <div class="status-bar-right" id="status_bar_extra"></div>
    </footer>

    <script>
        window.NUVEM_CONFIG = <?php echo json_encode($NUVEM_CONFIG); ?>;
        <?php if (!empty($NUVEM_DEBUG_MSG)): ?>
        console.warn('[NUVEM] Dropdown vazio:', <?php echo json_encode($NUVEM_DEBUG_MSG); ?>);
        <?php endif; ?>
    </script>
    <script type="module" src="js/viewer/timeline.js"></script>
    <script src="js/viewer/viewer-global.js"></script>
    <script type="module" src="js/tools/photos-at-point.js"></script>
    <script type="module" src="js/tools/compare-photos.js"></script>
    <script type="module" src="js/tools/layers.js"></script>

</body>
</html>
