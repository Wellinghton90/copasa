<?php
session_start();
require_once 'connection.php';
require_once 'EmailSender.php';

header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['user_copasa'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

$usuario_logado = $_SESSION['user_copasa'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'alterar_dados') {
        $nome = trim($_POST['nome'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $nova_senha = $_POST['nova_senha'] ?? '';
        $senha_atual = $_POST['senha_atual'] ?? '';
        
        // Validações básicas
        if (empty($nome) || empty($login) || empty($email) || empty($senha_atual)) {
            echo json_encode(['success' => false, 'message' => 'Por favor, preencha todos os campos obrigatórios.']);
            exit();
        }
        
        // Validar nome - deve conter apenas letras maiúsculas e espaços
        if (!preg_match('/^[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸ\s]+$/', $nome)) {
            echo json_encode(['success' => false, 'message' => 'O nome deve conter apenas letras maiúsculas e espaços.']);
            exit();
        }
        
        // Validar email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Email inválido.']);
            exit();
        }
        
        // Validar nova senha se preenchida
        if (!empty($nova_senha) && strlen($nova_senha) < 6) {
            echo json_encode(['success' => false, 'message' => 'A nova senha deve ter pelo menos 6 caracteres.']);
            exit();
        }
        
        try {
            // Verificar senha atual
            $stmt = $conn->prepare("SELECT senha FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_logado['id']]);
            $usuario_db = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario_db || !password_verify($senha_atual, $usuario_db['senha'])) {
                echo json_encode(['success' => false, 'message' => 'Senha atual incorreta.']);
                exit();
            }
            
            // Verificar se o login já existe (exceto para o próprio usuário)
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE login = ? AND id != ?");
            $stmt->execute([$login, $usuario_logado['id']]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Este login já está sendo usado por outro usuário.']);
                exit();
            }
            
            // Verificar se o email já existe (exceto para o próprio usuário)
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $usuario_logado['id']]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Este email já está sendo usado por outro usuário.']);
                exit();
            }
            
            // Gerar novo token de confirmação
            $confirmation_token = bin2hex(random_bytes(32));
            
            // Preparar dados para atualização
            $dados_alterados = false;
            $campos_atualizar = [];
            $valores = [];
            
            // Verificar se nome mudou
            if ($nome !== $usuario_logado['nome']) {
                $campos_atualizar[] = "nome = ?";
                $valores[] = $nome;
                $dados_alterados = true;
            }
            
            // Verificar se login mudou
            if ($login !== $usuario_logado['login']) {
                $campos_atualizar[] = "login = ?";
                $valores[] = $login;
                $dados_alterados = true;
            }
            
            // Verificar se email mudou
            if ($email !== $usuario_logado['email']) {
                $campos_atualizar[] = "email = ?";
                $valores[] = $email;
                $dados_alterados = true;
            }
            
            // Verificar se senha mudou
            if (!empty($nova_senha)) {
                $campos_atualizar[] = "senha = ?";
                $valores[] = password_hash($nova_senha, PASSWORD_DEFAULT);
                $dados_alterados = true;
            }
            
            // Se algo mudou, atualizar e desconfirmar
            if ($dados_alterados) {
                // Adicionar campos de desconfirmação
                $campos_atualizar[] = "confirmado = 0";
                $campos_atualizar[] = "habilitado = 0";
                $campos_atualizar[] = "confirmation_token = ?";
                $valores[] = $confirmation_token;
                $campos_atualizar[] = "ult_alteracao = NOW()";
                
                // Adicionar ID para WHERE
                $valores[] = $usuario_logado['id'];
                
                // Executar atualização
                $sql = "UPDATE usuarios SET " . implode(", ", $campos_atualizar) . " WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute($valores);
                
                // Enviar email de confirmação
                $emailSender = new EmailSender();
                if ($emailSender->sendConfirmationEmail($email, $nome, $confirmation_token)) {
                    // Destruir sessão
                    session_destroy();
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Dados alterados com sucesso! Verifique seu email para confirmar as alterações.'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Erro ao enviar email de confirmação. Tente novamente.'
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Nenhuma alteração foi detectada.'
                ]);
            }
            
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false, 
                'message' => 'Erro interno do servidor. Tente novamente.'
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
}
?>
