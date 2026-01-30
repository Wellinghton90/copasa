<?php

/**
 * Endpoint para extrair metadados do mapa Pix4D
 * 
 * Extrai bounds, zoom levels e caminho dos tiles a partir do KML
 * (com fallback para HTML se KML não existir)
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
 * Normaliza um caminho removendo . e .. sem verificar se o arquivo existe
 * 
 * @param string $path Caminho a normalizar
 * @return string Caminho normalizado
 */
function normalizePath(string $path): string
{
    $isAbsolute = false;
    $root = '';
    
    if (preg_match('/^([A-Za-z]:)[\/\\\\]/', $path, $matches)) {
        $isAbsolute = true;
        $root = $matches[1] . DIRECTORY_SEPARATOR;
        $path = substr($path, strlen($root));
    } elseif (strpos($path, DIRECTORY_SEPARATOR) === 0 || strpos($path, '/') === 0) {
        $isAbsolute = true;
        $root = DIRECTORY_SEPARATOR;
        $path = ltrim($path, '/\\');
    }
    
    $parts = preg_split('/[\/\\\\]+/', $path, -1, PREG_SPLIT_NO_EMPTY);
    $normalized = [];
    
    foreach ($parts as $part) {
        if ($part === '.' || $part === '') {
            continue;
        }
        
        if ($part === '..') {
            if (!empty($normalized)) {
                array_pop($normalized);
            } elseif (!$isAbsolute) {
                $normalized[] = $part;
            }
        } else {
            $normalized[] = $part;
        }
    }
    
    $result = implode(DIRECTORY_SEPARATOR, $normalized);
    return $isAbsolute ? $root . $result : $result;
}

/**
 * Valida e resolve caminho
 * 
 * @param string $relativePath Caminho relativo
 * @return string|null Caminho absoluto validado ou null
 */
function validateAndResolvePath(string $relativePath): ?string
{
    $relativePath = str_replace(['..', '\\'], ['', '/'], $relativePath);
    $relativePath = trim($relativePath, '/');
    
    $absolutePath = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $absolutePath = normalizePath($absolutePath);
    
    if (file_exists($absolutePath) && is_readable($absolutePath)) {
        $basePathConfig = normalizePath(BASE_PATH);
        $absolutePathNormalized = normalizePath($absolutePath);
        
        if (strcasecmp(substr($absolutePathNormalized, 0, strlen($basePathConfig)), $basePathConfig) === 0) {
            $resolvedPath = realpath($absolutePath);
            return $resolvedPath !== false ? $resolvedPath : $absolutePath;
        }
    }
    
    return null;
}

/**
 * Extrai metadados do KML
 * 
 * @param string $kmlPath Caminho absoluto do arquivo KML
 * @return array|null Metadados extraídos ou null se falhar
 */
function extractMetadataFromKml(string $kmlPath): ?array
{
    if (!file_exists($kmlPath) || !is_readable($kmlPath)) {
        return null;
    }
    
    $xml = @simplexml_load_file($kmlPath);
    if ($xml === false) {
        return null;
    }
    
    // Registra namespace do KML
    $xml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');
    
    // Busca todos os NetworkLink
    $networkLinks = $xml->xpath('//kml:NetworkLink');
    
    if (empty($networkLinks)) {
        return null;
    }
    
    $norths = [];
    $souths = [];
    $easts = [];
    $wests = [];
    $zoomLevels = [];
    
    foreach ($networkLinks as $networkLink) {
        // Extrai zoom do nome (ex: "17/49389/58122.kml" → 17)
        $name = (string)$networkLink->name;
        if (preg_match('/^(\d+)\//', $name, $matches)) {
            $zoomLevels[] = (int)$matches[1];
        }
        
        // Extrai bounds do LatLonAltBox
        $region = $networkLink->Region;
        if ($region && $region->LatLonAltBox) {
            $box = $region->LatLonAltBox;
            $north = (float)$box->north;
            $south = (float)$box->south;
            $east = (float)$box->east;
            $west = (float)$box->west;
            
            if ($north && $south && $east && $west) {
                $norths[] = $north;
                $souths[] = $south;
                $easts[] = $east;
                $wests[] = $west;
            }
        }
    }
    
    if (empty($norths) || empty($zoomLevels)) {
        return null;
    }
    
    // Calcula bounds totais
    $bounds = [
        'sw' => [
            'lat' => min($souths),
            'lng' => min($wests)
        ],
        'ne' => [
            'lat' => max($norths),
            'lng' => max($easts)
        ]
    ];
    
    // Calcula zoom levels
    $minZoom = min($zoomLevels);
    $maxZoom = max($zoomLevels);
    
    return [
        'bounds' => $bounds,
        'minZoom' => $minZoom,
        'maxZoom' => $maxZoom
    ];
}

