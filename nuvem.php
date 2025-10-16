<?php

session_start();

include('connection.php');

$nomeProjeto = $_GET['projeto'];
$cidade = $_GET['cidade'];

echo "<script>local_arquivos = 'projetos/" . $cidade . "/" . $nomeProjeto . "/2_densification/point_cloud/potree';</script>";
echo "<script>obra = " . json_encode($cidade) . ";</script>";

?>

<!DOCTYPE html>
<html lang="pt">


<head>
    <meta charset="utf-8">
    <meta name="description" content="Carrega Nuvem de Pontos">
    <meta name="author" content="Wellinghton Gomes">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>VIEWER</title>

    <!-- POTREE -->
    <!-- =========================================================================================================== -->
    <link rel="stylesheet" type="text/css" href="potree/build/potree/potree.css">
    <link rel="stylesheet" type="text/css" href="potree/libs/jquery-ui/jquery-ui.min.css">
    <link rel="stylesheet" type="text/css" href="potree/libs/openlayers3/ol.css">
    <link rel="stylesheet" type="text/css" href="potree/libs/spectrum/spectrum.css">
    <link rel="stylesheet" type="text/css" href="potree/libs/jstree/themes/mixed/style.css">
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
    <!-- =========================================================================================================== -->

    <style>
        .nav_list {
            position: absolute;
            top: 15px;
            right: 20px;
            font-family: 'Muli', sans-serif;
            padding-left: 30px;
            width: 105px;
            height: 50px;
            display: flex;
            list-style: none;
            align-items: center;
            background-color: white;
            border-radius: 10px;
            z-index: 3;
        }

        .nav_titulo {
            line-height: 17px;
            text-align: center;
            margin-right: 3em;
        }

        .nav_titulo a {
            font-family: 'Open Sans Bold', sans-serif;
            background: transparent;
            color: #404040;
            text-decoration: none;
            font-weight: bold;
            font-size: 18px;
            letter-spacing: 0.02em;
            text-align: center;
        }

        .nav_titulo span {
            background: transparent;
            color: #404040;
            text-decoration: none;
            font-weight: bold;
            font-size: 12px;
            letter-spacing: 0.02em;
            text-align: center;
        }

        .nav_item {
            margin-right: 1em;
        }

        #btn_3d {
            font-family: 'Muli', sans-serif;
            width: 40px;
            height: 40px;
            border-radius: 4px;
            background: #025e73;
            font-weight: bold;
            font-size: 16px;
            text-align: center;
            color: #fff;
            border: #fff;
            cursor: pointer;
        }

        #btn_3d:disabled {
            background: lightblue;
        }

        #info_i {
            display: none;
            font-family: 'Muli', sans-serif;
            position: absolute;
            margin-top: 18px;
            right: 2px;
            width: 330px;
            max-height: 625px;
            overflow: auto;
            background-color: rgba(255, 255, 255, 0.5);
            border: 1px solid lightblue;
            border-radius: 4px;
        }

        .span_item {
            margin-top: 10px;
        }

        #title_i {
            font-family: 'Muli', sans-serif;
            text-align: center;
            margin: 20px;
            font-weight: bold;
            color: black;
            font-size: larger;
        }

        #content_i {
            font-family: 'Muli', sans-serif;
            margin-left: 20px;
            margin-right: 20px;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
        }

        .gambWell {
            position: absolute;
            z-index: 5;
            top: 30px;
            left: 100px;
        }
    </style>

</head>

<body>

    <div class="gambWell">
        <span style="color: white">Tamanho dos pontos</span>
        <div>
            <button data-param='0.5' onclick="mudaPonto(this)">0.5</button>
            <button data-param='1' onclick="mudaPonto(this)">1</button>
            <button data-param='1.5' onclick="mudaPonto(this)">1.5</button>
            <button data-param='2' onclick="mudaPonto(this)">2</button>
            <button data-param='2.5' onclick="mudaPonto(this)">2.5</button>
            <button data-param='3' onclick="mudaPonto(this)">3</button>
        </div>
    </div>

    <div class="potree_container" style="position: absolute; width: 100%; height: 100%; left: 0px; top: 0px; ">
        <div id="potree_render_area" style="background-color: gradient;"></div>
        <div id="potree_sidebar_container"></div>
    </div>

    <script>
        let currentPointcloud; // Variável global para armazenar a nuvem de pontos carregada
        console.log(local_arquivos)
    </script>

    <script type="module">
        import {
            FirstPersonControls
        } from '/copasa/potree/src/navigation/FirstPersonControls.js';
        import * as THREE from "/copasa/potree/libs/three.js/build/three.module.js";
        import {
            GLTFLoader
        } from "/copasa/potree/libs/three.js/loaders/GLTFLoader.js";

        window.viewer = new Potree.Viewer(document.getElementById("potree_render_area"));

        viewer.setBackground("gradient");
        viewer.setEDLEnabled(false);
        viewer.setFOV(60);
        viewer.setPointBudget(10_000_000);
        viewer.loadSettingsFromURL();

        viewer.setDescription("Visualizador 3D - Obra: " + obra);

        viewer.loadGUI(() => {
            viewer.setLanguage('pt');
            $("#menu_tools").next().show();
            $("#menu_clipping").next().show();
            //viewer.toggleSidebar();
        });
        
        const localDefinitivo = `${local_arquivos}/cloud.js`

        // Load and add point cloud to scene
        Potree.loadPointCloud(localDefinitivo, "DSM", e => {
            let scene = viewer.scene;
            let pointcloud = e.pointcloud;

            let material = pointcloud.material;
            material.size = 1;
            material.pointSizeType = Potree.PointSizeType.ADAPTIVE;
            material.shape = Potree.PointShape.SQUARE;

            //material.splatQuality = Potree.SplatQuality.HIGH;

            scene.addPointCloud(pointcloud);

            // Configure First Person Controls
            //let firstPersonControls = new FirstPersonControls(viewer);
            //viewer.setControls(firstPersonControls);

            viewer.fitToScreen();

            // Armazena a referência global
            currentPointcloud = pointcloud; // Armazena a nuvem carregada

        });
    </script>

    <script>
        function mudaPonto(botao) {
            let tamanho = parseFloat(botao.getAttribute('data-param')); // Obtém o valor do botão
            console.log(tamanho);
            if (currentPointcloud) { // Verifica se a nuvem foi carregada
                let material = currentPointcloud.material; // Acessa o material
                material.size = tamanho; // Altera o tamanho do ponto
                viewer.render(); // Atualiza a visualização
            } else {
                console.warn("Nuvem de pontos ainda não carregada!");
            }
        }
    </script>


</body>

</html>