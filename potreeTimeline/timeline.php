<?php
/**
 * Página de visualização de nuvem de pontos (timeline).
 * Configuração centralizada no bloco abaixo; dados vêm de scripts/projetos.json.
 */

session_start();

if (file_exists('connection.php')) {
    include 'connection.php';
}

// -----------------------------------------------------------------------------
// Configuração
// -----------------------------------------------------------------------------
$CONFIG = [
    'jsonProjetos' => __DIR__ . '/data/projetos.json',
    'baseProjetos' => '../projetos/Juatuba',           // servidor com 2_densification: ex. 'projetos/Juatuba'
    'suffixPotree' => '2_densification/point_cloud/potree',             // servidor: '2_densification/point_cloud/potree'
];

// Lista de projetos (gerada por scripts/build_projetos_json.py)
$projetosDisponiveis = [];
if (file_exists($CONFIG['jsonProjetos'])) {
    $raw = file_get_contents($CONFIG['jsonProjetos']);
    $decoded = json_decode($raw, true);
    $projetosDisponiveis = is_array($decoded) ? $decoded : [];
}

// Projeto inicial: ?projeto=... se estiver na lista, senão primeiro da lista
$ids = array_column($projetosDisponiveis, 'id');
$nomeProjeto = $_GET['projeto'] ?? null;
$projetoInicial = ($nomeProjeto !== null && in_array($nomeProjeto, $ids, true))
    ? $nomeProjeto
    : (isset($projetosDisponiveis[0]['id']) ? $projetosDisponiveis[0]['id'] : '');

// Config injetada no JS (objeto único)
$NUVEM_CONFIG = [
    'projetosDisponiveis' => $projetosDisponiveis,
    'projetoInicial'      => $projetoInicial,
    'baseProjetos'        => $CONFIG['baseProjetos'],
    'suffixPotree'        => $CONFIG['suffixPotree'],
    'obra'                => $projetoInicial,
    'developerMode'       => false,   // false para desabilitar a ferramenta Offset
];
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

    <div class="viewer-toolbar">
        <div class="timeline-row">
            <span>Timeline</span>
            <div class="timeline-controls">
                <button type="button" id="btn_anterior" title="Projeto anterior">←</button>
                <select id="seletor_projeto" title="Nuvem / Data"></select>
                <button type="button" id="btn_proximo" title="Próximo projeto">→</button>
            </div>
        </div>
        <span>Tamanho dos pontos</span>
        <div>
            <button type="button" data-param="0.5" onclick="mudaPonto(this)">0.5</button>
            <button type="button" data-param="1" onclick="mudaPonto(this)">1</button>
            <button type="button" data-param="1.5" onclick="mudaPonto(this)">1.5</button>
            <button type="button" data-param="2" onclick="mudaPonto(this)">2</button>
            <button type="button" data-param="2.5" onclick="mudaPonto(this)">2.5</button>
            <button type="button" data-param="3" onclick="mudaPonto(this)">3</button>
        </div>
    </div>

    <div class="potree_container">
        <div id="potree_render_area"></div>
        <div id="potree_sidebar_container"></div>
    </div>

    <script>
        window.NUVEM_CONFIG = <?php echo json_encode($NUVEM_CONFIG); ?>;
    </script>
    <script type="module" src="js/nuvem-timeline.js"></script>
    <script src="js/nuvem.js"></script>

</body>
</html>
