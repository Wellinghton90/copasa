<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_copasa'])) {
    echo json_encode(['ok' => false, 'msg' => 'Não autorizado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método inválido.']);
    exit;
}

$cidade = trim($_POST['cidade'] ?? '');
$grupo_tipo = trim($_POST['grupo_tipo'] ?? '');
$pergunta = trim($_POST['pergunta'] ?? '');
$pergunta = trim(preg_replace('/\s+/', ' ', $pergunta)); // remove espaços grandes (copia/cola de PDF)
$resposta = trim($_POST['resposta'] ?? '');
$evidencia_fotografica = trim($_POST['evidencia_fotografica'] ?? '');

if ($cidade === '') {
    echo json_encode(['ok' => false, 'msg' => 'Selecione a cidade.']);
    exit;
}
if ($grupo_tipo === '') {
    echo json_encode(['ok' => false, 'msg' => 'Selecione ou informe o grupo de perguntas.']);
    exit;
}
if ($pergunta === '') {
    echo json_encode(['ok' => false, 'msg' => 'Informe a pergunta.']);
    exit;
}
if ($resposta === '' || ($resposta !== 'Sim' && $resposta !== 'Não')) {
    echo json_encode(['ok' => false, 'msg' => 'Selecione a resposta (Sim ou Não).']);
    exit;
}

try {
    $sql = "INSERT INTO riscos_obra (cidade, grupo_tipo, grau_risco, pergunta, resposta, evidencia_fotografica, nota_risco, peso)
            VALUES (?, ?, NULL, ?, ?, ?, NULL, NULL)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$cidade, $grupo_tipo, $pergunta, $resposta, $evidencia_fotografica]);
    echo json_encode(['ok' => true, 'msg' => 'Pergunta salva com sucesso.']);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar: ' . $e->getMessage()]);
}
