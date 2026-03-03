<?php
/**
 * API de mensagens e notificações (obra_detalhes).
 * Mensagens em sistema_mensagens; menções em sistema_notificacoes.
 */
session_start();
require_once 'connection.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_copasa'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Não autorizado.']);
    exit;
}

$usuario = $_SESSION['user_copasa'];
$user_id = (int) $usuario['id'];
$user_nome = $usuario['nome'] ?? $usuario['login'] ?? '';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'obras':
            $stmt = $conn->prepare("SELECT id, nome, cidade FROM obras ORDER BY nome");
            $stmt->execute();
            $obras = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'obras' => $obras]);
            break;

        case 'listar':
            $obra_id = isset($_GET['obra_id']) ? (int) $_GET['obra_id'] : 0;
            if ($obra_id > 0) {
                $stmt_obra = $conn->prepare("SELECT nome, cidade FROM obras WHERE id = ?");
                $stmt_obra->execute([$obra_id]);
                $obra = $stmt_obra->fetch(PDO::FETCH_ASSOC);
                $projeto_ref = $obra ? ($obra['nome'] ?? $obra['cidade'] ?? '') : '';
                $stmt = $conn->prepare("
                    SELECT m.id, m.usuario_id, m.usuario, m.projeto, m.mensagem, m.data,
                           n.lida, n.id_usuario_destino
                    FROM sistema_mensagens m
                    LEFT JOIN sistema_notificacoes n ON n.mensagem_id = m.id AND n.id_usuario_destino = ?
                    WHERE (m.usuario_id = ? OR n.id_usuario_destino = ?)
                      AND m.projeto = ?
                    ORDER BY m.data DESC
                    LIMIT 200
                ");
                $stmt->execute([$user_id, $user_id, $user_id, $projeto_ref]);
            } else {
                $stmt = $conn->prepare("
                    SELECT m.id, m.usuario_id, m.usuario, m.projeto, m.mensagem, m.data,
                           n.lida, n.id_usuario_destino
                    FROM sistema_mensagens m
                    LEFT JOIN sistema_notificacoes n ON n.mensagem_id = m.id AND n.id_usuario_destino = ?
                    WHERE (m.usuario_id = ? OR n.id_usuario_destino = ?)
                    ORDER BY m.data DESC
                    LIMIT 200
                ");
                $stmt->execute([$user_id, $user_id, $user_id]);
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Para cada mensagem, buscar mencionados e respostas (tabela sistema_respostas)
            $stmt_n = $conn->prepare("SELECT id_usuario_destino, usuario_destino FROM sistema_notificacoes WHERE mensagem_id = ?");
            $stmt_resp = null;
            try {
                $stmt_resp = $conn->prepare("SELECT id, usuario_id, usuario, texto, data FROM sistema_respostas WHERE mensagem_id = ? ORDER BY data ASC");
            } catch (PDOException $e) {
            }
            foreach ($rows as &$m) {
                $stmt_n->execute([$m['id']]);
                $lista = $stmt_n->fetchAll(PDO::FETCH_ASSOC);
                $m['mencionados'] = array_map(function ($men) {
                    return ['id' => (int) $men['id_usuario_destino'], 'nome' => $men['usuario_destino']];
                }, $lista);
                $m['respostas'] = [];
                if ($stmt_resp) {
                    try {
                        $stmt_resp->execute([$m['id']]);
                        $m['respostas'] = $stmt_resp->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                    }
                }
            }
            unset($m);

            echo json_encode(['ok' => true, 'mensagens' => $rows]);
            break;

        case 'usuarios':
            $stmt = $conn->prepare("SELECT id, nome, login FROM usuarios WHERE habilitado = 1 AND id != ? ORDER BY nome");
            $stmt->execute([$user_id]);
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'usuarios' => $usuarios]);
            break;

        case 'enviar_resposta':
            $mensagem_id = isset($_POST['mensagem_id']) ? (int) $_POST['mensagem_id'] : 0;
            $texto = trim($_POST['texto'] ?? $_POST['mensagem'] ?? '');
            if ($mensagem_id <= 0) {
                echo json_encode(['ok' => false, 'msg' => 'mensagem_id obrigatório.']);
                exit;
            }
            if ($texto === '') {
                echo json_encode(['ok' => false, 'msg' => 'Digite sua resposta.']);
                exit;
            }
            try {
                $stmt = $conn->prepare("INSERT INTO sistema_respostas (mensagem_id, usuario_id, usuario, texto, data) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$mensagem_id, $user_id, $user_nome, $texto]);
                echo json_encode(['ok' => true, 'msg' => 'Resposta enviada.']);
            } catch (PDOException $e) {
                echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar resposta: ' . $e->getMessage()]);
            }
            break;

        case 'enviar':
            $obra_id = isset($_POST['obra_id']) ? (int) $_POST['obra_id'] : 0;
            $mensagem = trim($_POST['mensagem'] ?? '');
            $mencionados = isset($_POST['mencionados']) ? $_POST['mencionados'] : [];

            if ($obra_id <= 0) {
                echo json_encode(['ok' => false, 'msg' => 'obra_id obrigatório.']);
                exit;
            }
            if ($mensagem === '') {
                echo json_encode(['ok' => false, 'msg' => 'Digite uma mensagem.']);
                exit;
            }

            if (!is_array($mencionados)) {
                $mencionados = array_filter(array_map('intval', explode(',', $mencionados)));
            } else {
                $mencionados = array_filter(array_map('intval', $mencionados));
            }

            $stmt_obra = $conn->prepare("SELECT nome, cidade FROM obras WHERE id = ?");
            $stmt_obra->execute([$obra_id]);
            $obra = $stmt_obra->fetch(PDO::FETCH_ASSOC);
            $obra_nome = $obra ? ($obra['nome'] ?? $obra['cidade'] ?? '') : '';

            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare("INSERT INTO sistema_mensagens (usuario_id, usuario, projeto, mensagem, data) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$user_id, $user_nome, $obra_nome, $mensagem]);
                $mensagem_id = (int) $conn->lastInsertId();

                foreach ($mencionados as $dest_id) {
                    if ((int)$dest_id === $user_id) continue;
                    $stmt_dest = $conn->prepare("SELECT nome, login FROM usuarios WHERE id = ?");
                    $stmt_dest->execute([$dest_id]);
                    $dest = $stmt_dest->fetch(PDO::FETCH_ASSOC);
                    $dest_nome = $dest ? ($dest['nome'] ?? $dest['login'] ?? '') : '';
                    $stmt_ins = $conn->prepare("
                        INSERT INTO sistema_notificacoes (id_usuario_destino, usuario_destino, id_usuario_origem, usuario_origem, mensagem_id, lida, data)
                        VALUES (?, ?, ?, ?, ?, 0, NOW())
                    ");
                    $stmt_ins->execute([$dest_id, $dest_nome, $user_id, $user_nome, $mensagem_id]);
                }

                $conn->commit();
                echo json_encode(['ok' => true, 'msg' => 'Mensagem enviada.', 'mensagem_id' => $mensagem_id]);
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;

        case 'contar_nao_lidas':
            $stmt = $conn->prepare("SELECT COUNT(*) FROM sistema_notificacoes WHERE id_usuario_destino = ? AND lida = 0");
            $stmt->execute([$user_id]);
            $total = (int) $stmt->fetchColumn();
            echo json_encode(['ok' => true, 'total' => $total]);
            break;

        case 'marcar_lida':
            $mensagem_id = isset($_POST['mensagem_id']) ? (int) $_POST['mensagem_id'] : 0;
            if ($mensagem_id <= 0) {
                echo json_encode(['ok' => false, 'msg' => 'mensagem_id obrigatório.']);
                exit;
            }
            $stmt = $conn->prepare("UPDATE sistema_notificacoes SET lida = 1 WHERE mensagem_id = ? AND id_usuario_destino = ?");
            $stmt->execute([$mensagem_id, $user_id]);
            echo json_encode(['ok' => true]);
            break;

        case 'atualizar':
            $mensagem_id = isset($_POST['mensagem_id']) ? (int) $_POST['mensagem_id'] : 0;
            $mensagem = trim($_POST['mensagem'] ?? '');
            $mencionados = isset($_POST['mencionados']) ? $_POST['mencionados'] : [];

            if ($mensagem_id <= 0) {
                echo json_encode(['ok' => false, 'msg' => 'mensagem_id obrigatório.']);
                exit;
            }
            if ($mensagem === '') {
                echo json_encode(['ok' => false, 'msg' => 'Digite uma mensagem.']);
                exit;
            }
            if (!is_array($mencionados)) {
                $mencionados = array_filter(array_map('intval', explode(',', $mencionados)));
            } else {
                $mencionados = array_filter(array_map('intval', $mencionados));
            }

            $stmt = $conn->prepare("SELECT id FROM sistema_mensagens WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$mensagem_id, $user_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['ok' => false, 'msg' => 'Não autorizado a editar esta mensagem.']);
                exit;
            }

            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare("UPDATE sistema_mensagens SET mensagem = ? WHERE id = ? AND usuario_id = ?");
                $stmt->execute([$mensagem, $mensagem_id, $user_id]);
                $stmt = $conn->prepare("DELETE FROM sistema_notificacoes WHERE mensagem_id = ?");
                $stmt->execute([$mensagem_id]);
                foreach ($mencionados as $dest_id) {
                    if ((int)$dest_id === $user_id) continue;
                    $stmt_dest = $conn->prepare("SELECT nome, login FROM usuarios WHERE id = ?");
                    $stmt_dest->execute([$dest_id]);
                    $dest = $stmt_dest->fetch(PDO::FETCH_ASSOC);
                    $dest_nome = $dest ? ($dest['nome'] ?? $dest['login'] ?? '') : '';
                    $stmt_ins = $conn->prepare("
                        INSERT INTO sistema_notificacoes (id_usuario_destino, usuario_destino, id_usuario_origem, usuario_origem, mensagem_id, lida, data)
                        VALUES (?, ?, ?, ?, ?, 0, NOW())
                    ");
                    $stmt_ins->execute([$dest_id, $dest_nome, $user_id, $user_nome, $mensagem_id]);
                }
                $conn->commit();
                echo json_encode(['ok' => true, 'msg' => 'Mensagem atualizada.']);
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;

        case 'excluir':
            $mensagem_id = isset($_POST['mensagem_id']) ? (int) $_POST['mensagem_id'] : 0;
            if ($mensagem_id <= 0) {
                echo json_encode(['ok' => false, 'msg' => 'mensagem_id obrigatório.']);
                exit;
            }
            try {
                $stmt = $conn->prepare("SELECT id FROM sistema_mensagens WHERE id = ? AND usuario_id = ?");
                $stmt->execute([$mensagem_id, $user_id]);
                if (!$stmt->fetch()) {
                    echo json_encode(['ok' => false, 'msg' => 'Não autorizado a excluir esta mensagem.']);
                    exit;
                }
                // Primeiro apaga notificações (quem foi mencionado), depois a mensagem
                $stmt = $conn->prepare("DELETE FROM sistema_notificacoes WHERE mensagem_id = ?");
                $stmt->execute([$mensagem_id]);
                $stmt = $conn->prepare("DELETE FROM sistema_mensagens WHERE id = ? AND usuario_id = ?");
                $stmt->execute([$mensagem_id, $user_id]);
                if ($stmt->rowCount() === 0) {
                    echo json_encode(['ok' => false, 'msg' => 'Mensagem não encontrada ou já excluída.']);
                    exit;
                }
                echo json_encode(['ok' => true, 'msg' => 'Mensagem excluída.']);
            } catch (PDOException $e) {
                echo json_encode(['ok' => false, 'msg' => 'Erro ao excluir: ' . $e->getMessage()]);
            }
            break;

        default:
            echo json_encode(['ok' => false, 'msg' => 'Ação inválida.']);
    }
} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro no servidor.']);
}
