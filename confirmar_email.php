<?php
session_start();
require_once 'connection.php';

$message = '';
$message_type = 'info';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE confirmation_token = ? AND confirmado = 0");
        $stmt->execute([$token]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            // Confirmar usuário
            $stmt = $conn->prepare("UPDATE usuarios SET confirmado = 1, habilitado = 1, confirmation_token = NULL WHERE id = ?");
            $stmt->execute([$usuario['id']]);
            
            $message = 'Email confirmado com sucesso! Sua conta foi ativada e você já pode fazer login.';
            $message_type = 'success';
        } else {
            $message = 'Token inválido ou expirado. Este link pode ter sido usado anteriormente.';
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        $message = 'Erro interno do servidor. Tente novamente mais tarde.';
        $message_type = 'error';
    }
} else {
    $message = 'Token de confirmação não fornecido.';
    $message_type = 'error';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmação de Email - COPASA</title>
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

        .confirmation-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .confirmation-card {
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
            text-align: center;
        }

        .confirmation-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .icon-section {
            margin-bottom: 30px;
        }

        .confirmation-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            animation: pulse 2s ease-in-out infinite;
        }

        .confirmation-icon.success {
            background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.3);
        }

        .confirmation-icon.error {
            background: linear-gradient(135deg, #f44336 0%, #c62828 100%);
            box-shadow: 0 10px 30px rgba(244, 67, 54, 0.3);
        }

        .confirmation-icon.info {
            background: var(--gradient-primary);
            box-shadow: 0 10px 30px rgba(0, 188, 212, 0.3);
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .confirmation-icon i {
            font-size: 3rem;
            color: white;
        }

        .confirmation-title {
            color: var(--text-light);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .confirmation-message {
            color: var(--text-light);
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .btn-action {
            background: var(--gradient-primary);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            padding: 15px 30px;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(0, 188, 212, 0.4);
            color: white;
        }

        .btn-action:active {
            transform: translateY(0);
        }

        .btn-action::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-action:hover::before {
            left: 100%;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 15px 35px rgba(255, 255, 255, 0.1);
        }

        .links-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
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

        .progress-steps {
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
            .confirmation-card {
                margin: 20px;
                padding: 30px 25px;
            }
            
            .confirmation-title {
                font-size: 1.5rem;
            }

            .progress-steps {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-card">
            <div class="icon-section">
                <div class="confirmation-icon <?= $message_type ?>">
                    <?php if ($message_type === 'success'): ?>
                        <i class="fas fa-check"></i>
                    <?php elseif ($message_type === 'error'): ?>
                        <i class="fas fa-times"></i>
                    <?php else: ?>
                        <i class="fas fa-info"></i>
                    <?php endif; ?>
                </div>
            </div>

            <h1 class="confirmation-title">
                <?php if ($message_type === 'success'): ?>
                    Email Confirmado!
                <?php elseif ($message_type === 'error'): ?>
                    Erro na Confirmação
                <?php else: ?>
                    Aguardando Confirmação
                <?php endif; ?>
            </h1>

            <p class="confirmation-message">
                <?= htmlspecialchars($message) ?>
            </p>

            <?php if ($message_type === 'success'): ?>
                <div class="progress-steps">
                    <div class="step completed">
                        <div class="step-number">1</div>
                        Cadastro
                    </div>
                    <div class="step completed">
                        <div class="step-number">2</div>
                        Email Confirmado
                    </div>
                    <div class="step current">
                        <div class="step-number">3</div>
                        Pronto para Login
                    </div>
                </div>

                <a href="index.php" class="btn-action">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Fazer Login
                </a>
            <?php elseif ($message_type === 'error'): ?>
                <a href="cadastro.php" class="btn-action">
                    <i class="fas fa-user-plus me-2"></i>
                    Tentar Novamente
                </a>
                <a href="index.php" class="btn-action btn-secondary">
                    <i class="fas fa-home me-2"></i>
                    Voltar ao Início
                </a>
            <?php endif; ?>

            <div class="links-section">
                <a href="index.php" class="link-item">
                    <i class="fas fa-home me-1"></i>
                    Página Inicial
                </a>
                <a href="cadastro.php" class="link-item">
                    <i class="fas fa-user-plus me-1"></i>
                    Criar Conta
                </a>
                <a href="esqueci_senha.php" class="link-item">
                    <i class="fas fa-key me-1"></i>
                    Esqueci a Senha
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animação de sucesso
        <?php if ($message_type === 'success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Criar confetes
            for (let i = 0; i < 50; i++) {
                createConfetti();
            }
            
            function createConfetti() {
                const confetti = document.createElement('div');
                confetti.style.position = 'fixed';
                confetti.style.width = '10px';
                confetti.style.height = '10px';
                confetti.style.backgroundColor = ['#00bcd4', '#26c6da', '#006064'][Math.floor(Math.random() * 3)];
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.top = '-10px';
                confetti.style.zIndex = '9999';
                confetti.style.borderRadius = '50%';
                confetti.style.pointerEvents = 'none';
                
                document.body.appendChild(confetti);
                
                const animation = confetti.animate([
                    { transform: 'translateY(0px) rotate(0deg)', opacity: 1 },
                    { transform: `translateY(${window.innerHeight + 100}px) rotate(720deg)`, opacity: 0 }
                ], {
                    duration: 3000 + Math.random() * 2000,
                    easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
                });
                
                animation.addEventListener('finish', () => {
                    confetti.remove();
                });
            }
        });
        <?php endif; ?>

        // Auto-redirect para login em caso de sucesso
        <?php if ($message_type === 'success'): ?>
        setTimeout(() => {
            window.location.href = 'index.php?success=' + encodeURIComponent('Email confirmado com sucesso! Faça login para continuar.');
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>
