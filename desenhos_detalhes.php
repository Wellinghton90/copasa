<?php
session_start();

include("connection.php");

$projeto = $_GET['projeto'];
$cidade = $_GET['cidade'];

echo "<script>let urlProjeto = '$projeto';</script>";
echo "<script>let urlCidade = '$cidade';</script>";

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
        html,
        body {
            width: 100%;
            height: 100vh;
            margin: 0;
            padding: 0;
            background-color: white;
            box-sizing: border-box;
        }

        #map {
            width: 100%;
            height: 100%;
            border-top: 0px solid black;
            border-left: 1px solid black;
            border-right: 1px solid black;
            border-bottom: 1px solid black;
        }
    </style>
</head>

<body>
    <div id="map"></div>

    <script>

        let map;
        let coordsLocal = { lat: -12.9716, lng: -38.5118 };

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
                mapTypeControl: true,

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

                zoom: 18,
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

            var url_ortofoto = ``;

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

            //===================================================================================

        }

        initMap();
    </script>
</body>

</html>