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
if (!isset($_POST['obra_id']) || !isset($_POST['cidade']) || !isset($_FILES['documentos'])) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit();
}

$obra_id = intval($_POST['obra_id']);
$cidade = $_POST['cidade'];

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

// Criar diretório se não existir
$upload_dir = "documentos/" . $cidade . "/" . $obra_id;
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar diretório']);
        exit();
    }
}

// Processar arquivos
$files = $_FILES['documentos'];
$uploaded_files = [];
$errors = [];

for ($i = 0; $i < count($files['name']); $i++) {
    if ($files['error'][$i] === UPLOAD_ERR_OK) {
        $file_name = basename($files['name'][$i]);
        $file_tmp = $files['tmp_name'][$i];
        
        // Sanitizar nome do arquivo
        $file_name = preg_replace("/[^a-zA-Z0-9._-]/", "_", $file_name);
        
        // Verificar se arquivo já existe
        $target_file = $upload_dir . '/' . $file_name;
        $counter = 1;
        $file_info = pathinfo($file_name);
        
        while (file_exists($target_file)) {
            $file_name = $file_info['filename'] . '_' . $counter . '.' . $file_info['extension'];
            $target_file = $upload_dir . '/' . $file_name;
            $counter++;
        }
        
        // Mover arquivo
        if (move_uploaded_file($file_tmp, $target_file)) {
            $uploaded_files[] = $file_name;
        } else {
            $errors[] = "Erro ao fazer upload de: " . $files['name'][$i];
        }
    } else {
        $errors[] = "Erro no arquivo: " . $files['name'][$i];
    }
}

if (count($uploaded_files) > 0) {
    $message = count($uploaded_files) . " arquivo(s) enviado(s) com sucesso";
    if (count($errors) > 0) {
        $message .= ", mas " . count($errors) . " arquivo(s) falharam";
    }
    echo json_encode([
        'success' => true,
        'message' => $message,
        'uploaded' => $uploaded_files,
        'errors' => $errors
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Nenhum arquivo foi enviado',
        'errors' => $errors
    ]);
}
?>

