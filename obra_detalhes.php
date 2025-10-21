<?php
session_start();
require_once 'connection.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_copasa'])) {
    header('Location: index.php');
    exit();
}

$usuario = $_SESSION['user_copasa'];

// Verificar se foi passado o ID da obra
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$obra_id = intval($_GET['id']);

// Buscar dados da obra
try {
    $stmt = $conn->prepare("SELECT * FROM obras WHERE id = ?");
    $stmt->execute([$obra_id]);
    $obra = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$obra) {
        header('Location: dashboard.php?error=' . urlencode('Obra não encontrada.'));
        exit();
    }
} catch (PDOException $e) {
    header('Location: dashboard.php?error=' . urlencode('Erro ao carregar obra.'));
    exit();
}

// Caminho dos documentos
$documentos_path = "documentos/" . $obra['cidade'] . "/" . $obra['id'];

// Criar diretório se não existir
if (!file_exists($documentos_path)) {
    mkdir($documentos_path, 0755, true);
}

// Listar documentos
$documentos = [];
if (is_dir($documentos_path)) {
    $files = scandir($documentos_path);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $documentos[] = [
                'nome' => $file,
                'caminho' => $documentos_path . '/' . $file,
                'tamanho' => filesize($documentos_path . '/' . $file),
                'data' => date('d/m/Y H:i', filemtime($documentos_path . '/' . $file))
            ];
        }
    }
}

// Caminho dos vídeos
$videos_path = "evidencias/" . $obra['cidade'] . "/Videos";

// Criar diretórios se não existirem
if (!file_exists("evidencias/" . $obra['cidade'] . "/Fotos")) {
    mkdir("evidencias/" . $obra['cidade'] . "/Fotos", 0755, true);
}
if (!file_exists($videos_path)) {
    mkdir($videos_path, 0755, true);
}

// Função para carregar metadados dos vídeos do arquivo JSON
function loadVideoMetadata($cidade) {
    $metadataPath = "evidencias/{$cidade}/Videos/metadados.json";
    
    if (!file_exists($metadataPath)) {
        return [];
    }
    
    $jsonContent = file_get_contents($metadataPath);
    if ($jsonContent === false) {
        return [];
    }
    
    $metadata = json_decode($jsonContent, true);
    return $metadata ?: [];
}

// Função para contar quantos frames existem para um vídeo
function contarFrames($nomeVideo, $cidade) {
    $nomeVideoSemExtensao = pathinfo($nomeVideo, PATHINFO_FILENAME);
    $framesPath = "evidencias/{$cidade}/frames/{$nomeVideoSemExtensao}(frames)";
    
    if (!is_dir($framesPath)) {
        return 0;
    }
    
    // Contar arquivos de imagem (JPG, JPEG)
    $files = scandir($framesPath);
    $contador = 0;
    
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $extensao = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($extensao === 'jpg' || $extensao === 'jpeg') {
                $contador++;
            }
        }
    }
    
    return $contador;
}

// Função para verificar se existem frames para um vídeo (manter compatibilidade)
function verificarFrames($nomeVideo, $cidade) {
    return contarFrames($nomeVideo, $cidade) > 0;
}

// Função para verificar se um vídeo está analisado
function verificarAnalisado($nomeVideo, $cidade) {
    // Se tiver 0 frames, automaticamente não está analisado
    $quantidadeFrames = contarFrames($nomeVideo, $cidade);
    if ($quantidadeFrames === 0) {
        return false;
    }
    
    $nomeVideoSemExtensao = pathinfo($nomeVideo, PATHINFO_FILENAME);
    $framesPath = "evidencias/{$cidade}/frames/{$nomeVideoSemExtensao}(frames)";
    
    if (!is_dir($framesPath)) {
        return false;
    }
    
    // Buscar por arquivos JPG e verificar se existe JSON correspondente
    $files = scandir($framesPath);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $extensao = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($extensao === 'jpg' || $extensao === 'jpeg') {
                $nomeArquivo = pathinfo($file, PATHINFO_FILENAME);
                $jsonFile = $framesPath . '/' . $nomeArquivo . '.json';
                
                if (file_exists($jsonFile)) {
                    return true; // Encontrou pelo menos um conjunto JPG + JSON
                }
            }
        }
    }
    
    return false;
}

