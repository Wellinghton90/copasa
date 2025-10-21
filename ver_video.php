<?php
session_start();
require_once 'connection.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_copasa'])) {
    header('Location: index.php');
    exit();
}

// Verificar se os parâmetros necessários foram passados
if (!isset($_GET['video']) || !isset($_GET['cidade'])) {
    header('Location: dashboard.php');
    exit();
}

$video_nome = $_GET['video'];
$cidade = $_GET['cidade'];
$caminho_video = $_GET['caminho'] ?? '';

// Carregar metadados dos vídeos
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

$videoMetadata = loadVideoMetadata($cidade);

// Função para buscar todos os vídeos na pasta e subpastas
function buscarCaminhosVideos($cidade) {
    $videos_path = "evidencias/{$cidade}/Videos";
    $caminhos_videos = [];
    
    if (!is_dir($videos_path)) {
        return $caminhos_videos;
    }
    
    $extensoes_video = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'm4v', 'mpeg', 'mpg'];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($videos_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $extensao = strtolower($file->getExtension());
            if (in_array($extensao, $extensoes_video)) {
                $nomeVideo = $file->getFilename();
                $caminhoCompleto = str_replace('\\', '/', $file->getPathname());
                
                // Extrair apenas a parte relativa a partir de "evidencias/{cidade}/"
                $caminhoRelativo = str_replace("evidencias/{$cidade}/", "", $caminhoCompleto);
                
                $caminhos_videos[$nomeVideo] = $caminhoRelativo;
            }
        }
    }
    
    return $caminhos_videos;
}

$caminhosVideos = buscarCaminhosVideos($cidade);

// Encontrar o vídeo selecionado nos metadados
$videoSelecionado = null;
$defaultLat = -19.9167; // Coordenada padrão se não encontrar
$defaultLng = -43.9345;

if (isset($videoMetadata[$video_nome])) {
    $videoSelecionado = $videoMetadata[$video_nome];
    $defaultLat = $videoSelecionado['latitude'] ?? $defaultLat;
    $defaultLng = $videoSelecionado['longitude'] ?? $defaultLng;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Localização do Vídeo - COPASA</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

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
            height: 100vh;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        /* Navbar */
        .navbar {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 15px 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .navbar-brand {
            color: var(--text-light);
            font-weight: 700;
            font-size: 1.5rem;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            cursor: default;
            pointer-events: none;
        }

        /* Prevenir mudança de cor em todos os estados do link */
        .navbar-brand:link,
        .navbar-brand:visited,
        .navbar-brand:hover,
        .navbar-brand:active {
            color: var(--text-light);
            text-decoration: none;
        }

        .navbar-title {
            font-size: 1.3rem;
            line-height: 1.2;
            color: var(--text-light);
        }

        .navbar-subtitle {
            font-size: 0.85rem;
            color: var(--accent-color);
            font-weight: 400;
        }

        /* Botões do Navbar */
        .navbar-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-navbar {
            background: var(--gradient-primary);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 5px 15px rgba(0, 188, 212, 0.3);
            font-size: 0.95rem;
        }

        .btn-navbar:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 188, 212, 0.4);
        }

        .btn-navbar i {
            font-size: 1.1rem;
        }

        /* Botão ativo (selecionado) */
        .btn-navbar.active {
            background: var(--accent-color);
            box-shadow: 0 0 20px rgba(38, 198, 218, 0.6);
        }

        /* Mapa Container */
        .map-container {
            position: fixed;
            top: 80px;
            left: 0;
            width: 100%;
            height: calc(100vh - 80px);
            margin: 0;
            padding: 0;
            z-index: 1;
        }

        #map {
            height: 100%;
            width: 100%;
            border-radius: 0;
            z-index: 1;
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

    </style>
</head>

