/**
 * Módulo: UI da timeline (controles de navegação).
 * Gerencia setas, seletor de projetos e navegação por teclado.
 */

/**
 * Atualiza o estado dos botões de navegação (anterior/próximo).
 * @param {number} currentIndex - Índice atual do projeto
 * @param {number} totalProjects - Total de projetos disponíveis
 */
export function updateArrowButtons(currentIndex, totalProjects) {
    const prev = document.getElementById('btn_anterior');
    const next = document.getElementById('btn_proximo');
    
    if (prev) {
        prev.disabled = totalProjects === 0 || currentIndex <= 0;
    }
    if (next) {
        next.disabled = totalProjects === 0 || currentIndex >= totalProjects - 1;
    }
}

/**
 * Inicializa o seletor de projetos com as opções disponíveis.
 * @param {Array} availableProjects - Lista de projetos disponíveis
 * @param {string} initialProjectId - ID do projeto inicial
 * @returns {number} Índice do projeto inicial
 */
export function initProjectSelector(availableProjects, initialProjectId) {
    const sel = document.getElementById('seletor_projeto');
    if (!sel) return 0;
    
    sel.innerHTML = '';
    availableProjects.forEach((p) => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.label || p.id;
        sel.appendChild(opt);
    });
    
    const initial = initialProjectId || (availableProjects[0] && availableProjects[0].id);
    const idx = availableProjects.findIndex((p) => p.id === initial);
    const currentIndex = idx >= 0 ? idx : 0;
    
    if (availableProjects[currentIndex]) {
        sel.value = availableProjects[currentIndex].id;
    }
    
    return currentIndex;
}

/**
 * Atualiza o valor do seletor para um projeto específico.
 * @param {string} projectId - ID do projeto
 */
export function updateProjectSelector(projectId) {
    const sel = document.getElementById('seletor_projeto');
    if (sel && projectId) {
        sel.value = projectId;
    }
}

/**
 * Cria handler para mudança no seletor de projetos.
 * @param {Array} availableProjects - Lista de projetos disponíveis
 * @param {Function} onProjectChange - Callback chamado quando o projeto muda
 * @returns {Function} Handler de evento
 */
export function createSelectChangeHandler(availableProjects, onProjectChange) {
    return function onSelectChange() {
        const id = this.value;
        const idx = availableProjects.findIndex((p) => p.id === id);
        if (idx >= 0 && onProjectChange) {
            onProjectChange(idx, id);
        }
    };
}

/**
 * Cria handler para navegação por teclado (setas esquerda/direita).
 * @param {Array} availableProjects - Lista de projetos disponíveis
 * @param {number} currentIndex - Índice atual
 * @param {Function} onNavigate - Callback chamado para navegar (recebe novo índice)
 * @returns {Function} Handler de evento
 */
export function createKeyboardNavigationHandler(availableProjects, currentIndex, onNavigate) {
    return function onKeydown(e) {
        if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') {
            return;
        }
        
        if (availableProjects.length === 0) {
            return;
        }
        
        if (e.key === 'ArrowLeft') {
            if (currentIndex <= 0) return;
            e.preventDefault();
            onNavigate(currentIndex - 1);
        } else {
            if (currentIndex >= availableProjects.length - 1) return;
            e.preventDefault();
            onNavigate(currentIndex + 1);
        }
    };
}

/**
 * Registra event listeners para os controles da timeline.
 * @param {Array} availableProjects - Lista de projetos disponíveis
 * @param {number} currentIndex - Índice atual
 * @param {Function} onNavigate - Callback para navegação
 * @param {Function} onSelectChange - Callback para mudança no seletor
 */
export function registerTimelineEventListeners(availableProjects, currentIndex, onNavigate, onSelectChange) {
    const sel = document.getElementById('seletor_projeto');
    const btnPrev = document.getElementById('btn_anterior');
    const btnNext = document.getElementById('btn_proximo');
    
    if (sel && onSelectChange) {
        sel.addEventListener('change', onSelectChange);
    }
    
    if (btnPrev) {
        btnPrev.addEventListener('click', () => {
            if (currentIndex > 0) {
                onNavigate(currentIndex - 1);
            }
        });
    }
    
    if (btnNext) {
        btnNext.addEventListener('click', () => {
            if (currentIndex < availableProjects.length - 1) {
                onNavigate(currentIndex + 1);
            }
        });
    }
    
    const keyboardHandler = createKeyboardNavigationHandler(availableProjects, currentIndex, onNavigate);
    document.addEventListener('keydown', keyboardHandler);
}
