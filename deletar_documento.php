<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['user_copasa'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

// Verificar se os dados foram enviados
if (!isset($_POST['obra_id']) || !isset($_POST['cidade']) || !isset($_POST['nome_arquivo'])) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit();
}

$obra_id = intval($_POST['obra_id']);
$cidade = $_POST['cidade'];
$nome_arquivo = basename($_POST['nome_arquivo']); // Sanitizar nome

// Validar obra
try {
    $stmt = $conn->prepare("SELECT id FROM obras WHERE id = ?");
    $stmt->execute([$obra_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Obra não encontrada']);
        exit();
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao validar obra']);
    exit();
}

// Caminho do arquivo
$file_path = "documentos/" . $cidade . "/" . $obra_id . "/" . $nome_arquivo;

// Verificar se o arquivo existe
if (!file_exists($file_path)) {
    echo json_encode(['success' => false, 'message' => 'Arquivo não encontrado']);
    exit();
}

// Deletar arquivo
if (unlink($file_path)) {
    echo json_encode(['success' => true, 'message' => 'Documento deletado com sucesso']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao deletar documento']);
}
?>