// Função para listar vídeos recursivamente
function listarVideos($dir, $cidade)
{
    $videos = [];
    $extensoes_video = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'm4v', 'mpeg', 'mpg'];

    if (!is_dir($dir)) {
        return $videos;
    }

    // Carregar metadados do JSON uma única vez
    $videoMetadata = loadVideoMetadata($cidade);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $extensao = strtolower($file->getExtension());
            if (in_array($extensao, $extensoes_video)) {
                $nomeVideo = $file->getFilename();
                
                // Buscar dados do vídeo no JSON
                $metadataVideos = $videoMetadata[$nomeVideo] ?? [];
                
                // Extrair dados do JSON ou usar valores padrão
                $tamanhoBytes = $metadataVideos['tamanho'] ?? $file->getSize();
                $dataExibicao = 'S/D';
                $latitude = 'S/D';
                $longitude = 'S/D';
                $duracao = 'S/D';
                
                // Processar data do JSON
                if (!empty($metadataVideos['data'])) {
                    try {
                        $dataObj = new DateTime($metadataVideos['data']);
                        $dataExibicao = $dataObj->format('d/m/Y H:i');
                    } catch (Exception $e) {
                        $dataExibicao = date('d/m/Y H:i', $file->getMTime());
                    }
                } else {
                    $dataExibicao = date('d/m/Y H:i', $file->getMTime());
                }
                
                // Extrair latitude e longitude do JSON
                if (isset($metadataVideos['latitude']) && $metadataVideos['latitude'] !== null) {
                    $latitude = $metadataVideos['latitude'];
                }
                if (isset($metadataVideos['longitude']) && $metadataVideos['longitude'] !== null) {
                    $longitude = $metadataVideos['longitude'];
                }
                
                // Extrair duração do JSON
                if (!empty($metadataVideos['tempo'])) {
                    $duracao = $metadataVideos['tempo'];
                }
                
                // Verificar quantidade de frames e se está analisado
                $quantidadeFrames = contarFrames($nomeVideo, $cidade);
                $analisado = verificarAnalisado($nomeVideo, $cidade);
                
                $videos[] = [
                    'nome' => $nomeVideo,
                    'caminho' => $file->getPathname(),
                    'caminho_relativo' => str_replace('\\', '/', $file->getPathname()),
                    'tamanho' => $tamanhoBytes,
                    'data' => $dataExibicao,
                    'extensao' => $extensao,
                    'data_arquivo' => date('d/m/Y H:i', $file->getMTime()), // Manter data do arquivo para referência
                    'data_metadata' => !empty($metadataVideos['data']) ? $metadataVideos['data'] : null,
                    'frames' => $quantidadeFrames,
                    'analisado' => $analisado,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'duracao' => $duracao
                ];
            }
        }
    }

    return $videos;
}

// Listar vídeos
$videos = listarVideos($videos_path, $obra['cidade']);

// Caminho dos projetos (fiscalizações)
$projetos_path = "projetos/" . $obra['cidade'];

// Criar diretório se não existir
if (!file_exists($projetos_path)) {
    mkdir($projetos_path, 0755, true);
}

