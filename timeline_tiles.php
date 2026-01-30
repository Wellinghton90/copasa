<?php
session_start();
require_once 'connection.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_copasa'])) {
    header('Location: index.php');
    exit();
}

$usuario = $_SESSION['user_copasa'];

// Verificar se foi passado o parâmetro cidade
if (!isset($_GET['cidade']) || empty($_GET['cidade'])) {
    $cidade_nao_escolhida = true;
    $cidade = '';
} else {
    $cidade_nao_escolhida = false;
    $cidade = $_GET['cidade'];
}

// Função para carregar dados da cidade do JSON
function carregarDadosCidade($cidade)
{
    $jsonPath = "data/cidades/{$cidade}.json";

    if (!file_exists($jsonPath)) {
        // Retornar dados padrão se o JSON não existir
        return [
            'nome' => $cidade,
            'coordenadas' => [
                'lat' => -19.9167,
                'lng' => -43.9345
            ],
            'zoom' => 15
        ];
    }

    $jsonContent = file_get_contents($jsonPath);
    $dados = json_decode($jsonContent, true);

    if (!$dados) {
        // Retornar dados padrão se o JSON estiver inválido
        return [
            'nome' => $cidade,
            'coordenadas' => [
                'lat' => -19.9167,
                'lng' => -43.9345
            ],
            'zoom' => 15
        ];
    }

    return $dados;
}

