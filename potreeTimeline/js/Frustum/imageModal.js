/**
 * Mostra a imagem da câmera em um modal
 * @param {Object} cameraInfo - Informações da câmera
 * @param {string} cameraInfo.name - Nome da câmera
 * @param {string} cameraInfo.imagePath - Caminho da imagem
 * @param {Object} [ui] - Opções de layout
 * @param {number} [ui.topOffset=64] - Espaço abaixo do topo da tela (px)
 * @param {number} [ui.bottomOffset=24] - Espaço acima da parte inferior (px)
 * @param {number} [ui.sidePadding=24] - Espaço nas laterais (px)
 * @param {number|string} [ui.maxWidth='min(95vw, 1600px)'] - Largura máx. do conteúdo
 */
export function showCameraImage(cameraInfo, ui = {}) {
  console.log('📸 Mostrando imagem da câmera:', cameraInfo.name);

  // Criar modal (backdrop) - ocupando 100% da tela
  const modal = document.createElement('div');
  modal.className = 'camera-modal';
  Object.assign(modal.style, {
    position: 'fixed',
    inset: '0',
    background: 'rgba(0,0,0,0.8)',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: '10000',
  });

  // Container do conteúdo - ocupando 100% da área
  const imageContainer = document.createElement('div');
  imageContainer.className = 'camera-modal-content';
  Object.assign(imageContainer.style, {
    maxWidth: '95vw',
    maxHeight: '95vh',
    position: 'relative',
    width: '100%',
    height: '100%',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  });

  // Imagem
  const image = document.createElement('img');
  image.alt = `Imagem da câmera ${cameraInfo.name}`;
  Object.assign(image.style, {
    maxWidth: '100%',
    maxHeight: '95vh',
    objectFit: 'contain',
    pointerEvents: 'none',
  });

  // Botão de fechar - maior e melhor posicionado
  const closeButton = document.createElement('button');
  closeButton.className = 'camera-modal-close';
  closeButton.innerHTML = '×';
  closeButton.title = 'Fechar (ESC)';
  Object.assign(closeButton.style, {
    position: 'absolute',
    top: '16px',
    right: '16px',
    width: '40px',
    height: '40px',
    border: 'none',
    background: 'rgba(2,94,115,0.9)',
    color: '#fff',
    fontSize: '28px',
    cursor: 'pointer',
    borderRadius: '8px',
    zIndex: '10001',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    lineHeight: '1',
  });

  // Controlar se já foi carregada para evitar duplos callbacks
  let imageLoaded = false;

  image.onload = function () {
    if (!imageLoaded) {
      imageLoaded = true;
      // ajusta novamente o contain, caso a imagem seja muito alta/larga
      // (já coberto por CSS, mas mantemos por clareza)
      image.style.objectFit = 'contain';
    }
  };

  image.onerror = function () {
    if (!imageLoaded) {
      imageLoaded = true;
      console.warn('⚠️ Erro ao carregar imagem:', cameraInfo.imagePath);

      image.onload = null;
      image.onerror = null;

      image.src = createPlaceholderImage();
      info.textContent = `Câmera: ${cameraInfo.name} - Imagem não encontrada`;
    }
  };

  image.src = cameraInfo.imagePath;

  // Montagem
  imageContainer.appendChild(image);
  imageContainer.appendChild(closeButton);
  modal.appendChild(imageContainer);
  document.body.appendChild(modal);

  // Eventos de fechamento
  setupModalCloseEvents(modal, closeButton, imageContainer, image);
}


/**
 * Cria uma imagem placeholder em SVG
 * @returns {string} Data URL da imagem placeholder
 */
function createPlaceholderImage() {
    return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlbSBuw6NvIGVuY29udHJhZGE8L3RleHQ+PC9zdmc+';
}

/**
 * Configura os eventos de fechamento do modal
 * @param {HTMLElement} modal - Elemento do modal
 * @param {HTMLElement} closeButton - Botão de fechar
 * @param {HTMLElement} imageContainer - Container da imagem
 * @param {HTMLElement} image - Elemento da imagem
 */
function setupModalCloseEvents(modal, closeButton, imageContainer, image) {
    // Função para fechar o modal
    const closeModal = function() {
        if (document.body.contains(modal)) {
            document.body.removeChild(modal);
            document.removeEventListener('keydown', handleKeyPress);
        }
    };
    
    // Fechar ao clicar no botão de fechar
    closeButton.addEventListener('click', (e) => {
        e.stopPropagation();
        closeModal();
    });
    
    // Fechar ao clicar em qualquer lugar do modal (fundo ou container, mas não na imagem)
    modal.addEventListener('click', (e) => {
        if (e.target === modal || e.target === imageContainer) {
            closeModal();
        }
    });
    
    // Prevenir que clique na imagem feche o modal
    image.addEventListener('click', (e) => e.stopPropagation());
    
    // Fechar modal com tecla ESC
    const handleKeyPress = function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    };
    document.addEventListener('keydown', handleKeyPress);
}