// Listar pastas de projetos (apenas primeiro nível)
$projetos = [];
if (is_dir($projetos_path)) {
    $items = scandir($projetos_path);
    foreach ($items as $item) {
        if ($item != '.' && $item != '..') {
            $item_path = $projetos_path . '/' . $item;
            if (is_dir($item_path)) {
                $projetos[] = [
                    'nome' => $item,
                    'data' => date('d/m/Y H:i', filemtime($item_path))
                ];
            }
        }
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Obra - COPASA</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="dataTables.dataTables.min.css" rel="stylesheet">

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
            padding: 15px 0;
        }

        .navbar-brand {
            color: var(--text-light);
            font-weight: 700;
            font-size: 1.5rem;
            text-decoration: none;
        }

        .navbar-brand:hover {
            color: var(--accent-color);
        }

        .navbar-nav .nav-link {
            color: var(--text-light);
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }

        .navbar-nav .nav-link:hover {
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        .navbar-nav .nav-link .fa-cog {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .navbar-nav .nav-link:hover .fa-cog {
            transform: rotate(90deg);
        }

        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }

        .navbar-nav .nav-link:hover::after {
            width: 100%;
        }

        /* Container */
        .container-fluid {
            padding: 30px;
        }

        /* Header */
        .page-header {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            box-shadow:
                0 25px 45px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .page-header h1 {
            color: var(--text-light);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 188, 212, 0.3);
        }

        .page-header h1 i {
            color: var(--primary-color);
        }

        .page-header .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 0;
        }

        .breadcrumb-item a {
            color: var(--accent-color);
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: var(--text-light);
        }

        /* Card de Dados */
        .dados-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            box-shadow:
                0 25px 45px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .dados-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .dados-card h3 {
            color: var(--text-light);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-shadow: 0 2px 10px rgba(0, 188, 212, 0.3);
        }

        .dados-card h3 i {
            color: var(--primary-color);
        }

        .form-label {
            color: var(--accent-color);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-control {
            background: rgba(0, 188, 212, 0.05);
            border: 1px solid rgba(0, 188, 212, 0.2);
            color: var(--text-light);
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:read-only {
            background: rgba(0, 188, 212, 0.05);
            border: 1px solid rgba(0, 188, 212, 0.2);
            color: var(--text-light);
            cursor: default;
        }

        .form-control:read-only:focus {
            background: rgba(0, 188, 212, 0.08);
            border-color: var(--primary-color);
            box-shadow: 0 0 15px rgba(0, 188, 212, 0.3);
            outline: none;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        /* Puxador de Gaveta */
        .puxador-gaveta {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .puxador-gaveta i {
            color: white;
            font-size: 1.3rem;
        }

        .dados-card {
            position: relative;
            margin-bottom: 50px;
        }

        /* Animação dos dados expandíveis */
        .dados-expandiveis {
            overflow: hidden;
            transition: opacity 0.4s ease, max-height 0.4s ease;
            opacity: 0;
            max-height: 0;
        }

        .dados-expandiveis.show {
            opacity: 1;
            max-height: 2000px;
            margin-top: 10px;
        }

        .dados-expandiveis.show .row {
            animation: fadeInUp 0.5s ease forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Tabs */
        .nav-tabs {
            border-bottom: 2px solid rgba(0, 188, 212, 0.2);
            margin-bottom: 30px;
        }

        .nav-tabs .nav-link {
            color: var(--text-light);
            border: none;
            border-bottom: 3px solid transparent;
            padding: 15px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            background: transparent;
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
            border-bottom-color: rgba(0, 188, 212, 0.5);
        }

        .nav-tabs .nav-link.active {
            color: var(--accent-color);
            background: rgba(0, 188, 212, 0.1);
            border-bottom-color: var(--primary-color);
        }

        .tab-content {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            box-shadow:
                0 25px 45px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            padding: 30px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .tab-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .tab-content h4 {
            color: var(--text-light);
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 0 2px 10px rgba(0, 188, 212, 0.3);
        }

        .tab-content h4 i {
            color: var(--primary-color);
        }

        /* Tabela de Documentos */
        .table {
            margin: 0;
            background: transparent;
            color: var(--text-light);
        }

        .table thead th {
            background: rgba(0, 188, 212, 0.1);
            border: none;
            color: var(--accent-color);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
            padding: 20px 15px;
            border-bottom: 2px solid rgba(0, 188, 212, 0.2);
            cursor: pointer;
        }

        .table thead th.sorting,
        .table thead th.sorting_asc,
        .table thead th.sorting_desc {
            cursor: pointer;
        }

        .table thead th.sorting:hover,
        .table thead th.sorting_asc:hover,
        .table thead th.sorting_desc:hover {
            background: rgba(0, 188, 212, 0.2);
        }

        .table tbody tr {
            border: none;
            transition: background-color 0.3s ease;
        }

        .table tbody tr:hover {
            background: rgba(0, 188, 212, 0.1);
        }

        .table tbody td {
            border: none;
            padding: 20px 15px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Configurações de alinhamento são controladas pelo DataTables com className específicas */

        .table tbody td a {
            color: var(--dark-bg);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .table tbody td a:hover {
            color: black;
        }

        /* Botões */
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

        .btn-danger {
            background: linear-gradient(135deg, #f44336 0%, #c62828 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(244, 67, 54, 0.5);
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

        .btn-secondary i {
            color: var(--accent-color);
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.875rem;
        }

        .btn-outline-primary {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .btn-outline-info {
            border-color: var(--accent-color);
            color: var(--accent-color);
        }

        .btn-outline-info:hover {
            background: var(--accent-color);
            border-color: var(--accent-color);
            color: var(--dark-bg);
        }

        /* Badge de Status */
        .badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        /* Mensagens */
        .alert {
            border-radius: 15px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: rgba(227, 242, 253, 0.6);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 25, 41, 0.95);
            backdrop-filter: blur(10px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            flex-direction: column;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            width: 80px;
            height: 80px;
            border: 5px solid rgba(0, 188, 212, 0.2);
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-text {
            color: var(--accent-color);
            font-size: 1.2rem;
            font-weight: 600;
            margin-top: 20px;
            text-align: center;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Desabilitar botões durante loading */
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* DataTables Customização */
        .dataTables_wrapper {
            color: var(--text-light);
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            color: var(--text-light);
            margin-bottom: 15px;
        }

        .dataTables_wrapper .dataTables_filter input {
            background: rgba(0, 188, 212, 0.05);
            border: 1px solid rgba(0, 188, 212, 0.2);
            color: var(--text-light);
            border-radius: 8px;
            padding: 5px 10px;
            margin-left: 10px;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(0, 188, 212, 0.08);
        }

        .dataTables_wrapper .dataTables_length select {
            background: rgba(0, 188, 212, 0.05);
            border: 1px solid rgba(0, 188, 212, 0.2);
            color: var(--text-light);
            border-radius: 8px;
            padding: 5px 10px;
            margin: 0 10px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: var(--text-light) !important;
            background: rgba(0, 188, 212, 0.05);
            border: 1px solid rgba(0, 188, 212, 0.2);
            border-radius: 8px;
            padding: 5px 12px;
            margin: 0 2px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            color: var(--accent-color) !important;
            background: rgba(0, 188, 212, 0.2) !important;
            border: 1px solid rgba(0, 188, 212, 0.3);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            color: white !important;
            background: var(--gradient-primary) !important;
            border: 1px solid var(--primary-color);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        div.dt-container .dt-length,
        div.dt-container .dt-search,
        div.dt-container .dt-info,
        div.dt-container .dt-processing,
        div.dt-container .dt-paging {
            color: var(--text-light);
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .container-fluid {
                padding: 15px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .nav-tabs .nav-link {
                padding: 10px 15px;
                font-size: 0.9rem;
            }

            .loading-spinner {
                width: 60px;
                height: 60px;
            }

            .loading-text {
                font-size: 1rem;
            }
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text" id="loadingText">Carregando...</div>
    </div>

    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-water me-2"></i>
                COPASA
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="abrirModalAlteracao(); return false;" title="Configurações da Conta">
                            <i class="fas fa-cog me-1"></i>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="?logout=1">
                            <i class="fas fa-sign-out-alt me-1"></i>
                            Sair
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">

        <!-- Dados da Obra -->
        <div class="dados-card">
            <h3>
                <i class="fas fa-clipboard-list"></i>
                Informações da obra de <?= htmlspecialchars($obra['cidade']) ?>
            </h3>

            <form>
                <!-- Campos sempre visíveis -->
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Nome da Obra</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($obra['nome']) ?>" readonly>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($obra['status']) ?>" readonly>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($obra['descricao'] ?? '') ?></textarea>
                    </div>
                </div>


                <!-- Campos expandíveis (ocultos por padrão) -->
                <div id="dadosExpandiveis" class="dados-expandiveis" style="display: none;">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Localização</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($obra['localizacao'] ?? '') ?>" readonly>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Latitude</label>
                            <input id="input_lat" type="text" class="form-control" value="<?= htmlspecialchars($obra['latitude'] ?? '-') ?>" readonly>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Longitude</label>
                            <input id="input_lng" type="text" class="form-control" value="<?= htmlspecialchars($obra['longitude'] ?? '-') ?>" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cidade</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($obra['cidade']) ?>" readonly>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">UF</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($obra['uf']) ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Situação</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($obra['situacao']) ?>" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Data de Início</label>
                            <input type="text" class="form-control" value="<?= $obra['data_inicio'] ? date('d/m/Y', strtotime($obra['data_inicio'])) : '-' ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Data Prevista</label>
                            <input type="text" class="form-control" value="<?= $obra['data_prevista'] ? date('d/m/Y', strtotime($obra['data_prevista'])) : '-' ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Data de Conclusão</label>
                            <input type="text" class="form-control" value="<?= $obra['data_conclusao'] ? date('d/m/Y', strtotime($obra['data_conclusao'])) : '-' ?>" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Orçamento Total</label>
                            <input type="text" class="form-control" value="<?= $obra['orcamento_total'] ? 'R$ ' . number_format($obra['orcamento_total'], 2, ',', '.') : '-' ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Orçamento Utilizado</label>
                            <input type="text" class="form-control" value="<?= $obra['orcamento_utilizado'] ? 'R$ ' . number_format($obra['orcamento_utilizado'], 2, ',', '.') : '-' ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Responsável</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($obra['responsavel'] ?? '-') ?>" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($obra['observacoes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <!-- Fim dos campos expandíveis -->
            </form>

            <!-- Puxador de gaveta -->
            <div class="puxador-gaveta" id="puxadorGaveta" onclick="toggleDadosObra()">
                <i class="fas fa-chevron-down" id="iconExpandir"></i>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="obraTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="documentos-tab" data-bs-toggle="tab" data-bs-target="#documentos" type="button" role="tab">
                    <i class="fas fa-file-alt me-2"></i>
                    Documentos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="videos-tab" data-bs-toggle="tab" data-bs-target="#videos" type="button" role="tab">
                    <i class="fas fa-video me-2"></i>
                    Vídeos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="fiscalizacoes-tab" data-bs-toggle="tab" data-bs-target="#fiscalizacoes" type="button" role="tab">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Gêmeos Digitais
                </button>
            </li>
        </ul>

        <div class="tab-content" id="obraTabContent">
            <!-- Aba Documentos -->
            <div class="tab-pane fade show active" id="documentos" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">
                        <i class="fas fa-folder-open me-2"></i>
                        Documentos da Obra
                    </h4>
                    <div>
                        <?php if (count($documentos) > 0): ?>
                            <button class="btn btn-secondary me-2" onclick="baixarTodosDocumentos()">
                                <i class="fas fa-download me-2"></i>
                                Baixar Tudo
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                            <i class="fas fa-plus me-2"></i>
                            Adicionar Documentos
                        </button>
                    </div>
                </div>

                <input type="file" id="fileInput" multiple style="display: none;" onchange="uploadDocumentos(this.files)">

                <?php if (count($documentos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table" id="documentosTable">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Ações</th>
                                    <th>Nome do Arquivo</th>
                                    <th style="width: 120px;">Tamanho</th>
                                    <th style="width: 150px;">Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documentos as $doc): ?>
                                    <tr>
                                        <td>
                                            <button class="btn btn-danger btn-sm" onclick="deletarDocumento('<?= htmlspecialchars($doc['nome']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <a href="<?= htmlspecialchars($doc['caminho']) ?>" target="_blank">
                                                <i class="fas fa-file me-2"></i>
                                                <?= htmlspecialchars($doc['nome']) ?>
                                            </a>
                                        </td>
                                        <td data-order="<?= $doc['tamanho'] ?>"><?= formatBytes($doc['tamanho']) ?></td>
                                        <td><?= $doc['data'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h4>Nenhum documento encontrado</h4>
                        <p>Adicione documentos para esta obra clicando no botão acima.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Aba Vídeos -->
            <div class="tab-pane fade" id="videos" role="tabpanel">
                <div class="mb-4">
                    <h4 class="mb-0">
                        <i class="fas fa-video me-2"></i>
                        Vídeos da Obra
                    </h4>
                </div>

                <?php if (count($videos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table" id="videosTable">
                            <thead>
                                <tr>
                                    <th>Nome do Vídeo</th>
                                    <th style="width: 120px;">Formato</th>
                                    <th style="width: 120px;">Tamanho</th>
                                    <th style="width: 100px;">Frames</th>
                                    <th style="width: 100px;">Analisado</th>
                                    <th style="width: 100px;">Latitude</th>
                                    <th style="width: 100px;">Longitude</th>
                                    <th style="width: 100px;">Duração</th>
                                    <th style="width: 150px;">Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($videos as $video): ?>
                                    <tr>
                                        <td>
                                            <a href="video_ia.php?video=<?= urlencode($video['caminho_relativo']) ?>" target="_blank">
                                                <i class="fas fa-play-circle me-2"></i>
                                                <?= htmlspecialchars($video['nome']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= strtoupper($video['extensao']) ?></span>
                                        </td>
                                        <td data-order="<?= $video['tamanho'] ?>"><?= formatBytes($video['tamanho']) ?></td>
                                        <td data-order="<?= $video['frames'] ?>">
                                            <span class="badge <?= $video['frames'] > 0 ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $video['frames'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $video['analisado'] ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $video['analisado'] ? 'SIM' : 'NÃO' ?>
                                            </span>
                                        </td>
                                        <td><?= $video['latitude'] ?></td>
                                        <td><?= $video['longitude'] ?></td>
                                        <td><?= $video['duracao'] ?></td>
                                        <td><?= $video['data'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-video"></i>
                        <h4>Nenhum vídeo encontrado</h4>
                        <p>Adicione vídeos para esta obra clicando no botão acima.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Aba Fiscalizações -->
            <div class="tab-pane fade" id="fiscalizacoes" role="tabpanel">
                <div class="mb-4">
                    <h4 class="mb-0">
                        <i class="fas fa-clipboard-check me-2"></i>
                        Fiscalizações e Projetos
                    </h4>
                </div>

                <?php if (count($projetos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table" id="projetosTable">
                            <thead>
                                <tr>
                                    <th>Nome do Projeto</th>
                                    <th style="width: 200px;">Modelo 3D</th>
                                    <th style="width: 200px;">Modelo 2D</th>
                                    <th style="width: 150px;">Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projetos as $projeto): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-folder me-2"></i>
                                            <?= htmlspecialchars($projeto['nome']) ?>
                                        </td>
                                        <td>
                                            <a target="_blank" href="nuvem.php?projeto=<?= urlencode($projeto['nome']) ?>&cidade=<?= urlencode($obra['cidade']) ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-cube me-2"></i>
                                                Nuvem de pontos
                                            </a>
                                        </td>
                                        <td>

                                            <a target="_blank" href="desenhos_detalhes.php?id=<?= urlencode($obra_id) ?>&cidade=<?= urlencode($obra['cidade']) ?>&lat=<?= urlencode($obra['latitude']) ?>&lng=<?= urlencode($obra['longitude']) ?>&projeto=<?= urlencode($projeto['nome']) ?>/3_dsm_ortho/2_mosaic/google_tiles" class="btn btn-sm btn-outline-info">
                                               <i class="fas fa-map me-2"></i>
                                                Ortofoto
                                            </a>
                                        </td>
                                        <td><?= $projeto['data'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-check"></i>
                        <h4>Nenhuma fiscalização encontrada</h4>
                        <p>Não há projetos cadastrados para esta cidade.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="jquery.min.js"></script>

    <!-- DataTables JS -->
    <script src="dataTables.min.js"></script>

    <script>
        // Inicializar DataTables quando o documento estiver pronto
        $(document).ready(function() {
            // Variáveis para armazenar as tabelas
            let documentosTable, videosTable, projetosTable;
            
            // Configuração para tabela de documentos
            if ($('#documentosTable').length) {
                documentosTable = $('#documentosTable').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
                    },
                    pageLength: 10,
                    ordering: true,
                    searching: true,
                    info: true,
                    lengthMenu: [
                        [5, 10, 25, 50, -1],
                        [5, 10, 25, 50, "Todos"]
                    ],
                    order: [
                        [3, 'desc']
                    ], // Ordenar pela coluna de Data (índice 3) em ordem decrescente
                    columnDefs: [
                        {
                            orderable: false,
                            targets: 0,
                            className: 'text-center'
                        }, // Desabilitar ordenação na coluna de ações (primeira coluna)
                        {
                            targets: 1, // Coluna de Nome do Arquivo (índice 1) - alinhar à esquerda
                            className: 'text-start'
                        },
                        {
                            type: 'num',
                            targets: 2, // Coluna de Tamanho (índice 2) - usará o atributo data-order automaticamente
                            className: 'text-center'
                        },
                        {
                            type: 'date',
                            targets: 3, // Coluna de Data (índice 3)
                            className: 'text-center',
                            render: function(data, type, row) {
                                if (type === 'sort' || type === 'type') {
                                    // Converter data brasileira (DD/MM/YYYY HH:MM) para formato sortável (YYYY-MM-DD HH:MM)
                                    if (data && typeof data === 'string') {
                                        var parts = data.split(' ');
                                        if (parts.length === 2) {
                                            var datePart = parts[0].split('/');
                                            var timePart = parts[1];
                                            if (datePart.length === 3) {
                                                // Validar se são números válidos
                                                var day = parseInt(datePart[0]);
                                                var month = parseInt(datePart[1]);
                                                var year = parseInt(datePart[2]);
                                                
                                                if (!isNaN(day) && !isNaN(month) && !isNaN(year)) {
                                                    return year + '-' + 
                                                           String(month).padStart(2, '0') + '-' + 
                                                           String(day).padStart(2, '0') + ' ' + 
                                                           timePart;
                                                }
                                            }
                                        }
                                    }
                                }
                                return data; // Retornar dados originais para display
                            }
                        }
                    ]
                });
            }

            // Inicializar tabela de vídeos se existir
            if ($('#videosTable').length) {
                videosTable = $('#videosTable').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
                    },
                    pageLength: 10,
                    ordering: true,
                    searching: true,
                    info: true,
                    lengthMenu: [
                        [5, 10, 25, 50, -1],
                        [5, 10, 25, 50, "Todos"]
                    ],
                    order: [
                        [8, 'desc']
                    ], // Ordenar pela coluna de Data (índice 8) em ordem decrescente
                    columnDefs: [
                        {
                            targets: 0, // Coluna de Nome do Vídeo (índice 0) - alinhar à esquerda
                            className: 'text-start'
                        },
                        {
                            targets: 1, // Coluna de Formato (índice 1)
                            className: 'text-center'
                        },
                        {
                            type: 'num',
                            targets: 2, // Coluna de Tamanho (índice 2) - usará o atributo data-order automaticamente
                            className: 'text-center'
                        },
                        {
                            type: 'num',
                            targets: 3, // Coluna de Frames (índice 3) - usará o atributo data-order automaticamente
                            className: 'text-center'
                        },
                        {
                            targets: 4, // Coluna de Analisado (índice 4)
                            className: 'text-center'
                        },
                        {
                            targets: 5, // Coluna de Latitude (índice 5)
                            className: 'text-center'
                        },
                        {
                            targets: 6, // Coluna de Longitude (índice 6)
                            className: 'text-center'
                        },
                        {
                            targets: 7, // Coluna de Duração (índice 7)
                            className: 'text-center'
                        },
                        {
                            type: 'date',
                            targets: 8, // Coluna de Data (índice 8)
                            className: 'text-center',
                            render: function(data, type, row) {
                                if (type === 'sort' || type === 'type') {
                                    // Converter data brasileira (DD/MM/YYYY HH:MM) para formato sortável (YYYY-MM-DD HH:MM)
                                    if (data && typeof data === 'string') {
                                        var parts = data.split(' ');
                                        if (parts.length === 2) {
                                            var datePart = parts[0].split('/');
                                            var timePart = parts[1];
                                            if (datePart.length === 3) {
                                                // Validar se são números válidos
                                                var day = parseInt(datePart[0]);
                                                var month = parseInt(datePart[1]);
                                                var year = parseInt(datePart[2]);
                                                
                                                if (!isNaN(day) && !isNaN(month) && !isNaN(year)) {
                                                    return year + '-' + 
                                                           String(month).padStart(2, '0') + '-' + 
                                                           String(day).padStart(2, '0') + ' ' + 
                                                           timePart;
                                                }
                                            }
                                        }
                                    }
                                }
                                return data; // Retornar dados originais para display
                            }
                        }
                    ]
                });
            }

            // Inicializar tabela de fiscalizações/projetos se existir
            if ($('#projetosTable').length) {
                projetosTable = $('#projetosTable').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
                    },
                    pageLength: 10,
                    ordering: true,
                    searching: true,
                    info: true,
                    lengthMenu: [
                        [5, 10, 25, 50, -1],
                        [5, 10, 25, 50, "Todos"]
                    ],
                    order: [
                        [3, 'desc']
                    ] // Ordenar pela coluna de Data (índice 3) em ordem decrescente
                });
            }

            // Redesenhar tabelas quando as abas forem trocadas
            // Isso corrige problemas de paginação em abas ocultas
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
                // Pequeno delay para garantir que a aba foi totalmente renderizada
                setTimeout(function() {
                    // Redesenhar a tabela de vídeos quando a aba for mostrada
                    if (e.target.id === 'videos-tab' && videosTable) {
                        videosTable.columns.adjust().draw();
                    }
                    // Redesenhar a tabela de fiscalizações quando a aba for mostrada
                    if (e.target.id === 'fiscalizacoes-tab' && projetosTable) {
                        projetosTable.columns.adjust().draw();
                    }
                }, 10);
            });
        });

        // Funções de Loading
        function showLoading(message = 'Carregando...') {
            document.getElementById('loadingText').textContent = message;
            document.getElementById('loadingOverlay').classList.add('active');
            // Desabilitar todos os botões
            document.querySelectorAll('button, a.btn').forEach(btn => {
                btn.disabled = true;
            });
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
            // Reabilitar todos os botões
            document.querySelectorAll('button, a.btn').forEach(btn => {
                btn.disabled = false;
            });
        }

        function uploadDocumentos(files) {
            if (files.length === 0) return;

            // Mostrar loading
            showLoading('Enviando ' + files.length + ' arquivo(s)...');

            const formData = new FormData();
            formData.append('obra_id', <?= $obra_id ?>);
            formData.append('cidade', '<?= htmlspecialchars($obra['cidade']) ?>');

            for (let i = 0; i < files.length; i++) {
                formData.append('documentos[]', files[i]);
            }

            fetch('upload_documento.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        alert('Documentos enviados com sucesso!');
                        showLoading('Recarregando página...');
                        location.reload();
                    } else {
                        alert('Erro ao enviar documentos: ' + data.message);
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Erro:', error);
                    alert('Erro ao enviar documentos.');
                });
        }

        function deletarDocumento(nome) {
            if (!confirm('Tem certeza que deseja deletar este documento?\n\nEsta ação não pode ser desfeita.')) {
                return;
            }

            // Mostrar loading
            showLoading('Deletando documento...');

            const formData = new FormData();
            formData.append('obra_id', <?= $obra_id ?>);
            formData.append('cidade', '<?= htmlspecialchars($obra['cidade']) ?>');
            formData.append('nome_arquivo', nome);

            fetch('deletar_documento.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        alert('Documento deletado com sucesso!');
                        showLoading('Recarregando página...');
                        location.reload();
                    } else {
                        alert('Erro ao deletar documento: ' + data.message);
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Erro:', error);
                    alert('Erro ao deletar documento.');
                });
        }

        function abrirModalAlteracao() {
            showLoading('Redirecionando...');
            window.location.href = 'dashboard.php';
        }

        function baixarTodosDocumentos() {
            showLoading('Preparando download...');

            // Criar link temporário para download
            const link = document.createElement('a');
            link.href = 'baixar_todos_documentos.php?obra_id=<?= $obra_id ?>&cidade=<?= urlencode($obra['cidade']) ?>';
            link.download = '';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Esconder loading após 2 segundos (tempo para o download iniciar)
            setTimeout(function() {
                hideLoading();
            }, 2000);
        }

        function toggleDadosObra() {
            const dadosExpandiveis = document.getElementById('dadosExpandiveis');
            const puxadorGaveta = document.getElementById('puxadorGaveta');
            const iconExpandir = document.getElementById('iconExpandir');

            // Verificar se está expandido pela classe, não pelo display
            const isExpanded = puxadorGaveta.classList.contains('expanded');

            if (!isExpanded) {
                // Expandir
                dadosExpandiveis.style.display = 'block';
                setTimeout(function() {
                    dadosExpandiveis.classList.add('show');
                }, 10);
                puxadorGaveta.classList.add('expanded');
                iconExpandir.classList.remove('fa-chevron-down');
                iconExpandir.classList.add('fa-chevron-up');
            } else {
                // Recolher
                dadosExpandiveis.classList.remove('show');
                setTimeout(function() {
                    dadosExpandiveis.style.display = 'none';
                }, 400);
                puxadorGaveta.classList.remove('expanded');
                iconExpandir.classList.remove('fa-chevron-up');
                iconExpandir.classList.add('fa-chevron-down');

                // Scroll suave para o topo do card ao recolher
                document.querySelector('.dados-card').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }

        // Mostrar loading ao clicar em links de navegação
        document.addEventListener('DOMContentLoaded', function() {
            // Links de navegação
            document.querySelectorAll('a[href="dashboard.php"], a.breadcrumb-item a').forEach(link => {
                link.addEventListener('click', function(e) {
                    showLoading('Redirecionando...');
                });
            });

            // Botão voltar
            document.querySelectorAll('.btn-secondary[href="dashboard.php"]').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    showLoading('Voltando ao dashboard...');
                });
            });
        });
    </script>
</body>

</html>

<?php
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