<body>
    <nav class="navbar">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center w-100">
                <!-- Título e Subtítulo -->
                <a class="navbar-brand" href="#">
                    <div class="navbar-title">
                        <i class="fas fa-map-marked-alt me-2"></i>Copasa
                    </div>
                    <div class="navbar-subtitle">Vídeos em <?= htmlspecialchars($cidade) ?></div>
                </a>
                
                <!-- Botões do Navbar -->
                <div class="navbar-buttons">
                    <button id="roadmap-btn" class="btn-navbar active">
                        <i class="fas fa-map"></i>
                        <span>Roadmap</span>
                    </button>
                    <button id="satellite-btn" class="btn-navbar">
                        <i class="fas fa-satellite"></i>
                        <span>Satélite</span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mapa -->
    <div class="map-container">
        <div id="map"></div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Aguardar todos os recursos carregarem, incluindo o Leaflet
        window.addEventListener('load', function() {
            // Verificar se o Leaflet está disponível
            if (typeof L === 'undefined') {
                console.error('Leaflet não carregou corretamente');
                return;
            }

            // Dados dos vídeos vindos do PHP
            const videoMetadata = <?= json_encode($videoMetadata) ?>;
            const caminhosVideos = <?= json_encode($caminhosVideos) ?>;
            const videoSelecionado = '<?= htmlspecialchars($video_nome) ?>';
            const cidade = '<?= htmlspecialchars($cidade) ?>';
            const defaultLat = <?= $defaultLat ?>;
            const defaultLng = <?= $defaultLng ?>;

            // Inicializar o mapa
        const map = L.map('map', {
            maxZoom: 22
        }).setView([defaultLat, defaultLng], 17);

        // Criar as camadas de mapa
        const roadmapLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '',
            maxZoom: 22
        });

        const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '',
            maxZoom: 18
        });

        // Adicionar camada roadmap por padrão
        roadmapLayer.addTo(map);

        // Variável para controlar a camada ativa
        let currentLayer = roadmapLayer;

        // Criar ícones personalizados - bolinhas coloridas
        const iconVideos = L.divIcon({
            className: 'custom-video-marker',
            html: '<div style="background-color: #87CEEB; border: 2px solid black; border-radius: 50%; width: 12px; height: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
            iconSize: [16, 16],
            iconAnchor: [8, 8]
        });

        const iconVideoSelecionado = L.divIcon({
            className: 'custom-selected-marker',
            html: '<div style="background-color: #ff0000; border: 3px solid black; border-radius: 50%; width: 20px; height: 20px; box-shadow: 0 3px 6px rgba(0,0,0,0.4);"></div>',
            iconSize: [26, 26],
            iconAnchor: [13, 13]
        });

        // Agrupar vídeos por coordenadas
        const coordenadasGrupos = {};
        
        Object.entries(videoMetadata).forEach(([nomeVideo, metadados]) => {
            if (metadados.latitude && metadados.longitude) {
                const lat = parseFloat(metadados.latitude);
                const lng = parseFloat(metadados.longitude);
                
                if (!isNaN(lat) && !isNaN(lng)) {
                    // Criar uma chave única para as coordenadas (arredondando para evitar diferenças mínimas)
                    const coordKey = `${lat.toFixed(6)},${lng.toFixed(6)}`;
                    
                    if (!coordenadasGrupos[coordKey]) {
                        coordenadasGrupos[coordKey] = {
                            lat: lat,
                            lng: lng,
                            videos: []
                        };
                    }
                    
                    coordenadasGrupos[coordKey].videos.push({
                        nome: nomeVideo,
                        metadados: metadados
                    });
                }
            }
        });

        // Criar marcadores agrupados
        const markers = [];
        
        Object.values(coordenadasGrupos).forEach((grupo) => {
            const lat = grupo.lat;
            const lng = grupo.lng;
            const videos = grupo.videos;
            
            // Verificar se algum vídeo do grupo é o selecionado
            const temVideoSelecionado = videos.some(video => video.nome === videoSelecionado);
            
            // Usar ícone especial se há vídeo selecionado neste grupo
            const icon = temVideoSelecionado ? iconVideoSelecionado : iconVideos;
            
            // Ordenar vídeos para que o selecionado apareça primeiro
            const videosOrdenados = videos.sort((a, b) => {
                if (a.nome === videoSelecionado) return -1; // Vídeo selecionado primeiro
                if (b.nome === videoSelecionado) return 1;
                return 0; // Manter ordem original para os demais
            });
            
            // Criar popup com todos os vídeos do grupo
            const videosContent = videosOrdenados.map((video, index) => {
                const isSelected = video.nome === videoSelecionado;
                const caminhoRelativo = caminhosVideos[video.nome] || '';
                const caminhoCompleto = caminhoRelativo ? `evidencias/${cidade}/${caminhoRelativo}` : '';
                return `
                    <div style="text-align: left; margin-bottom: 15px;">
                        <strong><i class="fas fa-video me-1"></i>${video.nome}</strong>
                        ${isSelected ? ' <span style="color: #ff4444;"><strong>(SELECIONADO)</strong></span>' : ''}<br>
                        ${video.metadados.data ? `<small>Data: ${video.metadados.data}</small><br>` : ''}
                        ${video.metadados.tempo ? `<small>Duração: ${video.metadados.tempo}</small><br>` : ''}
                        <small>Coordenadas: ${lat.toFixed(6)}, ${lng.toFixed(6)}</small><br>
                        ${caminhoCompleto ? `<a href="video_ia.php?video=${encodeURIComponent(caminhoCompleto)}" target="_blank" 
                           style="display: inline-block; margin-top: 8px; padding: 4px 12px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 12px;">
                            Ver
                        </a>` : '<span style="color: #999; font-size: 12px;">Caminho não encontrado</span>'}
                    </div>
                `;
            }).join('<hr style="margin: 10px 0; border: none; border-top: 1px solid #ccc;">');
            
            const popupContent = `
                <div style="max-height: 300px; overflow-y: auto; padding: 5px;">
                    ${videosContent}
                </div>
            `;
            
            const marker = L.marker([lat, lng], { 
                icon: icon,
                zIndexOffset: temVideoSelecionado ? 1000 : 0
            })
                .addTo(map)
                .bindPopup(popupContent, {
                    maxWidth: 350
                });
            
            markers.push(marker);
        });

        // Ajustar o zoom para mostrar todos os marcadores
        if (markers.length > 1) {
            const group = new L.featureGroup(markers);
        }

        // Controles de alternância entre roadmap e satélite
        const roadmapBtn = document.getElementById('roadmap-btn');
        const satelliteBtn = document.getElementById('satellite-btn');

        function switchToRoadmap() {
            map.removeLayer(currentLayer);
            roadmapLayer.addTo(map);
            currentLayer = roadmapLayer;
            
            // Restaurar zoom máximo para roadmap
            map.options.maxZoom = 22;
            roadmapLayer.options.maxZoom = 22;
            satelliteLayer.options.maxZoom = 18;
            
            // Atualizar estado dos botões
            roadmapBtn.classList.add('active');
            satelliteBtn.classList.remove('active');
        }

        function switchToSatellite() {
            map.removeLayer(currentLayer);
            satelliteLayer.addTo(map);
            currentLayer = satelliteLayer;
            
            // Limitar zoom máximo para satélite e definir zoom para 18
            map.options.maxZoom = 18;
            roadmapLayer.options.maxZoom = 22;
            satelliteLayer.options.maxZoom = 18;
            map.setZoom(18);
            
            // Atualizar estado dos botões
            satelliteBtn.classList.add('active');
            roadmapBtn.classList.remove('active');
        }

        // Event listeners para os botões
        roadmapBtn.addEventListener('click', switchToRoadmap);
        satelliteBtn.addEventListener('click', switchToSatellite);

        // Controlar zoom máximo dinamicamente
        map.on('zoomend', function() {
            if (currentLayer === satelliteLayer && map.getZoom() > 18) {
                map.setZoom(18);
            }
        });

        }); // Fechar window.addEventListener('load')
    </script>
</body>
</html>
