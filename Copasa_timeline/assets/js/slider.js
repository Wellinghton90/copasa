/**
 * Classe principal do Timeline Slider
 * 
 * Gerencia a navegação entre projetos e atualização da UI
 * Segue princípios OOP e Event-Driven Architecture
 */

class TimelineSlider {
    /**
     * Container principal do slider
     * 
     * @type {HTMLElement}
     */
    #container;

    /**
     * Lista de projetos
     * 
     * @type {Array}
     */
    #projects = [];

    /**
     * Índice do projeto atual
     * 
     * @type {number}
     */
    #currentIndex = 0;

    /**
     * Estado de carregamento
     * 
     * @type {boolean}
     */
    #isLoading = false;

    /**
     * Elementos DOM (cache)
     * 
     * @type {Object}
     */
    #elements = {};

    /**
     * Instância do MapViewer
     * 
     * @type {MapViewer|null}
     */
    #mapViewer = null;

    /**
     * Opções de configuração
     * 
     * @type {Object}
     */
    #options = {};

    /**
     * Construtor
     * 
     * @param {HTMLElement|string} container Container do slider ou seletor CSS
     * @param {Object} options Opções de configuração
     */
    constructor(container, options = {}) {
        // Resolve container
        if (typeof container === 'string') {
            this.#container = document.querySelector(container);
        } else {
            this.#container = container;
        }

        if (!this.#container) {
            throw new Error('Container do slider não encontrado');
        }

        // Opções padrão
        this.#options = {
            autoLoad: true,
            ...options
        };

        // Cache elementos DOM
        this.#cacheElements();

        // Inicializa MapViewer
        this.#initializeMapViewer();

        // Inicializa listeners
        this.#attachEventListeners();

