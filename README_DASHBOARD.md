# Dashboard COPASA - Sistema de Obras

Dashboard moderno e futurista para gestão de obras de saneamento com DataTables.

## 🚀 Funcionalidades Implementadas

### 📊 **Dashboard com DataTables**
- Tabela interativa com paginação, busca e ordenação
- Estatísticas em tempo real
- Design futurista mantido
- Responsivo para todos os dispositivos

### 🔍 **Sistema de Busca e Filtros**
- Busca global em todas as colunas
- Filtros por status e situação
- Ordenação por qualquer coluna
- Paginação configurável

### 📈 **Estatísticas Dinâmicas**
- Total de obras
- Obras em execução
- Obras concluídas
- Obras atrasadas
- Atualização automática

## 🛠️ Instalação

### 1. **Criar Tabela de Obras**
Execute o arquivo `create_obras_table.sql` no seu banco de dados MySQL:

```sql
-- O script criará a tabela 'obras' com dados de exemplo
-- Execute no phpMyAdmin ou MySQL Workbench
```

### 2. **Arquivos Necessários**
Certifique-se de que todos os arquivos estão presentes:
- `dashboard.php` - Dashboard principal
- `get_obras.php` - API para buscar dados das obras
- `get_obras_stats.php` - API para estatísticas
- `create_obras_table.sql` - Script de criação da tabela

### 3. **Verificar Conexão**
Confirme que o arquivo `connection.php` está configurado corretamente.

## 📋 Estrutura da Tabela Obras

```sql
CREATE TABLE obras (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    localizacao VARCHAR(500),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    cidade VARCHAR(100) NOT NULL,
    uf CHAR(2) NOT NULL,
    status ENUM('planejamento', 'execucao', 'concluida', 'suspensa', 'cancelada'),
    situacao ENUM('normal', 'atrasada', 'emergencia', 'prioritaria'),
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- Campos adicionais para futuras funcionalidades
    data_inicio DATE,
    data_prevista DATE,
    data_conclusao DATE,
    orcamento_total DECIMAL(15, 2),
    orcamento_utilizado DECIMAL(15, 2),
    responsavel VARCHAR(255),
    observacoes TEXT
);
```

## 🎨 Características Visuais

### **Design Futurista Mantido**
- Tema aquático com cores azul/ciano
- Glassmorphism e efeitos de blur
- Animações fluidas
- Gradientes modernos

### **Tabela Responsiva**
- Scroll horizontal em dispositivos móveis
- Colunas adaptáveis
- Botões de ação otimizados

### **Badges Coloridos**
- Status: Planejamento, Execução, Concluída, Suspensa, Cancelada
- Situação: Normal, Atrasada, Emergência, Prioritária

## 🔧 Funcionalidades da Tabela

### **Busca Inteligente**
- Busca em tempo real
- Filtros por múltiplas colunas
- Highlight dos resultados

### **Ordenação**
- Clique no cabeçalho para ordenar
- Múltiplas colunas
- Indicadores visuais

### **Paginação**
- 10, 25, 50 ou todos os registros
- Navegação por páginas
- Informações de registros

### **Ações por Linha**
- 👁️ Ver detalhes
- ✏️ Editar obra
- 🗑️ Excluir obra

## 📱 Responsividade

### **Desktop**
- Tabela completa com todas as colunas
- Estatísticas em grid 4 colunas
- Ações completas

### **Tablet**
- Tabela adaptada
- Estatísticas em grid 2 colunas
- Botões otimizados

### **Mobile**
- Scroll horizontal
- Estatísticas em coluna única
- Ações simplificadas

## 🚀 Próximas Funcionalidades

### **CRUD Completo** (Em desenvolvimento)
- ✅ Visualizar obras
- 🔄 Adicionar nova obra
- 🔄 Editar obra existente
- 🔄 Excluir obra
- 🔄 Upload de documentos

### **Relatórios Avançados**
- 🔄 Exportação para Excel/PDF
- 🔄 Gráficos e dashboards
- 🔄 Relatórios personalizados

### **Integração com Mapas**
- 🔄 Visualização em mapa
- 🔄 Geolocalização
- 🔄 Roteamento

## 🐛 Solução de Problemas

### **Tabela não carrega**
1. Verifique se a tabela `obras` existe
2. Confirme a conexão com o banco
3. Verifique logs de erro do PHP

### **Estatísticas não aparecem**
1. Verifique se `get_obras_stats.php` está acessível
2. Confirme permissões do usuário
3. Verifique dados na tabela

### **Busca não funciona**
1. Verifique se JavaScript está habilitado
2. Confirme se jQuery está carregando
3. Verifique console do navegador

## 📞 Suporte

Para problemas técnicos:
1. Verifique logs de erro do PHP
2. Confirme configurações do banco
3. Teste em diferentes navegadores

---

**Desenvolvido com ❤️ para COPASA - Sistema de Obras de Saneamento**
