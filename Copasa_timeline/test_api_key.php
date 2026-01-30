<?php
/**
 * Script de teste para verificar se a chave do Google Maps está sendo carregada
 * Acesse este arquivo no navegador para verificar
 */

require_once __DIR__ . '/config/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teste - Chave Google Maps</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Teste de Configuração - Chave Google Maps</h1>
    
    <div class="info">
        <h2>Status da Chave:</h2>
        <?php if (defined('GOOGLE_MAPS_API_KEY')): ?>
            <?php if (!empty(GOOGLE_MAPS_API_KEY)): ?>
                <p class="success">✓ Chave definida e não está vazia</p>
                <p><strong>Chave (primeiros 20 caracteres):</strong> <?php echo htmlspecialchars(substr(GOOGLE_MAPS_API_KEY, 0, 20)); ?>...</p>
                <p><strong>Tamanho da chave:</strong> <?php echo strlen(GOOGLE_MAPS_API_KEY); ?> caracteres</p>
            <?php else: ?>
                <p class="error">✗ Chave definida mas está VAZIA</p>
                <p>Verifique o arquivo <code>config/api_keys.local.php</code></p>
            <?php endif; ?>
        <?php else: ?>
            <p class="error">✗ Constante GOOGLE_MAPS_API_KEY NÃO está definida</p>
            <p>Verifique se o arquivo <code>config/api_keys.local.php</code> existe e está sendo carregado</p>
        <?php endif; ?>
    </div>
    
    <div class="info">
        <h2>Informações do Sistema:</h2>
        <p><strong>Arquivo de configuração local:</strong> 
            <?php 
            $apiKeysPath = __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'api_keys.local.php';
            echo $apiKeysPath;
            ?>
        </p>
        <p><strong>Arquivo existe?</strong> <?php echo file_exists($apiKeysPath) ? 'SIM' : 'NÃO'; ?></p>
        <p><strong>Arquivo é legível?</strong> <?php echo file_exists($apiKeysPath) && is_readable($apiKeysPath) ? 'SIM' : 'NÃO'; ?></p>
    </div>
    
    <div class="info">
        <h2>Teste de URL do Google Maps:</h2>
        <?php if (defined('GOOGLE_MAPS_API_KEY') && !empty(GOOGLE_MAPS_API_KEY)): ?>
            <p>URL de teste com a chave:</p>
            <code style="background: #e0e0e0; padding: 5px; display: block; margin: 10px 0;">
                https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode(GOOGLE_MAPS_API_KEY); ?>
            </code>
            <p><a href="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode(GOOGLE_MAPS_API_KEY); ?>" target="_blank">Testar URL no navegador</a></p>
        <?php else: ?>
            <p class="error">Não é possível gerar URL de teste sem a chave configurada</p>
        <?php endif; ?>
    </div>
    
    <div class="info">
        <h2>Próximos Passos:</h2>
        <ol>
            <li>Se a chave não está definida, verifique o arquivo <code>config/api_keys.local.php</code></li>
            <li>Certifique-se de que a chave está entre aspas: <code>define('GOOGLE_MAPS_API_KEY', 'SUA_CHAVE_AQUI');</code></li>
            <li>Teste acessando um projeto no <a href="index.php">index.php</a></li>
            <li>Abra o console do navegador (F12) e verifique as mensagens de debug</li>
        </ol>
    </div>
</body>
</html>
