<?php

/**
 * Endpoint RESTful para obter lista de projetos Pix4D
 * 
 * Retorna JSON com todos os projetos encontrados,
 * lendo do arquivo JSON gerado pelo script Python.
 */

// Headers para resposta JSON
header('Content-Type: application/json; charset=utf-8');

// CORS (se necessário para desenvolvimento)
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
}

// Carrega configurações
require_once __DIR__ . '/../config/config.php';

/**
 * Envia resposta JSON padronizada
 * 
 * @param bool $success Indica se a operação foi bem-sucedida
 * @param mixed $data Dados da resposta
 * @param string|null $error Mensagem de erro (se houver)
 * @param string|null $errorCode Código do erro
 * @param int $httpCode Código HTTP da resposta
 */
function sendJsonResponse(
    bool $success,
    $data = null,
    ?string $error = null,
    ?string $errorCode = null,
    int $httpCode = 200
): void {
    http_response_code($httpCode);
    
    $response = [
        'success' => $success,
        'timestamp' => date('c')
    ];
    
    if ($success) {
        $response['data'] = $data;
        $response['meta'] = [
            'total' => is_array($data) ? count($data) : 0
        ];
    } else {
        $response['error'] = $error ?? 'Erro desconhecido';
        if ($errorCode !== null) {
            $response['code'] = $errorCode;
        }
    }
    
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Caminho do arquivo JSON
    $jsonFile = __DIR__ . '/../data/projects.json';
    
    // Verifica se o arquivo existe
    if (!file_exists($jsonFile)) {
        sendJsonResponse(
            false,
            null,
            'Arquivo de projetos não encontrado. Execute o script generate_projects_json.py para gerar o JSON.',
            'JSON_FILE_NOT_FOUND',
            404
        );
    }
    
    // Verifica se o arquivo é legível
    if (!is_readable($jsonFile)) {
        sendJsonResponse(
            false,
            null,
            'Arquivo de projetos não pode ser lido. Verifique as permissões.',
            'JSON_FILE_NOT_READABLE',
            500
        );
    }
    
    // Lê o conteúdo do arquivo JSON
    $jsonContent = file_get_contents($jsonFile);
    
    if ($jsonContent === false) {
        sendJsonResponse(
            false,
            null,
            'Erro ao ler arquivo de projetos.',
            'JSON_READ_ERROR',
            500
        );
    }
    
    // Decodifica o JSON
    $data = json_decode($jsonContent, true);
    
    if ($data === null) {
        $jsonError = json_last_error_msg();
        sendJsonResponse(
            false,
            null,
            'Erro ao decodificar JSON: ' . $jsonError,
            'JSON_DECODE_ERROR',
            500
        );
    }
    
    // Verifica se a estrutura está correta
    if (!isset($data['success']) || !isset($data['data'])) {
        sendJsonResponse(
            false,
            null,
            'Formato do JSON inválido. Execute o script generate_projects_json.py novamente.',
            'INVALID_JSON_FORMAT',
            500
        );
    }
    
    // Retorna os dados do JSON
    // Mantém a estrutura original do JSON gerado
    sendJsonResponse(true, $data['data']);
    
} catch (Exception $e) {
    // Log do erro (sem expor detalhes ao cliente)
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        error_log('Erro em get_projects.php: ' . $e->getMessage());
        sendJsonResponse(
            false,
            null,
            'Erro ao processar requisição: ' . $e->getMessage(),
            'INTERNAL_ERROR',
            500
        );
    } else {
        error_log('Erro em get_projects.php: ' . $e->getMessage());
        sendJsonResponse(
            false,
            null,
            'Erro ao processar requisição. Tente novamente mais tarde.',
            'INTERNAL_ERROR',
            500
        );
    }
}
