<?php
session_start();
require_once 'connection.php';

// Se já estiver logado, redireciona para dashboard
if (isset($_SESSION['user_copasa'])) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - COPASA</title>
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
            max-width: 450px;
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

        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            color: var(--accent-color);
            font-size: 1.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            color: var(--primary-color);
            transform: translateX(-5px);
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

        .info-section {
            margin-top: 30px;
            text-align: center;
        }

        .info-text {
            color: rgba(227, 242, 253, 0.7);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 20px;
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

        .alert-info {
            background: rgba(33, 150, 243, 0.1);
            color: #bbdefb;
            border: 1px solid rgba(33, 150, 243, 0.2);
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

        .step.active {
            color: var(--accent-color);
        }

        .step.completed {
            color: var(--primary-color);
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

        .step.active .step-number {
            background: var(--accent-color);
            color: white;
        }

        .step.completed .step-number {
            background: var(--primary-color);
            color: white;
        }

        @media (max-width: 768px) {
            .reset-card {
                margin: 20px;
                padding: 30px 25px;
            }
            
            .back-btn {
                position: static;
                display: inline-block;
                margin-bottom: 20px;
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
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </a>

            <div class="header-section">
                <div class="logo-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h1 class="logo-text">Recuperar Senha</h1>
                <p class="logo-subtitle">Redefina sua senha com segurança</p>
            </div>

            <div class="steps">
                <div class="step active">
                    <div class="step-number">1</div>
                    Email
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    Verificar
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    Nova Senha
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($_GET['success']) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['info'])): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <?= htmlspecialchars($_GET['info']) ?>
                </div>
            <?php endif; ?>

            <form action="auth.php" method="POST" id="resetForm">
                <input type="hidden" name="action" value="forgot_password">
                
                <div class="form-group">
                    <label class="form-label">Email ou Login</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" class="form-control" name="login_email" placeholder="Digite seu email ou login" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-reset">
                    <i class="fas fa-paper-plane me-2"></i>
                    Enviar Link de Recuperação
                </button>
            </form>

            <div class="info-section">
                <p class="info-text">
                    <i class="fas fa-shield-alt me-2"></i>
                    Enviaremos um link seguro para seu email para redefinir sua senha. 
                    Verifique sua caixa de spam caso não receba em alguns minutos.
                </p>
                <a href="index.php" class="link-item">
                    <i class="fas fa-sign-in-alt me-1"></i>
                    Voltar ao Login
                </a>
                <a href="cadastro.php" class="link-item">
                    <i class="fas fa-user-plus me-1"></i>
                    Criar Conta
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Efeitos visuais nos inputs
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.length > 0) {
                    this.style.borderColor = 'var(--primary-color)';
                    this.style.boxShadow = '0 0 10px rgba(0, 188, 212, 0.2)';
                } else {
                    this.style.borderColor = 'rgba(255, 255, 255, 0.1)';
                    this.style.boxShadow = 'none';
                }
            });
        });

        // Validação do formulário
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const loginEmail = document.querySelector('input[name="login_email"]').value.trim();
            
            if (loginEmail.length === 0) {
                e.preventDefault();
                alert('Por favor, digite seu email ou login!');
                return false;
            }
            
            // Mostrar loading no botão
            const button = document.querySelector('.btn-reset');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';
            button.disabled = true;
            
            // Reverter após 3 segundos caso haja erro
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 3000);
        });

        // Animação dos steps
        document.addEventListener('DOMContentLoaded', function() {
            const steps = document.querySelectorAll('.step');
            let currentStep = 0;
            
            // Simular progresso dos steps (apenas visual)
            setInterval(() => {
                steps.forEach((step, index) => {
                    step.classList.remove('active', 'completed');
                    if (index < currentStep) {
                        step.classList.add('completed');
                    } else if (index === currentStep) {
                        step.classList.add('active');
                    }
                });
                currentStep = (currentStep + 1) % 3;
            }, 3000);
        });
    </script>
</body>
</html>
