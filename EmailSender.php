<?php
/**
 * Classe para Envio de Emails - COPASA
 * Sistema de Obras de Saneamento
 */

require_once 'email_config.php';

class EmailSender {
    private $host;
    private $port;
    private $username;
    private $password;
    private $secure;
    private $fromEmail;
    private $fromName;
    
    public function __construct() {
        $this->host = SMTP_HOST;
        $this->port = SMTP_PORT;
        $this->username = SMTP_USER;
        $this->password = SMTP_PASS;
        $this->secure = SMTP_SECURE;
        $this->fromEmail = FROM_EMAIL;
        $this->fromName = FROM_NAME;
    }
    
    /**
     * Envia email usando SMTP com sockets
     */
    public function sendEmailSMTP($to, $subject, $message, $isHTML = true) {
        try {
            // Conectar ao servidor SMTP
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
            
            $smtp = stream_socket_client(
                ($this->secure === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port,
                $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context
            );
            
            if (!$smtp) {
                error_log("Erro ao conectar SMTP: $errstr ($errno)");
                return false;
            }
            
            // Ler resposta inicial
            $this->readSMTPResponse($smtp);
            
            // EHLO
            fwrite($smtp, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
            $this->readSMTPResponse($smtp);
            
            // STARTTLS se necessário
            if ($this->secure === 'tls') {
                fwrite($smtp, "STARTTLS\r\n");
                $this->readSMTPResponse($smtp);
                stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                fwrite($smtp, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
                $this->readSMTPResponse($smtp);
            }
            
            // Autenticação
            fwrite($smtp, "AUTH LOGIN\r\n");
            $this->readSMTPResponse($smtp);
            
            fwrite($smtp, base64_encode($this->username) . "\r\n");
            $this->readSMTPResponse($smtp);
            
            fwrite($smtp, base64_encode($this->password) . "\r\n");
            $authResponse = $this->readSMTPResponse($smtp);
            
            if (strpos($authResponse, '235') === false) {
                error_log("Falha na autenticação SMTP: $authResponse");
                fclose($smtp);
                return false;
            }
            
            // MAIL FROM
            fwrite($smtp, "MAIL FROM: <" . $this->fromEmail . ">\r\n");
            $this->readSMTPResponse($smtp);
            
            // RCPT TO
            fwrite($smtp, "RCPT TO: <$to>\r\n");
            $this->readSMTPResponse($smtp);
            
            // DATA
            fwrite($smtp, "DATA\r\n");
            $this->readSMTPResponse($smtp);
            
            // Headers e corpo
            $headers = "From: " . $this->fromName . " <" . $this->fromEmail . ">\r\n";
            $headers .= "To: $to\r\n";
            $headers .= "Subject: $subject\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: " . ($isHTML ? 'text/html' : 'text/plain') . "; charset=UTF-8\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            
            fwrite($smtp, $headers . "\r\n" . $message . "\r\n.\r\n");
            $sendResponse = $this->readSMTPResponse($smtp);
            
            // QUIT
            fwrite($smtp, "QUIT\r\n");
            fclose($smtp);
            
            if (strpos($sendResponse, '250') !== false) {
                error_log("Email enviado com sucesso para: $to");
                return true;
            } else {
                error_log("Falha ao enviar email: $sendResponse");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Erro no envio SMTP: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lê resposta do servidor SMTP
     */
    private function readSMTPResponse($smtp) {
        $response = '';
        while (($line = fgets($smtp, 515)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    }
    
    /**
     * Envia email usando método apropriado
     */
    public function sendEmail($to, $subject, $message, $isHTML = true) {
        // Tentar SMTP primeiro
        if ($this->sendEmailSMTP($to, $subject, $message, $isHTML)) {
            return true;
        }
        
        // Fallback para mail() nativo
        error_log("SMTP falhou, tentando mail() nativo");
        return $this->sendEmailNative($to, $subject, $message, $isHTML);
    }
    
    /**
     * Envia email usando PHP nativo (fallback)
     */
    public function sendEmailNative($to, $subject, $message, $isHTML = true) {
        // Configurar ini para SMTP (XAMPP)
        ini_set('SMTP', $this->host);
        ini_set('smtp_port', $this->port);
        ini_set('sendmail_from', $this->fromEmail);
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: ' . ($isHTML ? 'text/html' : 'text/plain') . '; charset=UTF-8',
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To: ' . $this->fromEmail,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $result = mail($to, $subject, $message, implode("\r\n", $headers));
        
        if ($result) {
            error_log("Email enviado com sucesso para: $to");
        } else {
            error_log("Falha ao enviar email para: $to");
        }
        
        return $result;
    }
    
    /**
     * Envia email de confirmação de cadastro
     */
    public function sendConfirmationEmail($to, $name, $token) {
        $confirmUrl = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/confirmar_email.php?token=" . $token;
        
        $subject = "Confirme seu cadastro - COPASA";
        
        $message = $this->getConfirmationEmailTemplate($name, $confirmUrl);
        
        return $this->sendEmail($to, $subject, $message, true);
    }
    
    /**
     * Envia email de recuperação de senha
     */
    public function sendPasswordResetEmail($to, $name, $token) {
        $resetUrl = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/nova_senha.php?token=" . $token;
        
        $subject = "Redefinir Senha - COPASA";
        
        $message = $this->getPasswordResetEmailTemplate($name, $resetUrl);
        
        return $this->sendEmail($to, $subject, $message, true);
    }
    
    /**
     * Template HTML para email de confirmação
     */
    private function getConfirmationEmailTemplate($name, $confirmUrl) {
        return "
        <!DOCTYPE html>
        <html lang='pt-br'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Confirmação de Cadastro</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    background: linear-gradient(135deg, #0a1929 0%, #1a237e 50%, #0a1929 100%);
                    margin: 0; 
                    padding: 20px; 
                    color: #333;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white; 
                    border-radius: 15px; 
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    overflow: hidden;
                }
                .header { 
                    background: linear-gradient(135deg, #00bcd4 0%, #006064 100%); 
                    color: white; 
                    padding: 40px 30px; 
                    text-align: center; 
                }
                .header h1 { 
                    margin: 0; 
                    font-size: 28px; 
                    font-weight: 700;
                }
                .header p { 
                    margin: 10px 0 0 0; 
                    font-size: 16px; 
                    opacity: 0.9;
                }
                .content { 
                    padding: 40px 30px; 
                    color: #333; 
                    line-height: 1.6; 
                }
                .greeting {
                    font-size: 18px;
                    margin-bottom: 20px;
                }
                .button { 
                    display: inline-block; 
                    background: linear-gradient(135deg, #00bcd4 0%, #006064 100%);
                    color: white; 
                    padding: 15px 30px; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    margin: 20px 0; 
                    font-weight: 600;
                    font-size: 16px;
                }
                .button:hover {
                    background: linear-gradient(135deg, #006064 0%, #004d40 100%);
                }
                .highlight {
                    background: linear-gradient(135deg, #e3f2fd 0%, #f8f9fa 100%);
                    border: 1px solid #e1e8ff;
                    padding: 20px; 
                    border-radius: 12px; 
                    margin: 25px 0; 
                    border-left: 5px solid #00bcd4;
                }
                .highlight h3 {
                    margin: 0 0 15px 0;
                    color: #006064;
                    font-size: 18px;
                    font-weight: 600;
                }
                .footer { 
                    background: #f8f9fa; 
                    padding: 30px; 
                    text-align: center; 
                    color: #6c757d; 
                    font-size: 14px; 
                    border-top: 1px solid #e9ecef;
                }
                .footer p {
                    margin: 5px 0;
                }
                .link-text {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 8px;
                    margin: 15px 0;
                    word-break: break-all;
                    font-family: monospace;
                    font-size: 12px;
                    color: #6c757d;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>💧 COPASA</h1>
                    <p>Sistema de Obras de Saneamento</p>
                </div>
                <div class='content'>
                    <div class='greeting'>
                        <p>Olá <strong>$name</strong>,</p>
                        <p>Obrigado por se cadastrar em nosso sistema!</p>
                    </div>
                    
                    <p>Para ativar sua conta e começar a usar todas as funcionalidades, clique no botão abaixo:</p>
                    
                    <div style='text-align: center;'>
                        <a href='$confirmUrl' class='button'>✅ Confirmar Cadastro</a>
                    </div>
                    
                    <div class='highlight'>
                        <h3>📋 Informações importantes:</h3>
                        <ul>
                            <li>Este link é válido por 24 horas</li>
                            <li>Após a confirmação, você poderá fazer login normalmente</li>
                            <li>Se você não solicitou este cadastro, ignore este email</li>
                        </ul>
                    </div>
                    
                    
                </div>
                <div class='footer'>
                    <p><strong>COPASA</strong> - Sistema de Obras de Saneamento</p>
                    <p>Este é um email automático, por favor não responda.</p>
                    <p>© " . date('Y') . " Todos os direitos reservados</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Template HTML para email de recuperação de senha
     */
    private function getPasswordResetEmailTemplate($name, $resetUrl) {
        return "
        <!DOCTYPE html>
        <html lang='pt-br'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Recuperação de Senha</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    background: linear-gradient(135deg, #0a1929 0%, #1a237e 50%, #0a1929 100%);
                    margin: 0; 
                    padding: 20px; 
                    min-height: 100vh;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white; 
                    border-radius: 15px; 
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    overflow: hidden;
                }
                .header { 
                    background: linear-gradient(135deg, #00bcd4 0%, #006064 100%); 
                    color: white; 
                    padding: 40px 30px; 
                    text-align: center; 
                }
                .header h1 { 
                    margin: 0; 
                    font-size: 28px; 
                    font-weight: 700;
                }
                .header p { 
                    margin: 10px 0 0 0; 
                    font-size: 16px; 
                    opacity: 0.9;
                }
                .content { 
                    padding: 40px 30px; 
                    color: #333; 
                    line-height: 1.6; 
                }
                .greeting {
                    font-size: 18px;
                    margin-bottom: 20px;
                }
                .reset-section { 
                    background: linear-gradient(135deg, #e3f2fd 0%, #f8f9fa 100%);
                    border: 1px solid #e1e8ff;
                    padding: 25px; 
                    border-radius: 12px; 
                    margin: 25px 0; 
                    border-left: 5px solid #00bcd4;
                    text-align: center;
                }
                .reset-section h3 {
                    margin: 0 0 15px 0;
                    color: #006064;
                    font-size: 18px;
                    font-weight: 600;
                }
                .button { 
                    display: inline-block; 
                    background: linear-gradient(135deg, #00bcd4 0%, #006064 100%);
                    color: white; 
                    padding: 15px 30px; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    margin: 15px 0; 
                    font-weight: 600;
                    font-size: 16px;
                }
                .button:hover {
                    background: linear-gradient(135deg, #006064 0%, #004d40 100%);
                }
                .warning { 
                    background: linear-gradient(135deg, #fff8e1 0%, #fff3cd 100%);
                    border: 1px solid #ffeaa7;
                    padding: 25px; 
                    border-radius: 12px; 
                    margin: 25px 0; 
                    border-left: 5px solid #f59e0b;
                }
                .warning h4 {
                    margin: 0 0 10px 0;
                    color: #f59e0b;
                    font-size: 16px;
                    font-weight: 600;
                }
                .warning p {
                    margin: 0;
                    color: #92400e;
                }
                .footer { 
                    background: #f8f9fa; 
                    padding: 30px; 
                    text-align: center; 
                    color: #6c757d; 
                    font-size: 14px; 
                    border-top: 1px solid #e9ecef;
                }
                .footer p {
                    margin: 5px 0;
                }
                .link-text {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 8px;
                    margin: 15px 0;
                    word-break: break-all;
                    font-family: monospace;
                    font-size: 12px;
                    color: #6c757d;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>💧 COPASA</h1>
                    <p>Recuperação de Senha</p>
                </div>
                <div class='content'>
                    <div class='greeting'>
                        <p>Olá <strong>$name</strong>,</p>
                        <p>Recebemos uma solicitação para redefinir a senha da sua conta.</p>
                    </div>
                    
                    <div class='reset-section'>
                        <h3>🔐 Redefinir Senha</h3>
                        <p>Clique no botão abaixo para criar uma nova senha:</p>
                        <a href='$resetUrl' class='button'>Redefinir Senha</a>
                        <p style='margin-top: 15px; font-size: 14px; color: #6c757d;'>
                            Este link é válido por 1 hora.
                        </p>
                    </div>
                    
                    <div class='warning'>
                        <h4>⚠️ Importante:</h4>
                        <p>Se você não solicitou esta recuperação de senha, ignore este email. Sua conta permanecerá segura.</p>
                    </div>
                    
                    
                </div>
                <div class='footer'>
                    <p><strong>COPASA</strong> - Sistema de Obras de Saneamento</p>
                    <p>Este é um email automático, não responda.</p>
                    <p>© " . date('Y') . " Todos os direitos reservados</p>
                </div>
            </div>
        </body>
        </html>";
    }
}

// Funções helper para facilitar o uso
function sendConfirmationEmail($email, $name, $token) {
    $emailSender = new EmailSender();
    return $emailSender->sendConfirmationEmail($email, $name, $token);
}

function sendPasswordResetEmail($email, $name, $token) {
    $emailSender = new EmailSender();
    return $emailSender->sendPasswordResetEmail($email, $name, $token);
}

?>
