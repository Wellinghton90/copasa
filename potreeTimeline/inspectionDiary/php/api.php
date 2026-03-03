<?php
/**
 * API do Diário de fiscalização.
 * GET: lê o JSON do usuário (user, entries).
 * POST: grava o JSON do usuário.
 * Caminho dos dados configurável (futuramente pasta raiz compartilhada).
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Caminho da pasta de dados (configurável; futuramente pasta raiz compartilhada)
$baseDir = dirname(__DIR__, 2);
$dataDir = $baseDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'diario';

$user = isset($_GET['user']) ? trim($_GET['user']) : '';
if ($user === '') {
    echo json_encode(['error' => 'Missing user parameter']);
    http_response_code(400);
    exit;
}

// Sanitize user to prevent path traversal (only alphanumeric and underscore)
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $user)) {
    echo json_encode(['error' => 'Invalid user parameter']);
    http_response_code(400);
    exit;
}

$filePath = $dataDir . DIRECTORY_SEPARATOR . $user . '.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!is_dir($dataDir)) {
        if (!mkdir($dataDir, 0755, true)) {
            echo json_encode(['error' => 'Could not create data directory']);
            http_response_code(500);
            exit;
        }
    }

    if (!file_exists($filePath)) {
        echo json_encode([
            'user' => $user,
            'entries' => []
        ]);
        exit;
    }

    $raw = @file_get_contents($filePath);
    if ($raw === false) {
        echo json_encode(['error' => 'Could not read file']);
        http_response_code(500);
        exit;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        echo json_encode([
            'user' => $user,
            'entries' => []
        ]);
        exit;
    }

    if (!isset($data['user'])) {
        $data['user'] = $user;
    }
    if (!isset($data['entries']) || !is_array($data['entries'])) {
        $data['entries'] = [];
    }

    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!is_array($data)) {
        echo json_encode(['error' => 'Invalid JSON body']);
        http_response_code(400);
        exit;
    }

    $data['user'] = $user;
    if (!isset($data['entries']) || !is_array($data['entries'])) {
        $data['entries'] = [];
    }

    if (!is_dir($dataDir)) {
        if (!mkdir($dataDir, 0755, true)) {
            echo json_encode(['error' => 'Could not create data directory']);
            http_response_code(500);
            exit;
        }
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        echo json_encode(['error' => 'JSON encode error']);
        http_response_code(500);
        exit;
    }

    if (file_put_contents($filePath, $json) === false) {
        echo json_encode(['error' => 'Could not write file']);
        http_response_code(500);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