/**
 * Extrai metadados do HTML (fallback)
 * 
 * @param string $htmlPath Caminho absoluto do arquivo HTML
 * @return array|null Metadados extraídos ou null se falhar
 */
function extractMetadataFromHtml(string $htmlPath): ?array
{
    if (!file_exists($htmlPath) || !is_readable($htmlPath)) {
        return null;
    }
    
    $content = file_get_contents($htmlPath);
    if ($content === false) {
        return null;
    }
    
    // Extrai mapBounds
    $bounds = null;
    if (preg_match('/mapBounds\s*=\s*new\s+google\.maps\.LatLngBounds\s*\(\s*new\s+google\.maps\.LatLng\s*\(([^,]+),\s*([^)]+)\)\s*,\s*new\s+google\.maps\.LatLng\s*\(([^,]+),\s*([^)]+)\)\s*\)/i', $content, $matches)) {
        $bounds = [
            'sw' => [
                'lat' => (float)trim($matches[1]),
                'lng' => (float)trim($matches[2])
            ],
            'ne' => [
                'lat' => (float)trim($matches[3]),
                'lng' => (float)trim($matches[4])
            ]
        ];
    }
    
    // Extrai minZoom e maxZoom
    $minZoom = null;
    $maxZoom = null;
    
    if (preg_match('/mapMinZoom\s*=\s*(\d+)/i', $content, $matches)) {
        $minZoom = (int)$matches[1];
    }
    
    if (preg_match('/mapMaxZoom\s*=\s*(\d+)/i', $content, $matches)) {
        $maxZoom = (int)$matches[1];
    }
    
    if ($bounds === null || $minZoom === null || $maxZoom === null) {
        return null;
    }
    
    return [
        'bounds' => $bounds,
        'minZoom' => $minZoom,
        'maxZoom' => $maxZoom
    ];
}

/**
 * Envia resposta JSON padronizada
 */
function sendJsonResponse(bool $success, $data = null, ?string $error = null, int $httpCode = 200): void
{
    http_response_code($httpCode);
    
    $response = [
        'success' => $success,
        'timestamp' => date('c')
    ];
    
    if ($success) {
        $response['data'] = $data;
    } else {
        $response['error'] = $error ?? 'Erro desconhecido';
    }
    
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Processa requisição
try {
    // Valida parâmetro
    if (!isset($_GET['path']) || empty($_GET['path'])) {
        sendJsonResponse(false, null, 'Parâmetro "path" é obrigatório', 400);
    }
    
    $htmlPath = $_GET['path'];
    
    // Resolve caminho do HTML
    $absoluteHtmlPath = validateAndResolvePath($htmlPath);
    if ($absoluteHtmlPath === null) {
        sendJsonResponse(false, null, 'Arquivo HTML não encontrado ou acesso negado', 404);
    }
    
    // Calcula diretório do HTML
    $htmlDir = dirname($absoluteHtmlPath);
    
    // Busca arquivo KML na mesma pasta
    $kmlPath = null;
    $files = scandir($htmlDir);
    foreach ($files as $file) {
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'kml') {
            $kmlPath = $htmlDir . DIRECTORY_SEPARATOR . $file;
            break;
        }
    }
    
    $metadata = null;
    $tilesBasePath = null;
    
    // Calcula tilesBasePath a partir do diretório do HTML/KML
    $tilesBasePath = getRelativePath($htmlDir);
    
    // Tenta extrair do KML primeiro
    if ($kmlPath !== null) {
        $metadata = extractMetadataFromKml($kmlPath);
    }
    
    // Fallback para HTML se KML não funcionou
    if ($metadata === null) {
        $metadata = extractMetadataFromHtml($absoluteHtmlPath);
    }
    
    if ($metadata === null) {
        sendJsonResponse(false, null, 'Não foi possível extrair metadados do KML ou HTML', 500);
    }
    
    // Adiciona tilesBasePath aos metadados
    $metadata['tilesBasePath'] = $tilesBasePath;
    
    sendJsonResponse(true, $metadata);
    
} catch (Exception $e) {
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        sendJsonResponse(false, null, 'Erro ao processar requisição: ' . $e->getMessage(), 500);
    } else {
        error_log('Erro em get_map_metadata.php: ' . $e->getMessage());
        sendJsonResponse(false, null, 'Erro ao processar requisição', 500);
    }
}
