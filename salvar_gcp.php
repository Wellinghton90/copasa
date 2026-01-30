<?php
session_start();
require_once 'connection.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_copasa'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit();
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

// Ler dados JSON do corpo da requisição
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['cidade']) || !isset($data['projeto']) || !isset($data['ponto_referencia'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit();
}

$cidade = $data['cidade'];
$projetoNome = $data['projeto'];
$pontoReferencia = $data['ponto_referencia'];

// Validar ponto de referência
if (!isset($pontoReferencia['lat']) || !isset($pontoReferencia['lng'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Coordenadas do ponto de referência inválidas']);
    exit();
}

// Caminho do arquivo JSON da cidade
$jsonPath = "data/cidades/{$cidade}.json";

if (!file_exists($jsonPath)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Arquivo JSON da cidade não encontrado']);
    exit();
}

// Ler JSON atual
$jsonContent = file_get_contents($jsonPath);
$dadosCidade = json_decode($jsonContent, true);

if (!$dadosCidade || !isset($dadosCidade['projetos'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Estrutura JSON inválida']);
    exit();
}

// Normalizar projeto: garantir pontos_referencia como array (suporta antigo ponto_referencia singular)
function normalizarPontosReferencia(&$projeto) {
    if (isset($projeto['pontos_referencia']) && is_array($projeto['pontos_referencia'])) {
        return $projeto['pontos_referencia'];
    }
    if (isset($projeto['ponto_referencia']) && is_array($projeto['ponto_referencia'])
        && isset($projeto['ponto_referencia']['lat']) && isset($projeto['ponto_referencia']['lng'])) {
        $p = $projeto['ponto_referencia'];
        $projeto['pontos_referencia'] = [[
            'id' => 'gcp_0',
            'lat' => floatval($p['lat']),
            'lng' => floatval($p['lng']),
            'descricao' => $p['descricao'] ?? '',
            'datahora' => $p['datahora'] ?? date('Y-m-d H:i:s')
        ]];
        unset($projeto['ponto_referencia']);
        return $projeto['pontos_referencia'];
    }
    $projeto['pontos_referencia'] = [];
    return $projeto['pontos_referencia'];
}

// Encontrar o projeto e adicionar ponto
$projetoEncontrado = false;
$pontoSalvo = null;
foreach ($dadosCidade['projetos'] as &$projeto) {
    if ($projeto['nome'] === $projetoNome) {
        $projetoEncontrado = true;
        
        $lista = normalizarPontosReferencia($projeto);
        
        $pontoSalvo = [
            'lat' => floatval($pontoReferencia['lat']),
            'lng' => floatval($pontoReferencia['lng']),
            'descricao' => $pontoReferencia['descricao'] ?? '',
            'datahora' => $pontoReferencia['datahora'] ?? date('Y-m-d H:i:s')
        ];
        
        if (isset($pontoReferencia['ref_id']) && $pontoReferencia['ref_id'] !== '' && $pontoReferencia['ref_id'] !== null) {
            $pontoSalvo['ref_id'] = $pontoReferencia['ref_id'];
        }
        if (isset($pontoReferencia['id']) && $pontoReferencia['id'] !== '' && $pontoReferencia['id'] !== null) {
            $pontoSalvo['id'] = $pontoReferencia['id'];
        } else {
            $pontoSalvo['id'] = 'gcp_' . (string)(time() * 1000 + count($lista));
        }
        
        $projeto['pontos_referencia'][] = $pontoSalvo;
        break;
    }
}

if (!$projetoEncontrado) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Projeto não encontrado']);
    exit();
}

// Salvar JSON atualizado
$jsonAtualizado = json_encode($dadosCidade, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (file_put_contents($jsonPath, $jsonAtualizado) === false) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar arquivo']);
    exit();
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'GCP salvo com sucesso', 'ponto' => $pontoSalvo]);
