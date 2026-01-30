# Visualizador Timeline - Processamentos Pix4D

Sistema web para visualização da evolução temporal dos processamentos Pix4D Mapper, permitindo navegar entre diferentes datas de processamento do mesmo local através de um slider interativo.

## 📋 Descrição

Este projeto permite visualizar a evolução dos processamentos Pix4D de uma área específica (Juatuba) ao longo do tempo. Os projetos são identificados pelo formato de nome `Juatuba_DD-MM_YY` e seus mosaicos HTML são exibidos em uma interface de timeline/slider.

## 🏗️ Estrutura do Projeto

```
Copasa_timeline/
├── config/
│   ├── config.php                  # Configurações centralizadas
│   ├── api_keys.local.php          # Chave do Google Maps (não versionado)
│   └── api_keys.example.php        # Exemplo de configuração
├── api/
│   ├── get_projects.php            # Endpoint RESTful - lista projetos
│   └── view_project.php            # Proxy seguro - serve HTML e recursos
├── assets/
│   ├── css/
│   │   ├── variables.css           # Variáveis CSS
│   │   └── style.css                # Estilos principais
│   └── js/
│       ├── api.js                   # Cliente API
│       ├── utils.js                 # Funções utilitárias
│       └── slider.js                # Classe do slider
├── index.php                        # Página principal
├── generate_projects_json.py        # Script Python para gerar JSON de projetos
├── data/
│   └── projects.json                # JSON com lista de projetos (gerado)
├── test_api_key.php                 # Script de teste da chave Google Maps
├── .htaccess                        # Configurações Apache
├── .gitignore                       # Arquivos ignorados pelo Git
└── README.md                        # Este arquivo
```

### Arquivos Principais

#### `index.php`
- Página principal do sistema
- Carrega o `TimelineSlider` via JavaScript
- Exibe o iframe onde os mosaicos são renderizados

#### `api/get_projects.php`
- Endpoint que retorna lista de projetos em JSON
- Lê projetos do arquivo `data/projects.json` (gerado pelo script Python)
- Não escaneia mais dinamicamente - use `generate_projects_json.py` para atualizar

#### `api/view_project.php` ⚠️ **ARQUIVO CRÍTICO**
- **Proxy seguro** que serve arquivos HTML dos projetos
- **Injeta chave do Google Maps** substituindo `sensor=false`
- **Ajusta caminhos relativos** para usar o próprio endpoint
- **Intercepta recursos** (imagens, CSS, JS) e os serve através do proxy
- **Injeta scripts JavaScript** para garantir carregamento correto do Google Maps
- **Valida caminhos** para prevenir path traversal attacks

## 🚀 Instalação

### Requisitos

- PHP 7.4 ou superior
- Python 3.6 ou superior (para gerar JSON de projetos)
- Servidor web (Apache recomendado)
- Acesso à pasta de projetos: `D:\Xampp_novo\htdocs\copasa\projetos\Juatuba`

### Configuração

1. **Clone ou copie os arquivos** para o diretório do servidor web

2. **Configure o caminho base** em `config/config.php`:
   ```php
   define('BASE_PATH', 'D:\\Xampp_novo\\htdocs\\copasa\\projetos\\Juatuba');
   ```
   **IMPORTANTE**: Configure o mesmo caminho em `generate_projects_json.py`:
   ```python
   BASE_PATH = r'D:\Xampp_novo\htdocs\copasa\projetos\Juatuba'
   ```

3. **Gere o arquivo JSON de projetos**:
   ```bash
   python generate_projects_json.py
   ```
   Isso criará o arquivo `data/projects.json` com todos os projetos encontrados.

4. **Atualize o JSON sempre que adicionar novos projetos**:
   - Execute `python generate_projects_json.py` novamente
   - O script é flexível e aceita qualquer padrão de nome de pasta

5. **Verifique permissões**:
   - Pasta de projetos (leitura necessária)
   - Pasta `data/` (escrita necessária para gerar o JSON)

6. **Acesse a aplicação** através do navegador:
   ```
   http://localhost/copasa/
   ```

## 📁 Formato dos Projetos

