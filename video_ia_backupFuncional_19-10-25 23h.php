<?php
session_start();
require_once 'connection.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_copasa'])) {
    header('Location: index.php');
    exit();
}

$usuario = $_SESSION['user_copasa'];

// Verificar se foi passado o caminho do vídeo
if (!isset($_GET['video']) || empty($_GET['video'])) {
    header('Location: dashboard.php');
    exit();
}

$video_path = $_GET['video'];

// Verificar se o arquivo existe
if (!file_exists($video_path)) {
    header('Location: dashboard.php?error=' . urlencode('Vídeo não encontrado.'));
    exit();
}

// Obter informações do vídeo
$video_info = pathinfo($video_path);
$video_nome = $video_info['basename'];
$video_nome_sem_extensao = $video_info['filename'];
$video_extensao = strtolower($video_info['extension']);
$video_tamanho = filesize($video_path);

// Extrair cidade do caminho (evidencias/{cidade}/Videos/...)
$path_parts = explode('/', str_replace('\\', '/', $video_path));
$cidade_index = array_search('evidencias', $path_parts);
$cidade = isset($path_parts[$cidade_index + 1]) ? $path_parts[$cidade_index + 1] : '';

// Construir caminho para pasta de frames
$frames_path = "evidencias/{$cidade}/frames/{$video_nome_sem_extensao}(frames)";
$json_path = "{$frames_path}/dados_video.json";

