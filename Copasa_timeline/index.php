<?php
// Carrega configurações para obter chave do Google Maps
require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Visualizador de evolução temporal dos processamentos Pix4D - Juatuba">
    <meta name="author" content="Copasa">
    <title>Timeline - Processamentos Pix4D | Juatuba</title>

    <!-- Estilos -->
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/style.css">

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
            key: "AIzaSyAwe4ZNUeKNW1Nh8oI72rwGFW6mFA-I8nw",
            v: "weekly"
        });
    </script>
</head>

<body>
    <!-- Container Principal -->
    <div class="timeline-container" id="timeline-container">

        <!-- Header -->
        <header class="timeline-header" role="banner">
            <h1 class="timeline-header__title" id="timeline-header-title">Evolução Temporal - Processamentos Pix4D</h1>
        </header>

        <!-- Área Principal -->
        <main class="timeline-main" role="main">

            <!-- Mensagem de Erro (escondida por padrão) -->
            <div class="timeline-error" role="alert" aria-live="polite">
                <div class="timeline-error__title">Erro</div>
                <div class="timeline-error__message"></div>
            </div>

            <!-- Container do Mapa -->
            <div id="map" class="timeline-map-container"></div>

            <!-- Controles de Navegação -->
            <nav class="timeline-controls" role="navigation" aria-label="Navegação entre projetos">
                <button
                    class="timeline-controls__button timeline-controls__button--prev"
                    type="button"
                    aria-label="Projeto anterior"
                    disabled>
                    ← Anterior
                </button>
                <button
                    class="timeline-controls__button timeline-controls__button--next"
                    type="button"
                    aria-label="Próximo projeto"
                    disabled>
                    Próximo →
                </button>
            </nav>

            <!-- Indicadores (Data e Contador) -->
            <div class="timeline-indicators" role="status" aria-live="polite">
                <div class="timeline-indicators__date timeline-indicators__date--prev" id="timeline-date-prev">-</div>
                <div class="timeline-indicators__date timeline-indicators__date--current" id="timeline-date">-</div>
                <div class="timeline-indicators__date timeline-indicators__date--next" id="timeline-date-next">-</div>
                <div class="timeline-indicators__counter" id="timeline-counter">-</div>
            </div>

        </main>
    </div>

    <!-- Scripts -->
    <script src="assets/js/utils.js"></script>
    <script src="assets/js/api.js"></script>
    <script src="assets/js/map-viewer.js"></script>
    <script src="assets/js/slider.js"></script>

    <script>
        let map;

        async function initMap() {
            const {
                Map
            } = (await google.maps.importLibrary('maps'));
            map = new Map(document.getElementById('map'), {
                center: {
                    lat: -19.946298097810196,
                    lng: -44.347159453933628
                },
                zoom: 17,
            });

            var url_ortofoto = "../projetos/Juatuba/Juatuba_02-12/3_dsm_ortho/2_mosaic/google_tiles";

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

        }

        initMap();

        // Função para inicializar o slider
        function initializeSlider() {
            // Verifica se Google Maps está carregado
            if (!window.google || !window.google.maps) {
                console.error('Google Maps API não está carregada. Verifique a chave da API.');
                const errorElement = document.querySelector('.timeline-error');
                const errorMessage = document.querySelector('.timeline-error__message');
                if (errorElement && errorMessage) {
                    errorElement.classList.add('timeline-error--visible');
                    errorMessage.textContent = 'Erro: Google Maps API não está carregada. Verifique a configuração da chave da API.';
                }
                return;
            }

            try {
                // Cria instância do slider
                const slider = new TimelineSlider('#timeline-container', {
                    autoLoad: true
                });

                // Event listeners opcionais para feedback adicional
                const container = document.getElementById('timeline-container');

                container?.addEventListener('slider:loaded', (e) => {
                    console.log(`Projetos carregados: ${e.detail.total}`);
                });

                container?.addEventListener('slider:changed', (e) => {
                    console.log(`Projeto alterado: ${e.detail.index}`, e.detail.project);
                });

                container?.addEventListener('slider:error', (e) => {
                    console.error('Erro no slider:', e.detail.error || e.detail.message);
                });

                // Expõe slider globalmente para debug (apenas em desenvolvimento)
                if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                    window.timelineSlider = slider;
                }

            } catch (error) {
                console.error('Erro ao inicializar slider:', error);

                // Mostra erro na UI
                const errorElement = document.querySelector('.timeline-error');
                const errorMessage = document.querySelector('.timeline-error__message');

                if (errorElement && errorMessage) {
                    errorElement.classList.add('timeline-error--visible');
                    errorMessage.textContent = 'Erro ao inicializar o visualizador. Recarregue a página.';
                }
            }
        }

        // Aguarda DOM e Google Maps estarem prontos
        function waitForInitialization() {
            // Verifica se DOM está pronto
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    // Aguarda Google Maps se necessário
                    if (window.google && window.google.maps) {
                        initializeSlider();
                    } else {
                        // Aguarda evento de carregamento do Google Maps
                        window.addEventListener('googlemapsloaded', initializeSlider, {
                            once: true
                        });
                        // Timeout de segurança (10 segundos)
                        setTimeout(() => {
                            if (!window.google || !window.google.maps) {
                                console.error('Timeout aguardando Google Maps carregar');
                                initializeSlider(); // Tenta mesmo assim
                            }
                        }, 10000);
                    }
                });
            } else {
                // DOM já está pronto
                if (window.google && window.google.maps) {
                    initializeSlider();
                } else {
                    // Aguarda evento de carregamento do Google Maps
                    window.addEventListener('googlemapsloaded', initializeSlider, {
                        once: true
                    });
                    // Timeout de segurança (10 segundos)
                    setTimeout(() => {
                        if (!window.google || !window.google.maps) {
                            console.error('Timeout aguardando Google Maps carregar');
                            initializeSlider(); // Tenta mesmo assim
                        }
                    }, 10000);
                }
            }
        }

        // Inicia processo de inicialização
        waitForInitialization();
    </script>
</body>

</html>