Os projetos devem seguir o padrão de nomenclatura:

- **Formato**: `Juatuba_DD-MM_YY`
- **Exemplo**: `Juatuba_02-12_25` (02 de Dezembro de 2025)

### Estrutura Interna

Cada projeto deve conter o arquivo HTML do mosaico em:
```
[NomeProjeto]/
└── 3_dsm_ortho/
    └── 2_mosaic/
        └── google_tiles/
            └── [NomeProjeto]_mosaic.html
```

**Exemplo completo**:
```
Juatuba_02-12_25/
└── 3_dsm_ortho/
    └── 2_mosaic/
        └── google_tiles/
            └── Juatuba_02-12_mosaic.html
```

## 🎯 Funcionalidades

- ✅ Escaneamento automático de projetos
- ✅ Ordenação cronológica (mais antigo → mais recente)
- ✅ Navegação via botões (Anterior/Próximo)
- ✅ Navegação via teclado (setas ← →)
- ✅ Exibição de data formatada
- ✅ Contador de projetos (ex: "1 de 5")
- ✅ Interface responsiva (mobile, tablet, desktop)
- ✅ Loading states e tratamento de erros
- ✅ Acessibilidade (ARIA, navegação por teclado)

## 🛠️ Tecnologias

- **Backend**: PHP 7.4+ (PSR-12, PSR-4)
- **Frontend**: JavaScript ES6+ (Classes, Async/Await)
- **Estilos**: CSS3 (BEM, Mobile-First, CSS Variables)
- **Servidor**: Apache (com mod_rewrite)

## 📝 Padrões de Código

### PHP
- PSR-12 para formatação
- PSR-4 para autoloading
- Type hints e return types
- PHPDoc para documentação
- Namespaces: `App\Models`, `App\Services`, `App\Utils`

### JavaScript
- ES6+ (classes, arrow functions, const/let)
- JSDoc para documentação
- Async/await ao invés de callbacks
- Classes modulares e encapsuladas

### CSS
- Metodologia BEM
- Mobile-first approach
- Variáveis CSS para temas
- Acessibilidade (prefers-reduced-motion)

## 🔒 Segurança

- Validação de caminhos (prevenção de path traversal)
- Sanitização de entrada
- Headers de segurança HTTP (.htaccess)
- Não exposição de caminhos absolutos no frontend
- Validação rigorosa de formato de nomes de projetos

## 🐛 Troubleshooting

### Nenhum projeto encontrado

1. **Execute o script Python** para gerar o JSON:
   ```bash
   python generate_projects_json.py
   ```

2. Verifique se o arquivo `data/projects.json` foi criado

3. Verifique se o caminho em `generate_projects_json.py` (BASE_PATH) está correto

4. Confirme que cada projeto tem o arquivo HTML no caminho esperado:
   ```
   [NomeProjeto]/3_dsm_ortho/2_mosaic/google_tiles/*.html
   ```

5. Verifique permissões de leitura da pasta de projetos

### Erro ao carregar HTML

1. Verifique se o arquivo HTML existe no caminho esperado
2. Confirme permissões de leitura do arquivo
3. Verifique o formato do nome do arquivo HTML

### Problemas de CORS

- Em desenvolvimento, o CORS está habilitado automaticamente
- Em produção, ajuste os headers em `api/get_projects.php` se necessário

## 📄 Licença

Este projeto é de uso interno da Copasa.

## 👥 Autor

Desenvolvido para visualização de processamentos Pix4D Mapper.

## 🔄 Como o Sistema Funciona