// Carregar dados dos frames se existirem
$frames_data = [];
if (file_exists($json_path)) {
    $json_content = file_get_contents($json_path);
    $frames_data = json_decode($json_content, true);
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizador de Vídeo - COPASA</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        :root {
            --primary-color: #00bcd4;
            --secondary-color: #006064;
            --accent-color: #26c6da;
            --dark-bg: #0a1929;
            --card-bg: rgba(255, 255, 255, 0.05);
            --text-light: #e3f2fd;
            --gradient-primary: linear-gradient(135deg, #00bcd4 0%, #006064 100%);
            --gradient-bg: linear-gradient(135deg, #0a1929 0%, #1a237e 50%, #0a1929 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gradient-bg);
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 20% 80%, rgba(0, 188, 212, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(38, 198, 218, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(0, 96, 100, 0.1) 0%, transparent 50%);
            animation: backgroundMove 20s ease-in-out infinite;
            z-index: -1;
        }

        @keyframes backgroundMove {

            0%,
            100% {
                transform: translate(0, 0) rotate(0deg);
            }

            33% {
                transform: translate(30px, -30px) rotate(120deg);
            }

            66% {
                transform: translate(-20px, 20px) rotate(240deg);
            }
        }

        /* Navbar */
        .navbar {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 0;
        }

        .navbar-brand {
            color: var(--text-light);
            font-weight: 700;
            font-size: 1.2rem;
            text-decoration: none;
        }

        .navbar-brand:hover {
            color: var(--accent-color);
        }

        .navbar-nav .nav-link {
            color: var(--text-light);
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover {
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        /* Container */
        .container-fluid {
            padding: 15px;
        }

        /* Video Container */
        .video-container {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            box-shadow:
                0 15px 30px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            padding: 0;
            margin-bottom: 0;
            position: relative;
            overflow: hidden;
            display: block;
            min-height: 400px;
        }

        .video-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .video-wrapper {
            position: relative;
            background: #000;
            border-radius: 10px;
            overflow: hidden;
            padding: 0;
            margin: 0;
            height: 100%;
            width: 100%;
        }

        video {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: contain;
        }

        /* Controls Section */
        .controls-section {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            box-shadow:
                0 25px 45px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            padding: 0;
            margin-bottom: 0;
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .controls-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .controls-section h3 {
            color: var(--text-light);
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 188, 212, 0.3);
            word-break: break-word;
            padding: 10px 15px 5px 15px;
            margin: 0;
        }

        .controls-section h3 i {
            color: var(--primary-color);
        }

        /* Minimapa */
        #minimapa {
            height: calc(100% - 60px);
            width: 100%;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid rgba(0, 188, 212, 0.3);
            margin: 0;
        }

        /* Estilos para marcadores do Leaflet */
        .leaflet-marker-icon.custom-marker {
            z-index: 2 !important;
        }

        .leaflet-marker-icon.custom-marker-active {
            z-index: 3 !important;
        }


        /* Carrossel de Frames - Agora em largura total */
        .carrossel-container {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            box-shadow:
                0 15px 30px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            padding: 15px;
            position: relative;
            overflow: hidden;
        }

        .carrossel-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .carrossel-container h4 {
            color: var(--text-light);
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0, 188, 212, 0.3);
        }

        .carrossel-container h4 i {
            color: var(--primary-color);
        }

        #carrossel-ia {
            overflow-x: auto;
            overflow-y: hidden;
            white-space: nowrap;
            padding: 20px 20px;
            scroll-behavior: smooth;
        }

        #carrossel-ia::-webkit-scrollbar {
            height: 8px;
        }

        #carrossel-ia::-webkit-scrollbar-track {
            background: rgba(0, 188, 212, 0.1);
            border-radius: 10px;
        }

        #carrossel-ia::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 10px;
        }

        #carrossel-ia::-webkit-scrollbar-thumb:hover {
            background: var(--accent-color);
        }

        .frame-item {
            display: inline-block;
            margin-right: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            border-radius: 8px;
            position: relative;
            vertical-align: top;
        }

        .frame-item > div:first-child {
            border-radius: 8px;
            overflow: hidden;
        }

        .frame-name {
            font-size: 0.7rem;
            color: var(--text-light);
            opacity: 0.8;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding: 0 5px;
        }

        .frame-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 188, 212, 0.3);
        }

        .frame-item.active {
            border-color: var(--primary-color);
            box-shadow: 0 0 20px rgba(0, 188, 212, 0.6);
            transform: scale(1.05);
        }

        .frame-item.frame-analisado {
            position: relative;
        }

        .frame-item.frame-analisado::after {
            content: '✓';
            position: absolute;
            top: -5px;
            right: -5px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            z-index: 10;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }

        .frame-item img {
            height: 80px;
            width: auto;
            display: block;
            border-radius: 6px;
        }

        .frame-fullscreen-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(0, 0, 0, 0.7);
            color: var(--accent-color);
            border: none;
            border-radius: 4px;
            padding: 4px 6px;
            font-size: 0.7rem;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .frame-item:hover .frame-fullscreen-btn {
            opacity: 1;
        }

        .frame-fullscreen-btn:hover {
            background: rgba(0, 188, 212, 0.8);
            color: white;
        }

        .frame-analysis-btn {
            position: absolute;
            top: 5px;
            left: 5px;
            background: rgba(0, 0, 0, 0.7);
            color: var(--primary-color);
            border: none;
            border-radius: 4px;
            padding: 4px 6px;
            font-size: 0.7rem;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .frame-item:hover .frame-analysis-btn {
            opacity: 1;
        }

        .frame-analysis-btn:hover {
            background: rgba(0, 188, 212, 0.8);
            color: white;
        }

        .frame-analysis-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .frame-time {
            position: absolute;
            bottom: 5px;
            left: 5px;
            background: rgba(0, 0, 0, 0.8);
            color: var(--accent-color);
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .no-frames-message {
            text-align: center;
            padding: 40px 20px;
            color: rgba(227, 242, 253, 0.6);
        }

        .no-frames-message i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        /* Buttons */
        .btn {
            border-radius: 12px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 5px 20px rgba(0, 188, 212, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 188, 212, 0.5);
            color: white;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--text-light);
            box-shadow: 0 5px 20px rgba(255, 255, 255, 0.1);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            color: var(--text-light);
            box-shadow: 0 10px 30px rgba(255, 255, 255, 0.2);
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .container-fluid {
                padding: 15px;
            }

            .frame-item img {
                height: 80px;
            }
        }

        /* Layout dos 3 containers */
        #videoContainer {
            width: 48%;
            flex: 0 0 48%;
            height: auto;
            padding: 0;
            min-height: 400px;
        }

        #mapContainer {
            width: 30%;
            flex: 0 0 30%;
            height: auto;
            padding: 0;
            min-height: 400px;
        }

        #iaContainer {
            width: 20%;
            flex: 0 0 20%;
            height: auto;
            padding: 0;
            min-height: 400px;
        }

        /* Container principal flexível */
        .row.three-columns {
            display: flex;
            justify-content: space-between;
            align-items: stretch; /* Faz todos terem a mesma altura */
            gap: 1%;
            margin: 0px 0px 10px 0px;
        }

        /* Fazer os containers internos ocuparem toda a altura disponível */
        #mapContainer .controls-section,
        #iaContainer .controls-section {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        /* Ajustar conteúdo para ocupar o espaço restante */
        #mapContainer #minimapa {
            flex: 1;
            min-height: 0;
            height: auto;
        }

        #iaContainer #analises-ia-list {
            flex: 1;
            min-height: 0;
            height: auto;
        }

        /* Container principal com altura dinâmica */
        .row.three-columns {
            min-height: 400px;
            align-items: stretch;
        }

        /* Garantir que todos os containers tenham a mesma altura */
        #videoContainer,
        #mapContainer,
        #iaContainer {
            display: flex;
            flex-direction: column;
        }

        /* Container de Análises IA */
        #analises-ia-list {
            overflow-y: auto;
            margin: 0;
            margin-top: 10px;
            flex: 1;
            min-height: 0;
        }

        /* Estilizar scrollbar da lista de análises igual aos frames */
        #analises-ia-list::-webkit-scrollbar {
            width: 8px;
        }

        #analises-ia-list::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        #analises-ia-list::-webkit-scrollbar-thumb {
            background: rgba(0, 188, 212, 0.3);
            border-radius: 4px;
            transition: background 0.3s ease;
        }

        #analises-ia-list::-webkit-scrollbar-thumb:hover {
            background: var(--accent-color);
        }

        .analise-item {
            background: rgba(0, 188, 212, 0.05);
            border: 1px solid rgba(0, 188, 212, 0.2);
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .analise-item:hover {
            background: rgba(0, 188, 212, 0.1);
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .analise-item-header {
            position: absolute;
            right: 15px;
        }

        .analise-item-time {
            font-size: 0.75rem;
            color: var(--accent-color);
            font-weight: 600;
        }

        .analise-item-preview {
            font-size: 0.8rem;
            color: var(--text-light);
            opacity: 0.8;
            line-height: 1.2;
        }

        .analise-item-status {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Estilos para timer de análise */
        .analise-item.analisando {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }


        .analise-progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            border-radius: 0 0 8px 8px;
            transition: width 1s linear;
            width: 0%;
        }

        .analise-item.analisando .analise-progress-bar {
            animation: progressBar 50s linear forwards;
        }

        @keyframes progressBar {
            from { width: 0%; }
            to { width: 100%; }
        }

        .analise-item.pronta {
            cursor: pointer;
            pointer-events: auto;
            margin-top: 5px;
        }


        .analise-item.pronta .analise-status-icon {
            color: #28a745;
        }

        .analise-filename {
            font-size: 0.85rem;
            color: var(--text-light);
            font-weight: 500;
        }

        /* Modal para análise */
        .modal-content {
            background: rgb(255 255 255);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-light);
        }

        .modal-header {
            border-bottom: 1px solid rgba(0, 188, 212, 0.2);
        }

        .modal-title {
            color: black;
        }

        .btn-close {
            filter: invert(1);
        }

        .accordion-button:not(.collapsed){
            background-color: lightblue;
        }

        .accordion-button{
            background-color: #ededed;
        }

        /* Loading Screen */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }

        .loading-screen.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(0, 188, 212, 0.3);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        .loading-text {
            color: var(--text-light);
            font-size: 1.1rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 10px;
        }

        .loading-subtitle {
            color: rgba(227, 242, 253, 0.7);
            font-size: 0.9rem;
            text-align: center;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Blur no body quando loading */
        body.loading {
            overflow: hidden;
        }

        body.loading .container-fluid {
            filter: blur(2px);
            pointer-events: none;
        }

        body.loading nav {
            filter: blur(1px);
            pointer-events: none;
        }
    </style>
</head>

<body class="loading">
    <!-- Loading Screen -->
    <div class="loading-screen" id="loadingScreen">
        <div class="loading-spinner"></div>
        <div class="loading-text">Carregando Visualizador</div>
        <div class="loading-subtitle">Preparando interface e dados...</div>
    </div>

    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#" style="cursor: default;">
                <i class="fas fa-video"></i>
                Visualizador de Vídeo com IA
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row three-columns">
            <!-- Video Player -->
            <div id="videoContainer">
                <div class="video-container">
                    <div class="video-wrapper">
                        <video id="videoPlayer" controls>
                            <source src="<?= htmlspecialchars($video_path) ?>" type="video/<?= $video_extensao ?>">
                            Seu navegador não suporta a reprodução de vídeos.
                        </video>
                    </div>
                </div>
            </div>

            <!-- Minimapa Panel -->
            <div id="mapContainer">
                <div class="controls-section">
                    <?php if (!empty($frames_data)): ?>
                        <!-- Minimapa -->
                        <div id="minimapa"></div>
                    <?php else: ?>
                        <div class="no-frames-message">
                            <i class="fas fa-images"></i>
                            <h5>Nenhum frame disponível</h5>
                            <p>Não foram encontrados frames para este vídeo.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Container de Análises IA -->
            <div id="iaContainer">
                <div class="controls-section" style="color: var(--text-light);">
                    <h3>
                        <i class="fas fa-lightbulb"></i>
                        Análises da IA
                    </h3>
                    
                    <!-- Lista de análises -->
                    <div id="analises-ia-list">
                        <!-- As análises serão adicionadas aqui dinamicamente -->
                        <div class="text-center" style="padding: 40px 20px; font-size: 0.9rem;">
                            <i class="fas fa-robot" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.3;"></i>
                            <p>Clique em um frame e analise com IA</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Carrossel de Frames - Largura Total -->
        <?php if (!empty($frames_data)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="carrossel-container">
                        <h4>
                            <i class="fas fa-film"></i>
                            Frames do Vídeo
                        </h4>
                        <div id="carrossel-ia">
                            <?php foreach ($frames_data as $frame_name => $frame_info): ?>
                                <div class="frame-item"
                                    data-time="<?= htmlspecialchars($frame_info['tempo_video']) ?>"
                                    data-lat="<?= htmlspecialchars($frame_info['latitude']) ?>"
                                    data-lng="<?= htmlspecialchars($frame_info['longitude']) ?>"
                                    data-frame="<?= htmlspecialchars($frame_name) ?>">
                                    <div style="position: relative;">
                                        <img src="<?= htmlspecialchars($frames_path . '/' . $frame_name) ?>"
                                            alt="<?= htmlspecialchars($frame_name) ?>">
                                        <button class="frame-analysis-btn" 
                                            onclick="analyzeFrameWithAI('<?= htmlspecialchars($frames_path . '/' . $frame_name) ?>', '<?= htmlspecialchars($frame_name) ?>', '<?= htmlspecialchars($frame_info['tempo_video']) ?>')"
                                            title="Analisar com IA">
                                            <i class="fas fa-lightbulb"></i>
                                        </button>
                                        <button class="frame-fullscreen-btn" 
                                            onclick="openFrameFullscreen('<?= htmlspecialchars($frames_path . '/' . $frame_name) ?>', '<?= htmlspecialchars($frame_name) ?>')"
                                            title="Ver em tela cheia">
                                            <i class="fas fa-expand"></i>
                                        </button>
                                        <div class="frame-time"><?= htmlspecialchars($frame_info['tempo_video']) ?></div>
                                    </div>
                                    <div class="frame-name"><?= htmlspecialchars($frame_name) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal para Análise IA -->
    <div class="modal fade" id="analiseModal" tabindex="-1" aria-labelledby="analiseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="analiseModalLabel">
                        <i class="fas fa-lightbulb me-2"></i>
                        Análise da IA
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="analiseModalBody">
                    <!-- Conteúdo da análise será inserido aqui -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Visualização da Imagem -->
    <div class="modal fade" id="imageViewerModal" tabindex="-1" aria-labelledby="imageViewerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content" style="background: #000;">
                <div class="modal-header" style="border-bottom: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.8);">
                    <h5 class="modal-title text-white" id="imageViewerModalLabel">
                        <i class="fas fa-image me-2"></i>
                        <span id="imageViewerFrameName"></span>
                    </h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary btn-sm" id="analyzeModalBtn">
                            <i class="fas fa-lightbulb me-1"></i>
                            Analisar com IA
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>
                            Fechar
                        </button>
                    </div>
                </div>
                <div class="modal-body p-0" style="overflow: hidden; position: relative; height: calc(100vh - 80px);">
                    <div id="imageContainer" style="width: 100%; height: 100%; background: #000; cursor: grab; position: relative; overflow: hidden; user-select: none;">
                        <img id="viewerImage" src="" alt="" style="max-width: none; max-height: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(1); transition: transform 0.1s ease-out; user-select: none; pointer-events: none; image-rendering: crisp-edges;">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Resultado da Análise IA -->
    <div class="modal fade" id="resultadoAnaliseModal" tabindex="-1" aria-labelledby="resultadoAnaliseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" style="max-width: 90%;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resultadoAnaliseModalLabel">
                        <i class="fas fa-robot me-2"></i>
                        Resultado da Análise IA
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background-color: aquamarine;"></button>
                </div>
                <div class="modal-body" id="resultadoAnaliseModalBody" style="max-height: 70vh; overflow-y: auto;">
                    <!-- Conteúdo do accordion será inserido aqui -->
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        // Variáveis PHP para JavaScript
        const cidade = '<?= addslashes($cidade) ?>';
        const framesPath = '<?= addslashes($frames_path) ?>';
        
        // Referência ao player de vídeo
        const videoPlayer = document.getElementById('videoPlayer');
        const carrossel = document.getElementById('carrossel-ia');
        const frameItems = document.querySelectorAll('.frame-item');

        // Variáveis do mapa
        let map = null;
        let markers = [];
        let currentActiveMarker = null;

        // Variável para controlar modal de análise
        let resultadoAnaliseModalInstance = null;

        // Função para inicializar e obter instância do modal de análise
        function getResultadoAnaliseModalInstance() {
            if (!resultadoAnaliseModalInstance) {
                const modalElement = document.getElementById('resultadoAnaliseModal');
                resultadoAnaliseModalInstance = new bootstrap.Modal(modalElement, {
                    backdrop: true,
                    keyboard: true,
                    focus: true
                });
                
                // Adicionar event listeners para limpar backdrop corretamente
                modalElement.addEventListener('hidden.bs.modal', function() {
                    // Força a remoção de qualquer backdrop remanescente
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => backdrop.remove());
                    
                    // Remove classe do body que pode estar causando problemas
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                });
                
                modalElement.addEventListener('hide.bs.modal', function() {
                    // Garantir que o backdrop seja limpo antes de fechar
                    setTimeout(() => {
                        const backdrops = document.querySelectorAll('.modal-backdrop');
                        backdrops.forEach(backdrop => backdrop.remove());
                    }, 100);
                });
                
                // Adicionar event listener específico para o botão de fechar
                const closeButton = modalElement.querySelector('.btn-close');
                if (closeButton) {
                    closeButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        resultadoAnaliseModalInstance.hide();
                    });
                }
            }
            return resultadoAnaliseModalInstance;
        }

        // Função utilitária para limpar backdrop (pode ser chamada globalmente se necessário)
        function limparModalBackdrop() {
            // Remove todos os backdrops
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            
            // Remove classes problemáticas do body
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            
            console.log('Modal backdrop limpo manualmente');
        }

        // Função para criar ícone padrão
        function createDefaultIcon() {
            return L.divIcon({
                className: 'custom-marker',
                html: '<div style="background-color: #6c757d; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
                iconSize: [12, 12],
                iconAnchor: [6, 6]
            });
        }

        // Função para criar ícone ativo
        function createActiveIcon() {
            return L.divIcon({
                className: 'custom-marker-active',
                html: '<div style="background-color: #00bcd4; width: 18px; height: 18px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 15px rgba(0, 188, 212, 0.8);"></div>',
                iconSize: [18, 18],
                iconAnchor: [9, 9]
            });
        }

        // Inicializar o mapa
        function initMap() {
            if (frameItems.length === 0) return;

            // Obter coordenadas do primeiro frame
            const firstFrame = frameItems[0];
            const lat = parseFloat(firstFrame.dataset.lat);
            const lng = parseFloat(firstFrame.dataset.lng);

            // Criar mapa com zoom inicial 19 e mínimo 1
            map = L.map('minimapa', {
                minZoom: 1,
                maxZoom: 22,     // já define aqui o máximo+
                zoom: 18,
                center: [lat, lng]
            });

            // Adicionar camada de tiles que permite zoom até 22
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '',
                maxZoom: 22,          // zoom máximo do Leaflet
                maxNativeZoom: 18     // zoom real dos tiles
            }).addTo(map);

            // Criar marcadores para cada frame
            frameItems.forEach((frame, index) => {
                const frameLat = parseFloat(frame.dataset.lat);
                const frameLng = parseFloat(frame.dataset.lng);
                const frameTime = frame.dataset.time;

                const marker = L.marker([frameLat, frameLng], {
                    icon: createDefaultIcon(),
                    title: `Frame ${index + 1} - ${frameTime}`,
                    zIndexOffset: 2 // Z-index base 2 para todos
                }).addTo(map);

                // Associar frame ao marcador
                marker.frameElement = frame;
                marker.frameIndex = index;
                marker.originalZIndex = 2000 + index;

                // Clique no marcador: pausa vídeo e vai para o frame
                marker.on('click', function() {
                    const frameTime = timeToSeconds(frame.dataset.time);
                    videoPlayer.currentTime = frameTime;
                    videoPlayer.pause();
                    updateActiveFrame(videoPlayer.currentTime);
                });

                markers.push(marker);
            });

            // Ajustar bounds do mapa para mostrar todos os marcadores
            if (markers.length > 1) {
                const group = L.featureGroup(markers);
                //map.fitBounds(group.getBounds().pad(0.1));
            }
        }

        // Converter tempo "HH:MM:SS.mmm" para segundos
        function timeToSeconds(timeString) {
            const parts = timeString.split(':');
            const hours = parseInt(parts[0]) || 0;
            const minutes = parseInt(parts[1]) || 0;
            const seconds = parseFloat(parts[2]) || 0;
            return hours * 3600 + minutes * 60 + seconds;
        }

        // Encontrar frame mais próximo do tempo atual do vídeo
        function findClosestFrame(currentTime) {
            let closestFrame = null;
            let closestMarker = null;
            let minDiff = Infinity;
            let closestIndex = -1;

            frameItems.forEach((frame, index) => {
                const frameTime = timeToSeconds(frame.dataset.time);
                const diff = Math.abs(currentTime - frameTime);

                if (diff < minDiff) {
                    minDiff = diff;
                    closestFrame = frame;
                    closestMarker = markers[index];
                    closestIndex = index;
                }
            });

            return {
                frame: closestFrame,
                marker: closestMarker,
                index: closestIndex
            };
        }

        // Variáveis para gerenciamento das análises
        let analisesSalvas = JSON.parse(localStorage.getItem('analisesIA') || '{}');

        // Variáveis para controle de zoom e movimento da imagem
        let imageScale = 1;
        let imageOffset = { x: 0, y: 0 };
        let isDragging = false;
        let dragStart = { x: 0, y: 0 };
        let currentImagePath = '';
        let currentFrameName = '';
        let currentFrameTime = '';

        // Função para abrir frame em modal com zoom e movimento
        function openFrameFullscreen(imagePath, frameName) {
            currentImagePath = imagePath;
            currentFrameName = frameName;
            
            // Resetar zoom e posição
            imageScale = 1;
            imageOffset = { x: 0, y: 0 };
            
            // Configurar modal
            document.getElementById('imageViewerFrameName').textContent = frameName;
            document.getElementById('viewerImage').src = imagePath;
            document.getElementById('viewerImage').alt = frameName;
            
            // Configurar botão de análise
            document.getElementById('analyzeModalBtn').onclick = function() {
                // Buscar o tempo do frame nos dados disponíveis
                const frameElement = document.querySelector(`[data-frame="${frameName}"]`);
                const frameTime = frameElement ? frameElement.dataset.time : '00:00:00.000';
                currentFrameTime = frameTime;
                
                // Chamar função de análise
                analyzeFrameWithAI(imagePath, frameName, frameTime);
                
                // Fechar modal de visualização
                const modal = bootstrap.Modal.getInstance(document.getElementById('imageViewerModal'));
                if (modal) {
                    modal.hide();
                }
            };
            
            // Aplicar transformação inicial
            updateImageTransform();
            
            // Garantir cursor inicial correto
            setTimeout(() => {
                const container = document.getElementById('imageContainer');
                if (container) {
                    container.style.cursor = 'grab';
                }
                setupImageModalListeners();
            }, 100);
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
            modal.show();
        }

        // Função para atualizar transformação da imagem
        function updateImageTransform() {
            const img = document.getElementById('viewerImage');
            if (img) {
                img.style.transform = `translate(-50%, -50%) translate(${imageOffset.x}px, ${imageOffset.y}px) scale(${imageScale})`;
            }
        }

        // Função para fazer zoom
        function zoomImage(delta, centerX = null, centerY = null) {
            const container = document.getElementById('imageContainer');
            const img = document.getElementById('viewerImage');
            
            if (!img || !container) return;
            
            const scaleFactor = delta > 0 ? 1.1 : 0.9;
            const oldScale = imageScale;
            imageScale *= scaleFactor;
            
            // Limitar zoom
            imageScale = Math.max(0.1, Math.min(imageScale, 10));
            
            if (imageScale !== oldScale) {
                // Se centro não especificado, usar centro do container
                if (centerX === null || centerY === null) {
                    const rect = container.getBoundingClientRect();
                    centerX = rect.width / 2;
                    centerY = rect.height / 2;
                }
                
                // Ajustar offset para fazer zoom no ponto correto
                const scaleRatio = imageScale / oldScale;
                const containerRect = container.getBoundingClientRect();
                const containerCenterX = containerRect.width / 2;
                const containerCenterY = containerRect.height / 2;
                
                imageOffset.x = centerX - containerCenterX + (imageOffset.x + containerCenterX - centerX) * scaleRatio;
                imageOffset.y = centerY - containerCenterY + (imageOffset.y + containerCenterY - centerY) * scaleRatio;
                
                updateImageTransform();
            }
        }

        // Função para configurar event listeners do modal
        function setupImageModalListeners() {
            const container = document.getElementById('imageContainer');
            
            if (!container) return;
            
            // Remover listeners antigos se existirem
            container.removeEventListener('wheel', handleWheel);
            container.removeEventListener('mousedown', handleMouseDown);
            container.removeEventListener('mouseleave', handleMouseLeave);
            
            // Adicionar novos listeners
            container.addEventListener('wheel', handleWheel);
            container.addEventListener('mousedown', handleMouseDown);
            container.addEventListener('mouseleave', handleMouseLeave);
        }

        // Handlers específicos para os eventos
        function handleWheel(e) {
            e.preventDefault();
            const container = document.getElementById('imageContainer');
            const rect = container.getBoundingClientRect();
            const centerX = e.clientX - rect.left;
            const centerY = e.clientY - rect.top;
            zoomImage(e.deltaY, centerX, centerY);
        }

        function handleMouseDown(e) {
            const container = document.getElementById('imageContainer');
            isDragging = true;
            container.style.cursor = 'grabbing';
            dragStart.x = e.clientX - imageOffset.x;
            dragStart.y = e.clientY - imageOffset.y;
        }

        function handleMouseLeave() {
            if (!isDragging) {
                const container = document.getElementById('imageContainer');
                if (container) {
                    container.style.cursor = 'grab';
                }
            }
        }

        // Event listeners globais para mouse move e up
        document.addEventListener('mousemove', function(e) {
            if (isDragging) {
                imageOffset.x = e.clientX - dragStart.x;
                imageOffset.y = e.clientY - dragStart.y;
                updateImageTransform();
            }
        });

        document.addEventListener('mouseup', function() {
            if (isDragging) {
                isDragging = false;
                const container = document.getElementById('imageContainer');
                if (container) {
                    container.style.cursor = 'grab';
                }
            }
        });

        // Função para verificar se análise já existe
        function analiseJaExiste(frameName) {
            const jsonFileName = frameName.replace(/\.[^/.]+$/, "") + "_(response).json";
            const jsonPath = framesPath + "/" + jsonFileName;
            
            return new Promise((resolve) => {
                $.ajax({
                    url: 'check_file_exists.php',
                    method: 'POST',
                    data: { file_path: jsonPath },
                    success: function(exists) {
                        resolve(exists === 'true');
                    },
                    error: function() {
                        resolve(false);
                    }
                });
            });
        }


        // Função para analisar frame com IA
        async function analyzeFrameWithAI(imagePath, frameName, frameTime) {
            console.log('Analisando frame:', { imagePath, frameName, frameTime });
            
            // Verificar se análise já existe
            const existeAnalise = await analiseJaExiste(frameName);
            
            if (existeAnalise) {
                // Carregar análise existente
                await carregarAnaliseExistente(frameName, frameTime);
                return;
            }
            
            // Criar card de análise com timer imediatamente
            criarCardAnalisando(frameName, frameTime);
            
            // Enviar para análise
            $.ajax({
                url: 'teste_analise.php',
                method: 'POST',
                data: {
                    image_path: imagePath,
                    frame_name: frameName,
                    frame_time: frameTime,
                    cidade: cidade
                },
                success: function(response) {
                    try {
                        const analiseData = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        // Salvar JSON no servidor
                        const jsonFileName = frameName.replace(/\.[^/.]+$/, "") + "_(response).json";
                        salvarAnaliseJSON(analiseData, jsonFileName, frameName, frameTime);
                        
                        // Finalizar timer e tornar item clicável
                        finalizarAnalise(frameName);
                        
                    } catch (error) {
                        console.error('Erro ao processar resposta:', error);
                        erroNaAnalise(frameName, 'Erro ao processar análise da IA');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro na requisição:', error);
                    erroNaAnalise(frameName, 'Erro ao enviar para análise da IA');
                }
            });
            
        }

        // Função para criar card de análise em andamento
        function criarCardAnalisando(frameName, frameTime) {
            const listaAnalises = document.getElementById('analises-ia-list');
            
            // Remover mensagem de placeholder se existir
            const placeholder = listaAnalises.querySelector('.text-center');
            if (placeholder) {
                placeholder.remove();
            }
            
            // Verificar se já existe na lista
            const existingItem = listaAnalises.querySelector(`[data-frame-name="${frameName}"]`);
            if (existingItem) {
                existingItem.remove();
            }
            
            const analiseItem = document.createElement('div');
            analiseItem.className = 'analise-item analisando';
            analiseItem.setAttribute('data-frame-name', frameName);
            
            // Remover extensão do nome para mostrar
            const nomeSemExtensao = frameName.replace(/\.[^/.]+$/, "");
            
            analiseItem.innerHTML = `
                <div class="analise-item-header">
                    <div class="analise-item-status">
                        <i class="fas fa-cog fa-spin analise-status-icon"></i>
                    </div>
                </div>
                <div class="analise-filename">${nomeSemExtensao}</div>
                <div class="analise-progress-bar"></div>
            `;
            
            listaAnalises.insertBefore(analiseItem, listaAnalises.firstChild);
        }


        // Função para finalizar análise
        function finalizarAnalise(frameName) {
            const analiseItem = document.querySelector(`[data-frame-name="${frameName}"]`);
            if (analiseItem) {
                analiseItem.classList.remove('analisando');
                analiseItem.classList.add('pronta');
                analiseItem.style.pointerEvents = 'auto';
                analiseItem.style.opacity = '1';
                
                // Atualizar ícone e adicionar onclick
                const statusIcon = analiseItem.querySelector('.analise-status-icon');
                if (statusIcon) {
                    statusIcon.className = 'fas fa-check-circle analise-status-icon';
                }
                
                // Adicionar onclick para mostrar análise
                analiseItem.onclick = () => {
                    const frameElement = document.querySelector(`[data-frame="${frameName}"]`);
                    const frameTime = frameElement ? frameElement.dataset.time : '00:00:00.000';
                    mostrarAnaliseModal({frameName: frameName, frameTime: frameTime});
                };
            }
        }

        // Função para tratar erro na análise
        function erroNaAnalise(frameName, mensagem) {
            const analiseItem = document.querySelector(`[data-frame-name="${frameName}"]`);
            if (analiseItem) {
                analiseItem.classList.remove('analisando');
                analiseItem.classList.add('erro');
                
                const statusIcon = analiseItem.querySelector('.analise-status-icon');
                if (statusIcon) {
                    statusIcon.className = 'fas fa-exclamation-triangle analise-status-icon';
                    statusIcon.style.color = '#dc3545';
                }
                
                // Manter item inacessível em caso de erro
                analiseItem.onclick = null;
            }
            
            alert(mensagem);
        }

        // Função para salvar JSON da análise
        function salvarAnaliseJSON(analiseData, jsonFileName, frameName, frameTime) {
            $.ajax({
                url: 'salvar_analise_json.php',
                method: 'POST',
                data: {
                    json_data: JSON.stringify(analiseData),
                    json_filename: jsonFileName,
                    frames_path: framesPath
                },
                success: function(response) {
                    // Marcar frame como analisado
                    marcarFrameComoAnalisado(frameName);
                    
                    // Finalizar análise (remover timer, tornar clicável)
                    finalizarAnalise(frameName);
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao salvar análise:', error);
                    alert('Erro ao salvar análise');
                }
            });
        }

        // Função para carregar análise existente
        async function carregarAnaliseExistente(frameName, frameTime) {
            const jsonFileName = frameName.replace(/\.[^/.]+$/, "") + "_(response).json";
            
            $.ajax({
                url: 'carregar_analise_json.php',
                method: 'POST',
                data: {
                    json_filename: jsonFileName,
                    frames_path: framesPath
                },
                success: function(response) {
                    try {
                        const analiseData = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        // Mostrar resultado no accordion
                        mostrarResultadoAnalise(analiseData, frameName);
                        
                    } catch (error) {
                        console.error('Erro ao carregar análise existente:', error);
                        alert('Erro ao carregar análise existente');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao carregar análise:', error);
                    alert('Erro ao carregar análise existente');
                }
            });
        }

        // Função para mostrar loading da análise
        function mostrarLoadingAnalise(show) {
            // Implementar indicador de loading se necessário
            if (show) {
                console.log('Iniciando análise...');
            } else {
                console.log('Análise finalizada');
            }
        }

        // Função para salvar análise no localStorage e interface
        function salvarAnalise(analiseData, frameName, frameTime) {
            const analiseId = `${frameName}_${Date.now()}`;
            
            analisesSalvas[analiseId] = {
                id: analiseId,
                frameName: frameName,
                frameTime: frameTime,
                timestamp: new Date().toISOString(),
                analise: analiseData
            };
            
            // Salvar no localStorage
            localStorage.setItem('analisesIA', JSON.stringify(analisesSalvas));
            
            // Adicionar à interface
            adicionarAnaliseNaLista(analisesSalvas[analiseId]);
        }


        // Função para mostrar análise no modal
        function mostrarAnaliseModal(analise) {
            // Buscar análise completa do servidor com feedback visual
            const frameName = analise.frameName || analise.id;
            const jsonFileName = frameName.replace(/\.[^/.]+$/, "") + "_(response).json";
            
            // Mostrar modal com loading primeiro usando instância única
            const modal = getResultadoAnaliseModalInstance();
            const modalLabel = document.getElementById('resultadoAnaliseModalLabel');
            const modalBody = document.getElementById('resultadoAnaliseModalBody');
            
            modalLabel.innerHTML = `<i class="fas fa-robot me-2"></i>Carregando Análise - ${frameName}`;
            modalBody.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="text-muted">Carregando análise da IA...</p>
                </div>
            `;
            
            modal.show();
            
            // Função para retry com timeout
            function loadAnalysisWithRetry(attempts = 0, maxAttempts = 3) {
                const timeout = attempts > 0 ? 2000 : 0; // Delay para retry
                
                setTimeout(() => {
                    console.log(`Tentando carregar análise: ${frameName} (tentativa ${attempts + 1})`);
                    
                    $.ajax({
                        url: 'carregar_analise_json.php',
                        method: 'POST',
                        timeout: 15000, // 15 segundos timeout
                        data: {
                            json_filename: jsonFileName,
                            frames_path: framesPath
                        },
                        success: function(response) {
                            try {
                                const analiseData = typeof response === 'string' ? JSON.parse(response) : response;
                                mostrarResultadoAnalise(analiseData, frameName);
                            } catch (error) {
                                console.error('Erro ao processar análise:', error);
                                modalBody.innerHTML = `
                                    <div class="text-center py-4">
                                        <i class="fas fa-exclamation-triangle text-danger mb-3" style="font-size: 2rem;"></i>
                                        <p class="text-danger">Erro ao processar análise da IA</p>
                                        <button class="btn btn-primary" onclick="mostrarAnaliseModal({frameName: '${frameName}', frameTime: '${analise.frameTime || '00:00:00.000'}'})">
                                            <i class="fas fa-redo me-2"></i>Tentar Novamente
                                        </button>
                                    </div>
                                `;
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Erro ao carregar análise:', status, error);
                            
                            if (attempts < maxAttempts) {
                                // Retry automático
                                modalBody.innerHTML = `
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-warning mb-3" role="status">
                                            <span class="visually-hidden">Tentando novamente...</span>
                                        </div>
                                        <p class="text-warning">Tentativa ${attempts + 1}/${maxAttempts} - Tentando novamente...</p>
                                    </div>
                                `;
                                loadAnalysisWithRetry(attempts + 1, maxAttempts);
                            } else {
                                // Mostrar erro após todas as tentativas
                                modalBody.innerHTML = `
                                    <div class="text-center py-4">
                                        <i class="fas fa-exclamation-triangle text-danger mb-3" style="font-size: 2rem;"></i>
                                        <p class="text-danger">Erro ao carregar análise da IA</p>
                                        <p class="text-muted small">Verifique sua conexão e tente novamente.</p>
                                        <button class="btn btn-primary" onclick="mostrarAnaliseModal({frameName: '${frameName}', frameTime: '${analise.frameTime || '00:00:00.000'}'})">
                                            <i class="fas fa-redo me-2"></i>Tentar Novamente
                                        </button>
                                    </div>
                                `;
                            }
                        }
                    });
                }, timeout);
            }
            
            // Iniciar carregamento
            loadAnalysisWithRetry();
        }

        // Função para marcar frame como analisado
        function marcarFrameComoAnalisado(frameName) {
            const frameElement = document.querySelector(`[data-frame="${frameName}"]`);
            if (frameElement) {
                frameElement.classList.add('frame-analisado');
            }
        }

        // Função para mostrar resultado da análise no accordion
        function mostrarResultadoAnalise(analiseData, frameName) {
            const accordionContent = criarAccordionContent(analiseData);
            
            document.getElementById('resultadoAnaliseModalLabel').innerHTML = 
                `<i class="fas fa-robot me-2"></i>Resultado da Análise - ${frameName}`;
            document.getElementById('resultadoAnaliseModalBody').innerHTML = accordionContent;
            
            const modal = getResultadoAnaliseModalInstance();
            modal.show();
        }

        // Função para criar conteúdo do accordion
        function criarAccordionContent(data) {
            let accordionHTML = `
                <div class="accordion" id="analiseAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#descricaoDetalhada">
                                <strong>Descrição Detalhada da Situação da Obra</strong>
                            </button>
                        </h2>
                        <div id="descricaoDetalhada" class="accordion-collapse collapse" data-bs-parent="#analiseAccordion">
                            <div class="accordion-body">
                                ${data["Descrição detalhada da situação da obra"] || 'Não disponível'}
                            </div>
                        </div>
                    </div>
            `;

            // Adicionar objetos relacionados
            if (data["Objetos relacionados com a obra"]) {
                Object.keys(data["Objetos relacionados com a obra"]).forEach((objeto, index) => {
                    const objetoData = data["Objetos relacionados com a obra"][objeto];
                    const accordionId = `objeto${index}`;
                    
                    accordionHTML += `
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#${accordionId}">
                                    <strong>${objeto}</strong>
                                </button>
                            </h2>
                            <div id="${accordionId}" class="accordion-collapse collapse" data-bs-parent="#analiseAccordion">
                                <div class="accordion-body">
                                    ${criarConteudoObjeto(objetoData)}
                                </div>
                            </div>
                        </div>
                    `;
                });
            }

            // Adicionar respostas das 24 questões
            if (data["Respostas das 24 questões analíticas"]) {
                accordionHTML += `
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#questoesAnaliticas">
                                <strong>Respostas das 24 Questões Analíticas</strong>
                            </button>
                        </h2>
                        <div id="questoesAnaliticas" class="accordion-collapse collapse" data-bs-parent="#analiseAccordion">
                            <div class="accordion-body">
                                ${criarConteudoQuestoes(data["Respostas das 24 questões analíticas"])}
                            </div>
                        </div>
                    </div>
                `;
            }

            accordionHTML += '</div>';
            return accordionHTML;
        }

        // Função para criar conteúdo de objeto
        function criarConteudoObjeto(objetoData) {
            let conteudo = '<div class="row">';
            
            Object.keys(objetoData).forEach(chave => {
                let valor = objetoData[chave];
                
                if (Array.isArray(valor)) {
                    valor = valor.join(', ');
                } else if (typeof valor === 'object' && valor !== null) {
                    valor = JSON.stringify(valor);
                }
                
                conteudo += `
                    <div class="col-md-6 mb-2">
                        <strong>${chave}:</strong> ${valor}
                    </div>
                `;
            });
            
            conteudo += '</div>';
            return conteudo;
        }

        // Função para criar conteúdo das questões
        function criarConteudoQuestoes(questoes) {
            let conteudo = '<div class="list-group">';
            
            Object.keys(questoes).forEach(numero => {
                conteudo += `
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Questão ${numero}</h6>
                        </div>
                        <p class="mb-1">${questoes[numero]}</p>
                    </div>
                `;
            });
            
            conteudo += '</div>';
            return conteudo;
        }


        // Atualizar função para adicionar análise na lista
        function adicionarAnaliseNaLista(analise) {
            const listaAnalises = document.getElementById('analises-ia-list');
            
            // Remover mensagem de placeholder se existir
            const placeholder = listaAnalises.querySelector('.text-center');
            if (placeholder) {
                placeholder.remove();
            }
            
            // Verificar se já existe na lista
            const existingItem = listaAnalises.querySelector(`[data-frame-name="${analise.frameName}"]`);
            if (existingItem) {
                existingItem.remove();
            }
            
            const analiseItem = document.createElement('div');
            analiseItem.className = 'analise-item pronta';
            analiseItem.setAttribute('data-frame-name', analise.frameName);
            analiseItem.onclick = () => mostrarAnaliseModal(analise);
            
            // Remover extensão do nome para mostrar
            const nomeSemExtensao = analise.frameName.replace(/\.[^/.]+$/, "");
            
            analiseItem.innerHTML = `
                <div class="analise-item-header">
                    <div class="analise-item-status">
                        <i class="fas fa-check-circle analise-status-icon"></i>
                    </div>
                </div>
                <div class="analise-filename">${nomeSemExtensao}</div>
            `;
            
            listaAnalises.insertBefore(analiseItem, listaAnalises.firstChild);
        }

        // Carregar análises salvas ao inicializar
        function carregarAnalisesSalvas() {
            // Buscar frames disponíveis no carrossel
            const frameItems = document.querySelectorAll('.frame-item');
            
            frameItems.forEach(frameItem => {
                const frameName = frameItem.dataset.frame;
                const frameTime = frameItem.dataset.time;
                
                // Verificar se existe análise para este frame
                const jsonFileName = frameName.replace(/\.[^/.]+$/, "") + "_(response).json";
                const jsonPath = framesPath + "/" + jsonFileName;
                
                $.ajax({
                    url: 'check_file_exists.php',
                    method: 'POST',
                    data: { file_path: jsonPath },
                    success: function(exists) {
                        if (exists === 'true') {
                            // Marcar frame como analisado
                            frameItem.classList.add('frame-analisado');
                            
                            // Carregar e adicionar análise à lista
                            $.ajax({
                                url: 'carregar_analise_json.php',
                                method: 'POST',
                                data: {
                                    json_filename: jsonFileName,
                                    frames_path: framesPath
                                },
                                success: function(response) {
                                    try {
                                        const analiseData = typeof response === 'string' ? JSON.parse(response) : response;
                                        
                                        // Adicionar à lista de análises
                                        adicionarAnaliseNaLista({
                                            frameName: frameName,
                                            frameTime: frameTime,
                                            timestamp: new Date().toISOString(),
                                            analise: analiseData
                                        });
                                    } catch (error) {
                                        console.error('Erro ao processar análise existente:', error);
                                    }
                                }
                            });
                        }
                    }
                });
            });
        }

        // Atualizar frame ativo e marcador no mapa
        function updateActiveFrame(currentTime) {
            const closest = findClosestFrame(currentTime);

            if (closest.frame) {
                // Remover classe active de todos os frames
                frameItems.forEach(f => f.classList.remove('active'));

                // Adicionar classe active ao frame mais próximo
                closest.frame.classList.add('active');

                // Fazer scroll para manter o frame visível
                closest.frame.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                    inline: 'center'
                });

                // Atualizar marcador ativo no mapa
                if (map && markers.length > 0) {
                    // Resetar todos os marcadores para o ícone padrão
                    markers.forEach((marker, index) => {
                        marker.setIcon(createDefaultIcon());
                        // Definir z-index padrão usando setZIndexOffset
                        marker.setZIndexOffset(2);
                        // Aplicar z-index também via CSS
                        setTimeout(() => {
                            const markerElement = marker.getElement();
                            if (markerElement) {
                                markerElement.style.zIndex = '2';
                            }
                        }, 10);
                    });

                    // Destacar marcador ativo
                    if (closest.marker) {
                        closest.marker.setIcon(createActiveIcon());
                        // Definir z-index maior para o marcador ativo
                        closest.marker.setZIndexOffset(3);
                        // Aplicar z-index também via CSS
                        setTimeout(() => {
                            const activeMarkerElement = closest.marker.getElement();
                            if (activeMarkerElement) {
                                activeMarkerElement.style.zIndex = '3';
                            }
                        }, 10);
                        currentActiveMarker = closest.marker;
                    }
                }
            }
        }

        // Sincronizar carrossel e mapa com vídeo durante reprodução
        videoPlayer.addEventListener('timeupdate', function() {
            updateActiveFrame(videoPlayer.currentTime);
        });

        // Clique em frame: pausar e ir para aquele tempo
        frameItems.forEach(frame => {
            frame.addEventListener('click', function() {
                const frameTime = timeToSeconds(this.dataset.time);
                videoPlayer.currentTime = frameTime;
                videoPlayer.pause();
                updateActiveFrame(frameTime);
            });
        });

        // Função para ajustar altura dos containers baseado no vídeo
        function ajustarAlturaContainers() {
            const videoContainer = document.getElementById('videoContainer');
            const mapContainer = document.getElementById('mapContainer');
            const iaContainer = document.getElementById('iaContainer');
            
            if (videoContainer && mapContainer && iaContainer) {
                // Usar requestAnimationFrame para aguardar o próximo ciclo de renderização
                requestAnimationFrame(() => {
                    const videoElement = document.getElementById('videoPlayer');
                    const videoContainerElement = videoContainer.querySelector('.video-container');
                    
                    let targetHeight;
                    
                    // Aguardar o vídeo carregar para obter a altura correta
                    if (videoElement && videoElement.videoHeight > 0 && videoElement.videoWidth > 0) {
                        // Usar a largura real do container do vídeo (não uma estimativa)
                        const videoWrapper = videoContainer.querySelector('.video-wrapper') || videoContainerElement;
                        const containerWidth = videoWrapper.offsetWidth || videoContainer.offsetWidth;
                        
                        // Calcular altura baseada na proporção real do vídeo
                        const videoRatio = videoElement.videoHeight / videoElement.videoWidth;
                        targetHeight = containerWidth * videoRatio;
                        
                        // Garantir altura mínima de 400px
                        targetHeight = Math.max(targetHeight, 400);
                        
                    } else if (videoElement && videoElement.offsetHeight > 0) {
                        targetHeight = videoElement.offsetHeight;
                        targetHeight = Math.max(targetHeight, 400);
                    } else {
                        // Altura padrão se o vídeo ainda não carregou
                        targetHeight = 500;
                        // Tentar novamente em breve
                        setTimeout(ajustarAlturaContainers, 200);
                    }
                    
                    console.log('Target height:', targetHeight);
                    
                    // Aplicar altura igual para todos os containers principais
                    videoContainer.style.height = targetHeight + 'px';
                    mapContainer.style.height = targetHeight + 'px';
                    iaContainer.style.height = targetHeight + 'px';
                    
                    // Aplicar altura também ao container interno do vídeo
                    if (videoContainerElement) {
                        videoContainerElement.style.height = targetHeight + 'px';
                    }
                    
                    // Aplicar altura para os controls-section dos outros containers
                    const mapControlsSection = mapContainer.querySelector('.controls-section');
                    const iaControlsSection = iaContainer.querySelector('.controls-section');
                    
                    if (mapControlsSection) {
                        mapControlsSection.style.height = targetHeight + 'px';
                    }
                    if (iaControlsSection) {
                        iaControlsSection.style.height = targetHeight + 'px';
                    }
                    
                    // Redesenhar o mapa se existir
                    if (map && map.invalidateSize) {
                        setTimeout(() => {
                            map.invalidateSize();
                        }, 100);
                    }
                });
            }
        }

        // Sistema de controle de loading
        let loadingTasks = 0;
        let completedTasks = 0;
        const loadingPromises = [];

        // Função para finalizar loading
        function finalizarLoading() {
            completedTasks++;
            console.log(`Loading: ${completedTasks}/${loadingTasks} tasks completed`);
            
            if (completedTasks >= loadingTasks) {
                // Aguardar um pouco mais para garantir que tudo está pronto
                setTimeout(() => {
                    esconderLoadingScreen();
                }, 500);
            }
        }

        // Função para esconder loading screen
        function esconderLoadingScreen() {
            const loadingScreen = document.getElementById('loadingScreen');
            const body = document.body;
            
            if (loadingScreen) {
                loadingScreen.classList.add('hidden');
                body.classList.remove('loading');
                
                // Remover loading screen do DOM após transição
                setTimeout(() => {
                    loadingScreen.remove();
                }, 500);
            }
        }

        // Função para aguardar carregamento do vídeo
        function aguardarVideoCarregar() {
            return new Promise((resolve) => {
                if (videoPlayer.readyState >= 3) { // HAVE_FUTURE_DATA
                    resolve();
                    return;
                }
                
                videoPlayer.addEventListener('canplay', resolve, { once: true });
                
                // Timeout de segurança
                setTimeout(resolve, 3000);
            });
        }

        // Função para aguardar carregamento do mapa
        function aguardarMapaCarregar() {
            return new Promise((resolve) => {
                if (document.getElementById('minimapa')) {
                    initMap();
                    // Aguardar um pouco para o mapa inicializar
                    setTimeout(resolve, 1000);
                } else {
                    resolve();
                }
            });
        }

        // Função para aguardar FontAwesome carregar
        function aguardarIconesCarregar() {
            return new Promise((resolve) => {
                const checkIcons = () => {
                    const icons = document.querySelectorAll('.fas, .far, .fab');
                    if (icons.length > 0) {
                        // Verificar se pelo menos alguns ícones estão visíveis
                        const visibleIcons = Array.from(icons).some(icon => {
                            const style = window.getComputedStyle(icon);
                            return style.fontFamily.includes('Font Awesome');
                        });
                        if (visibleIcons) {
                            resolve();
                            return;
                        }
                    }
                    setTimeout(checkIcons, 100);
                };
                
                // Timeout de segurança
                setTimeout(resolve, 2000);
                checkIcons();
            });
        }

        // Inicializar após carregamento da página
        window.addEventListener('load', async function() {
            console.log('Iniciando carregamento da página...');
            
            // Definir número de tasks para loading
            loadingTasks = 4; // vídeo + mapa + ícones + análises

            try {
                // Task 1: Aguardar vídeo carregar
                await aguardarVideoCarregar();
                console.log('Vídeo carregado');
                finalizarLoading();

                // Task 2: Aguardar mapa carregar
                await aguardarMapaCarregar();
                console.log('Mapa carregado');
                finalizarLoading();

                // Task 3: Aguardar ícones carregar
                await aguardarIconesCarregar();
                console.log('Ícones carregados');
                finalizarLoading();

                // Task 4: Carregar análises salvas (assíncrono)
                setTimeout(() => {
                    carregarAnalisesSalvas();
                    finalizarLoading();
                }, 500);

                // Configurar eventos após carregamento
                // Ajustar altura dos containers após carregar
                ajustarAlturaContainers();

                // Escutar eventos do vídeo para ajustar altura
                videoPlayer.addEventListener('loadedmetadata', ajustarAlturaContainers);
                videoPlayer.addEventListener('loadeddata', ajustarAlturaContainers);
                videoPlayer.addEventListener('canplay', ajustarAlturaContainers);
                
                // Ajustar também quando a janela redimensionar
                window.addEventListener('resize', ajustarAlturaContainers);
                
                // Detectar quando a aba volta a ficar ativa para melhorar performance
                document.addEventListener('visibilitychange', function() {
                    if (!document.hidden) {
                        // Aba voltou a ficar ativa - garantir que tudo esteja funcionando
                        console.log('Aba ativada - verificando conectividade');
                        
                        // Verificar se há modais em estado de loading que precisam ser retriados
                        const loadingModals = document.querySelectorAll('.spinner-border');
                        if (loadingModals.length > 0) {
                            console.log('Detectados elementos em loading - pode precisar de retry');
                        }
                    }
                });

                // Inicializar com o primeiro frame se o vídeo estiver no início
                setTimeout(() => {
                    if (videoPlayer.currentTime === 0 && frameItems.length > 0) {
                        updateActiveFrame(0);
                    }
                }, 1000);

            } catch (error) {
                console.error('Erro durante carregamento:', error);
                // Mesmo com erro, finalizar loading após timeout
                setTimeout(() => {
                    finalizarLoading();
                }, 2000);
            }
        });
    </script>
</body>

</html>