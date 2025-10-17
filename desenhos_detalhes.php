<?php
// Iniciar sessão
session_start();

require_once 'connection.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_copasa'])) {
    header('Location: index.php');
    exit();
}

$projeto = $_GET['projeto'];
$cidade = $_GET['cidade'];
$latitude = $_GET['lat'];
$longitude = $_GET['lng'];

echo "<script>let urlProjeto = '$projeto';</script>";
echo "<script>let urlCidade = '$cidade';</script>";
echo "<script>let latitude = '$latitude';</script>";
echo "<script>let longitude = '$longitude';</script>";

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa</title>

    <!-- jQuery -->
    <script src="jquery.min.js"></script>
    <!-- Bootstrap 5.3 -->
    <script src="bootstrap.bundle.min.js"></script>
    <link href="bootstrap.min.css" rel="stylesheet">

    <!--Conexão com fonts do Google-->
    <link href='https://fonts.googleapis.com/css?family=Muli' rel='stylesheet'>

    <!--Conexão com biblioteca de BUFFER para poligono-->
    <script src="https://unpkg.com/@turf/turf@6.5.0/turf.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.7.5/proj4.js"></script>

    <script>
        (g => {
            var h, a, k, p = "The Google Maps JavaScript API",
                c = "google",
                l = "importLibrary",
                q = "__ib__",
                m = document,
                b = window;
            b = b[c] || (b[c] = {});
            var d = b.maps || (b.maps = {}),
                r = new Set,
                e = new URLSearchParams,
                u = () => h || (h = new Promise(async (f, n) => {
                    await (a = m.createElement("script"));
                    e.set("libraries", [...r] + "");
                    for (k in g) e.set(k.replace(/[A-Z]/g, t => "_" + t[0].toLowerCase()), g[k]);
                    e.set("callback", c + ".maps." + q);
                    a.src = `https://maps.${c}apis.com/maps/api/js?` + e;
                    d[q] = f;
                    a.onerror = () => h = n(Error(p + " could not load."));
                    a.nonce = m.querySelector("script[nonce]")?.nonce || "";
                    m.head.append(a)
                }));
            d[l] ? console.warn(p + " only loads once. Ignoring:", g) : d[l] = (f, ...n) => r.add(f) && u().then(() => d[l](f, ...n))
        })
        ({
            key: "AIzaSyBLPXuO8WNaFICoY6YxGaZCi-gOHCLNkrQ",
            v: "weekly"
        });
    </script>

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

        html,
        body {
            width: 100%;
            height: 100vh;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gradient-bg);
            overflow: hidden;
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
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }

        /* Navbar */
        .navbar {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 15px 0;
            position: relative;
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

        /* Botão desabilitado */
        .btn-navbar:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Botão de sair do modo */
        .btn-exit-mode {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            box-shadow: 0 5px 15px rgba(244, 67, 54, 0.3);
        }

        .btn-exit-mode:hover:not(:disabled) {
            box-shadow: 0 8px 20px rgba(244, 67, 54, 0.4);
        }

        /* Botão Camadas */
        .btn-layers {
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
        }

        .btn-layers:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 188, 212, 0.4);
        }

        .btn-layers i {
            font-size: 1.1rem;
        }

        /* Ocultar elementos */
        .hidden {
            display: none !important;
        }

        /* Labels de distância no mapa */
        .distance-label,
        .distance-label-total {
            background: white;
            padding: 4px 8px;
            border-radius: 4px;
            border: 2px solid #FF0000;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
            white-space: nowrap;
        }

        /* Dropdown Menu Customizado */
        .layers-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 20px;
            background: var(--gradient-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            min-width: 280px;
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.3);
            display: none;
            z-index: 2000;
        }

        .layers-menu.show {
            display: block;
            animation: fadeInDown 0.3s ease;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .layers-section {
            margin-bottom: 15px;
        }

        .layers-section:last-child {
            margin-bottom: 0;
        }

        .layers-title {
            color: var(--accent-color);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .map-type-buttons {
            display: flex;
            gap: 10px;
        }

        .map-type-btn {
            flex: 1;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--text-light);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .map-type-btn:hover {
            background: rgba(0, 188, 212, 0.1);
            border-color: var(--primary-color);
        }

        .map-type-btn.active {
            background: var(--gradient-primary);
            border-color: var(--gradient-primary);
            color: white;
        }

        .layers-divider {
            border: none;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin: 15px 0;
        }

        /* Checkbox Customizado */
        .layer-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.3s ease;
            margin-bottom: 8px;
        }

        .layer-item:last-child {
            margin-bottom: 0;
        }

        .layer-item:hover {
            background: rgba(0, 188, 212, 0.1);
            border-color: var(--primary-color);
            box-shadow: 0 3px 10px rgba(0, 188, 212, 0.15);
        }

        .layer-checkbox {
            display: flex;
            align-items: center;
            cursor: pointer;
            margin: 0;
            font-weight: 500;
            color: var(--text-light);
        }

        .layer-checkbox input[type="checkbox"] {
            display: none;
        }

        .checkbox-custom {
            width: 18px;
            height: 18px;
            border: 2px solid var(--primary-color);
            border-radius: 4px;
            margin-right: 12px;
            position: relative;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .layer-checkbox input[type="checkbox"]:checked + .checkbox-custom {
            background: var(--primary-color);
        }

        .layer-checkbox input[type="checkbox"]:checked + .checkbox-custom::after {
            content: '\f00c';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 10px;
        }

        /* Mapa */
        #map-container {
            width: 100%;
            height: calc(100vh - 90px);
            position: relative;
        }

        #map {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .navbar-title {
                font-size: 1.1rem;
            }

            .navbar-subtitle {
                font-size: 0.75rem;
            }

            .btn-layers {
                padding: 8px 15px;
                font-size: 0.9rem;
            }

            .layers-menu {
                right: 10px;
                left: 10px;
                min-width: auto;
            }

            #map-container {
                height: calc(100vh - 65px);
            }
        }
    </style>
    
    <!-- Font Awesome para ícones -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center w-100">
                <!-- Título e Subtítulo -->
                <a class="navbar-brand" href="#">
                    <div class="navbar-title">
                        <i class="fas fa-map-marked-alt me-2"></i>Copasa
                    </div>
                    <div class="navbar-subtitle">Sistema de Mapeamento COPASA</div>
                </a>
                
                <!-- Botões do Navbar -->
                <div class="navbar-buttons">
                    <!-- Grupo: Botões de Serviços (Modo Normal) -->
                    <div id="service-buttons" class="navbar-buttons">
                        <button class="btn-navbar" onclick="enterMode('ruler')">
                            <i class="fas fa-ruler-combined"></i>
                            <span>Régua</span>
                        </button>
                        <!-- Adicione mais botões de serviço aqui no futuro -->
                        <!-- <button class="btn-navbar" onclick="enterMode('draw')">
                            <i class="fas fa-pen"></i>
                            <span>Desenho</span>
                        </button> -->
                    </div>

                    <!-- Grupo: Modo Régua -->
                    <div id="ruler-buttons" class="navbar-buttons hidden">
                        <button id="btn-distance" class="btn-navbar" onclick="activateSubMode('ruler', 'distance')">
                            <i class="fas fa-arrows-alt-h"></i>
                            <span>Distância</span>
                        </button>
                        <button id="btn-area" class="btn-navbar" onclick="activateSubMode('ruler', 'area')">
                            <i class="fas fa-draw-polygon"></i>
                            <span>Área</span>
                        </button>
                        <button class="btn-navbar btn-exit-mode" onclick="exitMode()">
                            <i class="fas fa-times"></i>
                            <span>Sair do Modo</span>
                        </button>
                    </div>

                    <!-- Separador visual -->
                    <div style="width: 1px; height: 30px; background: rgba(255,255,255,0.2); margin: 0 5px;"></div>

                    <!-- Botão Camadas (sempre visível) -->
                    <button class="btn-layers" onclick="toggleLayersMenu()">
                        <i class="fas fa-layer-group"></i>
                        <span>Camadas</span>
                    </button>
                </div>
                
                
                <!-- Menu de Camadas -->
                <div class="layers-menu" id="layersMenu">
                    <!-- Tipo de Mapa -->
                    <div class="layers-section">
                        <div class="layers-title">Tipo de Mapa</div>
                        <div class="map-type-buttons">
                            <button class="map-type-btn active" data-type="roadmap" onclick="changeMapType('roadmap')">
                                <i class="fas fa-road me-1"></i> Roadmap
                            </button>
                            <button class="map-type-btn" data-type="satellite" onclick="changeMapType('satellite')">
                                <i class="fas fa-satellite me-1"></i> Satélite
                            </button>
                        </div>
                    </div>
                    
                    <hr class="layers-divider">

                    <!-- Camadas do Mapa -->
                    <div class="layers-section">
                        <div class="layers-title">Camadas</div>
                        
                        <!-- Checkbox Ortofoto -->
                        <div class="layer-item">
                            <label class="layer-checkbox">
                                <input type="checkbox" checked data-layer="ortofoto" class="layer-toggle" onchange="toggleLayer(this)">
                                <span class="checkbox-custom"></span>
                                <span><i class="fas fa-image me-2"></i>Ortofoto</span>
                            </label>
                        </div>
                        
                        <!-- Checkbox Réguas -->
                        <div class="layer-item">
                            <label class="layer-checkbox">
                                <input type="checkbox" checked data-layer="rulers" class="layer-toggle" onchange="toggleLayer(this)">
                                <span class="checkbox-custom"></span>
                                <span><i class="fas fa-ruler-combined me-2"></i>Réguas</span>
                            </label>
                        </div>
                        
                        <!-- Adicione novos checkboxes aqui no futuro -->
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Container do Mapa -->
    <div id="map-container">
    <div id="map"></div>
    </div>

    <script>

        let map;
        let coordsLocal = { lat: parseFloat(latitude), lng: parseFloat(longitude) };

        async function initMap() {

            // Request needed libraries.
            const {
                Map
            } = await google.maps.importLibrary("maps");

            const {
                geometry
            } = await google.maps.importLibrary("geometry");

            const {
                Draw
            } = await google.maps.importLibrary("drawing");

            const {
                AdvancedMarkerElement
            } = await google.maps.importLibrary("marker");

            const {
                places
            } = await google.maps.importLibrary("places");
            //

            // The map, centered at Uluru
            map = new Map(document.getElementById("map"), {

                //configuração do botão de mapa e tipo
                mapTypeControl: false,

                //tipo do mapa
                mapTypeId: 'roadmap',

                //configuração do botão de zoom
                zoomControl: false,
                zoomControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_BOTTOM
                },

                //configuração do botão de escala                      
                scaleControl: true,

                //configuração do botão de tela cheia                                  
                fullscreenControl: true,
                fullscreenControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_BOTTOM
                },

                //configuração do botão de street view  
                streetViewControl: true,
                streetViewControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_BOTTOM
                },

                zoom: 16,
                center: coordsLocal,
                //mapId: "DEMO_MAP_ID",

                // **Remove o botão de rotação**
                rotateControl: false,
                // **Desativa a inclinação (3D) e a rotação**
                tilt: 0,
                heading: 0,
                mapId: "DEMO_MAP_ID",
            });

            //seta o cursor padrão no mapa
            map.setOptions({
                draggableCursor: 'default'
            });

            // Variável para controlar o estado da ortofoto
            var ortofotoAtiva = true;
            //pasta das fotos

            var url_ortofoto = `projetos/${urlCidade}/${urlProjeto}`;
            console.log(url_ortofoto);

            // Adicionando tiles e calculando os centros
            var ortofotoLayer = new google.maps.ImageMapType({
                getTileUrl: function(coord, zoom) {
                    const proj = map.getProjection();

                    if (!proj) {
                        console.error("Projeção não disponível.");
                        return null;
                    }

                    const tileSize = 256 / Math.pow(2, zoom);

                    const tileBounds = new google.maps.LatLngBounds(
                        proj.fromPointToLatLng(new google.maps.Point(coord.x * tileSize, (coord.y + 1) * tileSize)),
                        proj.fromPointToLatLng(new google.maps.Point((coord.x + 1) * tileSize, coord.y * tileSize))
                    );

                    const invertedY = Math.pow(2, zoom) - coord.y - 1;

                    return url_ortofoto + "/" + zoom + "/" + coord.x + "/" + invertedY + ".png";
                },
                tileSize: new google.maps.Size(256, 256),
                maxZoom: 30,
                minZoom: 0,
                name: "Ortofoto",
            });

            //coloca a ortofoto no mapa por padrão
            map.overlayMapTypes.push(ortofotoLayer);

            // Armazenar referência da ortofoto globalmente
            window.ortofotoLayer = ortofotoLayer;
            window.ortofotoIndex = 0; // Índice da ortofoto no overlayMapTypes

            //===================================================================================

        }

        initMap();

        // ============= ARMAZENAMENTO GLOBAL DE DESENHOS =============

        /**
         * Armazenamento global de todos os desenhos
         */
        const globalDrawings = {
            rulers: [],      // Array de réguas (polylines de distância)
            areas: [],       // Array de áreas (polygons)
            // Adicione mais tipos aqui no futuro
        };

        /**
         * Estado do desenho atual em progresso
         */
        let currentDrawing = {
            type: null,           // 'distance', 'area', etc.
            polyline: null,       // Objeto Polyline do Google Maps
            path: [],             // Array de LatLng
            markers: [],          // Array de marcadores dos vértices
            labels: [],           // Array de labels de distância
            totalLabel: null,     // Label do total
            clickListener: null,  // Listener de cliques no mapa
            rightClickListener: null  // Listener de botão direito
        };

        // ============= SISTEMA DE CONTROLE HIERÁRQUICO DE MODOS =============

        /**
         * Estado global do sistema de modos
         */
        const modeState = {
            currentMode: null,      // 'ruler', 'draw', etc.
            currentSubMode: null,   // 'distance', 'area', etc.
            modeHistory: []         // Pilha de histórico para navegação
        };

        /**
         * Entrar em um modo de serviço (ex: régua, desenho)
         * @param {string} mode - Nome do modo ('ruler', 'draw', etc.)
         */
        function enterMode(mode) {
            console.log(`Entrando no modo: ${mode}`);
            
            // Salvar estado atual na pilha
            modeState.modeHistory.push({
                mode: modeState.currentMode,
                subMode: modeState.currentSubMode
            });
            
            // Atualizar estado
            modeState.currentMode = mode;
            modeState.currentSubMode = null;
            
            // Atualizar interface
            updateButtonsVisibility();
            
            // Lógica específica do modo
            switch(mode) {
                case 'ruler':
                    console.log('Modo régua ativado');
                    // Adicione lógica específica do modo régua aqui
                    break;
                    
                case 'draw':
                    console.log('Modo desenho ativado');
                    // Adicione lógica específica do modo desenho aqui
                    break;
            }
        }

        /**
         * Ativar um sub-modo dentro de um modo (ex: distância dentro de régua)
         * @param {string} mode - Modo pai
         * @param {string} subMode - Sub-modo a ativar ('distance', 'area', etc.)
         */
        function activateSubMode(mode, subMode) {
            console.log(`Ativando sub-modo: ${mode} > ${subMode}`);
            
            // Verificar se estamos no modo correto
            if (modeState.currentMode !== mode) {
                console.warn('Modo incorreto');
                return;
            }
            
            // Salvar estado na pilha
            modeState.modeHistory.push({
                mode: modeState.currentMode,
                subMode: modeState.currentSubMode
            });
            
            // Atualizar estado
            modeState.currentSubMode = subMode;
            
            // Atualizar interface
            updateSubModeButtons(mode, subMode);
            
            // Lógica específica do sub-modo
            if (mode === 'ruler') {
                switch(subMode) {
                    case 'distance':
                        console.log('Medição de distância ativada');
                        startDistanceMeasurement();
                        break;
                        
                    case 'area':
                        console.log('Medição de área ativada');
                        // Adicione lógica de medição de área aqui
                        break;
                }
            }
        }

        /**
         * Sair do modo atual (volta um nível na hierarquia)
         */
        function exitMode() {
            console.log('Saindo do modo...');
            
            // Se estiver em um sub-modo, volta para o modo pai
            if (modeState.currentSubMode) {
                console.log(`Saindo do sub-modo: ${modeState.currentSubMode}`);
                
                // Limpar sub-modo
                const previousSubMode = modeState.currentSubMode;
                modeState.currentSubMode = null;
                
                // Limpar lógica do sub-modo
                clearSubMode(modeState.currentMode, previousSubMode);
                
                // Remover último estado da pilha
                if (modeState.modeHistory.length > 0) {
                    modeState.modeHistory.pop();
                }
                
                // Reabilitar botões do modo pai
                updateSubModeButtons(modeState.currentMode, null);
                
            } else if (modeState.currentMode) {
                // Voltar ao modo normal
                console.log(`Saindo do modo: ${modeState.currentMode}`);
                
                // Limpar modo atual
                const previousMode = modeState.currentMode;
                modeState.currentMode = null;
                modeState.currentSubMode = null;
                
                // Limpar lógica do modo
                clearMode(previousMode);
                
                // Limpar histórico
                modeState.modeHistory = [];
                
                // Atualizar interface
                updateButtonsVisibility();
            }
        }

        /**
         * Atualizar visibilidade dos grupos de botões
         */
        function updateButtonsVisibility() {
            // Ocultar todos os grupos de botões de modo
            document.getElementById('service-buttons').classList.add('hidden');
            document.getElementById('ruler-buttons').classList.add('hidden');
            // Adicione mais grupos aqui no futuro
            
            if (!modeState.currentMode) {
                // Modo normal: mostrar botões de serviço
                document.getElementById('service-buttons').classList.remove('hidden');
            } else {
                // Mostrar grupo do modo atual
                const modeButtonsId = `${modeState.currentMode}-buttons`;
                const modeButtons = document.getElementById(modeButtonsId);
                if (modeButtons) {
                    modeButtons.classList.remove('hidden');
                }
            }
        }

        /**
         * Atualizar estado dos botões de sub-modo
         * @param {string} mode - Modo atual
         * @param {string} activeSubMode - Sub-modo ativo (null = nenhum)
         */
        function updateSubModeButtons(mode, activeSubMode) {
            if (mode === 'ruler') {
                const distanceBtn = document.getElementById('btn-distance');
                const areaBtn = document.getElementById('btn-area');
                
                if (activeSubMode) {
                    // Algum sub-modo está ativo
                    distanceBtn.disabled = true;
                    areaBtn.disabled = true;
                    distanceBtn.classList.remove('active');
                    areaBtn.classList.remove('active');
                    
                    // Destacar botão ativo
                    if (activeSubMode === 'distance') {
                        distanceBtn.classList.add('active');
                    } else if (activeSubMode === 'area') {
                        areaBtn.classList.add('active');
                    }
                } else {
                    // Nenhum sub-modo ativo, habilitar todos
                    distanceBtn.disabled = false;
                    areaBtn.disabled = false;
                    distanceBtn.classList.remove('active');
                    areaBtn.classList.remove('active');
                }
            }
            
            // Adicione lógica para outros modos aqui no futuro
        }

        /**
         * Limpar lógica de um sub-modo
         * @param {string} mode - Modo pai
         * @param {string} subMode - Sub-modo a limpar
         */
        function clearSubMode(mode, subMode) {
            console.log(`Limpando sub-modo: ${mode} > ${subMode}`);
            
            if (mode === 'ruler') {
                switch(subMode) {
                    case 'distance':
                        // Limpar medição de distância
                        console.log('Limpando medição de distância');
                        cancelCurrentDrawing();
                        
                        // Desabilitar edição de todos os desenhos existentes
                        globalDrawings.rulers.forEach(ruler => {
                            if (ruler.polyline) {
                                ruler.polyline.setEditable(false);
                                ruler.polyline.setDraggable(false);
                            }
                        });
                        break;
                        
                    case 'area':
                        // Limpar medição de área
                        console.log('Limpando medição de área');
                        cancelCurrentDrawing();
                        break;
                }
            }
        }

        /**
         * Limpar lógica de um modo
         * @param {string} mode - Modo a limpar
         */
        function clearMode(mode) {
            console.log(`Limpando modo: ${mode}`);
            
            switch(mode) {
                case 'ruler':
                    // Limpar modo régua
                    console.log('Limpando modo régua');
                    // Adicione lógica de limpeza aqui
                    break;
                    
                case 'draw':
                    // Limpar modo desenho
                    console.log('Limpando modo desenho');
                    // Adicione lógica de limpeza aqui
                    break;
            }
        }

        // ============= FUNÇÕES DE CONTROLE DE INTERFACE =============

        /**
         * Toggle do menu de camadas
         */
        function toggleLayersMenu() {
            const menu = document.getElementById('layersMenu');
            menu.classList.toggle('show');
        }

        /**
         * Fechar menu ao clicar fora dele
         */
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('layersMenu');
            const button = event.target.closest('.btn-layers');
            const menuElement = event.target.closest('.layers-menu');
            
            if (!button && !menuElement && menu.classList.contains('show')) {
                menu.classList.remove('show');
            }
        });

        /**
         * Alternar tipo de mapa (roadmap ou satellite)
         * @param {string} type - Tipo do mapa ('roadmap' ou 'satellite')
         */
        function changeMapType(type) {
            if (map) {
                map.setMapTypeId(type);
                
                // Atualizar botões ativos
                document.querySelectorAll('.map-type-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.querySelector(`[data-type="${type}"]`).classList.add('active');
            }
        }

        /**
         * Função genérica para controlar visibilidade de camadas
         * @param {HTMLInputElement} checkbox - Elemento checkbox que foi alterado
         */
        function toggleLayer(checkbox) {
            const layerName = checkbox.dataset.layer;
            const isChecked = checkbox.checked;
            
            console.log(`Camada ${layerName}: ${isChecked ? 'ativada' : 'desativada'}`);
            
            // Switch case para diferentes camadas
            switch(layerName) {
                case 'ortofoto':
                    toggleOrtofoto(isChecked);
                    break;
                    
                case 'rulers':
                    toggleRulers(isChecked);
                    break;
                    
                // Adicione novos cases aqui para futuras camadas
                // case 'poligonos':
                //     togglePoligonos(isChecked);
                //     break;
                
                // case 'marcadores':
                //     toggleMarcadores(isChecked);
                //     break;
                
                default:
                    console.warn(`Camada ${layerName} não reconhecida`);
            }
        }

        /**
         * Controla visibilidade da ortofoto
         * @param {boolean} show - true para mostrar, false para esconder
         */
        function toggleOrtofoto(show) {
            if (!map || !window.ortofotoLayer) {
                console.error('Mapa ou camada de ortofoto não inicializado');
                return;
            }
            
            if (show) {
                // Adicionar ortofoto se não estiver presente
                if (map.overlayMapTypes.getLength() === 0) {
                    map.overlayMapTypes.push(window.ortofotoLayer);
                }
            } else {
                // Remover ortofoto
                if (map.overlayMapTypes.getLength() > 0) {
                    map.overlayMapTypes.removeAt(window.ortofotoIndex);
                }
            }
        }

        /**
         * Controla visibilidade das réguas
         * @param {boolean} show - true para mostrar, false para esconder
         */
        function toggleRulers(show) {
            globalDrawings.rulers.forEach(ruler => {
                if (ruler.polyline) {
                    ruler.polyline.setVisible(show);
                }
                ruler.labels.forEach(label => {
                    label.setVisible(show);
                });
                if (ruler.totalLabel) {
                    ruler.totalLabel.setVisible(show);
                }
            });
        }

        // ============= FUNÇÕES DE MEDIÇÃO DE DISTÂNCIA =============

        /**
         * Iniciar medição de distância
         */
        function startDistanceMeasurement() {
            console.log('Iniciando medição de distância...');
            
            // Habilitar edição de todos os desenhos existentes
            globalDrawings.rulers.forEach(ruler => {
                if (ruler.polyline) {
                    ruler.polyline.setEditable(true);
                    ruler.polyline.setDraggable(true);
                }
            });
            
            // Limpar desenho anterior se existir
            if (currentDrawing.polyline) {
                finishCurrentDrawing();
            }
            
            // Configurar novo desenho
            currentDrawing.type = 'distance';
            currentDrawing.path = [];
            currentDrawing.markers = [];
            currentDrawing.labels = [];
            
            // Criar polyline
            currentDrawing.polyline = new google.maps.Polyline({
                map: map,
                strokeColor: '#FF0000',
                strokeOpacity: 1,
                strokeWeight: 3,
                editable: true,
                draggable: true,
                geodesic: true
            });
            
            // Adicionar listener de clique no mapa
            currentDrawing.clickListener = google.maps.event.addListener(map, 'click', function(event) {
                addVertexToCurrentDrawing(event.latLng);
            });
            
            // Adicionar listener de botão direito no mapa (finalizar desenho)
            currentDrawing.rightClickListener = google.maps.event.addListener(map, 'rightclick', function(event) {
                finishCurrentDrawing();
            });
            
            // Listener de mudança no path (drag)
            google.maps.event.addListener(currentDrawing.polyline.getPath(), 'set_at', function(index) {
                updateDistanceLabels();
            });
            
            google.maps.event.addListener(currentDrawing.polyline.getPath(), 'insert_at', function(index) {
                updateDistanceLabels();
            });
            
            // Listener para deletar vértice com botão direito na polyline
            currentDrawing.polylineRightClickListener = google.maps.event.addListener(currentDrawing.polyline, 'rightclick', function(event) {
                // O índice do vértice clicado, se for um vértice
                if (event.vertex != null) {
                    console.log('Clicou com o botão direito no vértice:', event.vertex);
                    console.log('Coordenadas do vértice:', currentDrawing.polyline.getPath().getAt(event.vertex).toString());
                    
                    // Deletar o vértice
                    currentDrawing.polyline.getPath().removeAt(event.vertex);
                    updateDistanceLabels();
                } else {
                    console.log('Clicou com o botão direito fora de um vértice');
                }
            });
            
            // Mudar cursor do mapa
            map.setOptions({ draggableCursor: 'crosshair' });
        }

        /**
         * Adicionar vértice ao desenho atual
         * @param {google.maps.LatLng} latLng - Coordenadas do vértice
         */
        function addVertexToCurrentDrawing(latLng) {
            if (!currentDrawing.polyline) return;
            
            console.log('Adicionando vértice:', latLng.lat(), latLng.lng());
            
            // Adicionar ao path
            currentDrawing.path.push(latLng);
            currentDrawing.polyline.getPath().push(latLng);
            
            // Atualizar labels de distância
            updateDistanceLabels();
        }


        /**
         * Atualizar labels de distância entre vértices
         */
        function updateDistanceLabels() {
            // Limpar labels existentes (google.maps.Marker)
            currentDrawing.labels.forEach(label => label.setMap(null));
            currentDrawing.labels = [];
            
            const path = currentDrawing.polyline.getPath();
            const pathArray = path.getArray();
            
            if (pathArray.length < 2) return;
            
            let totalDistance = 0;
            
            // Criar label para cada segmento
            for (let i = 0; i < pathArray.length - 1; i++) {
                const start = pathArray[i];
                const end = pathArray[i + 1];
                
                // Calcular distância em metros
                const distance = google.maps.geometry.spherical.computeDistanceBetween(start, end);
                totalDistance += distance;
                
                // Calcular ponto médio
                const midPoint = google.maps.geometry.spherical.interpolate(start, end, 0.5);
                
                // Criar ícone customizado para a plaquinha
                const labelIcon = {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                        <svg width="80" height="30" xmlns="http://www.w3.org/2000/svg">
                            <rect width="80" height="30" fill="white" stroke="#FF0000" stroke-width="2" rx="4"/>
                            <text x="40" y="20" text-anchor="middle" fill="#FF0000" font-family="Arial" font-size="14" font-weight="bold">${formatDistance(distance)}</text>
                        </svg>
                    `),
                    anchor: new google.maps.Point(40, 40) // Ancora na parte inferior central da plaquinha
                };

                const label = new google.maps.Marker({
                    position: midPoint,
                    map: map,
                    icon: labelIcon,
                    clickable: false
                });
                
                currentDrawing.labels.push(label);
            }
            
            // Atualizar ou criar label total (sempre acima do primeiro vértice)
            if (currentDrawing.totalLabel) {
                currentDrawing.totalLabel.setMap(null);
            }
            
            if (pathArray.length >= 2) {
                // Usar posição do primeiro vértice diretamente
                const firstVertex = pathArray[0];
                
                // Criar ícone customizado para a plaquinha total
                const totalLabelIcon = {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                        <svg width="100" height="30" xmlns="http://www.w3.org/2000/svg">
                            <rect width="100" height="30" fill="white" stroke="#FF0000" stroke-width="2" rx="4"/>
                            <text x="50" y="20" text-anchor="middle" fill="#FF0000" font-family="Arial" font-size="14" font-weight="bold">Total: ${formatDistance(totalDistance)}</text>
                        </svg>
                    `),
                    anchor: new google.maps.Point(50, 40) // Ancora na parte inferior central da plaquinha
                };

                currentDrawing.totalLabel = new google.maps.Marker({
                    position: firstVertex,
                    map: map,
                    icon: totalLabelIcon,
                    clickable: false
                });
            }
        }

        /**
         * Formatar distância para exibição
         * @param {number} meters - Distância em metros
         * @returns {string} - Distância formatada
         */
        function formatDistance(meters) {
            if (meters >= 1000) {
                return (meters / 1000).toFixed(2) + ' km';
            } else {
                return meters.toFixed(2) + ' m';
            }
        }

        /**
         * Finalizar desenho atual e salvar no array global
         */
        function finishCurrentDrawing() {
            if (!currentDrawing.polyline) return;
            
            const path = currentDrawing.polyline.getPath().getArray();
            
            // Verificar se tem pelo menos 2 pontos
            if (path.length < 2) {
                console.log('Desenho precisa de pelo menos 2 pontos');
                cancelCurrentDrawing();
                return;
            }
            
            console.log('Finalizando desenho com', path.length, 'pontos');
            
            // Calcular distância total
            let totalDistance = 0;
            for (let i = 0; i < path.length - 1; i++) {
                totalDistance += google.maps.geometry.spherical.computeDistanceBetween(path[i], path[i + 1]);
            }
            
            // Criar estrutura do ruler
            const rulerData = {
                type: currentDrawing.type,
                polyline: currentDrawing.polyline,
                path: path,
                labels: currentDrawing.labels,
                totalLabel: currentDrawing.totalLabel,
                totalDistance: totalDistance,
                createdAt: new Date()
            };
            
            // Adicionar listeners para atualizar labels quando a polyline for editada
            google.maps.event.addListener(currentDrawing.polyline.getPath(), 'set_at', function() {
                updateRulerLabels(rulerData);
            });
            
            google.maps.event.addListener(currentDrawing.polyline.getPath(), 'insert_at', function() {
                updateRulerLabels(rulerData);
            });
            
            google.maps.event.addListener(currentDrawing.polyline.getPath(), 'remove_at', function() {
                updateRulerLabels(rulerData);
            });
            
            // Adicionar listener permanente para deletar vértices com botão direito
            google.maps.event.addListener(currentDrawing.polyline, 'rightclick', function(event) {
                // O índice do vértice clicado, se for um vértice
                if (event.vertex != null) {
                    console.log('Deletando vértice', event.vertex, 'da polyline finalizada');
                    rulerData.polyline.getPath().removeAt(event.vertex);
                    updateRulerLabels(rulerData);
                }
            });
            
            // Salvar no array global
            globalDrawings.rulers.push(rulerData);
            
            // Manter polyline editável e draggable
            // NÃO chamar setEditable(false) - usuário pode continuar editando
            
            console.log('Desenho salvo! Total de réguas:', globalDrawings.rulers.length);
            console.log('Distância total:', formatDistance(rulerData.totalDistance));
            
            // Remover listeners de clique no mapa (não pode mais adicionar vértices clicando)
            if (currentDrawing.clickListener) {
                google.maps.event.removeListener(currentDrawing.clickListener);
            }
            if (currentDrawing.rightClickListener) {
                google.maps.event.removeListener(currentDrawing.rightClickListener);
            }
            if (currentDrawing.polylineRightClickListener) {
                google.maps.event.removeListener(currentDrawing.polylineRightClickListener);
            }
            
            // Resetar cursor
            map.setOptions({ draggableCursor: 'default' });
            
            // Resetar desenho atual
            currentDrawing = {
                type: null,
                polyline: null,
                path: [],
                markers: [],
                labels: [],
                totalLabel: null,
                clickListener: null,
                rightClickListener: null,
                polylineRightClickListener: null
            };
            
            // Se ainda estiver no modo distância, iniciar novo desenho
            if (modeState.currentSubMode === 'distance') {
                setTimeout(() => {
                    startDistanceMeasurement();
                }, 100);
            }
        }

        /**
         * Atualizar labels de um ruler finalizado quando ele é editado
         * @param {Object} rulerData - Dados do ruler
         */
        function updateRulerLabels(rulerData) {
            // Limpar labels existentes (google.maps.Marker)
            rulerData.labels.forEach(label => label.setMap(null));
            rulerData.labels = [];
            if (rulerData.totalLabel) {
                rulerData.totalLabel.setMap(null);
            }
            
            const path = rulerData.polyline.getPath();
            const pathArray = path.getArray();
            
            if (pathArray.length < 2) return;
            
            let totalDistance = 0;
            
            // Criar label para cada segmento
            for (let i = 0; i < pathArray.length - 1; i++) {
                const start = pathArray[i];
                const end = pathArray[i + 1];
                
                // Calcular distância em metros
                const distance = google.maps.geometry.spherical.computeDistanceBetween(start, end);
                totalDistance += distance;
                
                // Calcular ponto médio
                const midPoint = google.maps.geometry.spherical.interpolate(start, end, 0.5);
                
                // Criar ícone customizado para a plaquinha
                const labelIcon = {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                        <svg width="80" height="30" xmlns="http://www.w3.org/2000/svg">
                            <rect width="80" height="30" fill="white" stroke="#FF0000" stroke-width="2" rx="4"/>
                            <text x="40" y="20" text-anchor="middle" fill="#FF0000" font-family="Arial" font-size="14" font-weight="bold">${formatDistance(distance)}</text>
                        </svg>
                    `),
                    anchor: new google.maps.Point(40, 30) // Ancora na parte inferior central da plaquinha
                };

                const label = new google.maps.Marker({
                    position: midPoint,
                    map: map,
                    icon: labelIcon,
                    clickable: false
                });
                
                rulerData.labels.push(label);
            }
            
            // Criar label total acima do primeiro vértice
            if (pathArray.length >= 2) {
                const firstVertex = pathArray[0];
                
                // Criar ícone customizado para a plaquinha total
                const totalLabelIcon = {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                        <svg width="100" height="30" xmlns="http://www.w3.org/2000/svg">
                            <rect width="100" height="30" fill="white" stroke="#FF0000" stroke-width="2" rx="4"/>
                            <text x="50" y="20" text-anchor="middle" fill="#FF0000" font-family="Arial" font-size="14" font-weight="bold">Total: ${formatDistance(totalDistance)}</text>
                        </svg>
                    `),
                    anchor: new google.maps.Point(50, 40) // Ancora na parte inferior central da plaquinha
                };

                rulerData.totalLabel = new google.maps.Marker({
                    position: firstVertex,
                    map: map,
                    icon: totalLabelIcon,
                    clickable: false
                });
            }
            
            // Atualizar distância total
            rulerData.totalDistance = totalDistance;
        }

        /**
         * Cancelar desenho atual sem salvar
         */
        function cancelCurrentDrawing() {
            console.log('Cancelando desenho atual...');
            
            // Remover polyline
            if (currentDrawing.polyline) {
                currentDrawing.polyline.setMap(null);
            }
            
            // Remover labels (google.maps.Marker)
            currentDrawing.labels.forEach(label => label.setMap(null));
            if (currentDrawing.totalLabel) {
                currentDrawing.totalLabel.setMap(null);
            }
            
            // Remover listeners
            if (currentDrawing.clickListener) {
                google.maps.event.removeListener(currentDrawing.clickListener);
            }
            if (currentDrawing.rightClickListener) {
                google.maps.event.removeListener(currentDrawing.rightClickListener);
            }
            if (currentDrawing.polylineRightClickListener) {
                google.maps.event.removeListener(currentDrawing.polylineRightClickListener);
            }
            
            // Resetar cursor
            map.setOptions({ draggableCursor: 'default' });
            
            // Resetar estado
            currentDrawing = {
                type: null,
                polyline: null,
                path: [],
                markers: [],
                labels: [],
                totalLabel: null,
                clickListener: null,
                rightClickListener: null,
                polylineRightClickListener: null
            };
        }

        

    </script>
</body>

</html>