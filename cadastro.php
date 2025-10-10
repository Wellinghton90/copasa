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
    <title>Cadastro - COPASA</title>
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

        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            box-shadow: 
                0 25px 45px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 550px;
            position: relative;
            overflow: hidden;
        }

        .register-card::before {
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
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            box-shadow: 0 10px 30px rgba(0, 188, 212, 0.3);
        }

        .logo-icon i {
            font-size: 1.8rem;
            color: white;
        }

        .logo-text {
            color: var(--text-light);
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .logo-subtitle {
            color: var(--accent-color);
            font-size: 0.9rem;
            font-weight: 300;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
            flex: 1;
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
            width: 100%;
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

        .btn-register {
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

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(0, 188, 212, 0.4);
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .btn-register::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-register:hover::before {
            left: 100%;
        }

        .terms-section {
            margin-top: 20px;
            text-align: center;
        }

        .terms-text {
            color: rgba(227, 242, 253, 0.7);
            font-size: 0.8rem;
            line-height: 1.4;
        }

        .terms-link {
            color: var(--accent-color);
            text-decoration: none;
        }

        .terms-link:hover {
            color: var(--primary-color);
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

        @media (max-width: 768px) {
            .register-card {
                margin: 20px;
                padding: 30px 25px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .back-btn {
                position: static;
                display: inline-block;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </a>

            <div class="header-section">
                <div class="logo-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1 class="logo-text">Criar Conta</h1>
                <p class="logo-subtitle">Junte-se ao sistema COPASA</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

            <form action="auth.php" method="POST" id="registerForm">
                <input type="hidden" name="action" value="register">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nome Completo</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control" name="nome" id="nome" placeholder="SEU NOME COMPLETO EM MAIÚSCULAS" required>
                        </div>
                        <small class="form-text text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Digite apenas letras maiúsculas e espaços (ex: JOÃO SILVA)
                        </small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Login</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-at"></i>
                            </span>
                            <input type="text" class="form-control" name="login" placeholder="Seu login de acesso" required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" class="form-control" name="email" placeholder="seu@email.com" required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Senha</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" name="senha" id="senha" placeholder="Mínimo 6 caracteres" required>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <span id="strengthText">Digite uma senha</span>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Confirmar Senha</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" name="confirmar_senha" placeholder="Confirme sua senha" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-register">
                    <i class="fas fa-user-plus me-2"></i>
                    Criar Conta
                </button>
            </form>

            <div class="terms-section">
                <p class="terms-text">
                    Ao criar uma conta, você concorda com nossos 
                    <a href="#" class="terms-link">Termos de Uso</a> e 
                    <a href="#" class="terms-link">Política de Privacidade</a>
                </p>
                <p class="terms-text mt-2">
                    Já tem uma conta? <a href="index.php" class="terms-link">Faça login</a>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validação de força da senha
        document.getElementById('senha').addEventListener('input', function() {
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
        document.querySelector('input[name="confirmar_senha"]').addEventListener('input', function() {
            const senha = document.getElementById('senha').value;
            const confirmarSenha = this.value;
            
            if (confirmarSenha.length > 0) {
                if (senha === confirmarSenha) {
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

        // Validação do nome em tempo real
        document.getElementById('nome').addEventListener('input', function() {
            let nome = this.value;
            
            // Converter para maiúsculo automaticamente
            nome = nome.toUpperCase();
            
            // Remover caracteres inválidos (apenas letras, espaços e acentos)
            nome = nome.replace(/[^A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸ\s]/g, '');
            
            // Atualizar o valor do campo
            this.value = nome;
            
            // Validar se contém apenas letras maiúsculas
            const regex = /^[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸ\s]+$/;
            if (nome.length > 0 && !regex.test(nome)) {
                this.style.borderColor = '#f44336';
                this.style.boxShadow = '0 0 10px rgba(244, 67, 54, 0.2)';
            } else {
                this.style.borderColor = 'var(--primary-color)';
                this.style.boxShadow = '0 0 10px rgba(0, 188, 212, 0.2)';
            }
        });

        // Validação do formulário
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const nome = document.getElementById('nome').value;
            const senha = document.getElementById('senha').value;
            const confirmarSenha = document.querySelector('input[name="confirmar_senha"]').value;
            
            // Validar nome
            const nomeRegex = /^[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸ\s]+$/;
            if (!nomeRegex.test(nome) || nome.length < 2) {
                e.preventDefault();
                alert('O nome deve conter apenas letras maiúsculas e ter pelo menos 2 caracteres!');
                return false;
            }
            
            if (senha !== confirmarSenha) {
                e.preventDefault();
                alert('As senhas não coincidem!');
                return false;
            }
            
            if (senha.length < 6) {
                e.preventDefault();
                alert('A senha deve ter pelo menos 6 caracteres!');
                return false;
            }
        });

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
    </script>
</body>
</html>
