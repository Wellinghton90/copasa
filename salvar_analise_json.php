<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['json_data']) && isset($_POST['json_filename']) && isset($_POST['frames_path'])) {
    $json_data = $_POST['json_data'];
    $json_filename = $_POST['json_filename'];
    $frames_path = $_POST['frames_path'];
    
    // Validar dados
    $data = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'JSON inválido']);
        exit;
    }
    
    // Construir caminho completo
    $full_path = $frames_path . '/' . $json_filename;
    
    // Garantir que o diretório existe
    $directory = dirname($full_path);
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Não foi possível criar diretório']);
            exit;
        }
    }
    
    // Salvar arquivo
    if (file_put_contents($full_path, $json_data)) {
        echo json_encode(['success' => true, 'path' => $full_path]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Não foi possível salvar arquivo']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Dados insuficientes']);
}
?>
