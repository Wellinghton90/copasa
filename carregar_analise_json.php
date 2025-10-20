<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['json_filename']) && isset($_POST['frames_path'])) {
    $json_filename = $_POST['json_filename'];
    $frames_path = $_POST['frames_path'];
    
    // Construir caminho completo
    $full_path = $frames_path . '/' . $json_filename;
    
    if (file_exists($full_path)) {
        $content = file_get_contents($full_path);
        if ($content !== false) {
            // Verificar se é JSON válido
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo $content; // Retornar JSON original
            } else {
                echo json_encode(['error' => 'Arquivo JSON inválido']);
            }
        } else {
            echo json_encode(['error' => 'Não foi possível ler arquivo']);
        }
    } else {
        echo json_encode(['error' => 'Arquivo não encontrado']);
    }
} else {
    echo json_encode(['error' => 'Dados insuficientes']);
}
?>
