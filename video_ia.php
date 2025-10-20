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

        .fa-lightbulb {
            color: #ffd700 !important; /* Amarelo dourado */
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

        /* Engrenagem no frame-item durante análise */
        .frame-item.analisando::before {
            content: '\f013';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: -5px;
            left: -5px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            animation: spin 2s linear infinite;
            z-index: 10;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }

        /* Progress bar abaixo do nome do frame */
        .frame-progress-bar {
            position: absolute;
            bottom: -3px;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            border-radius: 0 0 8px 8px;
            transition: width 1s linear;
            width: 0%;
        }

        .frame-item.analisando .frame-progress-bar {
            animation: progressBar 50s linear forwards;
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

        /* Layout dos containers */
        #videoContainer {
            flex: 0 0 auto;
            width: calc(48% - 5px);
            height: auto;
            padding: 0;
            min-height: 400px;
        }

        /* Wrapper para painéis laterais */
        #sidePanelsWrapper {
            flex: 1;
            height: auto;
            min-height: 400px;
            display: flex;
            flex-direction: row;
            align-items: stretch;
            position: relative;
            width: auto;
            padding-right: 0 !important;
        }

        #mapContainer {
            flex: 1;
            height: 100%;
            padding: 0;
            min-height: 400px;
            min-width: 200px;
            max-width: none;
            transition: all 0.3s ease;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            
            border-radius: 20px;
        }

        #iaContainer {
            flex: 1;
            height: 100%;
            padding: 0;
            min-height: 400px;
            min-width: 200px;
            max-width: none;
            transition: all 0.3s ease;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border-radius: 20px;
        }

        /* Splitter entre mapa e análises */
        .splitter {
            width: 12px;
            flex: 0 0 12px;
            cursor: col-resize;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            user-select: none;
            transition: all 0.3s ease;
        }

        .splitter:hover {
            width: 16px;
        }

        .splitter-handle {
            width: 6px;
            height: 80px;
            background: var(--primary-color);
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            opacity: 0.7;
            transition: all 0.3s ease;
            box-shadow: 0 0 10px rgba(0, 188, 212, 0.4);
        }

        .splitter:hover .splitter-handle {
            opacity: 1;
            box-shadow: 0 0 15px rgba(0, 188, 212, 0.7);
            transform: scale(1.1);
        }

        /* Estados quando painéis estão minimizados */
        #mapContainer.hidden {
            flex: 0 0 0 !important;
            min-width: 0 !important;
            width: 0 !important;
            opacity: 0;
            overflow: hidden;
            pointer-events: none;
            display: none !important;
        }

        #iaContainer.hidden {
            flex: 0 0 0 !important;
            min-width: 0 !important;
            width: 0 !important;
            opacity: 0;
            overflow: hidden;
            pointer-events: none;
            display: none !important;
        }

        /* Forçar layout horizontal */
        #sidePanelsWrapper > * {
            flex-shrink: 0;
        }

        #sidePanelsWrapper #mapContainer:not(.hidden),
        #sidePanelsWrapper #iaContainer:not(.hidden) {
            display: flex !important;
        }

        /* Container principal flexível */
        .row.three-columns {
            display: flex;
            align-items: stretch;
            gap: 10px;
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
        #videoContainer {
            display: flex;
            flex-direction: column;
        }

        #sidePanelsWrapper {
            display: flex;
            flex-direction: row;
            align-items: stretch;
        }

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
            padding: 15px;
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

        /* Botão de análise */
        .btn-analisar-frame {
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 15px;
            box-shadow: 0 5px 20px rgba(0, 188, 212, 0.3);
        }

        .btn-analisar-frame:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 188, 212, 0.5);
            color: white;
        }

        .btn-analisar-frame:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: 0 5px 20px rgba(0, 188, 212, 0.3);
        }

        /* Conteúdo da análise IA */
        .analise-content {
            color: var(--text-light);
            font-size: 0.85rem;
            line-height: 1.5;
        }

        /* Accordion da análise */
        .analise-accordion .accordion-button {
            background-color: rgba(0, 188, 212, 0.1);
            color: black !important;
            border: 1px solid rgba(0, 188, 212, 0.2);
            font-size: 0.8rem;
            padding: 8px 12px;
        }

        .analise-accordion .accordion-button:not(.collapsed) {
            background-color: rgba(0, 188, 212, 0.2);
            color: black !important;
            box-shadow: none;
        }

        .analise-accordion .accordion-button strong {
            color: black !important;
        }

        .analise-accordion .accordion-button:focus {
            box-shadow: 0 0 0 0.25rem rgba(0, 188, 212, 0.25);
        }

        .analise-accordion .accordion-body {
            background-color: rgba(255, 255, 255, 0.05);
            color: black !important;
            font-size: 0.8rem;
            padding: 12px;
        }

        .analise-accordion .accordion-body * {
            color: black !important;
        }

        .analise-accordion .accordion-body strong {
            color: black !important;
            font-weight: bold;
        }

        .analise-accordion .accordion-body span,
        .analise-accordion .accordion-body div,
        .analise-accordion .accordion-body p,
        .analise-accordion .accordion-body small,
        .analise-accordion .accordion-body .text-muted {
            color: black !important;
        }

        .analise-accordion .accordion-button * {
            color: black !important;
        }

        .analise-accordion .accordion-item {
            border: 1px solid rgba(0, 188, 212, 0.2);
            margin-bottom: 8px;
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

            <!-- Wrapper para Mapa e Análises IA -->
            <div id="sidePanelsWrapper">
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

                <!-- Splitter -->
                <div id="splitter" class="splitter">
                    <div class="splitter-handle">
                        <i class="fas fa-grip-vertical"></i>
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
                                    <div class="frame-progress-bar"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal para Visualização da Imagem (mantido apenas este) -->
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
        let currentFrameName = ''; // Frame atualmente selecionado

        // Variáveis para controle de zoom e movimento da imagem
        let imageScale = 1;
        let imageOffset = { x: 0, y: 0 };
        let isDragging = false;
        let dragStart = { x: 0, y: 0 };
        let currentImagePath = '';
        let currentFrameTime = '';

        // Função para abrir frame em modal com zoom e movimento
        function openFrameFullscreen(imagePath, frameName) {
            currentImagePath = imagePath;
            const modalFrameName = frameName;
            
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
            const jsonFileName = frameName.replace(/\.[^/.]+$/, "") + ".json";
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
            
            // Iniciar análise (adicionar classe ao frame e mostrar loading)
            iniciarAnalise(frameName, frameTime);
            
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
                        const jsonFileName = frameName.replace(/\.[^/.]+$/, "") + ".json";
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

        // Função para iniciar análise (sem mais cards)
        function iniciarAnalise(frameName, frameTime) {
            // Adicionar classe analisando ao frame-item
            const frameElement = document.querySelector(`[data-frame="${frameName}"]`);
            if (frameElement) {
                frameElement.classList.add('analisando');
            }
            
            // Mostrar loading na div de análises
            mostrarLoadingAnalise(frameName);
        }

        // Função para mostrar loading durante análise
        function mostrarLoadingAnalise(frameName) {
            const listaAnalises = document.getElementById('analises-ia-list');
            const nomeSemExtensao = frameName.replace(/\.[^/.]+$/, "");
            
            listaAnalises.innerHTML = `
                <div class="text-center" style="padding: 40px 20px;">
                    <i class="fas fa-cog fa-spin" style="font-size: 2rem; margin-bottom: 15px; color: var(--primary-color);"></i>
                    <p style="color: var(--text-light); font-weight: 600;">Analisando Frame: ${nomeSemExtensao}</p>
                    <p style="color: rgba(227, 242, 253, 0.7); font-size: 0.9rem;">A IA está processando a imagem...</p>
                </div>
            `;
        }

        // Função para finalizar análise
        function finalizarAnalise(frameName) {
            // Remover classe analisando do frame-item
            const frameElement = document.querySelector(`[data-frame="${frameName}"]`);
            if (frameElement) {
                frameElement.classList.remove('analisando');
            }
            
            // Recarregar análise do frame ativo
            atualizarAnaliseFrameAtivo(frameName);
        }

        // Função para tratar erro na análise
        function erroNaAnalise(frameName, mensagem) {
            // Remover classe analisando do frame-item
            const frameElement = document.querySelector(`[data-frame="${frameName}"]`);
            if (frameElement) {
                frameElement.classList.remove('analisando');
            }
            
            // Mostrar botão de analisar novamente
            mostrarBotaoAnalisar(frameName);
            
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
            const jsonFileName = frameName.replace(/\.[^/.]+$/, "") + ".json";
            
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




        // Função para marcar frame como analisado
        function marcarFrameComoAnalisado(frameName) {
            const frameElement = document.querySelector(`[data-frame="${frameName}"]`);
            if (frameElement) {
                frameElement.classList.add('frame-analisado');
            }
        }

        // Função para atualizar análise do frame ativo
        function atualizarAnaliseFrameAtivo(frameName) {
            if (currentFrameName === frameName && currentFrameName !== '') {
                return; // Já está mostrando este frame
            }
            
            currentFrameName = frameName;
            const jsonFileName = frameName.replace(/\.[^/.]+$/, "") + ".json";
            const fullPath = framesPath + "/" + jsonFileName;
            
            // Verificar se análise existe
            $.ajax({
                url: 'check_file_exists.php',
                method: 'POST',
                data: { file_path: fullPath },
                success: function(exists) {
                    if (exists === 'true') {
                        // Carregar e mostrar análise
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
                                    mostrarResultadoAnalise(analiseData, frameName);
                                } catch (error) {
                                    console.error('Erro ao processar análise:', error);
                                    mostrarBotaoAnalisar(frameName);
                                }
                            },
                            error: function(xhr, status, error) {
                                mostrarBotaoAnalisar(frameName);
                            }
                        });
                    } else {
                        // Mostrar botão para analisar
                        mostrarBotaoAnalisar(frameName);
                    }
                },
                error: function(xhr, status, error) {
                    mostrarBotaoAnalisar(frameName);
                }
            });
        }

        // Função para mostrar botão de análise
        function mostrarBotaoAnalisar(frameName) {
            const listaAnalises = document.getElementById('analises-ia-list');
            const frameElement = document.querySelector(`[data-frame="${frameName}"]`);
            const imagePath = frameElement ? framesPath + "/" + frameName : '';
            const frameTime = frameElement ? frameElement.dataset.time : '00:00:00.000';
            
            // Remover extensão do nome para mostrar
            const nomeSemExtensao = frameName.replace(/\.[^/.]+$/, "");
            
            listaAnalises.innerHTML = `
                <button class="btn-analisar-frame" onclick="analyzeFrameWithAI('${imagePath}', '${frameName}', '${frameTime}')">
                    <i class="fas fa-lightbulb me-2"></i>
                    Analisar Frame: ${nomeSemExtensao}
                </button>
                <div class="text-center" style="padding: 20px; font-size: 0.9rem; color: rgba(227, 242, 253, 0.6);">
                    <i class="fas fa-robot" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.3;"></i>
                    <p>Este frame ainda não foi analisado pela IA</p>
                </div>
            `;
        }

        // Função para mostrar resultado da análise diretamente na div de análises IA
        function mostrarResultadoAnalise(analiseData, frameName) {
            const accordionContent = criarAccordionContent(analiseData);
            const listaAnalises = document.getElementById('analises-ia-list');
            
            // Limpar conteúdo anterior
            listaAnalises.innerHTML = accordionContent;
        }

        // Função para criar conteúdo do accordion
        function criarAccordionContent(data) {
            let accordionHTML = `
                <div class="accordion analise-accordion" id="analiseAccordion">
            `;

            // Seção 1: Contexto Geral
            if (data.contexto_geral) {
                accordionHTML += `
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#contextoGeral">
                                <strong>Contexto Geral da Obra</strong>
                            </button>
                        </h2>
                        <div id="contextoGeral" class="accordion-collapse collapse" data-bs-parent="#analiseAccordion">
                            <div class="accordion-body">
                                ${criarConteudoContexto(data.contexto_geral)}
                            </div>
                        </div>
                    </div>
                `;
            }

            // Seção 2: Respostas Analíticas
            if (data.respostas_analiticas && Array.isArray(data.respostas_analiticas)) {
                accordionHTML += `
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#questoesAnaliticas">
                                <strong>Análise Analítica (${data.respostas_analiticas.length} questões)</strong>
                            </button>
                        </h2>
                        <div id="questoesAnaliticas" class="accordion-collapse collapse" data-bs-parent="#analiseAccordion">
                            <div class="accordion-body">
                                ${criarConteudoQuestoesAnaliticas(data.respostas_analiticas)}
                            </div>
                        </div>
                    </div>
                `;
            }

            // Seção 3: Avaliação Global
            if (data.avaliacao_global) {
                accordionHTML += `
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#avaliacaoGlobal">
                                <strong>Avaliação Global</strong>
                            </button>
                        </h2>
                        <div id="avaliacaoGlobal" class="accordion-collapse collapse" data-bs-parent="#analiseAccordion">
                            <div class="accordion-body">
                                ${criarConteudoAvaliacao(data.avaliacao_global)}
                            </div>
                        </div>
                    </div>
                `;
            }

            accordionHTML += '</div>';
            return accordionHTML;
        }

        // Função para criar conteúdo do contexto geral
        function criarConteudoContexto(contexto) {
            let conteudo = '<div class="row">';
            
            Object.keys(contexto).forEach(chave => {
                let valor = contexto[chave];
                let labelChave = chave.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                
                conteudo += `
                    <div class="col-md-6 mb-3">
                        <div class="d-flex">
                            <strong class="me-2">${labelChave}:</strong>
                            <span>${valor}</span>
                        </div>
                    </div>
                `;
            });
            
            conteudo += '</div>';
            return conteudo;
        }

        // Função para criar conteúdo das respostas analíticas
        function criarConteudoQuestoesAnaliticas(respostasAnaliticas) {
            let conteudo = `
                <div class="accordion accordion-flush" id="questoesFlush">
                    <div class="text-muted small mb-3">
                        <i class="fas fa-info-circle"></i> 
                        Clique em cada pergunta para ver a resposta detalhada
                    </div>
            `;
            
            respostasAnaliticas.forEach((item, index) => {
                const accordionId = `pergunta${item.id_pergunta}`;
                const confianca = item.resposta.toLowerCase().includes('confiança: alta') ? 'success' : 
                                 item.resposta.toLowerCase().includes('confiança: média') ? 'warning' : 
                                 item.resposta.toLowerCase().includes('confiança: baixa') ? 'danger' : 'secondary';
                
                conteudo += `
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#${accordionId}">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <span class="me-2">
                                        <strong>Q${item.id_pergunta}:</strong> ${item.pergunta.substring(0, 60)}${item.pergunta.length > 60 ? '...' : ''}
                                    </span>
                                    <span class="badge bg-${confianca} ms-2">
                                        <i class="fas fa-circle me-1"></i>
                                        ${confianca === 'success' ? 'Alta' : confianca === 'warning' ? 'Média' : confianca === 'danger' ? 'Baixa' : 'N/A'}
                                    </span>
                                </div>
                            </button>
                        </h2>
                        <div id="${accordionId}" class="accordion-collapse collapse" data-bs-parent="#questoesFlush">
                            <div class="accordion-body">
                                <div class="mb-2">
                                    <strong>Pergunta:</strong>
                                    <p class="mb-3 mt-1">${item.pergunta}</p>
                                </div>
                                <div>
                                    <strong>Resposta:</strong>
                                    <p class="mb-1 mt-1">${item.resposta}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            conteudo += '</div>';
            return conteudo;
        }

        // Função para criar conteúdo da avaliação global
        function criarConteudoAvaliacao(avaliacao) {
            let conteudo = `
                <div class="row">
                    <div class="col-12 mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-tachometer-alt"></i> Notas de Avaliação (0-10)
                        </h6>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card border-success h-100">
                            <div class="card-body text-center">
                                <h6 class="card-title text-success">Organização</h6>
                                <h2 class="text-success">${avaliacao.nota_organizacao_0a10}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card border-info h-100">
                            <div class="card-body text-center">
                                <h6 class="card-title text-info">Qualidade</h6>
                                <h2 class="text-info">${avaliacao.nota_qualidade_0a10}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card border-warning h-100">
                            <div class="card-body text-center">
                                <h6 class="card-title text-warning">Segurança</h6>
                                <h2 class="text-warning">${avaliacao.nota_segurança_0a10}</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card border-primary h-100">
                            <div class="card-body text-center">
                                <h6 class="card-title text-primary">Ambiental</h6>
                                <h2 class="text-primary">${avaliacao.nota_ambiental_0a10}</h2>
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="my-4">
                <div class="row">
                    <div class="col-12">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-clipboard-check"></i> Conformidade e Resumo
                        </h6>
                        <div class="alert alert-${avaliacao.grau_conformidade_normas.toLowerCase().includes('conforme') ? 'success' : 'warning'}" role="alert">
                            <strong>Conformidade com Normas:</strong> ${avaliacao.grau_conformidade_normas}
                        </div>
                        <div class="mb-3">
                            <strong>Resumo da Execução:</strong>
                            <p class="mt-2">${avaliacao.resumo_execucao}</p>
                        </div>
                        <div class="text-muted">
                            <small>
                                <i class="fas fa-shield-alt"></i> 
                                <strong>Confiança Global:</strong> 
                                <span class="badge bg-${avaliacao.confianca_global === 'alta' ? 'success' : avaliacao.confianca_global === 'média' ? 'warning' : 'danger'}">
                                    ${avaliacao.confianca_global.charAt(0).toUpperCase() + avaliacao.confianca_global.slice(1)}
                                </span>
                            </small>
                        </div>
                    </div>
                </div>
            `;
            
            return conteudo;
        }



        // Carregar análises salvas ao inicializar
        function carregarAnalisesSalvas() {
            // Buscar frames disponíveis no carrossel
            const frameItems = document.querySelectorAll('.frame-item');
            
            frameItems.forEach(frameItem => {
                const frameName = frameItem.dataset.frame;
                
                // Verificar se existe análise para este frame
                const jsonFileName = frameName.replace(/\.[^/.]+$/, "") + ".json";
                const jsonPath = framesPath + "/" + jsonFileName;
                
                $.ajax({
                    url: 'check_file_exists.php',
                    method: 'POST',
                    data: { file_path: jsonPath },
                    success: function(exists) {
                        if (exists === 'true') {
                            // Marcar frame como analisado
                            frameItem.classList.add('frame-analisado');
                        }
                    }
                });
            });
            
            // Configurar análise inicial do primeiro frame ativo ou disponível
            setTimeout(() => {
                let frameToShow = document.querySelector('.frame-item.active');
                
                // Se não há frame ativo, usar o primeiro frame disponível
                if (!frameToShow) {
                    frameToShow = document.querySelector('.frame-item:first-child');
                    if (frameToShow) {
                        frameToShow.classList.add('active');
                    }
                }
                
                if (frameToShow) {
                    atualizarAnaliseFrameAtivo(frameToShow.dataset.frame);
                }
            }, 1000);
        }

        // Atualizar frame ativo e marcador no mapa
        function updateActiveFrame(currentTime) {
            const closest = findClosestFrame(currentTime);

            if (closest.frame) {
                const frameName = closest.frame.dataset.frame;
                
                // Remover classe active de todos os frames
                frameItems.forEach(f => f.classList.remove('active'));

                // Adicionar classe active ao frame mais próximo
                closest.frame.classList.add('active');

                // Atualizar análise do frame ativo
                atualizarAnaliseFrameAtivo(frameName);

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

        // Função para inicializar o splitter entre mapa e análises IA
        function inicializarSplitter() {
            const splitter = document.getElementById('splitter');
            const mapContainer = document.getElementById('mapContainer');
            const iaContainer = document.getElementById('iaContainer');
            
            if (!splitter || !mapContainer || !iaContainer) {
                return;
            }
            
            let isDragging = false;
            let startX = 0;
            let startMapFlex = 0;
            let startIaFlex = 0;
            
            // Eventos de mouse
            splitter.addEventListener('mousedown', function(e) {
                isDragging = true;
                startX = e.clientX;
                
                // Obter flex atual dos containers (1 = flex: 1)
                const mapFlex = getComputedStyle(mapContainer).flex;
                const iaFlex = getComputedStyle(iaContainer).flex;
                
                startMapFlex = parseFloat(mapFlex) || 1;
                startIaFlex = parseFloat(iaFlex) || 1;
                
                document.body.style.cursor = 'col-resize';
                document.body.style.userSelect = 'none';
                
                e.preventDefault();
            });
            
            // Evento global de mousemove
            document.addEventListener('mousemove', function(e) {
                if (!isDragging) return;
                
                const wrapper = document.getElementById('sidePanelsWrapper');
                const wrapperRect = wrapper.getBoundingClientRect();
                const wrapperWidth = wrapper.offsetWidth;
                const splitterWidth = 12;
                const availableWidth = wrapperWidth - splitterWidth;
                
                // Calcular posição relativa dentro do wrapper
                const relativeX = e.clientX - wrapperRect.left;
                const minWidth = 150; // Largura mínima antes de esconder
                
                // Limitar a posição dentro dos limites do wrapper
                const clampedX = Math.max(minWidth, Math.min(availableWidth - minWidth, relativeX));
                
                const newMapWidth = clampedX;
                const newIaWidth = availableWidth - clampedX;
                
                // Verificar se deve esconder algum painel
                if (newMapWidth <= minWidth + 50) { // 50px de tolerância
                    // Esconder mapa
                    if (!mapContainer.classList.contains('hidden')) {
                        mapContainer.classList.add('hidden');
                        iaContainer.style.flex = '1';
                        iaContainer.style.display = 'flex';
                    }
                } else if (newIaWidth <= minWidth + 50) {
                    // Esconder análises IA
                    if (!iaContainer.classList.contains('hidden')) {
                        iaContainer.classList.add('hidden');
                        mapContainer.style.flex = '1';
                        mapContainer.style.display = 'flex';
                    }
                } else {
                    // Mostrar ambos os painéis e ajustar proporções
                    mapContainer.classList.remove('hidden');
                    iaContainer.classList.remove('hidden');
                    mapContainer.style.display = 'flex';
                    iaContainer.style.display = 'flex';
                    
                    const mapRatio = newMapWidth / availableWidth;
                    const iaRatio = newIaWidth / availableWidth;
                    
                    mapContainer.style.flex = mapRatio.toString();
                    iaContainer.style.flex = iaRatio.toString();
                }
                
                // Redesenhar o mapa se existir
                if (typeof map !== 'undefined' && map && map.invalidateSize) {
                    setTimeout(() => {
                        map.invalidateSize();
                    }, 10);
                }
            });
            
            // Evento global de mouseup
            document.addEventListener('mouseup', function() {
                if (isDragging) {
                    isDragging = false;
                    document.body.style.cursor = '';
                    document.body.style.userSelect = '';
                    
                    // Redesenhar o mapa após o redimensionamento
                    if (typeof map !== 'undefined' && map && map.invalidateSize) {
                        setTimeout(() => {
                            map.invalidateSize();
                        }, 100);
                    }
                }
            });
            
            // Prevenir seleção de texto durante o arraste
            splitter.addEventListener('selectstart', function(e) {
                e.preventDefault();
            });
            
            // Adicionar botões para mostrar/esconder painéis quando minimizados
            splitter.addEventListener('dblclick', function() {
                // Restaurar ambos os painéis
                mapContainer.classList.remove('hidden');
                iaContainer.classList.remove('hidden');
                mapContainer.style.flex = '1';
                iaContainer.style.flex = '1';
                mapContainer.style.display = 'flex';
                iaContainer.style.display = 'flex';
            });
        }

        // Função para ajustar altura dos containers baseado no vídeo
        function ajustarAlturaContainers() {
            const videoContainer = document.getElementById('videoContainer');
            const sidePanelsWrapper = document.getElementById('sidePanelsWrapper');
            const mapContainer = document.getElementById('mapContainer');
            const iaContainer = document.getElementById('iaContainer');
            
            if (videoContainer && sidePanelsWrapper && mapContainer && iaContainer) {
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
                    sidePanelsWrapper.style.height = targetHeight + 'px';
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

                // Inicializar splitter entre mapa e análises IA
                inicializarSplitter();

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