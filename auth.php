<?php
session_start();
require_once 'connection.php';
require_once 'EmailSender.php';

// Função para gerar token seguro
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Função para enviar email de confirmação (usando EmailSender)
function sendConfirmationEmailAuth($email, $nome, $token) {
    return sendConfirmationEmail($email, $nome, $token);
}

// Função para enviar email de recuperação de senha (usando EmailSender)
function sendPasswordResetEmailAuth($email, $nome, $token) {
    return sendPasswordResetEmail($email, $nome, $token);
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'login':
            $login = trim($_POST['login'] ?? '');
            $senha = $_POST['senha'] ?? '';
            
            if (empty($login) || empty($senha)) {
                header('Location: index.php?error=' . urlencode('Por favor, preencha todos os campos.'));
                exit();
            }
            
            try {
                $stmt = $conn->prepare("SELECT * FROM usuarios WHERE login = ? OR email = ?");
                $stmt->execute([$login, $login]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($usuario && password_verify($senha, $usuario['senha'])) {
                    // Verificar se o usuário está confirmado e habilitado
                    if (!$usuario['confirmado']) {
                        header('Location: index.php?error=' . urlencode('Sua conta ainda não foi confirmada. Verifique seu email.'));
                        exit();
                    }
                    
                    if (!$usuario['habilitado']) {
                        header('Location: index.php?error=' . urlencode('Sua conta está desabilitada. Entre em contato com o administrador.'));
                        exit();
                    }
                    
                    // Login bem-sucedido - usar array único
                    $_SESSION['user_copasa'] = [
                        'id' => $usuario['id'],
                        'nome' => $usuario['nome'],
                        'login' => $usuario['login'],
                        'email' => $usuario['email'],
                        'admin' => $usuario['admin'],
                        'confirmado' => $usuario['confirmado'],
                        'habilitado' => $usuario['habilitado']
                    ];
                    
                    // Atualizar último login
                    $stmt = $conn->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
                    $stmt->execute([$usuario['id']]);
                    
                    header('Location: dashboard.php');
                    exit();
                } else {
                    header('Location: index.php?error=' . urlencode('Login ou senha incorretos.'));
                    exit();
                }
            } catch (PDOException $e) {
                header('Location: index.php?error=' . urlencode('Erro interno do servidor. Tente novamente.'));
                exit();
            }
            break;
            
        case 'register':
            $nome = trim($_POST['nome'] ?? '');
            $login = trim($_POST['login'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';
            $confirmar_senha = $_POST['confirmar_senha'] ?? '';
            
            // Validações
            if (empty($nome) || empty($login) || empty($email) || empty($senha)) {
                header('Location: cadastro.php?error=' . urlencode('Por favor, preencha todos os campos.'));
                exit();
            }
            
            // Validar nome - deve conter apenas letras e espaços, obrigatório em maiúsculo
            if (!preg_match('/^[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸ\s]+$/', $nome)) {
                header('Location: cadastro.php?error=' . urlencode('O nome deve conter apenas letras maiúsculas e espaços.'));
                exit();
            }
            
            if ($senha !== $confirmar_senha) {
                header('Location: cadastro.php?error=' . urlencode('As senhas não coincidem.'));
                exit();
            }
            
            if (strlen($senha) < 6) {
                header('Location: cadastro.php?error=' . urlencode('A senha deve ter pelo menos 6 caracteres.'));
                exit();
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                header('Location: cadastro.php?error=' . urlencode('Email inválido.'));
                exit();
            }
            
            try {
                // Verificar se login ou email já existem
                $stmt = $conn->prepare("SELECT id FROM usuarios WHERE login = ? OR email = ?");
                $stmt->execute([$login, $email]);
                if ($stmt->fetch()) {
                    header('Location: cadastro.php?error=' . urlencode('Login ou email já cadastrados.'));
                    exit();
                }
                
                // Gerar token de confirmação
                $confirmation_token = generateToken();
                
                // Inserir novo usuário
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO usuarios (nome, login, email, senha, data_cadastro, confirmado, habilitado, admin, confirmation_token) VALUES (?, ?, ?, ?, NOW(), 0, 0, 0, ?)");
                $stmt->execute([$nome, $login, $email, $senha_hash, $confirmation_token]);
                
                // Enviar email de confirmação
                if (sendConfirmationEmailAuth($email, $nome, $confirmation_token)) {
                    header('Location: index.php?success=' . urlencode('Cadastro realizado com sucesso! Verifique seu email para confirmar a conta.'));
                } else {
                    header('Location: cadastro.php?error=' . urlencode('Erro ao enviar email de confirmação. Tente novamente.'));
                }
                exit();
                
            } catch (PDOException $e) {
                header('Location: cadastro.php?error=' . urlencode('Erro interno do servidor. Tente novamente.'));
                exit();
            }
            break;
            
        case 'forgot_password':
            $login_email = trim($_POST['login_email'] ?? '');
            
            if (empty($login_email)) {
                header('Location: esqueci_senha.php?error=' . urlencode('Por favor, digite seu email ou login.'));
                exit();
            }
            
            try {
                $stmt = $conn->prepare("SELECT * FROM usuarios WHERE login = ? OR email = ?");
                $stmt->execute([$login_email, $login_email]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($usuario) {
                    // Gerar token de recuperação
                    $reset_token = generateToken();
                    $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Salvar token no banco
                    $stmt = $conn->prepare("UPDATE usuarios SET reset_token = ?, reset_expires = ? WHERE id = ?");
                    $stmt->execute([$reset_token, $reset_expires, $usuario['id']]);
                    
                    // Enviar email de recuperação
                    if (sendPasswordResetEmailAuth($usuario['email'], $usuario['nome'], $reset_token)) {
                        header('Location: esqueci_senha.php?success=' . urlencode('Link de recuperação enviado para seu email!'));
                    } else {
                        header('Location: esqueci_senha.php?error=' . urlencode('Erro ao enviar email. Tente novamente.'));
                    }
                } else {
                    // Por segurança, sempre mostrar mensagem de sucesso
                    header('Location: esqueci_senha.php?success=' . urlencode('Se o email/login existir, você receberá um link de recuperação.'));
                }
                exit();
                
            } catch (PDOException $e) {
                header('Location: esqueci_senha.php?error=' . urlencode('Erro interno do servidor. Tente novamente.'));
                exit();
            }
            break;
            
        default:
            header('Location: index.php');
            exit();
    }
} else {
    header('Location: index.php');
    exit();
}
?>
