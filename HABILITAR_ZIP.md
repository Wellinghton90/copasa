# Como Habilitar a Extensão ZIP no XAMPP

## Passo 1: Localizar o arquivo php.ini

1. Abra o **XAMPP Control Panel**
2. Clique em **Config** ao lado de Apache
3. Selecione **PHP (php.ini)**

## Passo 2: Editar o php.ini

1. Procure pela linha (use Ctrl+F):
   ```
   ;extension=zip
   ```

2. Remova o ponto e vírgula (;) do início da linha:
   ```
   extension=zip
   ```

3. Salve o arquivo

## Passo 3: Reiniciar o Apache

1. No XAMPP Control Panel, clique em **Stop** para o Apache
2. Aguarde alguns segundos
3. Clique em **Start** para reiniciar o Apache

## Passo 4: Verificar

1. Acesse: `http://localhost/copasa/teste_zip.php`
2. Você deve ver a mensagem: "✓ Extensão ZipArchive está HABILITADA!"

---

## Observações

- **Método Alternativo**: O sistema já possui um método alternativo que cria arquivos ZIP manualmente, então mesmo sem a extensão habilitada, o download deve funcionar.
- **Performance**: Com a extensão habilitada, o processo é mais rápido e eficiente.
- **XAMPP Moderno**: Versões recentes do XAMPP já vêm com a extensão ZIP habilitada por padrão.

---

## Solução de Problemas

### Se ainda não funcionar:

1. Verifique se o arquivo `php_zip.dll` existe em:
   ```
   C:\xampp\php\ext\php_zip.dll
   ```

2. Se não existir, você pode precisar reinstalar o XAMPP ou atualizar para uma versão mais recente.

3. Verifique se não há outro php.ini sendo usado:
   - Execute `phpinfo()` e procure por "Loaded Configuration File"
   - Edite o arquivo correto

### Testando manualmente:

Execute o arquivo `teste_zip.php` para ver todas as extensões carregadas e verificar se o ZIP está entre elas.

