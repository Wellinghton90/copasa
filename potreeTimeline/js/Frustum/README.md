# Módulo Frustum

Este módulo gerencia os frustums das câmeras e a funcionalidade de clique para exibir imagens.

## Estrutura de Arquivos

### `index.js`
Módulo principal que exporta a função `loadCameraFrustumsAsync()` e coordena todos os outros módulos.

### `cameraData.js`
Gerencia o armazenamento e acesso aos dados das câmeras:
- `addCameraData()` - Adiciona dados de uma câmera
- `clearCameraData()` - Limpa todos os dados
- `getCameraData()` - Busca dados por nome
- `getAllCameraData()` - Retorna todos os dados

### `imagePath.js`
Constrói caminhos para as imagens das câmeras:
- `generateImagePath()` - Gera caminho principal
- `generateImagePathLowercase()` - Gera caminho com extensão minúscula
- `generateImagePathVariations()` - Retorna array com variações possíveis

### `frustumRenderer.js`
Cria e gerencia os frustums no Three.js:
- `createCameraFrustum()` - Cria um frustum
- `addFrustumToScene()` - Adiciona frustum à cena
- `removeFrustumFromScene()` - Remove frustum da cena
- `clearAllFrustums()` - Limpa todos os frustums
- `getClickableFrustums()` - Retorna frustums clicáveis

### `interaction.js`
Sistema de interação com frustums:
- `initializeInteraction()` - Inicializa sistema de clique/hover
- `cleanupInteraction()` - Remove event listeners
- Gerencia raycasting e detecção de cliques

### `imageModal.js`
Modal para exibição de imagens:
- `showCameraImage()` - Exibe imagem em modal
- `createPlaceholderImage()` - Cria imagem placeholder
- `setupModalCloseEvents()` - Configura eventos de fechamento

### `cameraLoader.js`
Parser do arquivo de parâmetros de câmera:
- `loadExternalCameraParameters()` - Carrega arquivo de parâmetros
- `findDataStartIndex()` - Encontra início dos dados
- `parseCameraLine()` - Faz parse de linha de dados

## Uso

```javascript
import { loadCameraFrustumsAsync } from './Frustum/index.js';

// Carregar frustums
await loadCameraFrustumsAsync(cameraParamsPath, pix4dOffset);
```

## Dependências

- Three.js
- Potree (CameraFrustumHelper)
- Variáveis globais: `window.viewer`, `window.fotosPath`, `window.cameraParamsPath`
