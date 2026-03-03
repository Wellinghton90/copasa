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

// Riscos da obra (agrupados por grupo_tipo para o mapa de risco)
$riscos_por_grupo = [];
try {
    $stmt_riscos = $conn->prepare("SELECT id_risco_obra, grupo_tipo, grau_risco, pergunta, resposta, evidencia_fotografica, nota_risco, peso FROM riscos_obra WHERE cidade = ? ORDER BY grupo_tipo, id_risco_obra");
    $stmt_riscos->execute([$obra['cidade']]);
    while ($row = $stmt_riscos->fetch(PDO::FETCH_ASSOC)) {
        $grupo = $row['grupo_tipo'] ?? 'Outros';
        if (!isset($riscos_por_grupo[$grupo])) {
            $riscos_por_grupo[$grupo] = [];
        }
        $riscos_por_grupo[$grupo][] = $row;
    }
} catch (PDOException $e) {
    // Tabela pode não existir; mantém array vazio
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
function loadVideoMetadata($cidade)
{
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
function contarFrames($nomeVideo, $cidade)
{
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
function verificarFrames($nomeVideo, $cidade)
{
    return contarFrames($nomeVideo, $cidade) > 0;
}

// Função para verificar se um vídeo está analisado
function verificarAnalisado($nomeVideo, $cidade)
{
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

                // Ignorar vídeos com flag _480p no final do nome
                $nomeVideoSemExtensao = pathinfo($nomeVideo, PATHINFO_FILENAME);
                if (substr($nomeVideoSemExtensao, -5) === '_480p') {
                    continue;
                }

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

/**
 * Lê o arquivo .p4d (XML) e extrai: quantidade_fotos, inicio_inspecao, fim_inspecao,
 * angulo_camera (média pitch), latitude_central, longitude_central (médias), altitude_inicial (primeira alt).
 * altura_media permanece 0.
 * Retorna array com as chaves preenchidas ou null/0 quando não houver dados.
 */
function extrairDadosP4d($caminho_arquivo_p4d) {
    $padrao = [
        'quantidade_fotos' => 0,
        'inicio_inspecao' => null,
        'fim_inspecao' => null,
        'altura_media' => 0,
        'angulo_camera_medio' => 0,
        'angulo_camera_min' => 0,
        'angulo_camera_max' => 0,
        'latitude_central' => 0,
        'longitude_central' => 0,
        'altitude_inicial' => 0,
    ];
    if (!is_file($caminho_arquivo_p4d)) {
        return $padrao;
    }
    libxml_use_internal_errors(true);
    $xml = @simplexml_load_file($caminho_arquivo_p4d);
    if ($xml === false) {
        return $padrao;
    }
    $images = $xml->xpath('//images/image');
    if (empty($images)) {
        return $padrao;
    }
    $padrao['quantidade_fotos'] = count($images);
    $times = [];
    $lats = [];
    $lngs = [];
    $alts = [];
    $pitches = [];
    foreach ($images as $img) {
        if (isset($img->time)) {
            $t = (string) $img->time;
            if ($t !== '') {
                $mysqlTime = str_replace(':', '-', substr($t, 0, 10)) . substr($t, 10);
                $times[] = $mysqlTime;
            }
        }
        if (isset($img->gps)) {
            $g = $img->gps;
            if (isset($g['lat'])) $lats[] = (float) $g['lat'];
            if (isset($g['lng'])) $lngs[] = (float) $g['lng'];
            if (isset($g['alt'])) $alts[] = (float) $g['alt'];
        }
        if (isset($img->ori) && isset($img->ori['pitch'])) {
            $pitches[] = (float) $img->ori['pitch'];
        }
    }
    if (!empty($times)) {
        $padrao['inicio_inspecao'] = min($times);
        $padrao['fim_inspecao'] = max($times);
    }
    if (!empty($lats)) $padrao['latitude_central'] = array_sum($lats) / count($lats);
    if (!empty($lngs)) $padrao['longitude_central'] = array_sum($lngs) / count($lngs);
    if (!empty($alts)) $padrao['altitude_inicial'] = $alts[0];
    if (!empty($pitches)) {
        $padrao['angulo_camera_medio'] = array_sum($pitches) / count($pitches);
        $padrao['angulo_camera_min'] = min($pitches);
        $padrao['angulo_camera_max'] = max($pitches);
    }
    return $padrao;
}

// Criar diretório se não existir
if (!file_exists($projetos_path)) {
    mkdir($projetos_path, 0755, true);
}

// ========== ETAPA 1: Ler a pasta e montar o JSON com os dados dos projetos ==========
$projetos_json = [];
$base_projetos = 'projetos/' . $obra['cidade'] . '/'; // pasta raiz para os caminhos
if (is_dir($projetos_path)) {
    $items = scandir($projetos_path);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $item_path = $projetos_path . '/' . $item;
        if (!is_dir($item_path)) {
            continue;
        }
        $nome_projeto = $item;
        $arquivo_p4d = $projetos_path . '/' . $nome_projeto . '.p4d';
        $caminho_p4d = file_exists($arquivo_p4d) ? $base_projetos . $nome_projeto . '.p4d' : '';
        // data_processamento = data de criação do arquivo .p4d (fallback: data de modificação da pasta do projeto)
        $data_processamento = file_exists($arquivo_p4d)
            ? date('Y-m-d H:i:s', filectime($arquivo_p4d))
            : date('Y-m-d H:i:s', filemtime($item_path));
        $caminho_ortofoto = $base_projetos . $nome_projeto . '/3_dsm_ortho/2_mosaic/google_tiles';
        $caminho_3d = $base_projetos . $nome_projeto . '/2_densification/point_cloud/potree';

        $dados_p4d = [
            'quantidade_fotos' => 0,
            'inicio_inspecao' => null,
            'fim_inspecao' => null,
            'altura_media' => 0,
            'angulo_camera_medio' => 0,
            'angulo_camera_min' => 0,
            'angulo_camera_max' => 0,
            'latitude_central' => 0,
            'longitude_central' => 0,
            'altitude_inicial' => 0,
        ];
        if (file_exists($arquivo_p4d)) {
            $dados_p4d = extrairDadosP4d($arquivo_p4d);
        }

        $projetos_json[] = [
            'data_processamento' => $data_processamento,
            'nome_projeto' => $nome_projeto,
            'caminho_p4d' => $caminho_p4d,
            'caminho_ortofoto' => $caminho_ortofoto,
            'caminho_3d' => $caminho_3d,
            'quantidade_fotos' => $dados_p4d['quantidade_fotos'],
            'inicio_inspecao' => $dados_p4d['inicio_inspecao'],
            'fim_inspecao' => $dados_p4d['fim_inspecao'],
            'altura_media' => $dados_p4d['altura_media'],
            'angulo_camera_medio' => $dados_p4d['angulo_camera_medio'],
            'angulo_camera_min' => $dados_p4d['angulo_camera_min'],
            'angulo_camera_max' => $dados_p4d['angulo_camera_max'],
            'latitude_central' => $dados_p4d['latitude_central'],
            'longitude_central' => $dados_p4d['longitude_central'],
            'altitude_inicial' => $dados_p4d['altitude_inicial'],
        ];
    }
}

//var_dump($projetos_json);

// ========== ETAPA 2: Ver cada objeto do JSON na tabela; se não tiver, adicionar; se tiver, atualizar dados do .p4d ==========
try {
    $stmt_existe = $conn->prepare("SELECT nome_projeto FROM gemeo_digital WHERE cidade = ? AND nome_projeto = ?");
    $stmt_insere = $conn->prepare("
        INSERT INTO gemeo_digital (
            data_processamento, inicio_inspecao, fim_inspecao, nome_projeto,
            caminho_p4d, caminho_ortofoto, caminho_3d, quantidade_fotos,
            altura_media, angulo_camera_medio, angulo_camera_min, angulo_camera_max,
            latitude_central, longitude_central, altitude_inicial, cidade, visibilidade
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_atualiza = $conn->prepare("
        UPDATE gemeo_digital SET
            inicio_inspecao = ?, fim_inspecao = ?, quantidade_fotos = ?,
            altura_media = ?, angulo_camera_medio = ?, angulo_camera_min = ?, angulo_camera_max = ?,
            latitude_central = ?, longitude_central = ?, altitude_inicial = ?,
            visibilidade = 1
        WHERE cidade = ? AND nome_projeto = ?
    ");
    $nomes_na_pasta = [];
    foreach ($projetos_json as $obj) {
        $nomes_na_pasta[] = $obj['nome_projeto'];
        $stmt_existe->execute([$obra['cidade'], $obj['nome_projeto']]);
        if ($stmt_existe->fetch() === false) {
            $stmt_insere->execute([
                $obj['data_processamento'],
                $obj['inicio_inspecao'],
                $obj['fim_inspecao'],
                $obj['nome_projeto'],
                $obj['caminho_p4d'],
                $obj['caminho_ortofoto'],
                $obj['caminho_3d'],
                $obj['quantidade_fotos'],
                $obj['altura_media'],
                $obj['angulo_camera_medio'],
                $obj['angulo_camera_min'],
                $obj['angulo_camera_max'],
                $obj['latitude_central'],
                $obj['longitude_central'],
                $obj['altitude_inicial'],
                $obra['cidade'],
                1,
            ]);
        } else {
            $stmt_atualiza->execute([
                $obj['inicio_inspecao'],
                $obj['fim_inspecao'],
                $obj['quantidade_fotos'],
                $obj['altura_media'],
                $obj['angulo_camera_medio'],
                $obj['angulo_camera_min'],
                $obj['angulo_camera_max'],
                $obj['latitude_central'],
                $obj['longitude_central'],
                $obj['altitude_inicial'],
                $obra['cidade'],
                $obj['nome_projeto'],
            ]);
        }
    }
    // Soft delete: projetos que não estão mais na pasta ficam com visibilidade = 0
    if (count($nomes_na_pasta) > 0) {
        $placeholders = implode(',', array_fill(0, count($nomes_na_pasta), '?'));
        $stmt_ocultar = $conn->prepare("UPDATE gemeo_digital SET visibilidade = 0 WHERE cidade = ? AND nome_projeto NOT IN ($placeholders)");
        $stmt_ocultar->execute(array_merge([$obra['cidade']], $nomes_na_pasta));
    } else {
        $stmt_ocultar = $conn->prepare("UPDATE gemeo_digital SET visibilidade = 0 WHERE cidade = ?");
        $stmt_ocultar->execute([$obra['cidade']]);
    }
} catch (PDOException $e) {
    // Tabela pode não existir; segue para exibir lista vazia
    echo "Erro ao inserir projetos no banco de dados: " . $e->getMessage();
}

// ========== ETAPA 3: Abrir a página lendo do banco (somente visibilidade = 1) ==========
$projetos = [];
try {
    $stmt = $conn->prepare("
        SELECT id_gemeo, data_processamento, nome_projeto, inicio_inspecao, fim_inspecao, quantidade_fotos,
               angulo_camera_medio, angulo_camera_min, angulo_camera_max,
               latitude_central, longitude_central, altitude_inicial
        FROM gemeo_digital
        WHERE cidade = ? AND visibilidade = 1
        ORDER BY data_processamento DESC
    ");
    $stmt->execute([$obra['cidade']]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $projetos[] = [
            'nome' => $row['nome_projeto'],
            'data_processamento' => date('d/m/Y H:i', strtotime($row['data_processamento'])),
            'data_processamento_order' => strtotime($row['data_processamento']),
            'inicio_inspecao' => $row['inicio_inspecao'] ? date('d/m/Y H:i', strtotime($row['inicio_inspecao'])) : '—',
            'inicio_inspecao_order' => $row['inicio_inspecao'] ? strtotime($row['inicio_inspecao']) : 0,
            'fim_inspecao' => $row['fim_inspecao'] ? date('d/m/Y H:i', strtotime($row['fim_inspecao'])) : '—',
            'fim_inspecao_order' => $row['fim_inspecao'] ? strtotime($row['fim_inspecao']) : 0,
            'quantidade_fotos' => (int) $row['quantidade_fotos'],
            'angulo_camera_medio' => $row['angulo_camera_medio'] !== null ? (float) $row['angulo_camera_medio'] : '—',
            'angulo_camera_min' => $row['angulo_camera_min'] !== null ? (float) $row['angulo_camera_min'] : '—',
            'angulo_camera_max' => $row['angulo_camera_max'] !== null ? (float) $row['angulo_camera_max'] : '—',
            'latitude_central' => $row['latitude_central'] !== null ? (float) $row['latitude_central'] : '—',
            'longitude_central' => $row['longitude_central'] !== null ? (float) $row['longitude_central'] : '—',
            'altitude_inicial' => $row['altitude_inicial'] !== null ? (float) $row['altitude_inicial'] : '—',
        ];
    }
} catch (PDOException $e) {
    $projetos = [];
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
            text-align: center;
        }

        .table thead th.sorting,
        .table thead th.sorting_asc,
        .table thead th.sorting_desc {
            cursor: pointer;
            padding-right: 26px;
            position: relative;
        }
        /* Setas de ordenação (flechinhas) no cabeçalho */
        .table thead th.sorting::after {
            content: "\f0dc";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.4;
            font-size: 0.75rem;
        }
        .table thead th.sorting_asc::after {
            content: "\f0de";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 1;
            color: var(--accent-color);
            font-size: 0.75rem;
        }
        .table thead th.sorting_desc::after {
            content: "\f0dd";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 1;
            color: var(--accent-color);
            font-size: 0.75rem;
        }

        .table thead th.sorting:hover,
        .table thead th.sorting_asc:hover,
        .table thead th.sorting_desc:hover {
            background: rgba(0, 188, 212, 0.2);
        }
        .table thead th.sorting_asc,
        .table thead th.sorting_desc {
            background: rgba(0, 188, 212, 0.25);
        }
        /* Tabela projetos: só nossa seta (::after), sem ícone padrão do DataTables */
        #projetosTable thead th.sorting,
        #projetosTable thead th.sorting_asc,
        #projetosTable thead th.sorting_desc {
            background-image: none !important;
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
            text-align: left;
        }

        /* Tabela Fiscalizações: todas as colunas centralizadas (vertical e horizontal) */
        #projetosTable thead th,
        #projetosTable tbody td {
            text-align: center !important;
            vertical-align: middle !important;
        }
        #projetosTable tbody td .btn {
            display: inline-block;
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

        .accordion-item {
            background-color: rgba(255, 255, 255, 0.05) !important;
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
        <div id="dados_obra_vez" class="dados-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <h3 class="mb-0">
                    <i class="fas fa-clipboard-list"></i>
                    Informações da obra de <?= htmlspecialchars($obra['cidade']) ?>
                </h3>
                <div id="botoesDadosObra">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnEditarDadosObra" onclick="entrarModoEdicaoDadosObra()" title="Editar dados da obra">
                        <i class="fas fa-edit me-1"></i> Editar
                    </button>
                    <span id="botoesSalvarCancelar" style="display: none;">
                        <button type="button" class="btn btn-success btn-sm me-1" id="btnSalvarDadosObra" onclick="salvarDadosObra()" title="Salvar alterações">
                            <i class="fas fa-save me-1"></i> Salvar
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" id="btnCancelarEdicaoObra" onclick="sairModoEdicaoDadosObra()" title="Cancelar edição">
                            <i class="fas fa-times me-1"></i> Cancelar
                        </button>
                    </span>
                </div>
            </div>

            <form id="formDadosObra">
                <!-- Campos sempre visíveis -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nome da Obra</label>
                        <input type="text" name="nome" class="form-control campo-dados-obra" value="<?= htmlspecialchars($obra['nome']) ?>" readonly>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Status</label>
                        <input type="text" name="status" class="form-control campo-dados-obra" value="<?= htmlspecialchars($obra['status']) ?>" readonly>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Tipo da Obra</label>
                        <select name="tipo_obra" class="form-control campo-dados-obra" disabled>
                            <option value="">— Selecione —</option>
                            <option value="Estacionária"<?= (isset($obra['tipo_obra']) && $obra['tipo_obra'] === 'Estacionária') ? ' selected' : '' ?>>Estacionária</option>
                            <option value="Linear"<?= (isset($obra['tipo_obra']) && $obra['tipo_obra'] === 'Linear') ? ' selected' : '' ?>>Linear</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control campo-dados-obra" rows="3" readonly><?= htmlspecialchars($obra['descricao'] ?? '') ?></textarea>
                    </div>
                </div>


                <!-- Campos expandíveis (ocultos por padrão) -->
                <div id="dadosExpandiveis" class="dados-expandiveis" style="display: none;">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Localização</label>
                            <input type="text" name="localizacao" class="form-control campo-dados-obra" value="<?= htmlspecialchars($obra['localizacao'] ?? '') ?>" readonly>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Latitude</label>
                            <input id="input_lat" type="text" name="latitude" class="form-control campo-dados-obra" value="<?= htmlspecialchars($obra['latitude'] ?? '-') ?>" readonly>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Longitude</label>
                            <input id="input_lng" type="text" name="longitude" class="form-control campo-dados-obra" value="<?= htmlspecialchars($obra['longitude'] ?? '-') ?>" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cidade</label>
                            <input type="text" name="cidade" class="form-control campo-dados-obra" value="<?= htmlspecialchars($obra['cidade']) ?>" readonly>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">UF</label>
                            <input type="text" name="uf" class="form-control campo-dados-obra" value="<?= htmlspecialchars($obra['uf']) ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Situação</label>
                            <input type="text" name="situacao" class="form-control campo-dados-obra" value="<?= htmlspecialchars($obra['situacao']) ?>" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Data de Início</label>
                            <input type="text" name="data_inicio" class="form-control campo-dados-obra" data-original="<?= $obra['data_inicio'] ? date('Y-m-d', strtotime($obra['data_inicio'])) : '' ?>" value="<?= $obra['data_inicio'] ? date('d/m/Y', strtotime($obra['data_inicio'])) : '-' ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Data Prevista</label>
                            <input type="text" name="data_prevista" class="form-control campo-dados-obra" data-original="<?= $obra['data_prevista'] ? date('Y-m-d', strtotime($obra['data_prevista'])) : '' ?>" value="<?= $obra['data_prevista'] ? date('d/m/Y', strtotime($obra['data_prevista'])) : '-' ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Data de Conclusão</label>
                            <input type="text" name="data_conclusao" class="form-control campo-dados-obra" data-original="<?= $obra['data_conclusao'] ? date('Y-m-d', strtotime($obra['data_conclusao'])) : '' ?>" value="<?= $obra['data_conclusao'] ? date('d/m/Y', strtotime($obra['data_conclusao'])) : '-' ?>" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Orçamento Total</label>
                            <input type="text" name="orcamento_total" class="form-control campo-dados-obra" data-original="<?= $obra['orcamento_total'] ?? '' ?>" value="<?= $obra['orcamento_total'] ? 'R$ ' . number_format($obra['orcamento_total'], 2, ',', '.') : '-' ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Orçamento Utilizado</label>
                            <input type="text" name="orcamento_utilizado" class="form-control campo-dados-obra" data-original="<?= $obra['orcamento_utilizado'] ?? '' ?>" value="<?= $obra['orcamento_utilizado'] ? 'R$ ' . number_format($obra['orcamento_utilizado'], 2, ',', '.') : '-' ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Responsável</label>
                            <input type="text" name="responsavel" class="form-control campo-dados-obra" value="<?= htmlspecialchars($obra['responsavel'] ?? '-') ?>" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" class="form-control campo-dados-obra" rows="3" readonly><?= htmlspecialchars($obra['observacoes'] ?? '') ?></textarea>
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
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="riscos-tab" data-bs-toggle="tab" data-bs-target="#riscos" type="button" role="tab">
                    <i class="fa-solid fa-chart-line me-2"></i>
                    Mapa de Riscos
                </button>
            </li>
            <button class="nav-link" id="timeline_tiles" onclick="window.location.href='timeline_tiles.php?cidade=<?= $obra['cidade'] ?>'">
                <i class="fa-solid fa-calendar-days me-2"></i>
                Timeline Ortofoto
            </button>
            <button class="nav-link" id="timeline_4d" onclick="window.location.href='potreeTimeline/timeline.php?cidade=<?= $obra['cidade'] ?>'">
                <i class="fa-solid fa-calendar-days me-2"></i>
                Timeline 4D
            </button>
            <button class="nav-link" id="tabela_diario" onclick="window.location.href='potreeTimeline/data/diario/diario_geral.php'">
                <i class="fa-solid fa-book-bookmark me-2"></i>
                Tabela Diário
            </button>
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
                                    <th style="width: 80px;">Ação</th>
                                    <th style="width: 150px;">Data</th>
                                    <th>Nome</th>
                                    <th style="width: 100px;">Duração</th>
                                    <th style="width: 100px;">Latitude</th>
                                    <th style="width: 100px;">Longitude</th>
                                    <th style="width: 100px;">Frames</th>
                                    <th style="width: 100px;">Analisado</th>
                                    <th style="width: 120px; display: none;">Formato</th>
                                    <th style="width: 120px; display: none;">Tamanho</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($videos as $video): ?>
                                    <?php
                                    // Criar valor sortável para data
                                    $dataSortavel = '';
                                    if ($video['data'] !== 'S/D') {
                                        try {
                                            $dataPartes = explode(' ', $video['data']);
                                            if (count($dataPartes) === 2) {
                                                $dataPartes2 = explode('/', $dataPartes[0]);
                                                if (count($dataPartes2) === 3) {
                                                    $dataSortavel = $dataPartes2[2] . '-' . str_pad($dataPartes2[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($dataPartes2[0], 2, '0', STR_PAD_LEFT) . ' ' . $dataPartes[1];
                                                }
                                            }
                                        } catch (Exception $e) {
                                            $dataSortavel = '0000-00-00 00:00';
                                        }
                                    } else {
                                        $dataSortavel = '0000-00-00 00:00';
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="ver_video.php?video=<?= urlencode($video['nome']) ?>&cidade=<?= urlencode($obra['cidade']) ?>" class="btn btn-primary btn-sm" target="_blank" title="Ver no mapa">
                                                <i class="fas fa-globe"></i>
                                            </a>
                                        </td>
                                        <td data-order="<?= htmlspecialchars($dataSortavel) ?>">
                                            <a style="color: blue;" href="video_ia.php?video=<?= urlencode($video['caminho_relativo']) ?>" target="_blank">
                                                <?= htmlspecialchars($video['data']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a style="color: blue;" href="video_ia.php?video=<?= urlencode($video['caminho_relativo']) ?>" target="_blank">
                                                <i style="color: #0a1929;" class="fas fa-play-circle me-2"></i>
                                                <?= htmlspecialchars($video['nome']) ?>
                                            </a>
                                        </td>
                                        <td><?= $video['duracao'] ?></td>
                                        <td><?= $video['latitude'] ?></td>
                                        <td><?= $video['longitude'] ?></td>
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
                                        <td style="display: none;">
                                            <span class="badge bg-info"><?= strtoupper($video['extensao']) ?></span>
                                        </td>
                                        <td data-order="<?= $video['tamanho'] ?>" style="display: none;"><?= formatBytes($video['tamanho']) ?></td>
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
                                    <th style="width: 200px;">Modelo 3D</th>
                                    <th style="width: 200px;">Modelo 2D</th>
                                    <th style="width: 150px;">Data Processamento</th>
                                    <th style="width: 150px;">Início Inspeção</th>
                                    <th style="width: 150px;">Fim Inspeção</th>
                                    <th style="width: 120px;">Quantidade de fotos</th>
                                    <th style="width: 100px;">Ângulo câmera (médio)</th>
                                    <th style="width: 90px;">Ângulo câmera (mín)</th>
                                    <th style="width: 90px;">Ângulo câmera (máx)</th>
                                    <th style="width: 120px;">Latitude central</th>
                                    <th style="width: 120px;">Longitude central</th>
                                    <th style="width: 100px;">Altitude inicial</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projetos as $projeto): ?>
                                    <tr>
                                        <td>
                                            <a target="_blank" href="nuvem.php?projeto=<?= urlencode($projeto['nome']) ?>&cidade=<?= urlencode($obra['cidade']) ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-cube me-2"></i>
                                                Nuvem de pontos
                                            </a>
                                        </td>
                                        <td>
                                            <a target="_blank" href="desenhos_detalhes.php?id=<?= urlencode($obra_id) ?>&cidade=<?= urlencode($obra['cidade']) ?>&projeto=<?= urlencode($projeto['nome']) ?>" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-map me-2"></i>
                                                Ortofoto
                                            </a>
                                        </td>
                                        <td data-order="<?= (int) $projeto['data_processamento_order'] ?>"><?= htmlspecialchars($projeto['data_processamento']) ?></td>
                                        <td data-order="<?= (int) $projeto['inicio_inspecao_order'] ?>"><?= htmlspecialchars($projeto['inicio_inspecao']) ?></td>
                                        <td data-order="<?= (int) $projeto['fim_inspecao_order'] ?>"><?= htmlspecialchars($projeto['fim_inspecao']) ?></td>
                                        <td><?= (int) $projeto['quantidade_fotos'] ?></td>
                                        <td><?= is_numeric($projeto['angulo_camera_medio']) ? number_format($projeto['angulo_camera_medio'], 2, ',', '.') : $projeto['angulo_camera_medio'] ?></td>
                                        <td><?= is_numeric($projeto['angulo_camera_min']) ? number_format($projeto['angulo_camera_min'], 2, ',', '.') : $projeto['angulo_camera_min'] ?></td>
                                        <td><?= is_numeric($projeto['angulo_camera_max']) ? number_format($projeto['angulo_camera_max'], 2, ',', '.') : $projeto['angulo_camera_max'] ?></td>
                                        <td><?= is_numeric($projeto['latitude_central']) ? number_format($projeto['latitude_central'], 6, ',', '.') : $projeto['latitude_central'] ?></td>
                                        <td><?= is_numeric($projeto['longitude_central']) ? number_format($projeto['longitude_central'], 6, ',', '.') : $projeto['longitude_central'] ?></td>
                                        <td><?= is_numeric($projeto['altitude_inicial']) ? number_format($projeto['altitude_inicial'], 2, ',', '.') : $projeto['altitude_inicial'] ?></td>
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

            <!-- Aba Riscos -->
            <div class="tab-pane fade" id="riscos" role="tabpanel">
                <div class="mb-4">
                    <h4 class="mb-0">
                        <i class="fa-solid fa-chart-line me-2"></i>
                        Mapa de Riscos
                    </h4>
                </div>

                <?php if (empty($riscos_por_grupo)): ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Nenhum registro de risco cadastrado para esta obra.
                    </div>
                <?php else:
                    // Porcentagem por grupo: cada pergunta vale (100/N)%. Resposta "Sim" = risco (ganha a %); "Não" = 0%.
                    // até 33.0% verde | 33.1% a 67.0% amarelo | acima de 67.1% vermelho
                    $corPorPorcentagem = function($pct) {
                        $p = (float) $pct;
                        if ($p <= 33.0) return ['fundo' => 'rgba(212, 237, 218, 1)', 'badge' => 'bg-success'];
                        if ($p <= 67.0) return ['fundo' => 'rgba(255, 243, 205, 1)', 'badge' => 'bg-warning text-dark'];
                        return ['fundo' => 'rgba(248, 215, 218, 1)', 'badge' => 'bg-danger'];
                    };
                    $temRisco = function($resposta) {
                        $r = mb_strtoupper(trim($resposta ?? ''));
                        return ($r === 'SIM');
                    };
                    // Ordenar grupos pelo menor id_risco_obra dos itens (ordem numérica, não alfabética)
                    uasort($riscos_por_grupo, function($a, $b) {
                        $id_a = min(array_map(function($r) { return (int)($r['id_risco_obra'] ?? 0); }, $a));
                        $id_b = min(array_map(function($r) { return (int)($r['id_risco_obra'] ?? 0); }, $b));
                        return $id_a <=> $id_b;
                    });
                ?>
                    <div class="accordion" id="accordionRiscos">
                        <?php
                        $primeiro = false;
                        foreach ($riscos_por_grupo as $grupo_tipo => $itens):
                            $n_perguntas = count($itens);
                            $pct_por_pergunta = $n_perguntas > 0 ? (100.0 / $n_perguntas) : 0;
                            $total_pct = 0;
                            foreach ($itens as $r) {
                                $total_pct += $temRisco($r['resposta'] ?? '') ? $pct_por_pergunta : 0;
                            }
                            $titulo_cores = $corPorPorcentagem($total_pct);
                            $id_collapse = 'risco-' . preg_replace('/[^a-z0-9]/i', '-', $grupo_tipo) . '-' . bin2hex(random_bytes(4));
                            $expandido = $primeiro ? 'show' : '';
                            $colapsado = $primeiro ? '' : 'collapsed';
                            $aria_expanded = $primeiro ? 'true' : 'false';
                            $primeiro = false;
                        ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button <?= $colapsado ?> text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#<?= htmlspecialchars($id_collapse) ?>" aria-expanded="<?= $aria_expanded ?>" aria-controls="<?= htmlspecialchars($id_collapse) ?>" style="background-color: <?= $titulo_cores['fundo'] ?>;">
                                        <b><?= htmlspecialchars($grupo_tipo) ?></b>
                                        <span class="badge <?= $titulo_cores['badge'] ?> ms-2 opacity-100"><?= number_format($total_pct, 1, ',', '') ?>%</span>
                                    </button>
                                </h2>
                                <div id="<?= htmlspecialchars($id_collapse) ?>" class="accordion-collapse collapse <?= $expandido ?>" data-bs-parent="#accordionRiscos">
                                    <div class="accordion-body">
                                        <?php foreach ($itens as $idx => $r):
                                            $evidencia = trim($r['evidencia_fotografica'] ?? '');
                                            $risco_detectado = $temRisco($r['resposta'] ?? '');
                                            $pct_esta = $risco_detectado ? $pct_por_pergunta : 0;
                                        ?>
                                            <div class="card mb-3 border border-dark border-2" style="background-color: <?= $titulo_cores['fundo'] ?>;">
                                                <div class="card-body">
                                                    <h6 class="card-subtitle mb-2 text-muted">
                                                        <i class="fas fa-question-circle me-1"></i> Pergunta
                                                    </h6>
                                                    <p class="card-text mb-2"><?= nl2br(htmlspecialchars($r['pergunta'] ?? '-')) ?></p>
                                                    <h6 class="card-subtitle mb-2 text-muted mt-2">
                                                        <i class="fas fa-comment-alt me-1"></i> Resposta
                                                    </h6>
                                                    <p class="card-text mb-2"><?= nl2br(htmlspecialchars($r['resposta'] ?? '-')) ?></p>
                                                    <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                                                        <span class="text-muted small">Risco nesta pergunta:</span>
                                                        <span class="badge <?= $risco_detectado ? 'bg-danger' : 'bg-success' ?>"><?= $risco_detectado ? number_format($pct_esta, 1, ',', '') . '%' : '0%' ?></span>
                                                    </div>
                                                    <div class="mt-2">
                                                        <span class="text-muted small">Evidências fotográficas:</span>
                                                        <?php if ($evidencia !== ''): ?>
                                                            <p class="mb-0 small">
                                                                <a href="<?= htmlspecialchars($evidencia) ?>" class="text-primary" target="_blank" rel="noopener"><?= htmlspecialchars($evidencia) ?></a>
                                                            </p>
                                                        <?php else: ?>
                                                            <p class="mb-0 small text-muted">Não há evidências.</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                    columnDefs: [{
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
                        [1, 'desc']
                    ], // Ordenar pela coluna de Data (índice 1) em ordem decrescente
                    columnDefs: [{
                            orderable: false,
                            targets: 0, // Coluna de Ação (índice 0) - não ordenável
                            className: 'text-center'
                        },
                        {
                            targets: 1, // Coluna de Data (índice 1)
                            className: 'text-start'
                            // Ordenação usando data-order do HTML
                        },
                        {
                            targets: 2, // Coluna de Nome (índice 2) - alinhar à esquerda
                            className: 'text-start'
                        },
                        {
                            targets: 3, // Coluna de Duração (índice 3)
                            className: 'text-start'
                        },
                        {
                            targets: 4, // Coluna de Latitude (índice 4)
                            className: 'text-start'
                        },
                        {
                            targets: 5, // Coluna de Longitude (índice 5)
                            className: 'text-start'
                        },
                        {
                            type: 'num',
                            targets: 6, // Coluna de Frames (índice 6) - usará o atributo data-order automaticamente
                            className: 'text-start'
                        },
                        {
                            targets: 7, // Coluna de Analisado (índice 7)
                            className: 'text-start'
                        },
                        {
                            targets: 8, // Coluna de Formato (índice 8) - oculta
                            className: 'text-start',
                            visible: false
                        },
                        {
                            type: 'num',
                            targets: 9, // Coluna de Tamanho (índice 9) - oculta
                            className: 'text-start',
                            visible: false
                        }
                    ]
                });
            }

            // Ordenação por data-order (timestamp numérico): lê o atributo e converte em número para ordenar
            $.fn.dataTable.ext.order['dom-data-order'] = function(settings, col) {
                var api = new $.fn.dataTable.Api(settings);
                return api.column(col, { order: 'index' }).nodes().map(function(td) {
                    return parseInt($(td).attr('data-order'), 10) || 0;
                });
            };

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
                        [2, 'desc']
                    ],
                    columnDefs: [
                        { className: 'text-center', targets: '_all' },
                        { orderDataType: 'dom-data-order', targets: [2, 3, 4] },
                        { type: 'num', targets: [5, 6, 7, 8, 9, 10, 11] }
                    ]
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

        // Snapshot dos valores ao entrar em edição (para cancelar)
        let snapshotDadosObra = null;

        function entrarModoEdicaoDadosObra() {
            const campos = document.querySelectorAll('#formDadosObra .campo-dados-obra');
            snapshotDadosObra = {};
            campos.forEach(function(el) {
                if (el.tagName === 'SELECT') {
                    el.removeAttribute('disabled');
                } else {
                    el.removeAttribute('readonly');
                }
                el.classList.add('border', 'border-primary');
                snapshotDadosObra[el.name] = el.value;
                // Campos de data: permitir edição em formato dd/mm/yyyy
                if (el.getAttribute('data-original')) {
                    el.placeholder = 'dd/mm/aaaa';
                }
            });
            document.getElementById('btnEditarDadosObra').style.display = 'none';
            document.getElementById('botoesSalvarCancelar').style.display = 'inline';
        }

        function sairModoEdicaoDadosObra() {
            const campos = document.querySelectorAll('#formDadosObra .campo-dados-obra');
            campos.forEach(function(el) {
                if (el.tagName === 'SELECT') {
                    el.setAttribute('disabled', 'disabled');
                } else {
                    el.setAttribute('readonly', 'readonly');
                }
                el.classList.remove('border', 'border-primary');
                if (snapshotDadosObra && snapshotDadosObra[el.name] !== undefined) {
                    el.value = snapshotDadosObra[el.name];
                }
            });
            document.getElementById('btnEditarDadosObra').style.display = 'inline';
            document.getElementById('botoesSalvarCancelar').style.display = 'none';
            snapshotDadosObra = null;
        }

        function salvarDadosObra() {
            const form = document.getElementById('formDadosObra');
            const formData = new FormData(form);
            formData.append('obra_id', '<?= $obra_id ?>');

            showLoading('Salvando alterações...');

            fetch('atualizar_obra.php', {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                hideLoading();
                if (res.ok) {
                    alert(res.msg);
                    window.location.reload();
                } else {
                    alert('Erro: ' + (res.msg || 'Não foi possível salvar.'));
                }
            })
            .catch(function(err) {
                hideLoading();
                alert('Erro ao enviar: ' + err.message);
            });
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