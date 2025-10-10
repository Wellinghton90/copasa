<?php
session_start();
require_once 'connection.php';

$message = '';
$message_type = 'info';
$token_valid = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE reset_token = ? AND reset_expires > NOW()");
        $stmt->execute([$token]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            $token_valid = true;
        } else {
            $message = 'Token inválido ou expirado. Solicite um novo link de recuperação.';
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        $message = 'Erro interno do servidor. Tente novamente mais tarde.';
        $message_type = 'error';
    }
} else {
    $message = 'Token de recuperação não fornecido.';
    $message_type = 'error';
}

// Processar redefinição de senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    if (empty($nova_senha) || empty($confirmar_senha)) {
        $message = 'Por favor, preencha todos os campos.';
        $message_type = 'error';
    } elseif ($nova_senha !== $confirmar_senha) {
        $message = 'As senhas não coincidem.';
        $message_type = 'error';
    } elseif (strlen($nova_senha) < 6) {
        $message = 'A senha deve ter pelo menos 6 caracteres.';
        $message_type = 'error';
    } else {
        try {
            $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET senha = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ?");
            $stmt->execute([$senha_hash, $token]);
            
            $message = 'Senha redefinida com sucesso! Você já pode fazer login com sua nova senha.';
            $message_type = 'success';
            $token_valid = false; // Impedir nova redefinição
        } catch (PDOException $e) {
            $message = 'Erro interno do servidor. Tente novamente.';
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Senha - COPASA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #00bcd4;
            --secondary-color: #006064;
            --accent-color: #26c6da;
            --dark-bg: #0a1929;
            --card-bg: rgba(255, 255, 255, 0.05);
            --text-light: #e3f2fd;
            --gradient-primary: linear-gradient(135deg, #00bcd4 0%, #006064 100%);
            --gradient-bg: linear-gradient(135deg, #0a1929 0%, #1a237e 50%, #0a1929 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gradient-bg);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(0, 188, 212, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(38, 198, 218, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(0, 96, 100, 0.1) 0%, transparent 50%);
            animation: backgroundMove 20s ease-in-out infinite;
            z-index: -1;
        }

        @keyframes backgroundMove {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }

        .reset-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .reset-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            box-shadow: 
                0 25px 45px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
            position: relative;
            overflow: hidden;
        }

        .reset-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .header-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 188, 212, 0.3);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .logo-icon i {
            font-size: 2.5rem;
            color: white;
        }

        .logo-text {
            color: var(--text-light);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
            text-shadow: 0 2px 10px rgba(0, 188, 212, 0.3);
        }

        .logo-subtitle {
            color: var(--accent-color);
            font-size: 0.9rem;
            font-weight: 300;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            color: var(--text-light);
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: var(--text-light);
            padding: 15px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--primary-color);
            box-shadow: 0 0 20px rgba(0, 188, 212, 0.2);
            color: var(--text-light);
            outline: none;
        }

        .form-control::placeholder {
            color: rgba(227, 242, 253, 0.6);
        }

        .input-group {
            position: relative;
        }

        .input-group-text {
            background: rgba(0, 188, 212, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-right: none;
            color: var(--accent-color);
            padding: 15px 20px;
            border-radius: 12px 0 0 12px;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }

        .password-strength {
            margin-top: 8px;
            font-size: 0.8rem;
        }

        .strength-bar {
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            overflow: hidden;
            margin-top: 5px;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak { background: #f44336; }
        .strength-medium { background: #ff9800; }
        .strength-strong { background: #4caf50; }

        .btn-reset {
            background: var(--gradient-primary);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            padding: 15px 30px;
            width: 100%;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(0, 188, 212, 0.4);
        }

        .btn-reset:active {
            transform: translateY(0);
        }

        .btn-reset::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-reset:hover::before {
            left: 100%;
        }

        .alert {
            border-radius: 12px;
            border: none;
            backdrop-filter: blur(10px);
            margin-bottom: 20px;
        }

        .alert-danger {
            background: rgba(244, 67, 54, 0.1);
            color: #ffcdd2;
            border: 1px solid rgba(244, 67, 54, 0.2);
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            color: #c8e6c9;
            border: 1px solid rgba(76, 175, 80, 0.2);
        }

        .links-section {
            margin-top: 30px;
            text-align: center;
        }

        .link-item {
            color: var(--accent-color);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-block;
            margin: 10px 15px;
            position: relative;
        }

        .link-item:hover {
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        .link-item::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }

        .link-item:hover::after {
            width: 100%;
        }

        .steps {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 20px;
        }

        .step {
            display: flex;
            align-items: center;
            color: rgba(227, 242, 253, 0.5);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .step.completed {
            color: var(--primary-color);
        }

        .step.current {
            color: var(--accent-color);
        }

        .step-number {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            font-size: 0.7rem;
        }

        .step.completed .step-number {
            background: var(--primary-color);
            color: white;
        }

        .step.current .step-number {
            background: var(--accent-color);
            color: white;
        }

        @media (max-width: 768px) {
            .reset-card {
                margin: 20px;
                padding: 30px 25px;
            }

            .steps {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="header-section">
                <div class="logo-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h1 class="logo-text">Nova Senha</h1>
                <p class="logo-subtitle">Redefina sua senha com segurança</p>
            </div>

            <div class="steps">
                <div class="step completed">
                    <div class="step-number">1</div>
                    Email
                </div>
                <div class="step completed">
                    <div class="step-number">2</div>
                    Verificar
                </div>
                <div class="step current">
                    <div class="step-number">3</div>
                    Nova Senha
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>">
                    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($token_valid && $message_type !== 'success'): ?>
                <form method="POST" id="resetForm">
                    <div class="form-group">
                        <label class="form-label">Nova Senha</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" name="nova_senha" id="nova_senha" placeholder="Mínimo 6 caracteres" required>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <span id="strengthText">Digite uma senha</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirmar Nova Senha</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" name="confirmar_senha" placeholder="Confirme sua nova senha" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-reset">
                        <i class="fas fa-save me-2"></i>
                        Redefinir Senha
                    </button>
                </form>
            <?php endif; ?>

            <div class="links-section">
                <?php if ($message_type === 'success'): ?>
                    <a href="index.php" class="link-item">
                        <i class="fas fa-sign-in-alt me-1"></i>
                        Fazer Login
                    </a>
                <?php else: ?>
                    <a href="esqueci_senha.php" class="link-item">
                        <i class="fas fa-redo me-1"></i>
                        Solicitar Novo Link
                    </a>
                    <a href="index.php" class="link-item">
                        <i class="fas fa-home me-1"></i>
                        Voltar ao Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validação de força da senha
        document.getElementById('nova_senha')?.addEventListener('input', function() {
            const password = this.value;
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            let strengthLabel = '';
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            switch (strength) {
                case 0:
                case 1:
                    strengthLabel = 'Fraca';
                    strengthFill.className = 'strength-fill strength-weak';
                    strengthFill.style.width = '20%';
                    break;
                case 2:
                case 3:
                    strengthLabel = 'Média';
                    strengthFill.className = 'strength-fill strength-medium';
                    strengthFill.style.width = '60%';
                    break;
                case 4:
                case 5:
                    strengthLabel = 'Forte';
                    strengthFill.className = 'strength-fill strength-strong';
                    strengthFill.style.width = '100%';
                    break;
            }
            
            strengthText.textContent = password.length > 0 ? `Força: ${strengthLabel}` : 'Digite uma senha';
        });

        // Validação de confirmação de senha
        document.querySelector('input[name="confirmar_senha"]')?.addEventListener('input', function() {
            const novaSenha = document.getElementById('nova_senha').value;
            const confirmarSenha = this.value;
            
            if (confirmarSenha.length > 0) {
                if (novaSenha === confirmarSenha) {
                    this.style.borderColor = '#4caf50';
                    this.style.boxShadow = '0 0 10px rgba(76, 175, 80, 0.2)';
                } else {
                    this.style.borderColor = '#f44336';
                    this.style.boxShadow = '0 0 10px rgba(244, 67, 54, 0.2)';
                }
            } else {
                this.style.borderColor = 'rgba(255, 255, 255, 0.1)';
                this.style.boxShadow = 'none';
            }
        });

        // Validação do formulário
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const novaSenha = document.getElementById('nova_senha').value;
            const confirmarSenha = document.querySelector('input[name="confirmar_senha"]').value;
            
            if (novaSenha !== confirmarSenha) {
                e.preventDefault();
                alert('As senhas não coincidem!');
                return false;
            }
            
            if (novaSenha.length < 6) {
                e.preventDefault();
                alert('A senha deve ter pelo menos 6 caracteres!');
                return false;
            }
        });

        // Auto-redirect para login em caso de sucesso
        <?php if ($message_type === 'success'): ?>
        setTimeout(() => {
            window.location.href = 'index.php?success=' + encodeURIComponent('Senha redefinida com sucesso! Faça login com sua nova senha.');
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>