        // Carrega projetos se autoLoad estiver ativo
        if (this.#options.autoLoad) {
            this.loadProjects();
        }
    }

    /**
     * Cache elementos DOM para melhor performance
     * 
     * @private
     */
    #cacheElements() {
        this.#elements = {
            mapContainer: this.#container.querySelector('.timeline-map-container'),
            loading: this.#container.querySelector('.timeline-loading'),
            loadingText: this.#container.querySelector('.timeline-loading__text'),
            error: this.#container.querySelector('.timeline-error'),
            errorMessage: this.#container.querySelector('.timeline-error__message'),
            headerTitle: document.querySelector('#timeline-header-title'),
            dateDisplay: this.#container.querySelector('#timeline-date'),
            datePrev: this.#container.querySelector('#timeline-date-prev'),
            dateNext: this.#container.querySelector('#timeline-date-next'),
            counter: this.#container.querySelector('.timeline-indicators__counter'),
            prevButton: this.#container.querySelector('.timeline-controls__button--prev'),
            nextButton: this.#container.querySelector('.timeline-controls__button--next')
        };
    }

    /**
     * Inicializa o MapViewer
     * 
     * @private
     */
    #initializeMapViewer() {
        try {
            // Verifica se Google Maps API está disponível
            if (!window.google || !window.google.maps) {
                console.warn('Google Maps API não está carregada. O mapa não será inicializado.');
                return;
            }

            // Verifica se container existe
            const mapContainer = document.getElementById('timeline-map-container');
            if (!mapContainer) {
                console.warn('Container do mapa não encontrado. Tentando novamente...');
                // Tenta novamente após um pequeno delay
                setTimeout(() => {
                    const retryContainer = document.getElementById('timeline-map-container');
                    if (retryContainer) {
                        this.#mapViewer = new MapViewer('#timeline-map-container', {
                            apiKey: window.GOOGLE_MAPS_API_KEY
                        });
                        this.#attachMapViewerListeners();
                    } else {
                        console.error('Container do mapa não encontrado após retry.');
                    }
                }, 100);
                return;
            }

            // Cria instância do MapViewer (usa seletor com #)
            this.#mapViewer = new MapViewer('#timeline-map-container', {
                apiKey: window.GOOGLE_MAPS_API_KEY
            });
            
            this.#attachMapViewerListeners();


        } catch (error) {
            console.error('Erro ao inicializar MapViewer:', error);
            this.#showError('Erro ao inicializar o mapa. Recarregue a página.');
        }
    }

    /**
     * Anexa listeners do MapViewer
     * 
     * @private
     */
    #attachMapViewerListeners() {
        const mapContainer = document.getElementById('timeline-map-container');
        if (!mapContainer) {
            return;
        }

        // Adiciona listeners do MapViewer
        mapContainer.addEventListener('mapviewer:loading', () => {
            this.#elements.mapContainer?.classList.add('timeline-map-container--loading');
        });

        mapContainer.addEventListener('mapviewer:loaded', () => {
            this.#elements.mapContainer?.classList.remove('timeline-map-container--loading');
        });

        mapContainer.addEventListener('mapviewer:error', (e) => {
            console.error('Erro no MapViewer:', e.detail.error);
            this.#elements.mapContainer?.classList.remove('timeline-map-container--loading');
            this.#showError('Erro ao carregar visualização do projeto');
        });
    }

    /**
     * Anexa event listeners
     * 
     * @private
     */
    #attachEventListeners() {
        // Botões de navegação
        if (this.#elements.prevButton) {
            this.#elements.prevButton.addEventListener('click', () => this.previous());
        }

        if (this.#elements.nextButton) {
            this.#elements.nextButton.addEventListener('click', () => this.next());
        }

        // Navegação por teclado
        this.#attachKeyboardListeners();
    }

    /**
     * Anexa listeners de teclado
     * 
     * @private
     */
    #attachKeyboardListeners() {
        document.addEventListener('keydown', (e) => {
            // Ignora se estiver digitando em input
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }

            switch (e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    this.previous();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    this.next();
                    break;
            }
        });
    }

    /**
     * Carrega projetos da API
     * 
     * @returns {Promise<void>}
     */
    async loadProjects() {
        if (this.#isLoading) {
            return;
        }

        this.#isLoading = true;
        this.#showLoading('Carregando projetos...');

        try {
            const projects = await ApiClient.fetchProjects();

            if (!projects || projects.length === 0) {
                this.#showError('Nenhum projeto encontrado');
                this.#dispatchEvent('slider:error', { message: 'Nenhum projeto encontrado' });
                return;
            }

            this.#projects = projects;
            this.#currentIndex = 0;

            this.#hideLoading();
            this.#hideError();
            this.#updateDisplay();

            this.#dispatchEvent('slider:loaded', { total: projects.length });

        } catch (error) {
            console.error('Erro ao carregar projetos:', error);
            this.#showError(error.message || 'Erro ao carregar projetos');
            this.#dispatchEvent('slider:error', { error });
        } finally {
            this.#isLoading = false;
        }
    }

    /**
     * Navega para um projeto específico
     * 
     * @param {number} index Índice do projeto
     */
    goTo(index) {
        if (this.#projects.length === 0) {
            return;
        }

        // Valida índice
        if (index < 0 || index >= this.#projects.length) {
            return;
        }

        this.#currentIndex = index;
        this.#updateDisplay();
        this.#dispatchEvent('slider:changed', {
            index,
            project: this.#projects[index]
        });
    }

    /**
     * Navega para o próximo projeto
     */
    next() {
        if (this.#currentIndex < this.#projects.length - 1) {
            this.goTo(this.#currentIndex + 1);
        }
    }

    /**
     * Navega para o projeto anterior
     */
    previous() {
        if (this.#currentIndex > 0) {
            this.goTo(this.#currentIndex - 1);
        }
    }

    /**
     * Extrai o nome do projeto do nome completo (ex: "Juatuba" de "Juatuba_04-12_25")
     * 
     * @param {string} fullName Nome completo do projeto
     * @returns {string} Nome do projeto extraído
     * @private
     */
    #extractProjectName(fullName) {
        if (!fullName) return '';
        // Remove a parte da data (tudo após o último underscore seguido de números)
        const match = fullName.match(/^([^_]+)/);
        return match ? match[1] : fullName;
    }

    /**
     * Atualiza a exibição com o projeto atual
     * 
     * @private
     */
    #updateDisplay() {
        if (this.#projects.length === 0) {
            return;
        }

        const project = this.#projects[this.#currentIndex];

        // Atualiza título com nome do projeto
        if (this.#elements.headerTitle) {
            const projectName = this.#extractProjectName(project.name);
            this.#elements.headerTitle.textContent = `Evolução Temporal - ${projectName}`;
        }

        // Atualiza mapa
        if (this.#mapViewer) {
            this.#elements.mapContainer?.classList.add('timeline-map-container--loading');
            
            this.#mapViewer.loadProject(project)
                .then(() => {
                    this.#elements.mapContainer?.classList.remove('timeline-map-container--loading');
                })
                .catch((error) => {
                    console.error('Erro ao carregar projeto no mapa:', error);
                    this.#elements.mapContainer?.classList.remove('timeline-map-container--loading');
                    this.#showError('Erro ao carregar visualização do projeto');
                });
        }

        // Atualiza data atual (centro)
        if (this.#elements.dateDisplay) {
            const dateText = project.date_display || project.date || '-';
            this.#elements.dateDisplay.textContent = dateText;
        }

        // Atualiza data anterior (esquerda)
        if (this.#elements.datePrev) {
            if (this.#currentIndex > 0) {
                const prevProject = this.#projects[this.#currentIndex - 1];
                const prevDateText = prevProject.date_display || prevProject.date || '-';
                this.#elements.datePrev.textContent = prevDateText;
            } else {
                this.#elements.datePrev.textContent = '-';
            }
        }

        // Atualiza data posterior (direita)
        if (this.#elements.dateNext) {
            if (this.#currentIndex < this.#projects.length - 1) {
                const nextProject = this.#projects[this.#currentIndex + 1];
                const nextDateText = nextProject.date_display || nextProject.date || '-';
                this.#elements.dateNext.textContent = nextDateText;
            } else {
                this.#elements.dateNext.textContent = '-';
            }
        }

        // Atualiza contador (extrema direita)
        if (this.#elements.counter) {
            this.#elements.counter.textContent = `${this.#currentIndex + 1} de ${this.#projects.length}`;
        }

        // Atualiza estado dos botões
        this.#updateButtons();
    }

    /**
     * Atualiza estado dos botões de navegação
     * 
     * @private
     */
    #updateButtons() {
        if (this.#elements.prevButton) {
            this.#elements.prevButton.disabled = this.#currentIndex === 0;
        }

        if (this.#elements.nextButton) {
            this.#elements.nextButton.disabled = this.#currentIndex === this.#projects.length - 1;
        }
    }

    /**
     * Mostra indicador de carregamento
     * 
     * @param {string} message Mensagem de carregamento
     * @private
     */
    #showLoading(message = 'Carregando...') {
        if (this.#elements.loading) {
            this.#elements.loading.classList.remove('timeline-loading--hidden');
        }

        if (this.#elements.loadingText) {
            this.#elements.loadingText.textContent = message;
        }
    }

    /**
     * Esconde indicador de carregamento
     * 
     * @private
     */
    #hideLoading() {
        if (this.#elements.loading) {
            this.#elements.loading.classList.add('timeline-loading--hidden');
        }
    }

    /**
     * Mostra mensagem de erro
     * 
     * @param {string} message Mensagem de erro
     * @private
     */
    #showError(message) {
        if (this.#elements.error) {
            this.#elements.error.classList.add('timeline-error--visible');
        }

        if (this.#elements.errorMessage) {
            this.#elements.errorMessage.textContent = message;
        }
    }

    /**
     * Esconde mensagem de erro
     * 
     * @private
     */
    #hideError() {
        if (this.#elements.error) {
            this.#elements.error.classList.remove('timeline-error--visible');
        }
    }

    /**
     * Dispara evento customizado
     * 
     * @param {string} eventName Nome do evento
     * @param {Object} detail Dados do evento
     * @private
     */
    #dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(eventName, {
            detail: {
                slider: this,
                ...detail
            },
            bubbles: true
        });

        this.#container.dispatchEvent(event);
    }

    /**
     * Retorna o projeto atual
     * 
     * @returns {Object|null} Projeto atual ou null
     */
    getCurrentProject() {
        if (this.#projects.length === 0 || this.#currentIndex < 0 || this.#currentIndex >= this.#projects.length) {
            return null;
        }

        return this.#projects[this.#currentIndex];
    }

    /**
     * Retorna todos os projetos
     * 
     * @returns {Array} Array de projetos
     */
    getProjects() {
        return [...this.#projects];
    }

    /**
     * Retorna o índice atual
     * 
     * @returns {number} Índice atual
     */
    getCurrentIndex() {
        return this.#currentIndex;
    }

    /**
     * Limpa recursos e remove listeners
     */
    destroy() {
        // Limpa MapViewer
        if (this.#mapViewer) {
            this.#mapViewer.destroy();
            this.#mapViewer = null;
        }
        
        // Remove event listeners (se necessário)
        // Por enquanto, os listeners são anexados ao document/container
        // que serão limpos quando a página for descarregada
        this.#projects = [];
        this.#currentIndex = 0;
    }
}
