<?php

/**
 * Configurações centralizadas do sistema
 * 
 * Este arquivo contém todas as constantes e configurações
 * utilizadas pela aplicação, seguindo o princípio de
 * Single Source of Truth.
 */

// Caminho base onde estão os projetos Pix4D
define('BASE_PATH', 'D:\\Xampp_novo\\htdocs\\copasa\\projetos\\Juatuba');

// Padrão regex para validar nomes de projetos
// Formato esperado: Juatuba_DD-MM_YY (ex: Juatuba_02-12_25)
define('PROJECT_PATTERN', '/^Juatuba_(\d{2})-(\d{2})_(\d{2})$/');

// Caminho relativo dentro de cada projeto onde está o HTML do mosaico
define('HTML_SUBPATH', '3_dsm_ortho\\2_mosaic\\google_tiles');

// Sufixo do arquivo HTML (será combinado com o nome do projeto)
define('HTML_SUFFIX', '_mosaic.html');

// Ambiente (development ou production)
define('ENVIRONMENT', 'development');

// Carrega chaves de API (se existir arquivo local)
$apiKeysLocalPath = __DIR__ . DIRECTORY_SEPARATOR . 'api_keys.local.php';
if (file_exists($apiKeysLocalPath)) {
    require_once $apiKeysLocalPath;
} else {
    // Define uma constante vazia se o arquivo não existir
    // Isso evita erros, mas a chave precisará ser configurada
    if (!defined('GOOGLE_MAPS_API_KEY')) {
        define('GOOGLE_MAPS_API_KEY', '');
    }
}

// Configurações de erro
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

/**
 * Valida se o caminho base existe e é acessível
 * 
 * @return bool True se o caminho é válido, false caso contrário
 */
function validateBasePath(): bool
{
    if (!defined('BASE_PATH')) {
        return false;
    }
    
    return is_dir(BASE_PATH) && is_readable(BASE_PATH);
}

/**
 * Retorna o caminho relativo para exibição no frontend
 * Remove o caminho absoluto do servidor e retorna apenas o caminho relativo
 * 
 * @param string $absolutePath Caminho absoluto do arquivo
 * @return string Caminho relativo
 */
function getRelativePath(string $absolutePath): string
{
    // Normaliza ambos os caminhos usando realpath para garantir correspondência
    $basePathReal = realpath(BASE_PATH);
    $absolutePathReal = realpath($absolutePath);
    
    if ($basePathReal === false || $absolutePathReal === false) {
        // Fallback: usa comparação de strings simples
        $relativePath = str_replace(BASE_PATH, '', $absolutePath);
    } else {
        // Usa caminhos normalizados
        $relativePath = str_replace($basePathReal, '', $absolutePathReal);
    }
    
    // Normaliza para usar barras normais
    $relativePath = str_replace('\\', '/', $relativePath);
    
    // Remove barra inicial se existir
    $relativePath = ltrim($relativePath, '/');
    
    return $relativePath;
}
