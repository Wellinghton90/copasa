<?php
require_once 'EmailSender.php';

// Teste do sistema de email
echo "<h1>Teste do Sistema de Email - COPASA</h1>";

try {
    $emailSender = new EmailSender();
    
    // Mostrar configurações
    echo "<h2>Configurações SMTP:</h2>";
    echo "<ul>";
    echo "<li><strong>Host:</strong> " . SMTP_HOST . "</li>";
    echo "<li><strong>Porta:</strong> " . SMTP_PORT . "</li>";
    echo "<li><strong>Usuário:</strong> " . SMTP_USER . "</li>";
    echo "<li><strong>Segurança:</strong> " . SMTP_SECURE . "</li>";
    echo "<li><strong>Email From:</strong> " . FROM_EMAIL . "</li>";
    echo "<li><strong>Nome From:</strong> " . FROM_NAME . "</li>";
    echo "</ul>";
    
    // Teste de envio (descomente e adicione um email válido para testar)
    /*
    $email_teste = "seu-email@exemplo.com"; // ALTERE AQUI
    $nome_teste = "Usuário Teste";
    $token_teste = bin2hex(random_bytes(32));
    
    echo "<h2>Teste de Envio:</h2>";
    echo "<p>Enviando email de teste para: <strong>$email_teste</strong></p>";
    
    $resultado = $emailSender->sendConfirmationEmail($email_teste, $nome_teste, $token_teste);
    
    if ($resultado) {
        echo "<p style='color: green;'><strong>✅ Email enviado com sucesso!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Falha ao enviar email</strong></p>";
    }
    */
    
    echo "<h2>Status do Sistema:</h2>";
    echo "<p style='color: blue;'><strong>📧 Sistema de email configurado e pronto para uso!</strong></p>";
    echo "<p>Para testar o envio, descomente o código de teste acima e adicione um email válido.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Erro:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>← Voltar ao Sistema de Login</a></p>";
?>
