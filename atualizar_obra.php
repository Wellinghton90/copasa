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

$obra_id = isset($_POST['obra_id']) ? intval($_POST['obra_id']) : 0;
if ($obra_id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'ID da obra inválido.']);
    exit;
}

// Converte data dd/mm/yyyy para Y-m-d
function parseDataBr($str) {
    if (empty(trim($str ?? '')) || trim($str) === '-') return null;
    $str = trim($str);
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $str, $m)) {
        return $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $str, $m)) return $str;
    return null;
}

// Converte "R$ 1.234,56" ou "1234,56" para número
function parseMoeda($str) {
    if ($str === null || $str === '' || trim($str) === '-' || trim($str) === '') return null;
    $str = preg_replace('/[^\d,\-]/', '', $str);
    $str = str_replace('.', '', $str);
    $str = str_replace(',', '.', $str);
    return is_numeric($str) ? floatval($str) : null;
}

$nome = trim($_POST['nome'] ?? '');
$status = trim($_POST['status'] ?? '');
$tipo_obra = trim($_POST['tipo_obra'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$localizacao = trim($_POST['localizacao'] ?? '');
$latitude = trim($_POST['latitude'] ?? '') ?: null;
$longitude = trim($_POST['longitude'] ?? '') ?: null;
$cidade = trim($_POST['cidade'] ?? '');
$uf = trim($_POST['uf'] ?? '');
$situacao = trim($_POST['situacao'] ?? '');
$data_inicio = parseDataBr($_POST['data_inicio'] ?? '');
$data_prevista = parseDataBr($_POST['data_prevista'] ?? '');
$data_conclusao = parseDataBr($_POST['data_conclusao'] ?? '');
$orcamento_total = parseMoeda($_POST['orcamento_total'] ?? null);
$orcamento_utilizado = parseMoeda($_POST['orcamento_utilizado'] ?? null);
$responsavel = trim($_POST['responsavel'] ?? '');
$observacoes = trim($_POST['observacoes'] ?? '');

if ($nome === '') {
    echo json_encode(['ok' => false, 'msg' => 'Nome da obra é obrigatório.']);
    exit;
}

try {
    $sql = "UPDATE obras SET
        nome = ?, status = ?, tipo_obra = ?, descricao = ?, localizacao = ?,
        latitude = ?, longitude = ?, cidade = ?, uf = ?, situacao = ?,
        data_inicio = ?, data_prevista = ?, data_conclusao = ?,
        orcamento_total = ?, orcamento_utilizado = ?, responsavel = ?, observacoes = ?
        WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $nome, $status, $tipo_obra, $descricao, $localizacao,
        $latitude, $longitude, $cidade, $uf, $situacao,
        $data_inicio, $data_prevista, $data_conclusao,
        $orcamento_total, $orcamento_utilizado, $responsavel, $observacoes,
        $obra_id
    ]);
    echo json_encode(['ok' => true, 'msg' => 'Dados da obra atualizados com sucesso.']);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'msg' => 'Erro ao atualizar: ' . $e->getMessage()]);
}