### Fluxo de Funcionamento

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. CARREGAMENTO INICIAL (index.php)                            │
│    - Usuário acessa index.php                                   │
│    - TimelineSlider é inicializado                             │
│    - Requisição AJAX para api/get_projects.php                  │
└───────────────────────┬─────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. LEITURA DO JSON (api/get_projects.php)                      │
│    - Lê arquivo data/projects.json                              │
│    - Retorna JSON: [{name, date, path, ...}, ...]              │
│    - Para atualizar: execute generate_projects_json.py         │
└───────────────────────┬─────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. EXIBIÇÃO (TimelineSlider)                                    │
│    - Renderiza lista de projetos no slider                      │
│    - Usuário seleciona um projeto                               │
│    - Iframe.src = "api/view_project.php?path=..."              │
└───────────────────────┬─────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. PROCESSAMENTO (api/view_project.php)                         │
│    ┌─────────────────────────────────────────────────────────┐ │
│    │ 4.1. Validação de Segurança                             │ │
│    │     - Valida caminho (previne path traversal)            │ │
│    │     - Verifica se arquivo existe e é legível            │ │
│    └───────────────────────┬─────────────────────────────────┘ │
│                            │                                     │
│    ┌───────────────────────▼─────────────────────────────────┐ │
│    │ 4.2. Leitura do HTML                                     │ │
│    │     - file_get_contents($filePath)                       │ │
│    └───────────────────────┬─────────────────────────────────┘ │
│                            │                                     │
│    ┌───────────────────────▼─────────────────────────────────┐ │
│    │ 4.3. Injeção da Chave Google Maps                        │ │
│    │     - Substitui maps.google.com → maps.googleapis.com    │ │
│    │     - Substitui sensor=false → key=SUA_CHAVE             │ │
│    │     - Múltiplos padrões regex para capturar variações    │ │
│    └───────────────────────┬─────────────────────────────────┘ │
│                            │                                     │
│    ┌───────────────────────▼─────────────────────────────────┐ │
│    │ 4.4. Ajuste de Caminhos Relativos                       │ │
│    │     - src="tiles/13/1234/5678.png"                      │ │
│    │     → src="api/view_project.php?path=tiles/13/..."      │ │
│    └───────────────────────┬─────────────────────────────────┘ │
│                            │                                     │
│    ┌───────────────────────▼─────────────────────────────────┐ │
│    │ 4.5. Injeção de Scripts JavaScript                      │ │
│    │     - Script de interceptação de recursos                │ │
│    │     - Script de interceptação do Google Maps             │ │
│    │     - Script de aguardar carregamento                    │ │
│    └───────────────────────┬─────────────────────────────────┘ │
│                            │                                     │
│    ┌───────────────────────▼─────────────────────────────────┐ │
│    │ 4.6. Retorno                                             │ │
│    │     - Header: Content-Type: text/html                    │ │
│    │     - Echo do HTML processado                            │ │
│    └─────────────────────────────────────────────────────────┘ │
└───────────────────────┬─────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│ 5. RENDERIZAÇÃO (Navegador)                                      │
│    - HTML é renderizado no iframe                                │
│    - Scripts injetados executam                                 │
│    - Google Maps carrega com a chave correta                    │
│    - Mosaico é exibido no mapa                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Detalhamento do Processamento do HTML

Quando `view_project.php` processa um arquivo HTML, ele executa as seguintes transformações:

#### Exemplo: HTML Original (Pix4D)
```html
<script type="text/javascript" 
        src="https://maps.google.com/maps/api/js?sensor=false">
</script>
```

#### Após Processamento
```html
<script type="text/javascript" 
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAwe4ZNUeKNW1Nh8oI72rwGFW6mFA-I8nw">
</script>
<script>
// Scripts injetados para interceptação e aguardar carregamento
(function() {
    var apiKey = "AIzaSyAwe4ZNUeKNW1Nh8oI72rwGFW6mFA-I8nw";
    // ... código de interceptação ...
})();
</script>
```

### Sistema de Injeção da Chave do Google Maps

O sistema possui um mecanismo robusto para injetar a chave do Google Maps nos arquivos HTML dos projetos Pix4D:

#### 1. Configuração da Chave

A chave é armazenada em `config/api_keys.local.php` (arquivo não versionado):

```php
define('GOOGLE_MAPS_API_KEY', 'SUA_CHAVE_AQUI');
```

Este arquivo é carregado automaticamente por `config/config.php` e está no `.gitignore` para segurança.

#### 2. Substituição no PHP (Lado do Servidor)

Quando `view_project.php` processa um arquivo HTML, ele:

1. **Substitui URLs do Google Maps** usando múltiplos padrões regex:
   - `https://maps.google.com/maps/api/js?sensor=false` → `https://maps.googleapis.com/maps/api/js?key=SUA_CHAVE`
   - `http://maps.google.com/maps/api/js?sensor=false` → `https://maps.googleapis.com/maps/api/js?key=SUA_CHAVE`
   - Qualquer variação com `maps.google.com` ou `maps.googleapis.com`

2. **Converte formato antigo para novo**:
   - `maps.google.com` → `maps.googleapis.com`
   - `sensor=false` → `key=SUA_CHAVE`

#### 3. Interceptação JavaScript (Lado do Cliente)

Além da substituição no servidor, o sistema injeta scripts JavaScript que:

1. **Interceptam criação de elementos `<script>`**:
   - Quando um script do Google Maps é criado dinamicamente, adiciona a chave automaticamente

2. **Verificam scripts existentes**:
   - Procura por scripts do Google Maps já no DOM
   - Se encontrar sem chave, adiciona automaticamente
   - Se encontrar com `sensor=false`, substitui pela chave

3. **Criam script se necessário**:
   - Se nenhum script do Google Maps for encontrado, cria um novo com a chave

4. **Aguardam carregamento completo**:
   - Intercepta `window.onload` e `body.onload`
   - Aguarda o Google Maps estar totalmente carregado antes de executar código que depende dele
   - Previne erros como `google is not defined`

### Sistema de Proxy de Recursos

O `view_project.php` também atua como proxy para recursos estáticos:

- **Imagens**: Tiles do mosaico, ícones, etc.
- **CSS**: Estilos dos projetos
- **JavaScript**: Scripts dos projetos

Todos os recursos são servidos através do endpoint proxy para:
- Manter segurança (validação de caminhos)
- Evitar problemas de CORS
- Permitir ajustes dinâmicos (como a chave do Google Maps)

### Estrutura de Arquivos Processados

```
Projeto Pix4D/
└── 3_dsm_ortho/
    └── 2_mosaic/
        └── google_tiles/
            ├── Juatuba_06-11_mosaic.html  ← Arquivo principal
            ├── 13/                          ← Tiles do mosaico (zoom level 13)
            │   ├── 1234/
            │   │   └── 5678.png
            │   └── ...
            └── ...
```

O HTML do mosaico contém:
- Script do Google Maps (que precisa da chave)
- Código JavaScript para inicializar o mapa
- Referências aos tiles do mosaico (imagens PNG)

## 🔧 Configuração da Chave do Google Maps

### Passo a Passo

1. **Obtenha uma chave do Google Maps**:
   - Acesse: https://console.cloud.google.com/google/maps-apis
   - Crie um projeto (ou use um existente)
   - Ative a "Maps JavaScript API"
   - Crie uma chave de API

2. **Configure a chave localmente**:
   - Abra o arquivo `config/api_keys.local.php`
   - Cole sua chave entre as aspas:
     ```php
     define('GOOGLE_MAPS_API_KEY', 'SUA_CHAVE_AQUI');
     ```
   - Salve o arquivo

3. **Teste a configuração**:
   - Acesse `test_api_key.php` no navegador para verificar se a chave está sendo carregada
   - Abra o console do navegador (F12) ao carregar um projeto
   - Procure por mensagens `[Google Maps]` no console

### Segurança

- ✅ O arquivo `api_keys.local.php` está no `.gitignore` (não será versionado)
- ✅ Apenas o arquivo de exemplo (`api_keys.example.php`) é versionado
- ✅ A chave é injetada no servidor, não fica exposta no código-fonte
- ⚠️ **IMPORTANTE**: Configure restrições de API no Google Cloud Console:
  - Restrinja por referer HTTP (domínios permitidos)
  - Restrinja por IP se possível
  - Monitore o uso da API

## 🐛 Troubleshooting - Google Maps

### Problema: "Esta página não carregou o Google Maps corretamente"

**Causas possíveis**:
1. Chave não configurada ou vazia
2. Chave inválida ou expirada
3. Restrições de API muito restritivas
4. Quota da API excedida

