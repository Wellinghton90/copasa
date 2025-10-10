<?php
session_start();
require_once 'connection.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_copasa'])) {
    http_response_code(401);
    die('Não autorizado');
}

// Verificar se os dados foram enviados
if (!isset($_GET['obra_id']) || !isset($_GET['cidade'])) {
    http_response_code(400);
    die('Dados incompletos');
}

$obra_id = intval($_GET['obra_id']);
$cidade = $_GET['cidade'];

// Validar obra
try {
    $stmt = $conn->prepare("SELECT nome FROM obras WHERE id = ?");
    $stmt->execute([$obra_id]);
    $obra = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$obra) {
        http_response_code(404);
        die('Obra não encontrada');
    }
} catch (PDOException $e) {
    http_response_code(500);
    die('Erro ao validar obra');
}

// Caminho dos documentos
$documentos_path = "documentos/" . $cidade . "/" . $obra_id;

// Verificar se o diretório existe
if (!is_dir($documentos_path)) {
    http_response_code(404);
    die('Nenhum documento encontrado');
}

// Listar arquivos
$files = scandir($documentos_path);
$arquivos = [];

foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        $file_path = $documentos_path . '/' . $file;
        if (is_file($file_path)) {
            $arquivos[] = [
                'path' => $file_path,
                'name' => $file
            ];
        }
    }
}

// Verificar se há arquivos
if (count($arquivos) == 0) {
    http_response_code(404);
    die('Nenhum documento encontrado');
}

// Nome do arquivo ZIP
$obra_nome_limpo = preg_replace('/[^a-zA-Z0-9_-]/', '_', $obra['nome']);
$zip_filename = 'documentos_' . $obra_nome_limpo . '_' . date('Y-m-d_H-i-s') . '.zip';

// Tentar usar ZipArchive primeiro
if (class_exists('ZipArchive')) {
    $zip_path = sys_get_temp_dir() . '/' . $zip_filename;
    $zip = new ZipArchive();
    
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        http_response_code(500);
        die('Erro ao criar arquivo ZIP');
    }
    
    foreach ($arquivos as $arquivo) {
        $zip->addFile($arquivo['path'], $arquivo['name']);
    }
    
    $zip->close();
    
    // Enviar o arquivo para download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Content-Length: ' . filesize($zip_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    readfile($zip_path);
    unlink($zip_path);
    
} else {
    // Método alternativo: criar ZIP manualmente
    $zip_path = sys_get_temp_dir() . '/' . $zip_filename;
    
    // Criar arquivo ZIP manualmente
    $zip_content = createZipManually($arquivos);
    
    if ($zip_content === false) {
        http_response_code(500);
        die('Erro ao criar arquivo ZIP');
    }
    
    // Salvar temporariamente
    file_put_contents($zip_path, $zip_content);
    
    // Enviar o arquivo para download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Content-Length: ' . strlen($zip_content));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo $zip_content;
    unlink($zip_path);
}

exit();

// Função para criar ZIP manualmente (método alternativo)
function createZipManually($files) {
    $zip_data = '';
    $central_dir = '';
    $offset = 0;
    
    foreach ($files as $file) {
        $file_content = file_get_contents($file['path']);
        $file_name = $file['name'];
        $file_time = filemtime($file['path']);
        
        // Converter timestamp para formato DOS
        $dos_time = dechex(
            (($file_time & 0xFE000000) >> 25) + 1980 << 9 |
            (($file_time & 0x01E00000) >> 21) << 5 |
            (($file_time & 0x001F0000) >> 16)
        );
        $dos_date = dechex(
            (($file_time & 0x0000F800) >> 11) << 5 |
            (($file_time & 0x000007E0) >> 5) << 0 |
            (($file_time & 0x0000001F) >> 0) / 2
        );
        
        // Header do arquivo local
        $local_header = 
            "\x50\x4b\x03\x04" . // Assinatura
            "\x14\x00" .         // Versão necessária
            "\x00\x00" .         // Flag
            "\x00\x00" .         // Método de compressão (sem compressão)
            pack('v', hexdec($dos_time)) .
            pack('v', hexdec($dos_date)) .
            pack('V', crc32($file_content)) . // CRC32
            pack('V', strlen($file_content)) . // Tamanho comprimido
            pack('V', strlen($file_content)) . // Tamanho descomprimido
            pack('v', strlen($file_name)) .    // Tamanho do nome
            pack('v', 0);                      // Tamanho extra
        
        $zip_data .= $local_header . $file_name . $file_content;
        
        // Header do diretório central
        $central_header =
            "\x50\x4b\x01\x02" . // Assinatura
            "\x14\x00" .         // Versão criada
            "\x14\x00" .         // Versão necessária
            "\x00\x00" .         // Flag
            "\x00\x00" .         // Método de compressão
            pack('v', hexdec($dos_time)) .
            pack('v', hexdec($dos_date)) .
            pack('V', crc32($file_content)) . // CRC32
            pack('V', strlen($file_content)) . // Tamanho comprimido
            pack('V', strlen($file_content)) . // Tamanho descomprimido
            pack('v', strlen($file_name)) .    // Tamanho do nome
            pack('v', 0) .                     // Tamanho extra
            pack('v', 0) .                     // Tamanho comentário
            pack('v', 0) .                     // Número do disco
            pack('v', 0) .                     // Atributos internos
            pack('V', 32) .                    // Atributos externos
            pack('V', $offset);                // Offset do header local
        
        $central_dir .= $central_header . $file_name;
        
        $offset += strlen($local_header) + strlen($file_name) + strlen($file_content);
    }
    
    // End of central directory
    $end_central =
        "\x50\x4b\x05\x06" . // Assinatura
        "\x00\x00" .         // Número deste disco
        "\x00\x00" .         // Disco onde começa o diretório central
        pack('v', count($files)) . // Número de entradas neste disco
        pack('v', count($files)) . // Número total de entradas
        pack('V', strlen($central_dir)) . // Tamanho do diretório central
        pack('V', $offset) . // Offset do diretório central
        "\x00\x00";          // Tamanho do comentário
    
    return $zip_data . $central_dir . $end_central;
}
?>

