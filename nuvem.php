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

// Buscar a cidade do projeto (precisamos buscar da URL anterior ou da sessão)
// Vou buscar todas as cidades e encontrar onde está o projeto
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

// Verificar se existe conversão Potree (cloud.js)
$potree_path = $projeto_path . '/potree_converted';
$tem_potree = false;
$potree_clouds = [];

if (is_dir($potree_path)) {
    // Buscar por cloud.js em subpastas
    $items = scandir($potree_path);
    foreach ($items as $item) {
        if ($item != '.' && $item != '..') {
            $cloud_file = $potree_path . '/' . $item . '/cloud.js';
            if (file_exists($cloud_file)) {
                $tem_potree = true;
                $potree_clouds[] = [
                    'nome' => $item,
                    'caminho' => str_replace('\\', '/', $potree_path . '/' . $item)
                ];
            }
        }
    }
}

// Se não tem Potree, buscar arquivos originais
$nuvem_path = $projeto_path . '/2_densification/point_cloud';
$arquivos_ply = [];

if (!$tem_potree) {
    if (!is_dir($nuvem_path)) {
        die('Nuvem de pontos não encontrada. Caminhos verificados:<br>- ' . $potree_path . '<br>- ' . $nuvem_path);
    }
    
    // Listar apenas arquivos .ply
    $files = scandir($nuvem_path);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $file_path = $nuvem_path . '/' . $file;
            if (is_file($file_path)) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if ($ext === 'ply') {
                    $arquivos_ply[] = [
                        'nome' => $file,
                        'caminho' => str_replace('\\', '/', $file_path),
                        'tamanho' => filesize($file_path)
                    ];
                }
            }
        }
    }
    
    if (count($arquivos_ply) === 0) {
        die('Nenhum arquivo PLY encontrado em: ' . $nuvem_path);
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
    <title>Nuvem de Pontos - <?= htmlspecialchars($projeto_nome) ?></title>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <?php if ($tem_potree): ?>
        <!-- Potree -->
        <link rel="stylesheet" type="text/css" href="potree/build/potree/potree.css">
        <link rel="stylesheet" type="text/css" href="potree/libs/jquery-ui/jquery-ui.min.css">
        <link rel="stylesheet" type="text/css" href="potree/libs/openlayers3/ol.css">
        <link rel="stylesheet" type="text/css" href="potree/libs/spectrum/spectrum.css">
        <link rel="stylesheet" type="text/css" href="potree/libs/jstree/themes/mixed/style.css">
    <?php else: ?>
        <!-- Three.js para arquivos PLY -->
        <script src="potree/libs/three.js/build/three.min.js"></script>
        <script src="potree/libs/three.js/extra/PLYLoader.js"></script>
        <script src="potree/libs/three.js/extra/OrbitControls.js"></script>
    <?php endif; ?>
    
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

        /* Viewer Container */
        #viewer-container {
            position: fixed;
            top: 70px;
            left: 0;
            right: 0;
            bottom: 0;
            background: #000;
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
            z-index: 100;
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
    <?php if ($tem_potree): ?>
        <!-- Potree Scripts -->
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
        <script src="potree/libs/three.js/build/three.min.js"></script>
        <script src="potree/build/potree/potree.js"></script>
        <script src="potree/libs/plasio/js/laslaz.js"></script>
    <?php endif; ?>

    <!-- Loading -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Carregando nuvem de pontos...</div>
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
                    <i class="fas fa-cube me-2"></i>
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
            <strong>Tipo:</strong><br>
            <?php if ($tem_potree): ?>
                Potree (<?= count($potree_clouds) ?> nuvem(ns))
            <?php else: ?>
                PLY (<?= count($arquivos_ply) ?> arquivo(s))
            <?php endif; ?>
        </div>
    </div>

    <?php if ($tem_potree): ?>
        <!-- Potree Container -->
        <div class="potree_container" style="position: absolute; width: 100%; height: 100%; left: 0px; top: 70px;">
            <div id="potree_render_area" style="background-color: #000;"></div>
            <div id="potree_sidebar_container"></div>
        </div>
    <?php else: ?>
        <!-- Three.js Container -->
        <div id="viewer-container"></div>
    <?php endif; ?>

    <?php if ($tem_potree): ?>
    <script type="module">
        import { FirstPersonControls } from './potree/src/navigation/FirstPersonControls.js';

        window.viewer = new Potree.Viewer(document.getElementById("potree_render_area"));

        viewer.setEDLEnabled(true);
        viewer.setFOV(60);
        viewer.setPointBudget(5_000_000);
        viewer.loadSettingsFromURL();
        viewer.setBackground("gradient");
        viewer.setDescription("<?= htmlspecialchars($projeto_nome) ?>");

        viewer.loadGUI(() => {
            viewer.setLanguage('pt');
            $("#menu_tools").next().show();
            $("#menu_clipping").next().show();
        });

        // Carregar nuvens de pontos
        const clouds = <?= json_encode($potree_clouds) ?>;
        let loadedCount = 0;

        clouds.forEach((cloud, index) => {
            Potree.loadPointCloud(cloud.caminho + "/cloud.js", cloud.nome, e => {
                let scene = viewer.scene;
                let pointcloud = e.pointcloud;

                let material = pointcloud.material;
                material.size = 1;
                material.pointSizeType = Potree.PointSizeType.ADAPTIVE;
                material.shape = Potree.PointShape.SQUARE;

                scene.addPointCloud(pointcloud);

                if (index === 0) {
                    // Configurar controles apenas na primeira nuvem
                    let firstPersonControls = new FirstPersonControls(viewer);
                    viewer.setControls(firstPersonControls);
                    viewer.fitToScreen();
                }

                loadedCount++;
                if (loadedCount === clouds.length) {
                    document.getElementById('loadingOverlay').style.display = 'none';
                }
            });
        });
    </script>
    <?php else: ?>
    <script>
        // Visualizador Three.js otimizado para PLY
        const arquivos = <?= json_encode($arquivos_ply) ?>;
        
        let scene, camera, renderer, controls;
        let pointClouds = [];
        
        function init() {
            // Scene
            scene = new THREE.Scene();
            scene.background = new THREE.Color(0x000000);
            
            // Camera
            camera = new THREE.PerspectiveCamera(
                60,
                window.innerWidth / (window.innerHeight - 70),
                0.1,
                10000
            );
            camera.position.set(0, 10, 20);
            
            // Renderer
            renderer = new THREE.WebGLRenderer({ 
                antialias: true,
                alpha: true 
            });
            renderer.setSize(window.innerWidth, window.innerHeight - 70);
            renderer.setPixelRatio(window.devicePixelRatio);
            document.getElementById('viewer-container').appendChild(renderer.domElement);
            
            // Controls
            controls = new THREE.OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true;
            controls.dampingFactor = 0.05;
            controls.screenSpacePanning = false;
            controls.minDistance = 1;
            controls.maxDistance = 1000;
            controls.maxPolarAngle = Math.PI;
            
            // Lights
            const ambientLight = new THREE.AmbientLight(0xffffff, 0.8);
            scene.add(ambientLight);
            
            const directionalLight = new THREE.DirectionalLight(0xffffff, 0.5);
            directionalLight.position.set(10, 10, 10);
            scene.add(directionalLight);
            
            // Grid Helper
            const gridHelper = new THREE.GridHelper(100, 100, 0x00bcd4, 0x444444);
            scene.add(gridHelper);
            
            // Axes Helper
            const axesHelper = new THREE.AxesHelper(10);
            scene.add(axesHelper);
            
            // Load PLY files
            const loader = new THREE.PLYLoader();
            let loadedCount = 0;
            
            arquivos.forEach((arquivo, index) => {
                loader.load(
                    arquivo.caminho,
                    function(geometry) {
                        geometry.computeVertexNormals();
                        
                        // Material otimizado
                        const material = new THREE.PointsMaterial({
                            size: 0.05,
                            vertexColors: geometry.attributes.color !== undefined,
                            sizeAttenuation: true
                        });
                        
                        const points = new THREE.Points(geometry, material);
                        scene.add(points);
                        pointClouds.push(points);
                        
                        loadedCount++;
                        if (loadedCount === arquivos.length) {
                            // Centralizar câmera
                            const box = new THREE.Box3();
                            pointClouds.forEach(pc => box.expandByObject(pc));
                            const center = box.getCenter(new THREE.Vector3());
                            const size = box.getSize(new THREE.Vector3());
                            
                            camera.position.set(
                                center.x + size.x,
                                center.y + size.y,
                                center.z + size.z
                            );
                            controls.target.copy(center);
                            controls.update();
                            
                            document.getElementById('loadingOverlay').style.display = 'none';
                        }
                    },
                    function(xhr) {
                        const percent = (xhr.loaded / xhr.total * 100).toFixed(0);
                        document.querySelector('.loading-text').textContent = 
                            'Carregando arquivo ' + (index + 1) + '/' + arquivos.length + ' (' + percent + '%)';
                    },
                    function(error) {
                        console.error('Erro ao carregar PLY:', error);
                        alert('Erro ao carregar arquivo: ' + arquivo.nome);
                    }
                );
            });
            
            // Animation loop
            animate();
            
            // Resize handler
            window.addEventListener('resize', onWindowResize, false);
        }
        
        function animate() {
            requestAnimationFrame(animate);
            controls.update();
            renderer.render(scene, camera);
        }
        
        function onWindowResize() {
            camera.aspect = window.innerWidth / (window.innerHeight - 70);
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight - 70);
        }
        
        // Initialize
        window.addEventListener('load', init);
    </script>
    <?php endif; ?>
</body>
</html>

