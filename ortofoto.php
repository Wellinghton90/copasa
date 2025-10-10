<?php
session_start();
require_once 'connection.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_copasa'])) {
    header('Location: index.php');
    exit();
}

$usuario = $_SESSION['user_copasa'];

// Verificar se foi passado o projeto
if (!isset($_GET['projeto']) || empty($_GET['projeto'])) {
    header('Location: dashboard.php');
    exit();
}

$projeto_nome = $_GET['projeto'];

// Buscar a cidade do projeto
$projeto_path = '';
$cidade_encontrada = '';

$projetos_dir = 'projetos';
if (is_dir($projetos_dir)) {
    $cidades = scandir($projetos_dir);
    foreach ($cidades as $cidade) {
        if ($cidade != '.' && $cidade != '..') {
            $cidade_path = $projetos_dir . '/' . $cidade;
            if (is_dir($cidade_path)) {
                $projeto_test = $cidade_path . '/' . $projeto_nome;
                if (is_dir($projeto_test)) {
                    $projeto_path = $projeto_test;
                    $cidade_encontrada = $cidade;
                    break;
                }
            }
        }
    }
}

if (empty($projeto_path)) {
    die('Projeto não encontrado');
}

// Caminho da ortofoto (pode estar em diferentes locais)
$possiveis_caminhos = [
    $projeto_path . '/3_dsm_ortho/1_dsm',
    $projeto_path . '/3_dsm_ortho/2_ortho',
    $projeto_path . '/ortofoto',
    $projeto_path . '/orthophoto'
];

$ortofoto_path = '';
foreach ($possiveis_caminhos as $caminho) {
    if (is_dir($caminho)) {
        $ortofoto_path = $caminho;
        break;
    }
}

if (empty($ortofoto_path)) {
    die('Ortofoto não encontrada. Caminhos verificados: ' . implode(', ', $possiveis_caminhos));
}

// Listar arquivos de imagem
$imagens = [];
$extensoes_imagem = ['tif', 'tiff', 'jpg', 'jpeg', 'png', 'geotiff'];

$files = scandir($ortofoto_path);
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        $file_path = $ortofoto_path . '/' . $file;
        if (is_file($file_path)) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $extensoes_imagem)) {
                $imagens[] = [
                    'nome' => $file,
                    'caminho' => str_replace('\\', '/', $file_path),
                    'tamanho' => filesize($file_path),
                    'extensao' => $ext
                ];
            }
        }
    }
}

if (count($imagens) === 0) {
    die('Nenhuma ortofoto encontrada no diretório: ' . $ortofoto_path);
}

// Usar a primeira imagem encontrada
$ortofoto_principal = $imagens[0];

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
    <title>Ortofoto - <?= htmlspecialchars($projeto_nome) ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Leaflet para visualização de mapas -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Leaflet.Sync para sincronizar mapas -->
    <script src="https://cdn.jsdelivr.net/npm/leaflet.sync@0.2.4/L.Map.Sync.min.js"></script>
    
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
        }

        /* Map Container */
        #map-container {
            position: fixed;
            top: 70px;
            left: 0;
            right: 0;
            bottom: 0;
        }

        /* Info Panel */
        .info-panel {
            position: fixed;
            top: 90px;
            right: 20px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            color: var(--text-light);
            max-width: 300px;
            z-index: 1001;
        }

        .info-panel h5 {
            color: var(--accent-color);
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .info-item {
            margin-bottom: 10px;
            font-size: 0.9rem;
        }

        .info-item strong {
            color: var(--primary-color);
        }

        .btn-back {
            background: var(--gradient-primary);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 188, 212, 0.5);
            color: white;
        }

        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 25, 41, 0.95);
            backdrop-filter: blur(10px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            flex-direction: column;
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
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Carregando ortofoto...</div>
    </div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container-fluid px-4">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-water me-2"></i>
                COPASA
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-light">
                    <i class="fas fa-map me-2"></i>
                    <?= htmlspecialchars($projeto_nome) ?>
                </span>
                <button onclick="history.back()" class="btn-back">
                    <i class="fas fa-arrow-left me-2"></i>
                    Voltar
                </button>
            </div>
        </div>
    </nav>

    <!-- Info Panel -->
    <div class="info-panel">
        <h5><i class="fas fa-info-circle me-2"></i>Informações</h5>
        <div class="info-item">
            <strong>Projeto:</strong><br>
            <?= htmlspecialchars($projeto_nome) ?>
        </div>
        <div class="info-item">
            <strong>Cidade:</strong><br>
            <?= htmlspecialchars($cidade_encontrada) ?>
        </div>
        <div class="info-item">
            <strong>Arquivo:</strong><br>
            <?= htmlspecialchars($ortofoto_principal['nome']) ?>
        </div>
        <div class="info-item">
            <strong>Formato:</strong><br>
            <?= strtoupper($ortofoto_principal['extensao']) ?>
        </div>
    </div>

    <!-- Map Container -->
    <div id="map-container"></div>

    <script>
        // Inicializar mapa Leaflet
        const map = L.map('map-container', {
            center: [-19.9167, -43.9345], // Coordenadas padrão (Belo Horizonte)
            zoom: 15,
            zoomControl: true
        });

        // Adicionar camada base
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        // Adicionar ortofoto como overlay
        <?php if (in_array($ortofoto_principal['extensao'], ['jpg', 'jpeg', 'png'])): ?>
        // Para imagens JPG/PNG, adicionar como ImageOverlay
        const imageUrl = '<?= $ortofoto_principal['caminho'] ?>';
        
        // Você precisará ajustar os bounds conforme as coordenadas reais da ortofoto
        const imageBounds = [
            [-19.92, -43.94],  // Southwest
            [-19.91, -43.93]   // Northeast
        ];
        
        L.imageOverlay(imageUrl, imageBounds, {
            opacity: 0.8
        }).addTo(map);
        
        map.fitBounds(imageBounds);
        <?php else: ?>
        // Para arquivos GeoTIFF, será necessário usar georaster-layer-for-leaflet
        console.log('Arquivo GeoTIFF detectado. Implementação específica necessária.');
        <?php endif; ?>

        // Esconder loading
        setTimeout(function() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }, 1000);

        // Ajustar mapa ao redimensionar
        window.addEventListener('resize', function() {
            map.invalidateSize();
        });
    </script>
</body>
</html>

