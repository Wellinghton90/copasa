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

if (!$data || !isset($data['cidade']) || !isset($data['projeto']) || !isset($data['desenho'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit();
}

$cidade = $data['cidade'];
$projetoNome = $data['projeto'];
$desenho = $data['desenho'];

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

// Encontrar o projeto
$projetoEncontrado = false;
foreach ($dadosCidade['projetos'] as &$projeto) {
    if ($projeto['nome'] === $projetoNome) {
        $projetoEncontrado = true;
        
        // Inicializar desenhos se não existir
        if (!isset($projeto['desenhos'])) {
            $projeto['desenhos'] = [
                'poligonos' => [],
                'linhas' => [],
                'pontos' => []
            ];
        }
        
        // Adicionar desenho ao tipo correspondente
        $tipo = $desenho['tipo'];
        if (isset($projeto['desenhos'][$tipo . 's'])) {
            $projeto['desenhos'][$tipo . 's'][] = $desenho;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Tipo de desenho inválido']);
            exit();
        }
        
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
echo json_encode(['success' => true, 'message' => 'Desenho salvo com sucesso']);