**Soluções**:
1. Verifique se a chave está em `config/api_keys.local.php`
2. Teste a chave em `test_api_key.php`
3. Verifique o console do navegador (F12) para mensagens de erro
4. Verifique as restrições no Google Cloud Console

### Problema: Erro "google is not defined"

**Causa**: O código JavaScript está tentando usar `google.maps` antes da API carregar.

**Solução**: O sistema já intercepta e aguarda o carregamento, mas se persistir:
1. Limpe o cache do navegador (Ctrl+Shift+Delete)
2. Verifique no console se aparece `[Google Maps] Google Maps API está pronta!`
3. Se não aparecer, o script do Google Maps pode não estar sendo carregado

### Problema: Script do Google Maps não encontrado

**Causa**: A substituição no PHP não está funcionando.

**Solução**:
1. Verifique o código-fonte do iframe (botão direito → Inspecionar)
2. Procure por `<script src="...maps.googleapis.com...">`
3. Se encontrar `sensor=false`, a substituição não funcionou
4. Verifique os logs do PHP (se `ENVIRONMENT === 'development'`)

### Debug

Para debugar problemas com Google Maps:

1. **Console do navegador** (F12):
   - Procure por mensagens `[Google Maps]`
   - Verifique erros em vermelho

2. **Código-fonte do iframe**:
   - Botão direito no iframe → "Inspecionar elemento"
   - Vá para a aba "Elements" ou "Inspector"
   - Procure por scripts do Google Maps no `<head>`

3. **Teste da chave**:
   - Acesse `test_api_key.php`
   - Verifique se a chave está sendo carregada corretamente

4. **Logs do servidor**:
   - Se `ENVIRONMENT === 'development'`, mensagens são logadas
   - Verifique o log de erros do PHP

## 📝 Sistema de Geração de JSON

O sistema agora utiliza um arquivo JSON estático (`data/projects.json`) ao invés de escanear projetos dinamicamente. Isso oferece:

- ✅ **Flexibilidade**: Aceita qualquer padrão de nome de pasta (não precisa seguir `Juatuba_DD-MM_YY`)
- ✅ **Performance**: Leitura rápida do JSON ao invés de escanear diretórios
- ✅ **Controle**: Você decide quando atualizar a lista de projetos

### Como Gerar/Atualizar o JSON

1. **Execute o script Python**:
   ```bash
   python generate_projects_json.py
   ```

2. **O script irá**:
   - Escanear a pasta configurada em `BASE_PATH`
   - Encontrar todos os projetos (qualquer nome de pasta)
   - Tentar extrair datas dos nomes (suporta múltiplos formatos)
   - Validar que cada projeto tem um arquivo HTML do mosaico
   - Gerar `data/projects.json` com todos os projetos ordenados

3. **Execute novamente sempre que**:
   - Adicionar novos projetos
   - Renomear projetos
   - Remover projetos

### Formato do JSON

O arquivo `data/projects.json` segue este formato:

```json
{
  "success": true,
  "data": [
    {
      "name": "Juatuba_02-12_25",
      "date": "02-12-25",
      "date_display": "02 de Dezembro de 2025",
      "date_sortable": "2025-12-02",
      "html_path": "api/view_project.php?path=..."
    }
  ],
  "meta": {
    "total": 5
  }
}
```

## 🔄 Changelog

### v1.2.0 (2026-01-26)
- ✅ Sistema de JSON estático para projetos
- ✅ Script Python `generate_projects_json.py` para gerar JSON
- ✅ Suporte flexível para qualquer padrão de nome de pasta
- ✅ Melhor performance (leitura de JSON ao invés de escaneamento)

### v1.1.0 (2026-01-26)
- ✅ Sistema de injeção de chave do Google Maps
- ✅ Interceptação JavaScript para garantir carregamento correto
- ✅ Suporte para formato antigo (`maps.google.com`) e novo (`maps.googleapis.com`)
- ✅ Script de teste para verificação de chave (`test_api_key.php`)
- ✅ Documentação completa do sistema

### v1.0.0 (2026-01-26)
- Implementação inicial
- Escaneamento automático de projetos
- Slider interativo com navegação
- Interface responsiva
- Tratamento de erros
- Documentação completa
