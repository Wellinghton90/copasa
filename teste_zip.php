<?php
echo "<h2>Teste de Extensão ZIP</h2>";

if (class_exists('ZipArchive')) {
    echo "<p style='color: green;'>✓ Extensão ZipArchive está HABILITADA!</p>";
    
    $zip = new ZipArchive();
    echo "<p>Versão do PHP: " . phpversion() . "</p>";
    echo "<p>ZipArchive disponível: SIM</p>";
} else {
    echo "<p style='color: red;'>✗ Extensão ZipArchive NÃO está habilitada!</p>";
    echo "<p>Para habilitar:</p>";
    echo "<ol>";
    echo "<li>Abra o arquivo php.ini</li>";
    echo "<li>Procure por: ;extension=zip</li>";
    echo "<li>Remova o ponto e vírgula: extension=zip</li>";
    echo "<li>Reinicie o Apache</li>";
    echo "</ol>";
}

echo "<hr>";
echo "<h3>Extensões carregadas:</h3>";
echo "<pre>";
print_r(get_loaded_extensions());
echo "</pre>";
?>

