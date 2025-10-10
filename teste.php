<?php
/**
 * Classe para Envio de Emails
 * Sistema de Prefeituras
 */

require_once 'config.php';

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
        if (!MAIL_ENABLED) {
            writeLog("Envio de email desabilitado", 'INFO');
            return false;
        }
        
        if (MAIL_DEBUG) {
            writeLog("Enviando email para: $to | Assunto: $subject", 'DEBUG');
        }
        
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
                writeLog("Erro ao conectar SMTP: $errstr ($errno)", 'ERROR');
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
                writeLog("Falha na autenticação SMTP: $authResponse", 'ERROR');
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
                writeLog("Email enviado com sucesso para: $to", 'INFO');
                return true;
            } else {
                writeLog("Falha ao enviar email: $sendResponse", 'ERROR');
                return false;
            }
            
        } catch (Exception $e) {
            writeLog("Erro no envio SMTP: " . $e->getMessage(), 'ERROR');
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
        
        // Fallback para mail() nativo (pode não funcionar em XAMPP)
        writeLog("SMTP falhou, tentando mail() nativo", 'WARNING');
        return $this->sendEmailNative($to, $subject, $message, $isHTML);
    }
    
    /**
     * Envia email usando PHP nativo (fallback)
     */
    public function sendEmailNative($to, $subject, $message, $isHTML = true) {
        if (!MAIL_ENABLED) {
            writeLog("Envio de email desabilitado", 'INFO');
            return false;
        }
        
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
        
        if (MAIL_DEBUG) {
            writeLog("Enviando email nativo para: $to | Assunto: $subject", 'DEBUG');
        }
        
        $result = mail($to, $subject, $message, implode("\r\n", $headers));
        
        if ($result) {
            writeLog("Email enviado com sucesso para: $to", 'INFO');
        } else {
            writeLog("Falha ao enviar email para: $to", 'ERROR');
        }
        
        return $result;
    }
    
    /**
     * Envia email de confirmação de cadastro
     */
    public function sendConfirmationEmail($to, $name, $token) {
        $confirmUrl = SITE_URL . "/confirmar.php?token=" . $token;
        
        $subject = "Confirmação de Cadastro - " . SITE_NAME;
        
        $message = $this->getConfirmationEmailTemplate($name, $confirmUrl);
        
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
                    background-color: #f4f4f4; 
                    margin: 0; 
                    padding: 20px; 
                    color: #333;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white; 
                    padding: 30px; 
                    border-radius: 10px; 
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
                }
                .header { 
                    text-align: center; 
                    color: #333; 
                    border-bottom: 2px solid #6366f1; 
                    padding-bottom: 20px; 
                    margin-bottom: 30px; 
                }
                .content { 
                    color: #555; 
                    line-height: 1.6; 
                }
                .button { 
                    display: inline-block; 
                    background:rgb(99, 184, 241); 
                    color: black; 
                    padding: 12px 30px; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    margin: 20px 0; 
                    font-weight: bold;
                }
                .button:hover {
                    background: rgb(70, 135, 179); 
                }
                .footer { 
                    margin-top: 30px; 
                    padding-top: 20px; 
                    border-top: 1px solid #eee; 
                    color: #888; 
                    font-size: 12px; 
                    text-align: center; 
                }
                .highlight {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 5px;
                    border-left: 4px solid #6366f1;
                    margin: 15px 0;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🏛️ " . SITE_NAME . "</h1>
                    <p>Sistema Cartográfico de Prefeituras</p>
                </div>
                <div class='content'>
                    <p>Olá <strong>$name</strong>,</p>
                    <p>Obrigado por se cadastrar em nosso sistema!</p>
                    <p>Para ativar sua conta e começar a usar todas as funcionalidades, clique no botão abaixo:</p>
                    
                    <div style='text-align: center;'>
                        <a href='$confirmUrl' class='button'>✅ Confirmar Cadastro</a>
                    </div>
                    
                    <div class='highlight'>
                        <p><strong>📋 Informações importantes:</strong></p>
                        <ul>
                            <li>Este link é válido por 24 horas</li>
                            <li>Após a confirmação, você poderá fazer login normalmente</li>
                            <li>Se você não solicitou este cadastro, ignore este email</li>
                        </ul>
                    </div>
                    
                    <p><strong>🔗 Link alternativo:</strong> Se o botão não funcionar, copie e cole este link no seu navegador:</p>
                    <p style='word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace;'>$confirmUrl</p>
                </div>
                <div class='footer'>
                    <p>Este é um email automático, por favor não responda.</p>
                    <p>" . SITE_NAME . " - Sistema de Gestão Municipal</p>
                    <p>© " . date('Y') . " Todos os direitos reservados</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    
    /**
     * Envia email de recuperação de senha
     */
    public function sendPasswordResetEmail($to, $name, $token) {
        if (!MAIL_ENABLED) {
            writeLog("Envio de email desabilitado", 'INFO');
            return false;
        }
        
        $resetUrl = SITE_URL . "/reset_password.php?token=" . $token;
        $subject = "Recuperação de Senha - " . SITE_NAME;
        
        $message = "
        <!DOCTYPE html>
        <html lang='pt-br'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Recuperação de Senha</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    margin: 0; 
                    padding: 20px; 
                    min-height: 100vh;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white; 
                    border-radius: 15px; 
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    overflow: hidden;
                }
                .header { 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                    color: white; 
                    padding: 40px 30px; 
                    text-align: center; 
                    position: relative;
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
                    background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
                    border: 1px solid #e1e8ff;
                    padding: 25px; 
                    border-radius: 12px; 
                    margin: 25px 0; 
                    border-left: 5px solid #6366f1;
                    text-align: center;
                }
                .reset-section h3 {
                    margin: 0 0 15px 0;
                    color: #6366f1;
                    font-size: 18px;
                    font-weight: 600;
                }
                .button { 
                    display: inline-block; 
                    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
                    color: white; 
                    padding: 15px 30px; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    margin: 15px 0; 
                    font-weight: 600;
                    font-size: 16px;
                }
                .button:hover {
                    background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
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
                    <h1>" . SITE_NAME . "</h1>
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
                            Este link é válido por 24 horas.
                        </p>
                    </div>
                    
                    <div class='warning'>
                        <h4>⚠️ Importante:</h4>
                        <p>Por motivos de segurança, sua conta foi temporariamente desabilitada. Após redefinir a senha, você precisará confirmar sua conta novamente através do email.</p>
                    </div>
                    
                    <p>Se você não solicitou esta recuperação de senha, ignore este email. Sua conta permanecerá segura.</p>
                    
                    <div class='link-text'>
                        <strong>Link direto:</strong><br>
                        $resetUrl
                    </div>
                </div>
                <div class='footer'>
                    <p><strong>" . SITE_NAME . "</strong> - Sistema de Gestão</p>
                    <p>Este é um email automático, não responda.</p>
                    <p>© " . date('Y') . " Todos os direitos reservados</p>
                </div>
            </div>
        </body>
        </html>";
        
        return $this->sendEmail($to, $subject, $message);
    }
    
    /**
     * Obtém informações de configuração
     */
    public function getConfigurationInfo() {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'secure' => $this->secure,
            'from_email' => $this->fromEmail,
            'from_name' => $this->fromName,
            'enabled' => MAIL_ENABLED,
            'debug' => MAIL_DEBUG
        ];
    }
}

// Função helper para facilitar o uso
function sendConfirmationEmail($email, $name, $token) {
    $emailSender = new EmailSender();
    return $emailSender->sendConfirmationEmail($email, $name, $token);
}

?>