// Carregar dados da cidade se existir
$dadosCidade = null;
if (!$cidade_nao_escolhida) {
    $dadosCidade = carregarDadosCidade($cidade);
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
    <title>Timeline Tiles - COPASA</title>

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
            display: flex;
            flex-direction: column;
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
            flex: 1;
            display: flex;
            flex-direction: column;
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

        /* Card do Mapa */
        .mapa-card {
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
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .mapa-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .mapa-container {
            flex: 1;
            min-height: 500px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            background: rgba(0, 0, 0, 0.2);
        }
        
        #map {
            width: 100%;
            height: 100%;
            min-height: 500px;
        }
        
        /* Leaflet customização */
        .leaflet-container {
            background: rgba(0, 0, 0, 0.3);
        }
        .tooltip-medida.leaflet-tooltip {
            font-weight: 600;
            font-size: 13px;
            padding: 4px 8px;
            background: rgba(0, 0, 0, 0.85);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        /* Painel de camadas (canto superior esquerdo) */
        .painel-camadas {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 1000;
            background: rgba(30, 30, 40, 0.95);
            backdrop-filter: blur(20px);
            border: 2px solid rgba(0, 188, 212, 0.5);
            border-radius: 12px;
            padding: 12px 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.5);
        }
        .painel-camadas .titulo-camadas {
            color: var(--text-light);
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            padding-bottom: 6px;
        }
        .painel-camadas .camada-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-light);
            font-size: 0.9rem;
            cursor: pointer;
            padding: 2px 0;
            user-select: none;
        }
        .painel-camadas .camada-item:hover {
            color: var(--accent-color);
        }
        .painel-camadas .camada-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }
        
        /* Ferramentas de desenho */
        .ferramentas-desenho {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .btn-ferramenta {
            background: rgba(30, 30, 40, 0.95);
            backdrop-filter: blur(20px);
            border: 2px solid rgba(0, 188, 212, 0.5);
            border-radius: 12px;
            padding: 12px 20px;
            color: var(--text-light);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.5);
        }
        
        .btn-ferramenta:hover {
            background: rgba(0, 188, 212, 0.8);
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 188, 212, 0.6);
        }
        
        .btn-ferramenta.active {
            background: rgba(0, 188, 212, 0.9);
            border-color: var(--primary-color);
            box-shadow: 0 5px 20px rgba(0, 188, 212, 0.7);
        }
        
        .btn-ferramenta:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        .modal-content {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.5);
            position: relative;
        }
        
        .modal-content h3 {
            color: var(--text-light);
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        
        .modal-content label {
            color: var(--accent-color);
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }
        
        .modal-content textarea {
            width: 100%;
            min-height: 100px;
            background: rgba(0, 188, 212, 0.05);
            border: 1px solid rgba(0, 188, 212, 0.2);
            border-radius: 10px;
            padding: 12px;
            color: var(--text-light);
            resize: vertical;
        }
        
        .modal-content textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(0, 188, 212, 0.08);
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .btn-modal {
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-modal-cancelar {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-light);
        }
        
        .btn-modal-cancelar:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .btn-modal-salvar {
            background: var(--gradient-primary);
            color: white;
        }
        
        .btn-modal-salvar:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 188, 212, 0.5);
        }
        
        /* Polígono sendo desenhado */
        .poligono-desenho {
            stroke: var(--primary-color);
            stroke-width: 3;
            fill: rgba(0, 188, 212, 0.2);
            stroke-dasharray: 10, 5;
        }
        
        .vertice-ponto {
            fill: var(--primary-color);
            stroke: white;
            stroke-width: 2;
            cursor: pointer;
        }
        
        /* Vértice ao editar polígono */
        .vertice-edit-icon {
            background: transparent !important;
            border: none !important;
        }
        
        .vertice-edit-icon div {
            cursor: move;
        }
        
        /* Marcador GCP */
        .gcp-marker {
            background: transparent;
            border: none;
            text-align: center;
        }
        
        .gcp-marker i {
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.5));
        }
        
        /* Mensagem de modo de desenho */
        .mensagem-modo-desenho {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 1000;
            background: var(--gradient-primary);
            color: white;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 188, 212, 0.5);
            font-weight: 600;
            display: none;
        }
        
        .mensagem-modo-desenho.show {
            display: block;
        }

        /* Timeline */
        .timeline-container {
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
        }

        .timeline-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .timeline {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 20px;
            overflow-x: auto;
            padding: 20px;
            scrollbar-width: thin;
            scrollbar-color: var(--primary-color) rgba(0, 188, 212, 0.1);
        }
        
        .timeline::-webkit-scrollbar {
            height: 8px;
        }
        
        .timeline::-webkit-scrollbar-track {
            background: rgba(0, 188, 212, 0.1);
            border-radius: 10px;
        }
        
        .timeline::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 10px;
        }
        
        .timeline::-webkit-scrollbar-thumb:hover {
            background: var(--accent-color);
        }

        .timeline-item {
            background: rgba(0, 188, 212, 0.1);
            border: 2px solid rgba(0, 188, 212, 0.3);
            border-radius: 15px;
            padding: 15px 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 120px;
            text-align: center;
            color: var(--text-light);
            font-weight: 600;
        }

        .timeline-item:hover {
            background: rgba(0, 188, 212, 0.2);
            border-color: var(--primary-color);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 188, 212, 0.3);
        }

        .timeline-item.active {
            background: var(--gradient-primary);
            border-color: var(--primary-color);
            box-shadow: 0 5px 20px rgba(0, 188, 212, 0.5);
        }

        /* Mensagens */
        .alert {
            border-radius: 15px;
            border: none;
            padding: 20px 30px;
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

        /* Responsivo */
        @media (max-width: 768px) {
            .container-fluid {
                padding: 15px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .mapa-container {
                min-height: 400px;
            }

            .timeline {
                gap: 10px;
            }

            .timeline-item {
                min-width: 100px;
                padding: 10px 15px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body>
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
        <?php if ($cidade_nao_escolhida): ?>
            <!-- Mensagem quando cidade não foi escolhida -->
            <div class="page-header">
                <div class="alert alert-warning" role="alert">
                    <h4 class="alert-heading">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Cidade não escolhida
                    </h4>
                    <p class="mb-0">Por favor, selecione uma cidade para visualizar a timeline.</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Header com nome da cidade -->
            <div class="page-header">
                <h1>
                    <i class="fas fa-map-marker-alt me-2"></i>
                    <?= htmlspecialchars($dadosCidade['nome'] ?? $cidade) ?>
                </h1>
            </div>

            <!-- Card do Mapa -->
            <div class="mapa-card">
                <div class="mapa-container" style="position: relative;">
                    <div id="map"></div>
                    
                    <!-- Painel de camadas (canto superior esquerdo) -->
                    <div class="painel-camadas" id="painelCamadas">
                        <div class="titulo-camadas">Camadas</div>
                        <label class="camada-item">
                            <input type="checkbox" id="chkCamadaOrtofoto" checked>
                            <span>Ortofoto</span>
                        </label>
                        <label class="camada-item">
                            <input type="checkbox" id="chkCamadaGCP" checked>
                            <span>MTP</span>
                        </label>
                        <!--
                        <label class="camada-item">
                            <input type="checkbox" id="chkCamadaPoligonos" checked>
                            <span>Polígonos</span>
                        </label>
                        <label class="camada-item">
                            <input type="checkbox" id="chkCamadaPolilinhas" checked>
                            <span>Polilinhas</span>
                        </label>
                        -->
                    </div>
                    
                    <!-- Mensagem de Modo de Desenho -->
                    <div class="mensagem-modo-desenho" id="mensagemModoDesenho">
                        <i class="fas fa-info-circle me-2"></i>
                        Modo Polígono Ativo: Clique no mapa para adicionar vértices. Botão direito para finalizar.
                    </div>
                    
                    <!-- Ferramentas de Desenho -->
                    <div class="ferramentas-desenho" id="ferramentasDesenho">
                        <button class="btn-ferramenta" id="btnGCP" title="Manual tie point">
                            <i class="fas fa-map-pin"></i>
                            <span>MTP</span>
                        </button>
                        <!--
                        <button class="btn-ferramenta" id="btnPoligono" title="Desenhar Polígono" style="display: none;">
                            <i class="fas fa-draw-polygon"></i>
                            <span>Polígono</span>
                        </button>
                        <button class="btn-ferramenta" id="btnPolilinha" title="Desenhar Polilinha" style="display: none;">
                            <i class="fas fa-road"></i>
                            <span>Polilinha</span>
                        </button>
                        -->
                        <button class="btn-ferramenta" id="btnSairModo" style="display: none;" title="Sair do Modo de Desenho">
                            <i class="fas fa-times"></i>
                            <span>Sair</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Modal para Descrição do Desenho -->
            <div class="modal-overlay" id="modalDescricao">
                <div class="modal-content">
                    <h3>
                        <i class="fas fa-info-circle me-2" style="color: var(--primary-color);"></i>
                        <span id="modalDescricaoTitulo">Descrição do Desenho</span>
                    </h3>
                    <div id="modalDescricaoMedidaContainer" style="display: none; margin-bottom: 12px; padding: 8px 12px; background: rgba(0,0,0,0.2); border-radius: 8px;">
                        <strong style="color: var(--accent-color);">Medida total:</strong> <span id="modalDescricaoMedidaValor">0 m</span>
                    </div>
                    <label for="descricaoDesenho">Descreva este desenho:</label>
                    <textarea id="descricaoDesenho" placeholder="Digite a descrição do desenho... (Ctrl+Enter para salvar)"></textarea>
                    <div class="modal-buttons">
                        <button class="btn-modal btn-modal-cancelar" id="btnCancelarDesenho">Cancelar</button>
                        <button class="btn-modal btn-modal-salvar" id="btnSalvarDesenho">Salvar</button>
                    </div>
                </div>
            </div>
            
            <!-- Modal para Marcar GCP -->
            <div class="modal-overlay" id="modalGCP">
                <div class="modal-content">
                    <h3>
                        <i class="fas fa-map-pin me-2" style="color: var(--primary-color);"></i>
                        Marcar Ponto de Referência (MTP)
                    </h3>
                    <p id="gcpInstrucoes" style="color: var(--text-light); margin-bottom: 20px;">
                        Clique no mapa para marcar o ponto de referência (MTP). 
                        Este ponto será usado para alinhar os desenhos entre diferentes ortofotos.
                    </p>
                    <div id="gcpAlertaMesmoLocal" style="display: none; background: rgba(255, 193, 7, 0.2); padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                        <strong style="color: #ffc107;"><i class="fas fa-exclamation-triangle me-2"></i>Atenção:</strong>
                        <p style="color: var(--text-light); margin-top: 5px; margin-bottom: 0;">
                            Já existe um MTP definido em outro projeto. Por favor, marque o <strong>mesmo local</strong> para garantir o alinhamento correto dos desenhos.
                        </p>
                    </div>
                    <div id="gcpInfo" style="display: none; background: rgba(0, 188, 212, 0.1); padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                        <strong style="color: var(--accent-color);">Coordenadas marcadas:</strong>
                        <div id="gcpCoordenadas" style="color: var(--text-light); margin-top: 5px;"></div>
                    </div>
                    <div id="gcpListaAssociarContainer" style="display: none; margin-bottom: 20px;">
                        <label style="color: var(--accent-color); display: block; margin-bottom: 10px;">Este ponto corresponde a:</label>
                        <div id="gcpListaAssociar" style="max-height: 200px; overflow-y: auto;">
                            <!-- opções geradas via JS -->
                        </div>
                    </div>
                    <div id="gcpDescricaoContainer" style="display: none;">
                        <label for="descricaoGCP">Descrição do MTP:</label>
                        <textarea id="descricaoGCP" placeholder="Descreva este ponto de referência... (Ctrl+Enter para salvar)"></textarea>
                    </div>
                    <div class="modal-buttons">
                        <button class="btn-modal btn-modal-cancelar" id="btnCancelarGCP">Cancelar</button>
                        <button class="btn-modal btn-modal-salvar" id="btnConfirmarGCP" disabled>Confirmar MTP</button>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="timeline-container">
                <h3 class="mb-4" style="color: var(--text-light);">
                    <i class="fas fa-calendar-alt me-2" style="color: var(--primary-color);"></i>
                    Timeline
                </h3>
                <div class="timeline" id="timeline">
                    <!-- Timeline items serão adicionados aqui via JavaScript -->
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="jquery.min.js"></script>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        <?php if (!$cidade_nao_escolhida && $dadosCidade): ?>
        // Dados dos projetos
        const projetos = <?= json_encode($dadosCidade['projetos'] ?? []) ?>;
        const coordenadas = [<?= $dadosCidade['coordenadas']['lat'] ?? -19.9167 ?>, <?= $dadosCidade['coordenadas']['lng'] ?? -43.9345 ?>];
        const zoom = <?= $dadosCidade['zoom'] ?? 15 ?>;
        const cidade = '<?= htmlspecialchars($cidade) ?>';
        
        // Variáveis globais
        let map;
        let currentOrtofotoLayer = null;
        let modoDesenho = false;
        let modoPoligono = false;
        let verticesPoligono = [];
        let poligonoAtual = null;
        let modoPolilinha = false;
        let verticesPolilinha = [];
        let polilinhaAtual = null;
        let layerGroupDesenhos = null;
        let layerGroupLinhas = null;      // Polilinhas (para toggle em camadas)
        let layerGroupTemporario = null;  // Para marcadores temporários durante desenho
        let layerGroupMarcadoresGCP = null;
        let layerGroupVertices = null;    // Marcadores de vértices ao editar polígono
        let projetoAtual = null;
        let projetosOrdenados = [];
        let modoGCP = false;
        let gcpMarcado = null;
        let marcadorGCP = null;        // marcador temporário ao clicar (vermelho)
        let marcadoresGCP = [];         // array de marcadores GCP do projeto atual (verde)
        let projetoPendenteGCP = null;  // Variável para armazenar projeto pendente de GCP
        let poligonoEmEdicao = null;    // polígono atualmente em edição
        let polilinhaEmEdicao = null;   // polilinha atualmente em edição
        let marcadoresVertices = [];    // array de marcadores de vértice (para remover ao sair da edição)
        let marcadoresVerticesDesenho = [];  // marcadores de vértice arrastáveis durante desenho do polígono
        let marcadoresVerticesDesenhoLinha = [];  // vértices arrastáveis durante desenho da polilinha
        let tipoDesenhoAtual = 'poligono';  // 'poligono' | 'linha' ao abrir modal de descrição
        
        // Função para formatar data para exibição (não depende do Leaflet)
        function formatarData(datetime) {
            const data = new Date(datetime);
            const dia = String(data.getDate()).padStart(2, '0');
            const mes = String(data.getMonth() + 1).padStart(2, '0');
            const ano = data.getFullYear();
            return `${dia}/${mes}/${ano}`;
        }
        
        // Inicializar quando DOM estiver pronto e Leaflet carregado
        function inicializarMapa() {
            // Verificar se já foi inicializado
            if (window.mapaInicializado) return;
            
            // Verificar se Leaflet está carregado
            if (typeof L === 'undefined') {
                console.warn('Leaflet ainda não está disponível, aguardando...');
                setTimeout(inicializarMapa, 100);
                return;
            }
            
            window.mapaInicializado = true;
            console.log('Inicializando mapa...');
            
            (function() {
            
            // Função para criar camada de ortofoto
            function criarOrtofotoLayer(projetoNome) {
                const urlOrtofoto = `projetos/${cidade}/${projetoNome}/3_dsm_ortho/2_mosaic/google_tiles`;
                
                // Criar camada de tiles customizada para Google Tiles
                // Google Maps usa formato Y invertido: invertedY = Math.pow(2, zoom) - y - 1
                // maxNativeZoom: 22 significa que tiles só existem até zoom 22, mas permite zoom até 25 ampliando os tiles
                const GoogleTilesLayer = L.TileLayer.extend({
                    getTileUrl: function(coords) {
                        // Se o zoom solicitado for maior que maxNativeZoom, usar o zoom máximo disponível
                        const zoomParaUsar = Math.min(coords.z, 22);
                        const invertedY = Math.pow(2, zoomParaUsar) - coords.y - 1;
                        return `${urlOrtofoto}/${zoomParaUsar}/${coords.x}/${invertedY}.png`;
                    }
                });
                
                return new GoogleTilesLayer('', {
                    maxZoom: 25,
                    maxNativeZoom: 22,
                    minZoom: 0,
                    attribution: 'Ortofoto',
                    opacity: 1.0
                });
            }
            
            // Função para trocar ortofoto
            function trocarOrtofoto(projetoNome) {
                // Salvar zoom e centro atuais ANTES de qualquer operação
                const zoomAtual = map.getZoom();
                const centroAtual = map.getCenter();
                
                // Mostrar zoom atual no console
                console.log('Zoom atual antes de trocar:', zoomAtual);
                console.log('Centro atual:', centroAtual);
                
                // Remover camada anterior se existir
                if (currentOrtofotoLayer) {
                    map.removeLayer(currentOrtofotoLayer);
                }
                
                // Criar e adicionar nova camada
                currentOrtofotoLayer = criarOrtofotoLayer(projetoNome);
                currentOrtofotoLayer.addTo(map);
                
                // Garantir que o zoom seja mantido - usar setView que mantém ambos
                // Usar requestAnimationFrame para garantir que a camada foi renderizada
                requestAnimationFrame(function() {
                    map.setView(centroAtual, zoomAtual, {
                        animate: false,
                        reset: false
                    });
                    
                    // Verificar se o zoom foi mantido
                    setTimeout(function() {
                        const zoomFinal = map.getZoom();
                        const centroFinal = map.getCenter();
                        console.log('Zoom após trocar:', zoomFinal);
                        console.log('Centro após trocar:', centroFinal);
                        
                        // Se o zoom mudou, forçar novamente
                        if (zoomFinal !== zoomAtual) {
                            console.log('Zoom foi alterado, restaurando...');
                            map.setZoom(zoomAtual, { animate: false });
                            map.panTo(centroAtual, { animate: false });
                        }
                    }, 100);
                });
            }
            
            // Tornar função disponível globalmente
            window.trocarOrtofoto = trocarOrtofoto;
            // Ordenar projetos por data (mais antigo primeiro)
            const projetosOrdenados = projetos.sort((a, b) => {
                return new Date(a.datetime) - new Date(b.datetime);
            });
            
            // Inicializar mapa Leaflet com maxZoom 25 (zoom em canto inferior esquerdo para o painel de camadas ficar no superior esquerdo)
            map = L.map('map', {
                center: coordenadas,
                zoom: zoom,
                zoomControl: false,
                maxZoom: 25,
                minZoom: 0
            });
            L.control.zoom({ position: 'bottomleft' }).addTo(map);
            
            // Adicionar camada base (roadmap) - maxNativeZoom 19 permite zoom até 25 ampliando os tiles do zoom 19
            const baseLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 25,
                maxNativeZoom: 19,
                minZoom: 0
            });
            baseLayer.addTo(map);
            
            // Variável para armazenar camada base (para controle de camadas)
            window.baseLayer = baseLayer;
            
            // Mostrar zoom inicial no console
            console.log('Zoom inicial:', zoom);
            
            // Event listener para mostrar zoom quando mudar
            map.on('zoomend', function() {
                console.log('Zoom atualizado:', map.getZoom());
            });
            
            // Inicializar layer groups
            layerGroupDesenhos = L.layerGroup().addTo(map);
            layerGroupLinhas = L.layerGroup().addTo(map);
            layerGroupTemporario = L.layerGroup().addTo(map);
            layerGroupMarcadoresGCP = L.layerGroup().addTo(map);
            layerGroupVertices = L.layerGroup().addTo(map);
            
            // Painel de camadas: checkboxes para mapa base, ortofoto, GCP, Polígonos e Polilinhas
            const chkCamadaMapaBase = document.getElementById('chkCamadaMapaBase');
            const chkCamadaOrtofoto = document.getElementById('chkCamadaOrtofoto');
            if (chkCamadaMapaBase) {
                chkCamadaMapaBase.addEventListener('change', function() {
                    if (this.checked) {
                        if (window.baseLayer && !map.hasLayer(window.baseLayer)) map.addLayer(window.baseLayer);
                    } else {
                        if (window.baseLayer && map.hasLayer(window.baseLayer)) map.removeLayer(window.baseLayer);
                    }
                });
            }
            if (chkCamadaOrtofoto) {
                chkCamadaOrtofoto.addEventListener('change', function() {
                    if (this.checked) {
                        if (currentOrtofotoLayer && !map.hasLayer(currentOrtofotoLayer)) map.addLayer(currentOrtofotoLayer);
                    } else {
                        if (currentOrtofotoLayer && map.hasLayer(currentOrtofotoLayer)) map.removeLayer(currentOrtofotoLayer);
                    }
                });
            }
            const chkCamadaGCP = document.getElementById('chkCamadaGCP');
            const chkCamadaPoligonos = document.getElementById('chkCamadaPoligonos');
            if (chkCamadaGCP) {
                chkCamadaGCP.addEventListener('change', function() {
                    if (this.checked) {
                        if (layerGroupMarcadoresGCP && !map.hasLayer(layerGroupMarcadoresGCP)) map.addLayer(layerGroupMarcadoresGCP);
                    } else {
                        if (layerGroupMarcadoresGCP && map.hasLayer(layerGroupMarcadoresGCP)) map.removeLayer(layerGroupMarcadoresGCP);
                    }
                });
            }
            if (chkCamadaPoligonos) {
                chkCamadaPoligonos.addEventListener('change', function() {
                    if (this.checked) {
                        if (layerGroupDesenhos && !map.hasLayer(layerGroupDesenhos)) map.addLayer(layerGroupDesenhos);
                    } else {
                        if (layerGroupDesenhos && map.hasLayer(layerGroupDesenhos)) map.removeLayer(layerGroupDesenhos);
                    }
                });
            }
            const chkCamadaPolilinhas = document.getElementById('chkCamadaPolilinhas');
            if (chkCamadaPolilinhas) {
                chkCamadaPolilinhas.addEventListener('change', function() {
                    if (this.checked) {
                        if (layerGroupLinhas && !map.hasLayer(layerGroupLinhas)) map.addLayer(layerGroupLinhas);
                    } else {
                        if (layerGroupLinhas && map.hasLayer(layerGroupLinhas)) map.removeLayer(layerGroupLinhas);
                    }
                });
            }
            
            // Salvar projetosOrdenados globalmente
            window.projetosOrdenados = projetosOrdenados;
            
            // Carregar ortofoto do projeto mais antigo (primeiro da lista ordenada)
            if (projetosOrdenados.length > 0) {
                const projetoMaisAntigo = projetosOrdenados[0];
                verificarECarregarProjeto(projetoMaisAntigo);
            }
            
            // Criar itens da timeline
            const timelineContainer = document.getElementById('timeline');
            projetosOrdenados.forEach((projeto, index) => {
                const timelineItem = document.createElement('div');
                timelineItem.className = 'timeline-item';
                if (index === 0) {
                    timelineItem.classList.add('active'); // Primeiro item ativo
                }
                timelineItem.textContent = formatarData(projeto.datetime);
                timelineItem.dataset.projetoNome = projeto.nome;
                
                // Adicionar evento de clique
                timelineItem.addEventListener('click', function() {
                    // Não permitir trocar se estiver em modo de desenho
                    if (modoDesenho) {
                        alert('Saia do modo de desenho antes de trocar de projeto!');
                        return;
                    }
                    
                    // Remover classe active de todos os itens
                    document.querySelectorAll('.timeline-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    // Adicionar classe active ao item clicado
                    this.classList.add('active');
                    
                    // Encontrar projeto correspondente
                    const projetoSelecionado = projetosOrdenados.find(p => p.nome === this.dataset.projetoNome);
                    if (projetoSelecionado) {
                        // Verificar se precisa marcar GCP antes de trocar
                        verificarECarregarProjeto(projetoSelecionado);
                    }
                });
                
                timelineContainer.appendChild(timelineItem);
            });
            
            // Ajustar mapa ao redimensionar
            window.addEventListener('resize', function() {
                map.invalidateSize();
            });
            
            // ============= FERRAMENTAS DE DESENHO =============
            
            // Função para entrar no modo polígono
            function entrarModoPoligono() {
                modoDesenho = true;
                modoPoligono = true;
                verticesPoligono = [];
                marcadoresVerticesDesenho = [];
                layerGroupTemporario.clearLayers();
                const btnPoligono = document.getElementById('btnPoligono');
                if (btnPoligono) btnPoligono.classList.add('active');
                const btnSairModo = document.getElementById('btnSairModo');
                if (btnSairModo) btnSairModo.style.display = 'block';
                const msg = document.getElementById('mensagemModoDesenho');
                if (msg) {
                    msg.classList.add('show');
                    msg.innerHTML = '<i class="fas fa-info-circle me-2"></i>Modo Polígono Ativo: Clique no mapa para adicionar vértices. Botão direito para finalizar.';
                }
                
                // Desabilitar timeline
                document.querySelectorAll('.timeline-item').forEach(item => {
                    item.style.pointerEvents = 'none';
                    item.style.opacity = '0.5';
                });
                
                // Mudar cursor do mapa
                map.getContainer().style.cursor = 'crosshair';
            }
            
            // Função para sair do modo de desenho
            function sairModoDesenho() {
                modoDesenho = false;
                modoPoligono = false;
                modoPolilinha = false;
                
                // Limpar polígono/polilinha em desenho e marcadores temporários
                layerGroupTemporario.clearLayers();
                poligonoAtual = null;
                verticesPoligono = [];
                polilinhaAtual = null;
                verticesPolilinha = [];
                marcadoresVerticesDesenho = [];
                marcadoresVerticesDesenhoLinha = [];
                
                // Atualizar UI
                const btnPoligono = document.getElementById('btnPoligono');
                const btnPolilinha = document.getElementById('btnPolilinha');
                if (btnPoligono) btnPoligono.classList.remove('active');
                if (btnPolilinha) btnPolilinha.classList.remove('active');
                const btnSairModo = document.getElementById('btnSairModo');
                if (btnSairModo) btnSairModo.style.display = 'none';
                const msg = document.getElementById('mensagemModoDesenho');
                if (msg) msg.classList.remove('show');
                
                // Reabilitar timeline
                document.querySelectorAll('.timeline-item').forEach(item => {
                    item.style.pointerEvents = 'auto';
                    item.style.opacity = '1';
                });
                
                // Restaurar cursor do mapa
                map.getContainer().style.cursor = '';
            }
            
            // Ícone reutilizável para vértice (desenho e edição)
            const iconVerticeEdit = {
                get: function() {
                    return L.divIcon({
                        className: 'vertice-edit-icon',
                        html: '<div style="width:14px;height:14px;border-radius:50%;background:#00bcd4;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,0.4);"></div>',
                        iconSize: [14, 14],
                        iconAnchor: [7, 7]
                    });
                }
            };
            
            // Redesenha os marcadores de vértice arrastáveis durante o desenho do polígono
            function atualizarMarcadoresVerticesDesenho() {
                marcadoresVerticesDesenho.forEach(m => {
                    if (layerGroupTemporario.hasLayer(m)) layerGroupTemporario.removeLayer(m);
                });
                marcadoresVerticesDesenho = [];
                const iconV = iconVerticeEdit.get();
                verticesPoligono.forEach((coord, idx) => {
                    const m = L.marker(coord, { icon: iconV, draggable: true });
                    m._verticeIndex = idx;
                    m.on('dragend', function() {
                        const ll = this.getLatLng();
                        verticesPoligono[this._verticeIndex] = [ll.lat, ll.lng];
                        if (poligonoAtual && verticesPoligono.length >= 2) {
                            poligonoAtual.setLatLngs(verticesPoligono);
                        }
                    });
                    m.addTo(layerGroupTemporario);
                    marcadoresVerticesDesenho.push(m);
                });
            }
            
            // Função para adicionar vértice ao polígono
            function adicionarVerticePoligono(e) {
                if (!modoPoligono) return;
                
                const latlng = e.latlng;
                verticesPoligono.push([latlng.lat, latlng.lng]);
                
                // Remover polígono anterior se existir (está no layer temporário)
                if (poligonoAtual) {
                    layerGroupTemporario.removeLayer(poligonoAtual);
                }
                
                // Se tiver pelo menos 2 pontos, desenhar linha/polígono
                if (verticesPoligono.length >= 2) {
                    poligonoAtual = L.polygon(verticesPoligono, {
                        color: '#00bcd4',
                        weight: 3,
                        fillColor: '#00bcd4',
                        fillOpacity: 0.2,
                        dashArray: '10, 5'
                    }).addTo(layerGroupTemporario);
                }
                
                // Marcadores de vértice arrastáveis (substitui circleMarker fixo)
                atualizarMarcadoresVerticesDesenho();
            }
            
            // Função para finalizar polígono
            function finalizarPoligono() {
                if (!modoPoligono || verticesPoligono.length < 3) {
                    alert('Um polígono precisa de pelo menos 3 vértices!');
                    return;
                }
                tipoDesenhoAtual = 'poligono';
                const titulo = document.getElementById('modalDescricaoTitulo');
                if (titulo) titulo.textContent = 'Descrição do Desenho';
                const medidaContainer = document.getElementById('modalDescricaoMedidaContainer');
                if (medidaContainer) medidaContainer.style.display = 'none';
                const modal = document.getElementById('modalDescricao');
                if (modal) {
                    modal.classList.add('show');
                    const descricao = document.getElementById('descricaoDesenho');
                    if (descricao) {
                        descricao.value = '';
                        descricao.focus();
                    }
                }
            }
            
            // Função para salvar desenho (polígono ou polilinha)
            function salvarDesenho() {
                const descricaoInput = document.getElementById('descricaoDesenho');
                if (!descricaoInput) return;
                const descricao = descricaoInput.value.trim();
                
                if (!descricao) {
                    alert('Por favor, digite uma descrição!');
                    return;
                }
                
                if (!projetoAtual) {
                    alert('Erro: Projeto atual não encontrado!');
                    return;
                }
                
                const isLinha = tipoDesenhoAtual === 'linha';
                const coordenadasSalvar = isLinha ? verticesPolilinha : verticesPoligono;
                const desenho = {
                    tipo: isLinha ? 'linha' : 'poligono',
                    descricao: descricao,
                    coordenadas: coordenadasSalvar
                };
                if (isLinha) desenho.medida = calcularMedidaMetros(verticesPolilinha);
                
                const btnSalvar = document.getElementById('btnSalvarDesenho');
                const textoOriginal = btnSalvar.innerHTML;
                btnSalvar.disabled = true;
                btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Salvando...';
                
                fetch('salvar_desenho.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ cidade: cidade, projeto: projetoAtual.nome, desenho: desenho })
                })
                .then(response => response.json())
                .then(data => {
                    btnSalvar.disabled = false;
                    btnSalvar.innerHTML = textoOriginal;
                    
                    if (data.success) {
                        if (!projetoAtual.desenhos) {
                            projetoAtual.desenhos = { poligonos: [], linhas: [], pontos: [] };
                        }
                        if (isLinha) {
                            if (!projetoAtual.desenhos.linhas) projetoAtual.desenhos.linhas = [];
                            projetoAtual.desenhos.linhas.push(desenho);
                        } else {
                            if (!projetoAtual.desenhos.poligonos) projetoAtual.desenhos.poligonos = [];
                            projetoAtual.desenhos.poligonos.push(desenho);
                        }
                        
                        // Salvar coordenadas antes de limpar
                        const coordsParaDesenhar = isLinha ? [...verticesPolilinha] : [...verticesPoligono];
                        
                        layerGroupTemporario.clearLayers();
                        poligonoAtual = null;
                        polilinhaAtual = null;
                        verticesPoligono = [];
                        verticesPolilinha = [];
                        marcadoresVerticesDesenhoLinha = [];
                        
                        if (isLinha) {
                            const linhaPermanente = L.polyline(coordsParaDesenhar, { color: 'blue', weight: 2 }).addTo(layerGroupLinhas);
                            const popupTexto = descricao + (desenho.medida != null ? '<br><strong>Medida:</strong> ' + desenho.medida.toFixed(2) + ' m' : '');
                            linhaPermanente.bindPopup(popupTexto);
                            linhaPermanente._projetoNome = projetoAtual.nome;
                            linhaPermanente._linhaIndex = projetoAtual.desenhos.linhas.length - 1;
                            linhaPermanente._linhaDescricao = descricao;
                            linhaPermanente._linhaMedida = desenho.medida;
                            linhaPermanente._deslocamento = null;
                            linhaPermanente.on('click', function(ev) {
                                L.DomEvent.stopPropagation(ev);
                                if (!modoPoligono && !modoGCP && !modoPolilinha) entrarEdicaoPolilinha(this);
                            });
                        } else {
                            const poligonoPermanente = L.polygon(coordsParaDesenhar, {
                                color: '#00bcd4', weight: 2, fillColor: '#00bcd4', fillOpacity: 0.3
                            }).addTo(layerGroupDesenhos);
                            poligonoPermanente.bindPopup(descricao);
                        }
                        
                        const modal = document.getElementById('modalDescricao');
                        if (modal) modal.classList.remove('show');
                        const medidaContainer = document.getElementById('modalDescricaoMedidaContainer');
                        if (medidaContainer) medidaContainer.style.display = 'none';
                        sairModoDesenho();
                        alert('Desenho salvo com sucesso!');
                    } else {
                        alert('Erro ao salvar desenho: ' + (data.message || 'Erro desconhecido'));
                    }
                })
                .catch(error => {
                    btnSalvar.disabled = false;
                    btnSalvar.innerHTML = textoOriginal;
                    console.error('Erro:', error);
                    alert('Erro ao salvar desenho.');
                });
            }
            
            // Função para cancelar desenho
            function cancelarDesenho() {
                // Remover polígono/polilinha em desenho e marcadores temporários
                layerGroupTemporario.clearLayers();
                poligonoAtual = null;
                verticesPoligono = [];
                polilinhaAtual = null;
                verticesPolilinha = [];
                marcadoresVerticesDesenhoLinha = [];
                
                const medidaContainer = document.getElementById('modalDescricaoMedidaContainer');
                if (medidaContainer) medidaContainer.style.display = 'none';
                // Fechar modal e sair do modo
                const modal = document.getElementById('modalDescricao');
                if (modal) modal.classList.remove('show');
                sairModoDesenho();
            }
            
            // ---------- Polilinha ----------
            // Calcular medida total em metros (soma das distâncias entre vértices consecutivos)
            function calcularMedidaMetros(vertices) {
                if (!vertices || vertices.length < 2) return 0;
                let total = 0;
                for (let i = 0; i < vertices.length - 1; i++) {
                    const a = Array.isArray(vertices[i]) ? L.latLng(vertices[i][0], vertices[i][1]) : L.latLng(vertices[i].lat, vertices[i].lng);
                    const b = Array.isArray(vertices[i+1]) ? L.latLng(vertices[i+1][0], vertices[i+1][1]) : L.latLng(vertices[i+1].lat, vertices[i+1].lng);
                    total += a.distanceTo(b);
                }
                return Math.round(total * 100) / 100;
            }
            
            function entrarModoPolilinha() {
                modoDesenho = true;
                modoPolilinha = true;
                verticesPolilinha = [];
                marcadoresVerticesDesenhoLinha = [];
                layerGroupTemporario.clearLayers();
                const btnPolilinha = document.getElementById('btnPolilinha');
                if (btnPolilinha) btnPolilinha.classList.add('active');
                const btnSairModo = document.getElementById('btnSairModo');
                if (btnSairModo) btnSairModo.style.display = 'block';
                const msg = document.getElementById('mensagemModoDesenho');
                if (msg) {
                    msg.classList.add('show');
                    msg.innerHTML = '<i class="fas fa-info-circle me-2"></i>Modo Polilinha Ativo: Clique no mapa para adicionar vértices. Botão direito para finalizar.';
                }
                document.querySelectorAll('.timeline-item').forEach(item => { item.style.pointerEvents = 'none'; item.style.opacity = '0.5'; });
                map.getContainer().style.cursor = 'crosshair';
            }
            
            function atualizarMarcadoresVerticesDesenhoLinha() {
                marcadoresVerticesDesenhoLinha.forEach(m => {
                    if (layerGroupTemporario.hasLayer(m)) layerGroupTemporario.removeLayer(m);
                });
                marcadoresVerticesDesenhoLinha = [];
                const iconV = iconVerticeEdit.get();
                verticesPolilinha.forEach((coord, idx) => {
                    const m = L.marker(coord, { icon: iconV, draggable: true });
                    m._verticeIndex = idx;
                    m.on('dragend', function() {
                        const ll = this.getLatLng();
                        verticesPolilinha[this._verticeIndex] = [ll.lat, ll.lng];
                        if (polilinhaAtual && verticesPolilinha.length >= 2) {
                            polilinhaAtual.setLatLngs(verticesPolilinha);
                            atualizarTooltipPolilinha();
                        }
                    });
                    m.addTo(layerGroupTemporario);
                    marcadoresVerticesDesenhoLinha.push(m);
                });
            }
            
            function atualizarTooltipPolilinha() {
                if (!polilinhaAtual || verticesPolilinha.length < 2) return;
                const m = calcularMedidaMetros(verticesPolilinha);
                polilinhaAtual.setTooltipContent(m.toFixed(2) + ' m');
            }
            
            function adicionarVerticePolilinha(e) {
                if (!modoPolilinha) return;
                const latlng = e.latlng;
                verticesPolilinha.push([latlng.lat, latlng.lng]);
                if (polilinhaAtual) layerGroupTemporario.removeLayer(polilinhaAtual);
                if (verticesPolilinha.length >= 2) {
                    polilinhaAtual = L.polyline(verticesPolilinha, { color: 'blue', weight: 2 })
                        .addTo(layerGroupTemporario);
                    polilinhaAtual.bindTooltip('', { permanent: true, direction: 'top', className: 'tooltip-medida' });
                    atualizarTooltipPolilinha();
                }
                atualizarMarcadoresVerticesDesenhoLinha();
            }
            
            function finalizarPolilinha() {
                if (!modoPolilinha || verticesPolilinha.length < 2) {
                    alert('Uma polilinha precisa de pelo menos 2 vértices!');
                    return;
                }
                const medida = calcularMedidaMetros(verticesPolilinha);
                const titulo = document.getElementById('modalDescricaoTitulo');
                if (titulo) titulo.textContent = 'Descrição da Polilinha';
                const medidaContainer = document.getElementById('modalDescricaoMedidaContainer');
                if (medidaContainer) medidaContainer.style.display = 'block';
                const medidaValor = document.getElementById('modalDescricaoMedidaValor');
                if (medidaValor) medidaValor.textContent = medida.toFixed(2) + ' m';
                tipoDesenhoAtual = 'linha';
                const modal = document.getElementById('modalDescricao');
                if (modal) {
                    modal.classList.add('show');
                    const descricao = document.getElementById('descricaoDesenho');
                    if (descricao) {
                        descricao.value = '';
                        descricao.focus();
                    }
                }
            }
            
            // Retorna array de pontos_referencia do projeto (normaliza antigo ponto_referencia singular)
            function obterPontosReferencia(projeto) {
                if (!projeto) return [];
                if (projeto.pontos_referencia && Array.isArray(projeto.pontos_referencia)) {
                    return projeto.pontos_referencia;
                }
                if (projeto.ponto_referencia && projeto.ponto_referencia.lat != null && projeto.ponto_referencia.lng != null) {
                    const p = projeto.ponto_referencia;
                    return [{
                        id: 'gcp_0',
                        lat: p.lat,
                        lng: p.lng,
                        descricao: p.descricao || '',
                        datahora: p.datahora || ''
                    }];
                }
                return [];
            }
            
            // Retorna lista de TODOS os pontos únicos de TODOS os projetos (sem repetir), para associar ao novo GCP
            function obterListaReferenciaParaAssociar(projetoAtual) {
                const unicos = new Map(); // id canônico -> { id, descricao, datahora }
                for (let projeto of projetosOrdenados) {
                    const pontos = obterPontosReferencia(projeto);
                    for (let pt of pontos) {
                        const idCanonico = pt.ref_id || pt.id || ('gcp_' + String(pt.lat) + '_' + String(pt.lng));
                        const dh = pt.datahora || '';
                        const atual = unicos.get(idCanonico);
                        if (!atual || (dh && dh < (atual.datahora || 'z'))) {
                            unicos.set(idCanonico, {
                                id: idCanonico,
                                descricao: pt.descricao || idCanonico,
                                datahora: dh
                            });
                        }
                    }
                }
                return Array.from(unicos.values()).sort((a, b) => (a.datahora || '').localeCompare(b.datahora || ''));
            }
            
            // Verifica se existe algum ponto GCP em outros projetos (para compatibilidade)
            function encontrarGCPEmOutrosProjetos(projetoAtual) {
                const lista = obterListaReferenciaParaAssociar(projetoAtual);
                return lista.length > 0 ? lista[0] : null;
            }
            
            // Função para verificar e carregar projeto
            function verificarECarregarProjeto(projeto) {
                projetoAtual = projeto;
                trocarOrtofoto(projeto.nome);
                
                // Limpar marcador temporário GCP
                if (marcadorGCP) {
                    map.removeLayer(marcadorGCP);
                    marcadorGCP = null;
                }
                // Limpar e redesenhar marcadores GCP do projeto atual
                if (layerGroupMarcadoresGCP) {
                    layerGroupMarcadoresGCP.clearLayers();
                }
                marcadoresGCP = [];
                
                const pontos = obterPontosReferencia(projeto);
                const temGCP = pontos.length > 0;
                
                if (temGCP) {
                    carregarDesenhosProjeto(projeto);
                    mostrarBotoesDesenho(true);
                } else {
                    layerGroupDesenhos.clearLayers();
                    if (layerGroupLinhas) layerGroupLinhas.clearLayers();
                    mostrarBotoesDesenho(false);
                }
            }
            
            // Função para mostrar/esconder botões de desenho
            function mostrarBotoesDesenho(mostrar) {
                const btnPoligono = document.getElementById('btnPoligono');
                const btnPolilinha = document.getElementById('btnPolilinha');
                if (mostrar) {
                    if (btnPoligono) btnPoligono.style.display = 'block';
                    if (btnPolilinha) btnPolilinha.style.display = 'block';
                } else {
                    if (btnPoligono) btnPoligono.style.display = 'none';
                    if (btnPolilinha) btnPolilinha.style.display = 'none';
                    // Se estiver em modo de desenho, sair
                    if (modoDesenho) {
                        sairModoDesenho();
                    }
                }
            }
            
            // Função para entrar no modo de marcação GCP (permite colocar vários pontos)
            function entrarModoGCP() {
                if (!projetoAtual) {
                    alert('Nenhum projeto selecionado!');
                    return;
                }
                
                projetoPendenteGCP = projetoAtual;
                modoGCP = true;
                gcpMarcado = null;
                
                // Remover marcador temporário vermelho se existir
                if (marcadorGCP) {
                    map.removeLayer(marcadorGCP);
                    marcadorGCP = null;
                }
                
                map.getContainer().style.cursor = 'crosshair';
                
                const clickHandler = function(e) {
                    if (!modoGCP) return;
                    
                    const latlng = e.latlng;
                    gcpMarcado = { lat: latlng.lat, lng: latlng.lng };
                    
                    if (marcadorGCP) map.removeLayer(marcadorGCP);
                    marcadorGCP = L.marker([latlng.lat, latlng.lng], {
                        icon: L.divIcon({
                            className: 'gcp-marker',
                            html: '<i class="fas fa-map-pin" style="color: #ff0000; font-size: 30px;"></i>',
                            iconSize: [30, 30],
                            iconAnchor: [15, 30]
                        })
                    }).addTo(map);
                    
                    map.off('click', clickHandler);
                    window.gcpClickHandler = null;
                    map.getContainer().style.cursor = '';
                    
                    abrirModalGCP();
                };
                
                map.on('click', clickHandler);
                window.gcpClickHandler = clickHandler;
            }
            
            // Função para abrir modal de GCP (após marcar o ponto)
            function abrirModalGCP() {
                if (!projetoPendenteGCP || !gcpMarcado) {
                    return;
                }
                
                const projeto = projetoPendenteGCP;
                const listaRef = obterListaReferenciaParaAssociar(projeto);
                
                document.getElementById('gcpAlertaMesmoLocal').style.display = 'none';
                document.getElementById('gcpCoordenadas').textContent = 
                    `Lat: ${gcpMarcado.lat.toFixed(6)}, Lng: ${gcpMarcado.lng.toFixed(6)}`;
                document.getElementById('gcpInfo').style.display = 'block';
                
                if (listaRef.length > 0) {
                    // Há pontos em outro(s) projeto(s): mostrar lista para associar ou "Novo ponto"
                    document.getElementById('gcpListaAssociarContainer').style.display = 'block';
                    const div = document.getElementById('gcpListaAssociar');
                    div.innerHTML = '';
                    const name = 'gcp_assoc_' + Date.now();
                    listaRef.forEach((pt, i) => {
                        const label = document.createElement('label');
                        label.style.display = 'block';
                        label.style.marginBottom = '8px';
                        label.style.cursor = 'pointer';
                        label.style.color = 'var(--text-light)';
                        const radio = document.createElement('input');
                        radio.type = 'radio';
                        radio.name = name;
                        radio.value = pt.id || '';
                        radio.dataset.descricao = pt.descricao || pt.id || '';
                        radio.dataset.refId = pt.id || '';
                        label.appendChild(radio);
                        label.appendChild(document.createTextNode(' ' + (pt.descricao || pt.id || 'Ponto ' + (i+1))));
                        div.appendChild(label);
                    });
                    const lblNovo = document.createElement('label');
                    lblNovo.style.display = 'block';
                    lblNovo.style.marginBottom = '8px';
                    lblNovo.style.cursor = 'pointer';
                    lblNovo.style.color = 'var(--text-light)';
                    const radioNovo = document.createElement('input');
                    radioNovo.type = 'radio';
                    radioNovo.name = name;
                    radioNovo.value = '__novo__';
                    radioNovo.dataset.refId = '';
                    lblNovo.appendChild(radioNovo);
                    lblNovo.appendChild(document.createTextNode(' Novo ponto'));
                    div.appendChild(lblNovo);
                    
                    const firstRadio = div.querySelector('input[type="radio"]');
                    if (firstRadio) firstRadio.checked = true;
                    document.getElementById('gcpDescricaoContainer').style.display = 'none';
                    document.getElementById('descricaoGCP').value = '';
                    document.getElementById('gcpInstrucoes').innerHTML = 
                        'Selecione qual ponto (de qualquer ortofoto) corresponde a este, ou marque como "Novo ponto".';
                    document.getElementById('btnConfirmarGCP').disabled = false;
                    div.querySelectorAll('input[type="radio"]').forEach(r => {
                        r.addEventListener('change', function() {
                            const isNovo = this.value === '__novo__';
                            document.getElementById('gcpDescricaoContainer').style.display = isNovo ? 'block' : 'none';
                            document.getElementById('descricaoGCP').value = '';
                            document.getElementById('btnConfirmarGCP').disabled = isNovo;
                        });
                    });
                } else {
                    // Primeira ortofoto ou nenhum outro ponto: só descrição
                    document.getElementById('gcpListaAssociarContainer').style.display = 'none';
                    document.getElementById('descricaoGCP').value = '';
                    document.getElementById('descricaoGCP').disabled = false;
                    document.getElementById('gcpDescricaoContainer').style.display = 'block';
                    document.getElementById('gcpInstrucoes').innerHTML = 
                        'Descreva este ponto de referência (GCP). Você pode adicionar quantos pontos quiser nesta ortofoto.';
                    document.getElementById('btnConfirmarGCP').disabled = true;
                }
                
                document.getElementById('modalGCP').classList.add('show');
            }
            
            // Função para confirmar GCP
            function confirmarGCP() {
                if (!gcpMarcado || !projetoPendenteGCP) {
                    alert('Por favor, marque o ponto GCP no mapa!');
                    return;
                }
                
                const projeto = projetoPendenteGCP;
                const listaRef = obterListaReferenciaParaAssociar(projeto);
                let descricao = '';
                let ref_id = null;
                
                if (listaRef.length > 0) {
                    const sel = document.querySelector('#gcpListaAssociar input[type="radio"]:checked');
                    if (!sel) {
                        alert('Selecione a qual ponto este corresponde, ou "Novo ponto".');
                        return;
                    }
                    if (sel.value === '__novo__') {
                        descricao = document.getElementById('descricaoGCP').value.trim();
                        if (!descricao) {
                            alert('Por favor, informe uma descrição para o novo ponto.');
                            return;
                        }
                    } else {
                        ref_id = sel.dataset.refId || sel.value;
                        const pt = listaRef.find(p => (p.id || '') === ref_id);
                        descricao = (pt && pt.descricao) ? pt.descricao : ref_id;
                    }
                } else {
                    descricao = document.getElementById('descricaoGCP').value.trim();
                    if (!descricao) {
                        alert('Por favor, informe uma descrição para o GCP!');
                        return;
                    }
                }
                
                const agora = new Date();
                const datahora = agora.toISOString().slice(0, 19).replace('T', ' ');
                const gcpData = {
                    lat: gcpMarcado.lat,
                    lng: gcpMarcado.lng,
                    descricao: descricao,
                    datahora: datahora
                };
                if (ref_id) gcpData.ref_id = ref_id;
                
                const btnConfirmar = document.getElementById('btnConfirmarGCP');
                const textoOriginal = btnConfirmar.innerHTML;
                btnConfirmar.disabled = true;
                btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Salvando...';
                
                fetch('salvar_gcp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        cidade: cidade,
                        projeto: projeto.nome,
                        ponto_referencia: gcpData
                    })
                })
                .then(response => response.json())
                .then(data => {
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = textoOriginal;
                    
                    if (data.success && data.ponto) {
                        if (!projeto.pontos_referencia) projeto.pontos_referencia = [];
                        projeto.pontos_referencia.push(data.ponto);
                        if (projeto.ponto_referencia && !Array.isArray(projeto.ponto_referencia)) {
                            delete projeto.ponto_referencia;
                        }
                        if (marcadorGCP) {
                            map.removeLayer(marcadorGCP);
                            marcadorGCP = null;
                        }
                        document.getElementById('modalGCP').classList.remove('show');
                        gcpMarcado = null;
                        desenharMarcadoresGCPDoProjeto(projeto);
                        alert('Ponto salvo. Clique no botão GCP e depois no mapa para adicionar outro ponto, ou troque de projeto.');
                        modoGCP = false;
                    } else if (data.success) {
                        verificarECarregarProjeto(projeto);
                        if (marcadorGCP) { map.removeLayer(marcadorGCP); marcadorGCP = null; }
                        document.getElementById('modalGCP').classList.remove('show');
                        gcpMarcado = null;
                        modoGCP = false;
                        alert('GCP salvo com sucesso!');
                    } else {
                        alert('Erro ao salvar GCP: ' + (data.message || 'Erro desconhecido'));
                    }
                })
                .catch(error => {
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = textoOriginal;
                    console.error('Erro:', error);
                    alert('Erro ao salvar GCP.');
                });
            }
            
            // Desenha todos os marcadores GCP do projeto no layerGroupMarcadoresGCP
            function desenharMarcadoresGCPDoProjeto(projeto) {
                if (!layerGroupMarcadoresGCP) return;
                layerGroupMarcadoresGCP.clearLayers();
                const pontos = obterPontosReferencia(projeto);
                pontos.forEach(pt => {
                    if (pt.lat == null || pt.lng == null) return;
                    const m = L.marker([pt.lat, pt.lng], {
                        icon: L.divIcon({
                            className: 'gcp-marker',
                            html: '<i class="fas fa-map-pin" style="color: #00ff00; font-size: 30px;"></i>',
                            iconSize: [30, 30],
                            iconAnchor: [15, 30]
                        })
                    }).addTo(layerGroupMarcadoresGCP);
                    const popupText = (pt.descricao ? 'GCP: ' + pt.descricao + '<br>' : '') +
                        pt.lat.toFixed(6) + ', ' + pt.lng.toFixed(6) + (pt.datahora ? '<br>Criado em: ' + pt.datahora : '');
                    m.bindPopup(popupText);
                });
            }
            
            // Função para fechar modal GCP
            function fecharModalGCP() {
                modoGCP = false;
                document.getElementById('modalGCP').classList.remove('show');
                map.getContainer().style.cursor = '';
                
                // Remover marcador temporário (vermelho) ao cancelar
                if (marcadorGCP) {
                    map.removeLayer(marcadorGCP);
                    marcadorGCP = null;
                    gcpMarcado = null;
                }
                
                // Remover event listener se ainda existir
                if (window.gcpClickHandler) {
                    map.off('click', window.gcpClickHandler);
                    window.gcpClickHandler = null;
                }
                
                projetoPendenteGCP = null;
            }
            
            // Função para calcular deslocamento entre dois GCPs
            function calcularDeslocamento(gcpOrigem, gcpDestino) {
                return {
                    deltaLat: gcpDestino.lat - gcpOrigem.lat,
                    deltaLng: gcpDestino.lng - gcpOrigem.lng
                };
            }
            
            // Função para aplicar deslocamento a coordenadas
            function aplicarDeslocamento(coordenadas, deslocamento) {
                if (Array.isArray(coordenadas[0])) {
                    // Array de arrays (polígono/linha)
                    return coordenadas.map(coord => [
                        coord[0] + deslocamento.deltaLat,
                        coord[1] + deslocamento.deltaLng
                    ]);
                } else {
                    // Array simples (ponto)
                    return [
                        coordenadas[0] + deslocamento.deltaLat,
                        coordenadas[1] + deslocamento.deltaLng
                    ];
                }
            }
            
            // Função para encontrar GCP de referência (o primeiro GCP criado, baseado na datahora)
            function encontrarGCPReferencia() {
                let primeiroGCP = null;
                let primeiraDatahora = null;
                
                // Procurar o GCP mais antigo (primeiro criado) em todos os projetos
                for (let projeto of projetosOrdenados) {
                    if (projeto.ponto_referencia && 
                        projeto.ponto_referencia.lat && 
                        projeto.ponto_referencia.lng &&
                        projeto.ponto_referencia.datahora) {
                        
                        const datahora = projeto.ponto_referencia.datahora;
                        if (!primeiraDatahora || datahora < primeiraDatahora) {
                            primeiraDatahora = datahora;
                            primeiroGCP = projeto.ponto_referencia;
                        }
                    }
                }
                
                return primeiroGCP;
            }
            
            // Remover marcadores de vértice e sair do modo edição
            function sairEdicaoPoligono() {
                if (layerGroupVertices) layerGroupVertices.clearLayers();
                marcadoresVertices = [];
                poligonoEmEdicao = null;
            }
            
            function sairEdicaoPolilinha() {
                if (layerGroupVertices) layerGroupVertices.clearLayers();
                marcadoresVertices = [];
                polilinhaEmEdicao = null;
            }
            
            // Entrar em modo edição: mostra vértices arrastáveis no polígono clicado
            function entrarEdicaoPoligono(poligonoLayer) {
                if (poligonoLayer === poligonoEmEdicao) return;
                sairEdicaoPoligono();
                poligonoEmEdicao = poligonoLayer;
                
                const ring = poligonoLayer.getLatLngs()[0];
                if (!ring || ring.length < 3) return;
                
                ring.forEach((latlng, idx) => {
                    const m = L.marker([latlng.lat, latlng.lng], { icon: iconVerticeEdit.get(), draggable: true });
                    m._verticeIndex = idx;
                    m._polygonLayer = poligonoLayer;
                    m.addTo(layerGroupVertices);
                    marcadoresVertices.push(m);
                    
                    m.on('dragend', function() {
                        const novoLl = this.getLatLng();
                        const anel = poligonoLayer.getLatLngs()[0];
                        anel[this._verticeIndex] = novoLl;
                        poligonoLayer.setLatLngs([anel]);
                        salvarPoligonoEditado(poligonoLayer);
                    });
                });
            }
            
            // Salvar polígono editado no servidor (sem mensagem)
            function salvarPoligonoEditado(poligonoLayer) {
                const ring = poligonoLayer.getLatLngs()[0];
                if (!ring || poligonoLayer._projetoNome == null || poligonoLayer._projetoNome === undefined) return;
                const projetoNome = poligonoLayer._projetoNome;
                const indice = poligonoLayer._poligonoIndex;
                const descricao = poligonoLayer._poligonoDescricao || '';
                const desl = poligonoLayer._deslocamento;
                
                const coordsToSave = ring.map(ll => {
                    if (desl) {
                        return [ll.lat - desl.deltaLat, ll.lng - desl.deltaLng];
                    }
                    return [ll.lat, ll.lng];
                });
                
                fetch('atualizar_desenho.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        cidade: cidade,
                        projeto: projetoNome,
                        tipo: 'poligono',
                        indice: indice,
                        coordenadas: coordsToSave,
                        descricao: descricao
                    })
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        const proj = projetosOrdenados.find(p => p.nome === projetoNome);
                        if (proj && proj.desenhos && proj.desenhos.poligonos && proj.desenhos.poligonos[indice]) {
                            proj.desenhos.poligonos[indice].coordenadas = coordsToSave;
                        }
                    }
                }).catch(() => {});
            }
            
            // Edição de polilinha: vértices arrastáveis, ao soltar salva e atualiza medida
            function entrarEdicaoPolilinha(linhaLayer) {
                if (linhaLayer === polilinhaEmEdicao) return;
                sairEdicaoPoligono();
                sairEdicaoPolilinha();
                polilinhaEmEdicao = linhaLayer;
                const latlngs = linhaLayer.getLatLngs();
                if (!latlngs || latlngs.length < 2) return;
                latlngs.forEach((ll, idx) => {
                    const m = L.marker([ll.lat, ll.lng], { icon: iconVerticeEdit.get(), draggable: true });
                    m._verticeIndex = idx;
                    m.addTo(layerGroupVertices);
                    marcadoresVertices.push(m);
                    m.on('dragend', function() {
                        const novoLl = this.getLatLng();
                        const pts = linhaLayer.getLatLngs();
                        pts[this._verticeIndex] = novoLl;
                        linhaLayer.setLatLngs(pts);
                        salvarPolilinhaEditada(linhaLayer);
                    });
                });
            }
            
            function salvarPolilinhaEditada(linhaLayer) {
                const pts = linhaLayer.getLatLngs();
                if (!pts || pts.length < 2 || linhaLayer._projetoNome == null) return;
                const projetoNome = linhaLayer._projetoNome;
                const indice = linhaLayer._linhaIndex;
                const descricao = linhaLayer._linhaDescricao || '';
                const desl = linhaLayer._deslocamento;
                const coordsToSave = pts.map(ll => {
                    if (desl) return [ll.lat - desl.deltaLat, ll.lng - desl.deltaLng];
                    return [ll.lat, ll.lng];
                });
                const medida = calcularMedidaMetros(coordsToSave);
                fetch('atualizar_desenho.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        cidade: cidade, projeto: projetoNome, tipo: 'linha', indice: indice,
                        coordenadas: coordsToSave, descricao: descricao, medida: medida
                    })
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        const proj = projetosOrdenados.find(p => p.nome === projetoNome);
                        if (proj && proj.desenhos && proj.desenhos.linhas && proj.desenhos.linhas[indice]) {
                            proj.desenhos.linhas[indice].coordenadas = coordsToSave;
                            proj.desenhos.linhas[indice].medida = medida;
                        }
                        const popupTexto = descricao + (medida != null ? '<br><strong>Medida:</strong> ' + medida.toFixed(2) + ' m' : '');
                        linhaLayer.setPopupContent(popupTexto);
                    }
                }).catch(() => {});
            }
            
            // Função para carregar desenhos do projeto (acumulando desenhos de projetos anteriores)
            function carregarDesenhosProjeto(projeto) {
                sairEdicaoPoligono();
                sairEdicaoPolilinha();
                layerGroupDesenhos.clearLayers();
                if (layerGroupLinhas) layerGroupLinhas.clearLayers();
                if (marcadorGCP) {
                    map.removeLayer(marcadorGCP);
                    marcadorGCP = null;
                }
                
                const indiceProjeto = projetosOrdenados.findIndex(p => p.nome === projeto.nome);
                if (indiceProjeto === -1) {
                    console.warn('Projeto não encontrado na lista ordenada');
                    return;
                }
                
                const pontosAtual = obterPontosReferencia(projeto);
                desenharMarcadoresGCPDoProjeto(projeto);
                
                // Carregar desenhos de todos os projetos anteriores e do projeto atual
                for (let i = 0; i <= indiceProjeto; i++) {
                    const projetoParaCarregar = projetosOrdenados[i];
                    const pontosOrigem = obterPontosReferencia(projetoParaCarregar);
                    
                    // Deslocamento: média dos deltas de todos os pares (mesmo ref_id ou id)
                    let deslocamento = null;
                    if (i !== indiceProjeto && pontosAtual.length > 0 && pontosOrigem.length > 0) {
                        const deltas = [];
                        pontosOrigem.forEach(po => {
                            const idOrig = po.ref_id || po.id;
                            const pa = pontosAtual.find(p => (p.ref_id || p.id) === idOrig);
                            if (pa) {
                                deltas.push(calcularDeslocamento(po, pa));
                            }
                        });
                        if (deltas.length > 0) {
                            deslocamento = {
                                deltaLat: deltas.reduce((s, d) => s + d.deltaLat, 0) / deltas.length,
                                deltaLng: deltas.reduce((s, d) => s + d.deltaLng, 0) / deltas.length
                            };
                            console.log(`Deslocamento (média de ${deltas.length} pares) para projeto ${projetoParaCarregar.nome}:`, deslocamento);
                        }
                    }
                    
                    // Carregar polígonos
                    if (projetoParaCarregar.desenhos && projetoParaCarregar.desenhos.poligonos) {
                        projetoParaCarregar.desenhos.poligonos.forEach((poligono, idxPol) => {
                            if (poligono.coordenadas && poligono.coordenadas.length >= 3) {
                                let coordenadasFinais = poligono.coordenadas;
                                if (deslocamento) {
                                    coordenadasFinais = aplicarDeslocamento(poligono.coordenadas, deslocamento);
                                }
                                
                                const poligonoLayer = L.polygon(coordenadasFinais, {
                                    color: '#00bcd4',
                                    weight: 2,
                                    fillColor: '#00bcd4',
                                    fillOpacity: 0.3
                                }).addTo(layerGroupDesenhos);
                                
                                poligonoLayer._projetoNome = projetoParaCarregar.nome;
                                poligonoLayer._poligonoIndex = idxPol;
                                poligonoLayer._poligonoDescricao = poligono.descricao || '';
                                poligonoLayer._deslocamento = deslocamento;
                                
                                if (poligono.descricao) {
                                    poligonoLayer.bindPopup(poligono.descricao);
                                }
                                
                                poligonoLayer.on('click', function(ev) {
                                    L.DomEvent.stopPropagation(ev);
                                    if (!modoPoligono && !modoGCP) {
                                        entrarEdicaoPoligono(this);
                                    }
                                });
                            }
                        });
                    }
                    
                    // Carregar polilinhas
                    if (projetoParaCarregar.desenhos && projetoParaCarregar.desenhos.linhas) {
                        projetoParaCarregar.desenhos.linhas.forEach((linha, idxLinha) => {
                            if (!linha.coordenadas || linha.coordenadas.length < 2) return;
                            let coordsLinha = linha.coordenadas;
                            if (deslocamento) coordsLinha = aplicarDeslocamento(linha.coordenadas, deslocamento);
                            const linhaLayer = L.polyline(coordsLinha, { color: 'blue', weight: 2 }).addTo(layerGroupLinhas);
                            linhaLayer._projetoNome = projetoParaCarregar.nome;
                            linhaLayer._linhaIndex = idxLinha;
                            linhaLayer._linhaDescricao = linha.descricao || '';
                            linhaLayer._linhaMedida = linha.medida;
                            linhaLayer._deslocamento = deslocamento;
                            const med = linha.medida != null ? linha.medida : calcularMedidaMetros(coordsLinha);
                            const popupTexto = (linha.descricao || '') + (med != null ? '<br><strong>Medida:</strong> ' + Number(med).toFixed(2) + ' m' : '');
                            linhaLayer.bindPopup(popupTexto);
                            linhaLayer.on('click', function(ev) {
                                L.DomEvent.stopPropagation(ev);
                                if (!modoPoligono && !modoGCP && !modoPolilinha) entrarEdicaoPolilinha(this);
                            });
                        });
                    }
                    
                    // Carregar pontos (para futuro)
                    if (projetoParaCarregar.desenhos && projetoParaCarregar.desenhos.pontos) {
                        projetoParaCarregar.desenhos.pontos.forEach(ponto => {
                            // Implementar quando necessário
                        });
                    }
                }
                
                console.log(`Carregados desenhos acumulados até o projeto ${projeto.nome} (índice ${indiceProjeto})`);
            }
            
            // Event listeners para ferramentas de desenho (com verificação de existência)
            const btnGCP = document.getElementById('btnGCP');
            if (btnGCP) {
                btnGCP.addEventListener('click', function() {
                    entrarModoGCP();
                });
            }
            
            const btnPoligono = document.getElementById('btnPoligono');
            if (btnPoligono) {
                btnPoligono.addEventListener('click', function() {
                    if (!modoDesenho) entrarModoPoligono();
                });
            }
            
            const btnPolilinha = document.getElementById('btnPolilinha');
            if (btnPolilinha) {
                btnPolilinha.addEventListener('click', function() {
                    if (!modoDesenho) entrarModoPolilinha();
                });
            }
            
            const btnSairModo = document.getElementById('btnSairModo');
            if (btnSairModo) {
                btnSairModo.addEventListener('click', function() {
                    sairModoDesenho();
                });
            }
            
            // Event listener para cancelar GCP
            const btnCancelarGCP = document.getElementById('btnCancelarGCP');
            if (btnCancelarGCP) {
                btnCancelarGCP.addEventListener('click', function() {
                    fecharModalGCP();
                });
            }
            
            // Event listener para habilitar botão quando digitar descrição
            const descricaoGCP = document.getElementById('descricaoGCP');
            if (descricaoGCP) {
                descricaoGCP.addEventListener('input', function() {
                    if (!gcpMarcado) {
                        const btnConfirmar = document.getElementById('btnConfirmarGCP');
                        if (btnConfirmar) btnConfirmar.disabled = true;
                        return;
                    }
                    
                    const jaTemPontos = projetoPendenteGCP ? obterPontosReferencia(projetoPendenteGCP).length > 0 : false;
                    const gcpOutroProjeto = projetoPendenteGCP ? encontrarGCPEmOutrosProjetos(projetoPendenteGCP) : null;
                    const btnConfirmar = document.getElementById('btnConfirmarGCP');
                    
                    if (btnConfirmar) {
                        if (jaTemPontos || gcpOutroProjeto) {
                            btnConfirmar.disabled = false;
                        } else if (this.value.trim() !== '') {
                            // Se não tem GCP em nenhum projeto, precisa de descrição
                            btnConfirmar.disabled = false;
                        } else {
                            btnConfirmar.disabled = true;
                        }
                    }
                });
                
                // Salvar GCP ao pressionar Ctrl+Enter no textarea
                descricaoGCP.addEventListener('keydown', function(e) {
                    if (e.ctrlKey && e.key === 'Enter') {
                        const btnConfirmar = document.getElementById('btnConfirmarGCP');
                        if (btnConfirmar && !btnConfirmar.disabled) {
                            confirmarGCP();
                        }
                    }
                });
            }
            
            // Event listener para cliques no mapa
            map.on('click', function(e) {
                if (modoPoligono && !modoGCP) adicionarVerticePoligono(e);
                else if (modoPolilinha && !modoGCP) adicionarVerticePolilinha(e);
            });
            
            // Event listener para botão direito (finalizar polígono ou polilinha)
            map.on('contextmenu', function(e) {
                if (modoPoligono) {
                    e.originalEvent.preventDefault();
                    finalizarPoligono();
                } else if (modoPolilinha) {
                    e.originalEvent.preventDefault();
                    finalizarPolilinha();
                }
            });
            
            // Event listeners do modal (com verificação de existência)
            const btnSalvarDesenho = document.getElementById('btnSalvarDesenho');
            if (btnSalvarDesenho) {
                btnSalvarDesenho.addEventListener('click', salvarDesenho);
            }
            
            const btnCancelarDesenho = document.getElementById('btnCancelarDesenho');
            if (btnCancelarDesenho) {
                btnCancelarDesenho.addEventListener('click', cancelarDesenho);
            }
            
            // Salvar ao pressionar Ctrl+Enter no textarea
            const descricaoDesenho = document.getElementById('descricaoDesenho');
            if (descricaoDesenho) {
                descricaoDesenho.addEventListener('keydown', function(e) {
                    if (e.ctrlKey && e.key === 'Enter') {
                        salvarDesenho();
                    }
                });
            }
            
            // Event listener para confirmar GCP
            const btnConfirmarGCP = document.getElementById('btnConfirmarGCP');
            if (btnConfirmarGCP) {
                btnConfirmarGCP.addEventListener('click', confirmarGCP);
            }
            
            // Fechar modal ao pressionar ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (document.getElementById('modalDescricao').classList.contains('show')) {
                        cancelarDesenho();
                    } else if (document.getElementById('modalGCP').classList.contains('show')) {
                        fecharModalGCP();
                    }
                }
            });
            })(); // Fecha IIFE
        }
        
        // Aguardar carregamento completo da página e Leaflet
        window.addEventListener('load', function() {
            // Aguardar um pouco mais para garantir que Leaflet está pronto
            setTimeout(function() {
                if (typeof L === 'undefined') {
                    console.error('Leaflet não está disponível após carregamento da página!');
                    return;
                }
                inicializarMapa();
            }, 100);
        });
        
        // Também tentar quando DOM estiver pronto (caso window.load já tenha acontecido)
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(function() {
                if (typeof L !== 'undefined' && !window.mapaInicializado) {
                    inicializarMapa();
                }
            }, 200);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    if (typeof L !== 'undefined' && !window.mapaInicializado) {
                        inicializarMapa();
                    }
                }, 200);
            });
        }
        <?php endif; ?>
    </script>
</body>

</html>