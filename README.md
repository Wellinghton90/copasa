# Sistema de Login COPASA - Obras de Saneamento

Sistema de login moderno e futurista desenvolvido para gerenciamento de obras de saneamento da COPASA.

## 🚀 Características

- **Design Moderno e Futurista**: Interface com tema de saneamento e visual atrativo
- **Autenticação Segura**: Sistema completo de login com validação
- **Confirmação por Email**: Usuários devem confirmar email antes de fazer login
- **Recuperação de Senha**: Sistema completo de reset de senha via email
- **Dashboard Responsivo**: Painel administrativo com estatísticas e informações
- **Validação em Tempo Real**: Feedback visual imediato para o usuário
- **Animações Fluidas**: Efeitos visuais e transições suaves

## 📋 Funcionalidades

### 🔐 Sistema de Autenticação
- Login com email ou nome de usuário
- Cadastro de novos usuários
- Confirmação obrigatória por email
- Recuperação de senha via email
- Controle de sessão seguro

### 👤 Gerenciamento de Usuários
- Cadastro com validação de dados
- Confirmação automática de email
- Status de usuário (confirmado/habilitado)
- Controle de administradores
- Log de último acesso

### 🎨 Interface
- Design responsivo para todos os dispositivos
- Tema futurista com cores aquáticas
- Animações e efeitos visuais
- Feedback visual em tempo real
- Navegação intuitiva

## 🛠️ Instalação

### 1. Pré-requisitos
- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Servidor web (Apache/Nginx)
- Extensão PHP PDO habilitada
- Configuração de email SMTP

### 2. Configuração do Banco de Dados

1. Execute o script SQL para atualizar sua tabela:
```sql
-- Execute o arquivo update_database.sql no seu banco de dados
```

2. Verifique se sua tabela `usuarios` possui as seguintes colunas:
- `id` (int, auto_increment, primary key)
- `nome` (varchar)
- `login` (varchar)
- `email` (varchar)
- `senha` (varchar)
- `data_cadastro` (datetime)
- `pass` (varchar)
- `confirmado` (tinyint)
- `habilitado` (tinyint)
- `admin` (tinyint)
- `ult_alteracao` (datetime)
- `ultimo_login` (datetime)
- `confirmation_token` (varchar, nullable)
- `reset_token` (varchar, nullable)
- `reset_expires` (datetime, nullable)

### 3. Configuração de Email

Edite o arquivo `email_config.php` com suas configurações SMTP:

```php
define('SMTP_HOST', 'seu-servidor-smtp.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'seu-email@dominio.com');
define('SMTP_PASS', 'sua-senha-email');
define('SMTP_SECURE', 'ssl');
define('FROM_EMAIL', 'seu-email@dominio.com');
define('FROM_NAME', 'Nome do Sistema');
```

### 4. Configuração de Conexão

Verifique o arquivo `connection.php` com seus dados de conexão:

```php
$host = "localhost";
$user = "seu-usuario";
$password = "sua-senha";
$database = "seu-banco";
```

## 📁 Estrutura de Arquivos

```
copasa/
├── index.php              # Página principal de login
├── cadastro.php           # Página de cadastro
├── esqueci_senha.php      # Recuperação de senha
├── auth.php               # Sistema de autenticação
├── confirmar_email.php    # Confirmação de email
├── nova_senha.php         # Redefinição de senha
├── dashboard.php          # Painel administrativo
├── connection.php         # Conexão com banco
├── email_config.php       # Configurações de email
├── update_database.sql    # Script de atualização do banco
└── README.md             # Este arquivo
```

## 🎯 Como Usar

### 1. Primeiro Acesso
1. Acesse `index.php` no seu navegador
2. Clique em "Criar Conta"
3. Preencha os dados de cadastro
4. Verifique seu email e clique no link de confirmação
5. Faça login com suas credenciais

### 2. Recuperação de Senha
1. Na página de login, clique em "Esqueci a Senha"
2. Digite seu email ou login
3. Verifique seu email e clique no link de recuperação
4. Defina uma nova senha
5. Faça login com a nova senha

### 3. Dashboard
Após o login, você será redirecionado para o dashboard com:
- Estatísticas dos projetos
- Informações do usuário
- Navegação para diferentes seções
- Opção de logout

## 🔧 Personalização

### Cores e Tema
Edite as variáveis CSS no início de cada arquivo para personalizar:
```css
:root {
    --primary-color: #00bcd4;
    --secondary-color: #006064;
    --accent-color: #26c6da;
    /* ... outras variáveis */
}
```

### Logo e Textos
Modifique os textos e ícones nos arquivos HTML conforme necessário.

### Configurações de Email
Personalize os templates de email nos arquivos `auth.php`.

## 🔒 Segurança

- Senhas são criptografadas com `password_hash()`
- Tokens seguros para confirmação e recuperação
- Validação de entrada em todos os formulários
- Proteção contra SQL Injection com PDO
- Sessões seguras
- Links de recuperação com expiração

## 🐛 Solução de Problemas

### Email não está sendo enviado
1. Verifique as configurações SMTP
2. Confirme se o servidor suporta envio de emails
3. Verifique logs de erro do PHP

### Erro de conexão com banco
1. Confirme dados de conexão em `connection.php`
2. Verifique se o MySQL está rodando
3. Confirme se o usuário tem permissões

### Página não carrega
1. Verifique se o PHP está configurado
2. Confirme permissões de arquivo
3. Verifique logs de erro do servidor

## 📞 Suporte

Para suporte técnico ou dúvidas sobre implementação, consulte:
- Logs de erro do PHP
- Logs do servidor web
- Documentação oficial do PHP e MySQL

## 📄 Licença

Este projeto foi desenvolvido para uso interno da COPASA. Todos os direitos reservados.

---

**Desenvolvido com ❤️ para COPASA - Sistema de Obras de Saneamento**
