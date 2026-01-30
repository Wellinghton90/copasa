<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_copasa'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['cidade']) || !isset($data['projeto']) || !isset($data['tipo']) 
    || !isset($data['indice']) || !isset($data['coordenadas'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit();
}

$cidade = $data['cidade'];
$projetoNome = $data['projeto'];
$tipo = $data['tipo'];
$indice = intval($data['indice']);
$coordenadas = $data['coordenadas'];
$descricao = isset($data['descricao']) ? $data['descricao'] : '';
$medida = isset($data['medida']) ? floatval($data['medida']) : null;

$jsonPath = "data/cidades/{$cidade}.json";

if (!file_exists($jsonPath)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Arquivo JSON da cidade não encontrado']);
    exit();
}

$jsonContent = file_get_contents($jsonPath);
$dadosCidade = json_decode($jsonContent, true);

if (!$dadosCidade || !isset($dadosCidade['projetos'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Estrutura JSON inválida']);
    exit();
}

$campo = $tipo . 's';
if ($campo !== 'poligonos' && $campo !== 'linhas' && $campo !== 'pontos') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Tipo de desenho inválido']);
    exit();
}

$projetoEncontrado = false;
foreach ($dadosCidade['projetos'] as &$projeto) {
    if ($projeto['nome'] === $projetoNome) {
        $projetoEncontrado = true;
        if (!isset($projeto['desenhos'][$campo]) || !is_array($projeto['desenhos'][$campo])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Lista de desenhos inválida']);
            exit();
        }
        if ($indice < 0 || $indice >= count($projeto['desenhos'][$campo])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Índice inválido']);
            exit();
        }
        $projeto['desenhos'][$campo][$indice]['coordenadas'] = $coordenadas;
        if ($descricao !== '') {
            $projeto['desenhos'][$campo][$indice]['descricao'] = $descricao;
        }
        if ($campo === 'linhas' && $medida !== null) {
            $projeto['desenhos'][$campo][$indice]['medida'] = $medida;
        }
        break;
    }
}

if (!$projetoEncontrado) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Projeto não encontrado']);
    exit();
}

$jsonAtualizado = json_encode($dadosCidade, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (file_put_contents($jsonPath, $jsonAtualizado) === false) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar arquivo']);
    exit();
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Desenho atualizado']);
