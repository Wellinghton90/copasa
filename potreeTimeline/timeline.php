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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style id="mensagens-timeline-css">
        /* Mensagens: offcanvas e cards (scoped para não afetar Potree) */
        #offcanvasMensagensTimeline.offcanvas-mensagens-full {
            height: 100vh !important;
            max-height: 100vh !important;
            background: linear-gradient(135deg, #0a1929 0%, #1a237e 50%, #0a1929 100%) !important;
            border-left: 1px solid rgba(255, 255, 255, 0.1) !important;
        }
        #offcanvasMensagensTimeline.offcanvas-mensagens-full::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(135deg, #00bcd4 0%, #006064 100%);
            z-index: 1;
        }
        #offcanvasMensagensTimeline .offcanvas-body { overflow: hidden; background: transparent; }
        #offcanvasMensagensTimeline .offcanvas-header {
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #e3f2fd;
        }
        #offcanvasMensagensTimeline .offcanvas-title { color: #e3f2fd; font-weight: 700; }
        #offcanvasMensagensTimeline .offcanvas-title i { color: #00bcd4; }
        #offcanvasMensagensTimeline .btn-close { filter: invert(1); opacity: 0.8; }
        #mensagensListaTimeline { background: transparent; color: #e3f2fd; }
        #mensagensListaTimeline .text-muted { color: #26c6da !important; }
        #offcanvasMensagensTimeline .msg-item {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #e3f2fd;
        }
        #offcanvasMensagensTimeline .msg-item strong,
        #offcanvasMensagensTimeline .msg-data { color: #e3f2fd !important; }
        #offcanvasMensagensTimeline .msg-item .msg-texto-editar,
        #offcanvasMensagensTimeline .msg-item .msg-form-responder textarea {
            background: rgba(0, 188, 212, 0.05);
            border: 1px solid rgba(0, 188, 212, 0.2);
            color: #e3f2fd;
        }
        #offcanvasMensagensTimeline .msg-item .dropdown-mencionar-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(0, 188, 212, 0.2);
            border-radius: 8px;
            max-height: 180px;
            overflow-y: auto;
        }
        #offcanvasMensagensTimeline .msg-item .mencionados-chips-card .badge {
            background: rgba(0, 188, 212, 0.2) !important;
            color: #26c6da;
            border: 1px solid rgba(0, 188, 212, 0.3);
        }
        #btn_mensagens_badge { font-size: 0.7rem; min-width: 1.2em; }
    </style>

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
<body class="app-layout">

    <header class="top-bar" id="top_bar">
        <div class="top-bar-left">
            <select id="top_bar_platform" class="top-bar-platform-select" title="Plataforma" aria-label="Plataforma"></select>
        </div>
        <div class="top-bar-center"></div>
        <div class="top-bar-right">
            <button type="button" class="top-bar-diary-btn" id="btn_mensagens_timeline" title="Mensagens" aria-label="Mensagens">
                <i class="fa-regular fa-message" aria-hidden="true"></i>
                <span id="btn_mensagens_badge" class="badge bg-warning text-dark ms-1" style="display: none;">0</span>
                Mensagens
            </button>
            <button class="top-bar-diary-btn" onclick="window.open('data/diario/diario_geral.php', '_blank')">Tabela</button>
            <button type="button" id="btn_diario" class="top-bar-diary-btn" title="Diário de fiscalização" aria-label="Diário de fiscalização">Diário</button>
            <div id="top_bar_user_badge" class="top-bar-user-badge" title="Usuário atual" aria-label="Usuário atual"></div>
        </div>
    </header>

    <!-- Offcanvas Mensagens (igual dashboard/obra_detalhes) -->
    <div class="offcanvas offcanvas-end offcanvas-mensagens-full" tabindex="-1" id="offcanvasMensagensTimeline" aria-labelledby="offcanvasMensagensTimelineLabel" data-bs-backdrop="false" data-bs-scroll="true">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="offcanvasMensagensTimelineLabel">
                <i class="fa-regular fa-message me-2"></i> Mensagens
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0">
            <div id="mensagensListaTimeline" class="flex-grow-1 overflow-auto p-3" style="min-height: 0;">
                <div id="mensagensListaToolbarTimeline" class="d-flex justify-content-end mb-2">
                    <button type="button" class="btn btn-primary btn-sm" id="btnNovaMensagemTimeline" title="Nova mensagem">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div id="mensagensCardsNovosTimeline"></div>
                <div id="mensagensListaLoadingTimeline" class="text-center py-4 text-muted">
                    <span class="spinner-border spinner-border-sm me-2"></span> Carregando mensagens...
                </div>
                <div id="mensagensListaConteudoTimeline" style="display: none;"></div>
                <div id="mensagensListaVaziaTimeline" class="text-center py-4 text-muted" style="display: none;">Nenhuma mensagem.</div>
            </div>
        </div>
    </div>

    <main class="app-main">
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
            <button type="button" id="btn_sidebar_compare_clouds" class="sidebar-btn" data-mode="compare_clouds" title="Comparar nuvens">
                <span class="sidebar-icon">☁️☁️</span>
                <span class="sidebar-label">Comparar Nuvens</span>
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
            <div class="left-panel-content" data-mode="compare_clouds" id="left_panel_compare_clouds">
                <!-- Painel criado dinamicamente via CompareCloudsTool.js usando compareCloudsPanelTemplate.js -->
            </div>
            <div class="left-panel-content" data-mode="fotos" id="left_panel_fotos"></div>
        </div>

        <div id="potree_render_area"></div>
        <div id="developer_offset_container" class="developer-offset-wrapper" aria-hidden="true"></div>

        <div class="right-panel" id="right_panel">
            <div class="right-panel-content" data-mode="diario" id="right_panel_diario"></div>
        </div>
    </div>
    </main>

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
        <?php
        // Diário único: sempre ler/gravar o mesmo JSON (data/diario/MatheusPrates.json), independente do usuário
        $diaryUserId = 'MatheusPrates';
        $diaryUserDisplay = isset($_SESSION['user_copasa']['nome']) ? trim($_SESSION['user_copasa']['nome']) : 'Anônimo';
        ?>
        window.INSPECTION_DIARY_USER = <?php echo json_encode($diaryUserId); ?>;
        window.INSPECTION_DIARY_USER_DISPLAY = <?php echo json_encode($diaryUserDisplay); ?>;
        window.INSPECTION_DIARY_USER_ROLE = <?php echo json_encode($_SESSION['usuario_cargo'] ?? 'admin'); ?>;
        window.USER_ID_MENSAGENS = <?php echo (int)($_SESSION['user_copasa']['id'] ?? 0); ?>;
        window.OBRA_ID_MENSAGENS = 0;
        window.API_MENSAGENS_BASE = '../api_mensagens.php';
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script type="module" src="js/viewer/timeline.js"></script>
    <script>
        (function() {
            var API = window.API_MENSAGENS_BASE || '../api_mensagens.php';
            var OBRA_ID = window.OBRA_ID_MENSAGENS !== undefined ? window.OBRA_ID_MENSAGENS : 0;
            var USER_ID = window.USER_ID_MENSAGENS || 0;
            var listaUsuariosMencionar = [];
            var listaObras = [];
            var contadorCardNovo = 0;
            var cardMencionados = {};
            var cardMencionadosNomes = {};

            function escapeHtml(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function abrirModalMensagensTimeline() {
                var offcanvasEl = document.getElementById('offcanvasMensagensTimeline');
                if (!offcanvasEl || typeof bootstrap === 'undefined') return;
                var offcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
                document.getElementById('mensagensListaLoadingTimeline').style.display = 'block';
                document.getElementById('mensagensListaConteudoTimeline').style.display = 'none';
                document.getElementById('mensagensListaVaziaTimeline').style.display = 'none';
                offcanvas.show();
                carregarMensagensTimeline();
                carregarUsuariosMencionarTimeline();
                if (listaObras.length === 0) {
                    fetch(API + '?action=obras').then(function(r) { return r.json(); }).then(function(res) {
                        if (res.ok && res.obras) listaObras = res.obras;
                    });
                }
            }

            function carregarMensagensTimeline() {
                fetch(API + '?action=listar&obra_id=' + OBRA_ID)
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        document.getElementById('mensagensListaLoadingTimeline').style.display = 'none';
                        if (res.ok && res.mensagens && res.mensagens.length > 0) {
                            document.getElementById('mensagensListaVaziaTimeline').style.display = 'none';
                            var div = document.getElementById('mensagensListaConteudoTimeline');
                            div.style.display = 'block';
                            div.innerHTML = '';
                            res.mensagens.forEach(function(m) {
                                if (parseInt(m.usuario_id) === USER_ID) {
                                    div.appendChild(criarCardMensagemSalvaTimeline(m));
                                } else {
                                    div.appendChild(criarMsgItemReadOnlyTimeline(m));
                                }
                            });
                            div.scrollTop = 0;
                            res.mensagens.forEach(function(m) {
                                if (m.id_usuario_destino && parseInt(m.id_usuario_destino) === USER_ID && (m.lida == '0' || m.lida === 0)) {
                                    var fd = new FormData();
                                    fd.append('action', 'marcar_lida');
                                    fd.append('mensagem_id', m.id);
                                    fetch(API, { method: 'POST', body: fd });
                                }
                            });
                            atualizarContadorMensagensTimeline();
                        } else {
                            document.getElementById('mensagensListaConteudoTimeline').style.display = 'none';
                            document.getElementById('mensagensListaVaziaTimeline').style.display = 'block';
                        }
                    })
                    .catch(function() {
                        document.getElementById('mensagensListaLoadingTimeline').style.display = 'none';
                        document.getElementById('mensagensListaConteudoTimeline').style.display = 'none';
                        document.getElementById('mensagensListaVaziaTimeline').innerHTML = 'Erro ao carregar mensagens.';
                        document.getElementById('mensagensListaVaziaTimeline').style.display = 'block';
                    });
            }

            function criarMsgItemReadOnlyTimeline(m) {
                var dataFormatada = m.data ? new Date(m.data).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
                var fuiMencionado = m.id_usuario_destino && parseInt(m.id_usuario_destino) === USER_ID;
                var naoLida = fuiMencionado && (m.lida == '0' || m.lida === 0);
                var badge = naoLida ? '<span class="badge bg-warning text-dark ms-1">não lida</span>' : '';
                var btnLida = naoLida ? '<button type="button" class="btn btn-sm btn-outline-primary mt-1 btn-marcar-lida" title="Marcar como lida"><i class="fas fa-check me-1"></i>Marcar como lida</button>' : '';
                var btnResponder = fuiMencionado ? '<button type="button" class="btn btn-sm btn-outline-secondary mt-1 btn-responder-msg" title="Responder"><i class="fas fa-pencil-alt me-1"></i>Responder</button>' : '';
                var projetoLabel = (m.projeto && OBRA_ID === 0) ? '<span class="small msg-data opacity-75">Obra: ' + escapeHtml(m.projeto) + '</span>' : '';
                var htmlRespostas = '';
                if (m.respostas && m.respostas.length > 0) {
                    htmlRespostas = '<div class="msg-respostas mt-2 pt-2 border-top border-secondary">';
                    m.respostas.forEach(function(r) {
                        var dr = r.data ? new Date(r.data).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
                        var txt = r.texto || r.mensagem || '';
                        htmlRespostas += '<div class="small mb-2"><strong class="msg-data">' + escapeHtml(r.usuario) + '</strong> <span class="msg-data opacity-75">' + dr + '</span><br><span class="msg-data">' + escapeHtml(txt) + '</span></div>';
                    });
                    htmlRespostas += '</div>';
                }
                var el = document.createElement('div');
                el.className = 'msg-item border rounded p-2 mb-2' + (naoLida ? ' msg-item-nao-lida' : '');
                el.setAttribute('data-id', m.id);
                el.innerHTML = '<div class="d-flex justify-content-between align-items-start">' +
                    '<strong class="small">' + escapeHtml(m.usuario) + '</strong>' +
                    '<span class="small text-muted msg-data">' + dataFormatada + '</span>' +
                    '</div>' + (projetoLabel ? '<div class="small">' + projetoLabel + '</div>' : '') +
                    '<p class="mb-0 mt-1 small">' + escapeHtml(m.mensagem) + ' ' + badge + '</p>' +
                    htmlRespostas +
                    '<div class="d-flex flex-wrap gap-1 mt-1">' + btnLida + btnResponder + '</div>' +
                    '<div class="msg-form-responder mt-2" style="display: none;">' +
                    '<textarea class="form-control form-control-sm mb-1 msg-texto-resposta" rows="2" placeholder="Sua resposta..." maxlength="2000"></textarea>' +
                    '<button type="button" class="btn btn-primary btn-sm btn-enviar-resposta">Enviar resposta</button>' +
                    '</div>';
                if (naoLida && el.querySelector('.btn-marcar-lida')) {
                    el.querySelector('.btn-marcar-lida').addEventListener('click', function() {
                        var btn = this;
                        btn.disabled = true;
                        var fd = new FormData();
                        fd.append('action', 'marcar_lida');
                        fd.append('mensagem_id', m.id);
                        fetch(API, { method: 'POST', body: fd })
                            .then(function(r) { return r.json(); })
                            .then(function(res) {
                                if (res.ok) { carregarMensagensTimeline(); atualizarContadorMensagensTimeline(); } else { btn.disabled = false; }
                            })
                            .catch(function() { btn.disabled = false; });
                    });
                }
                if (fuiMencionado) {
                    var formResp = el.querySelector('.msg-form-responder');
                    var txtResp = el.querySelector('.msg-texto-resposta');
                    var btnEnviarResp = el.querySelector('.btn-enviar-resposta');
                    if (el.querySelector('.btn-responder-msg')) {
                        el.querySelector('.btn-responder-msg').addEventListener('click', function() {
                            formResp.style.display = formResp.style.display === 'none' ? 'block' : 'none';
                            if (formResp.style.display === 'block') txtResp.focus();
                        });
                    }
                    btnEnviarResp.addEventListener('click', function() {
                        var texto = txtResp.value.trim();
                        if (!texto) return;
                        btnEnviarResp.disabled = true;
                        var fd = new FormData();
                        fd.append('action', 'enviar_resposta');
                        fd.append('mensagem_id', m.id);
                        fd.append('texto', texto);
                        fetch(API, { method: 'POST', body: fd })
                            .then(function(r) { return r.json(); })
                            .then(function(res) {
                                btnEnviarResp.disabled = false;
                                if (res.ok) { txtResp.value = ''; formResp.style.display = 'none'; carregarMensagensTimeline(); } else { alert(res.msg || 'Erro ao enviar resposta.'); }
                            })
                            .catch(function() { btnEnviarResp.disabled = false; });
                    });
                }
                return el;
            }

            function criarCardMensagemSalvaTimeline(m) {
                var cardId = 'msg-' + m.id;
                cardMencionados[cardId] = (m.mencionados || []).map(function(x) { return parseInt(x.id); });
                cardMencionadosNomes[cardId] = {};
                (m.mencionados || []).forEach(function(x) { cardMencionadosNomes[cardId][parseInt(x.id)] = x.nome || ('ID ' + x.id); });
                var dataFormatada = m.data ? new Date(m.data).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
                var projetoLabel = (m.projeto && OBRA_ID === 0) ? '<span class="small msg-data opacity-75">Obra: ' + escapeHtml(m.projeto) + '</span>' : '';
                var htmlRespostas = '';
                if (m.respostas && m.respostas.length > 0) {
                    htmlRespostas = '<div class="msg-respostas mt-2 pt-2 border-top border-secondary">';
                    m.respostas.forEach(function(r) {
                        var dr = r.data ? new Date(r.data).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
                        var txt = r.texto || r.mensagem || '';
                        htmlRespostas += '<div class="small mb-2"><strong class="msg-data">' + escapeHtml(r.usuario) + '</strong> <span class="msg-data opacity-75">' + dr + '</span><br><span class="msg-data">' + escapeHtml(txt) + '</span></div>';
                    });
                    htmlRespostas += '</div>';
                }
                var card = document.createElement('div');
                card.className = 'msg-item border rounded p-2 mb-2';
                card.setAttribute('data-card-id', cardId);
                card.setAttribute('data-mensagem-id', m.id);
                card.innerHTML =
                    '<div class="d-flex justify-content-between align-items-start">' +
                    '<strong class="small">Você</strong>' +
                    '<span class="small text-muted msg-data">' + dataFormatada + '</span>' +
                    '</div>' + (projetoLabel ? '<div class="small">' + projetoLabel + '</div>' : '') +
                    '<textarea class="form-control form-control-sm mt-1 mb-2 msg-texto-editar" rows="2" placeholder="Digite sua mensagem..." maxlength="2000">' + escapeHtml(m.mensagem) + '</textarea>' +
                    '<div class="mencionados-chips-card d-flex flex-wrap gap-1 mb-2"></div>' +
                    '<div class="dropdown-mencionar-card p-2" style="display: none;"><div class="small fw-bold mb-1" style="color: #26c6da;">Mencionar usuário</div><div class="lista-usuarios-card"></div></div>' +
                    htmlRespostas +
                    '<div class="d-flex flex-wrap gap-1 mt-1">' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary btn-card-mencionar" title="Mencionar"><i class="fas fa-at me-1"></i>Mencionar</button>' +
                    '<button type="button" class="btn btn-sm btn-danger btn-card-excluir" title="Excluir"><i class="fas fa-trash-alt me-1"></i>Excluir</button>' +
                    '<button type="button" class="btn btn-sm btn-primary btn-card-salvar" title="Salvar"><i class="fas fa-save me-1"></i>Salvar</button>' +
                    '</div>';
                renderChipsForCardTimeline(cardId, card);
                card.querySelector('.btn-card-mencionar').addEventListener('click', function() { toggleDropdownCardTimeline(cardId); });
                card.querySelector('.btn-card-excluir').addEventListener('click', function() {
                    if (!confirm('Excluir esta mensagem?')) return;
                    var btn = this;
                    btn.disabled = true;
                    var fd = new FormData();
                    fd.append('action', 'excluir');
                    fd.append('mensagem_id', parseInt(m.id, 10) || m.id);
                    fetch(API, { method: 'POST', body: fd })
                        .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                        .then(function(res) {
                            if (res.ok) { delete cardMencionados[cardId]; delete cardMencionadosNomes[cardId]; carregarMensagensTimeline(); atualizarContadorMensagensTimeline(); } else { alert(res.msg || 'Erro ao excluir.'); btn.disabled = false; }
                        })
                        .catch(function(err) { btn.disabled = false; alert('Erro ao excluir: ' + (err.message || '')); });
                });
                card.querySelector('.btn-card-salvar').addEventListener('click', function() {
                    var texto = card.querySelector('.msg-texto-editar').value.trim();
                    if (!texto) { alert('Digite uma mensagem.'); return; }
                    var btn = this;
                    btn.disabled = true;
                    var fd = new FormData();
                    fd.append('action', 'atualizar');
                    fd.append('mensagem_id', m.id);
                    fd.append('mensagem', texto);
                    (cardMencionados[cardId] || []).forEach(function(id) { fd.append('mencionados[]', id); });
                    fetch(API, { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            btn.disabled = false;
                            if (res.ok) carregarMensagensTimeline(); else alert(res.msg || 'Erro ao atualizar.');
                        })
                        .catch(function() { btn.disabled = false; alert('Erro ao atualizar.'); });
                });
                return card;
            }

            function atualizarContadorMensagensTimeline() {
                fetch(API + '?action=contar_nao_lidas')
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (!res.ok) return;
                        var badge = document.getElementById('btn_mensagens_badge');
                        if (!badge) return;
                        var n = res.total || 0;
                        if (n > 0) { badge.textContent = n > 99 ? '99+' : n; badge.style.display = ''; } else { badge.style.display = 'none'; }
                    });
            }

            function carregarUsuariosMencionarTimeline() {
                fetch(API + '?action=usuarios')
                    .then(function(r) { return r.json(); })
                    .then(function(res) { if (res.ok && res.usuarios) listaUsuariosMencionar = res.usuarios; });
            }

            function renderChipsForCardTimeline(cardId, cardEl) {
                var card = cardEl || document.querySelector('#offcanvasMensagensTimeline [data-card-id="' + cardId + '"]');
                if (!card) return;
                var container = card.querySelector('.mencionados-chips-card');
                if (!container) return;
                var ids = cardMencionados[cardId] || [];
                var nomes = cardMencionadosNomes[cardId] || {};
                container.innerHTML = '';
                ids.forEach(function(id) {
                    var nome = nomes[id] || (function() {
                        var u = listaUsuariosMencionar.find(function(x) { return parseInt(x.id) === parseInt(id); });
                        return u ? (u.nome || u.login) : 'ID ' + id;
                    })();
                    var span = document.createElement('span');
                    span.className = 'badge bg-secondary d-inline-flex align-items-center gap-1 me-1 mb-1';
                    span.innerHTML = nome + ' <button type="button" class="btn-close btn-close-white btn-close-sm p-0 ms-1" style="font-size: 0.6rem;" data-id="' + id + '" aria-label="Remover"></button>';
                    span.querySelector('button').addEventListener('click', function() {
                        cardMencionados[cardId] = (cardMencionados[cardId] || []).filter(function(x) { return x != id; });
                        renderChipsForCardTimeline(cardId);
                    });
                    container.appendChild(span);
                });
            }

            function toggleDropdownCardTimeline(cardId) {
                var card = document.querySelector('#offcanvasMensagensTimeline [data-card-id="' + cardId + '"]');
                if (!card) return;
                var dd = card.querySelector('.dropdown-mencionar-card');
                if (!dd) return;
                if (dd.style.display === 'block') { dd.style.display = 'none'; return; }
                var ids = cardMencionados[cardId] || [];
                var disponiveis = listaUsuariosMencionar.filter(function(u) { return ids.indexOf(parseInt(u.id)) === -1; });
                var lista = dd.querySelector('.lista-usuarios-card');
                if (!lista) return;
                if (disponiveis.length === 0) {
                    lista.innerHTML = '<div class="small p-2 text-muted">Nenhum usuário para adicionar.</div>';
                } else {
                    lista.innerHTML = disponiveis.map(function(u) {
                        return '<button type="button" class="btn btn-sm btn-outline-secondary w-100 text-start mb-1" data-id="' + u.id + '"><i class="fas fa-user me-1"></i> ' + escapeHtml(u.nome || u.login) + '</button>';
                    }).join('');
                    lista.querySelectorAll('button[data-id]').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            var id = parseInt(this.getAttribute('data-id'));
                            var nome = (listaUsuariosMencionar.find(function(x) { return parseInt(x.id) === id; }) || {}).nome || btn.textContent.trim();
                            if (!cardMencionados[cardId]) cardMencionados[cardId] = [];
                            if (cardMencionados[cardId].indexOf(id) === -1) {
                                cardMencionados[cardId].push(id);
                                if (!cardMencionadosNomes[cardId]) cardMencionadosNomes[cardId] = {};
                                cardMencionadosNomes[cardId][id] = nome;
                            }
                            renderChipsForCardTimeline(cardId);
                            dd.style.display = 'none';
                        });
                    });
                }
                dd.style.display = 'block';
            }

            function addCardNovoTimeline() {
                if (listaObras.length === 0) {
                    fetch(API + '?action=obras').then(function(r) { return r.json(); }).then(function(res) {
                        if (res.ok && res.obras) listaObras = res.obras;
                        addCardNovoTimeline();
                    });
                    return;
                }
                var cardId = 'card-' + (++contadorCardNovo);
                cardMencionados[cardId] = [];
                cardMencionadosNomes[cardId] = {};
                var container = document.getElementById('mensagensCardsNovosTimeline');
                var card = document.createElement('div');
                card.className = 'msg-item border rounded p-2 mb-2';
                card.setAttribute('data-card-id', cardId);
                var selectObra = '';
                if (listaObras.length > 0) {
                    selectObra = '<div class="mb-2"><label class="small msg-data">Obra</label><select class="form-select form-select-sm msg-select-obra" style="background: rgba(0,188,212,0.05); border: 1px solid rgba(0,188,212,0.2); color: #e3f2fd;">' +
                        listaObras.map(function(o) { return '<option value="' + o.id + '">' + escapeHtml(o.nome || o.cidade || 'ID ' + o.id) + '</option>'; }).join('') +
                        '</select></div>';
                }
                card.innerHTML =
                    '<div class="d-flex justify-content-between align-items-start">' +
                    '<strong class="small">Você</strong>' +
                    '<span class="small msg-data opacity-75">Nova mensagem</span>' +
                    '</div>' +
                    selectObra +
                    '<textarea class="form-control form-control-sm mt-1 mb-2 msg-texto-editar" rows="2" placeholder="Digite sua mensagem..." maxlength="2000"></textarea>' +
                    '<div class="mencionados-chips-card d-flex flex-wrap gap-1 mb-2"></div>' +
                    '<div class="dropdown-mencionar-card p-2" style="display: none;"><div class="small fw-bold mb-1" style="color: #26c6da;">Mencionar usuário</div><div class="lista-usuarios-card"></div></div>' +
                    '<div class="d-flex flex-wrap gap-1 mt-1">' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary btn-card-mencionar" title="Mencionar"><i class="fas fa-at me-1"></i>Mencionar</button>' +
                    '<button type="button" class="btn btn-sm btn-danger btn-card-excluir" title="Excluir"><i class="fas fa-trash-alt me-1"></i>Excluir</button>' +
                    '<button type="button" class="btn btn-sm btn-primary btn-card-salvar" title="Salvar"><i class="fas fa-save me-1"></i>Salvar</button>' +
                    '</div>';
                card.querySelector('.btn-card-mencionar').addEventListener('click', function() { toggleDropdownCardTimeline(cardId); });
                card.querySelector('.btn-card-excluir').addEventListener('click', function() {
                    card.remove();
                    delete cardMencionados[cardId];
                    delete cardMencionadosNomes[cardId];
                });
                card.querySelector('.btn-card-salvar').addEventListener('click', function() {
                    var texto = card.querySelector('.msg-texto-editar').value.trim();
                    if (!texto) { alert('Digite uma mensagem.'); return; }
                    var selObra = card.querySelector('.msg-select-obra');
                    var obraId = selObra ? parseInt(selObra.value, 10) : 0;
                    if (obraId <= 0) { alert('Selecione uma obra.'); return; }
                    var btn = this;
                    btn.disabled = true;
                    var formData = new FormData();
                    formData.append('action', 'enviar');
                    formData.append('obra_id', obraId);
                    formData.append('mensagem', texto);
                    (cardMencionados[cardId] || []).forEach(function(id) { formData.append('mencionados[]', id); });
                    fetch(API, { method: 'POST', body: formData })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            btn.disabled = false;
                            if (res.ok) {
                                card.remove();
                                delete cardMencionados[cardId];
                                delete cardMencionadosNomes[cardId];
                                carregarMensagensTimeline();
                            } else { alert(res.msg || 'Erro ao enviar.'); }
                        })
                        .catch(function() { btn.disabled = false; alert('Erro ao enviar mensagem.'); });
                });
                container.appendChild(card);
            }

            document.addEventListener('DOMContentLoaded', function() {
                atualizarContadorMensagensTimeline();
                var btnNova = document.getElementById('btnNovaMensagemTimeline');
                if (btnNova) btnNova.addEventListener('click', addCardNovoTimeline);
                var btnMensagens = document.getElementById('btn_mensagens_timeline');
                if (btnMensagens) btnMensagens.addEventListener('click', abrirModalMensagensTimeline);
                document.getElementById('offcanvasMensagensTimeline').addEventListener('click', function(e) {
                    if (!e.target.closest('.btn-card-mencionar') && !e.target.closest('.dropdown-mencionar-card')) {
                        document.querySelectorAll('#offcanvasMensagensTimeline [data-card-id] .dropdown-mencionar-card').forEach(function(dd) { dd.style.display = 'none'; });
                    }
                });
            });
        })();
    </script>

</body>
</html>
