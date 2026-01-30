/**
 * Visualizador de Mapa Customizado para Tiles Pix4D
 * 
 * Gerencia o mapa Google Maps e exibe tiles do Pix4D como overlay
 * com persistência de posição/zoom durante a sessão
 */

class MapViewer {
    /**
     * Container do mapa
     * @type {HTMLElement}
     */
    #container;

    /**
     * Instância do Google Maps
     * @type {google.maps.Map|null}
     */
    #map = null;

    /**
     * Instância do tile layer atual
     * @type {google.maps.ImageMapType|null}
     */
    #currentTileLayer = null;

    /**
     * Metadados do projeto atual
     * @type {Object|null}
     */
    #currentMetadata = null;

    /**
     * Caminho base dos tiles do projeto atual
     * @type {string|null}
     */
    #currentTilesBasePath = null;

    /**
     * Estado de carregamento
     * @type {boolean}
     */
    #isLoading = false;

    /**
     * Opções de configuração
     * @type {Object}
     */
    #options = {};

    /**
     * Construtor
     * 
     * @param {string|HTMLElement} container Container do mapa ou seletor CSS
     * @param {Object} options Opções de configuração
     */
    constructor(container, options = {}) {
        // Resolve container
        if (typeof container === 'string') {
            // Se não começa com #, adiciona
            const selector = container.startsWith('#') ? container : '#' + container;
            this.#container = document.querySelector(selector);
        } else {
            this.#container = container;
        }

        if (!this.#container) {
            const selector = typeof container === 'string' ? container : 'elemento fornecido';
            throw new Error(`Container do mapa não encontrado: ${selector}. Verifique se o elemento existe no DOM.`);
        }

        // Opções padrão
        this.#options = {
            apiKey: options.apiKey || window.GOOGLE_MAPS_API_KEY || '',
            ...options
        };

        // Verifica se Google Maps API está disponível
        if (!window.google || !window.google.maps) {
            throw new Error('Google Maps API não está carregada');
        }

        // Inicializa mapa
        this.#initializeMap();
    }

    /**
     * Inicializa o mapa Google Maps
     * @private
     */
    #initializeMap() {
        const mapOptions = {
            zoom: 17,
            center: { lat: -19.945, lng: -44.346 },
            mapTypeId: google.maps.MapTypeId.ROADMAP,
            streetViewControl: false,
            fullscreenControl: true
        };

        this.#map = new google.maps.Map(this.#container, mapOptions);

        // Adiciona listeners para salvar estado
        this.#map.addListener('center_changed', () => {
            this.#saveViewState();
        });

        this.#map.addListener('zoom_changed', () => {
            this.#saveViewState();
        });

        // Dispara evento de inicialização
        this.#dispatchEvent('mapviewer:initialized', { map: this.#map });
    }

    /**
     * Carrega um projeto específico
     * 
     * @param {Object} projectData Dados do projeto (deve ter html_path)
     * @returns {Promise<void>}
     */
    async loadProject(projectData) {
        if (this.#isLoading) {
            return;
        }

        if (!projectData || !projectData.html_path) {
            throw new Error('Dados do projeto inválidos');
        }

        this.#isLoading = true;
        this.#dispatchEvent('mapviewer:loading', { project: projectData });

        try {
            // Extrai path do html_path (remove api/view_project.php?path=)
            let htmlPath = projectData.html_path;
            if (htmlPath.includes('api/view_project.php?path=')) {
                htmlPath = decodeURIComponent(htmlPath.split('path=')[1].split('&')[0]);
            }

            // Busca metadados
            const metadata = await this.#fetchMetadata(htmlPath);

            // Remove tile layer anterior se existir
            if (this.#currentTileLayer) {
                this.#map.overlayMapTypes.removeAt(0);
                this.#currentTileLayer = null;
            }

            // Cria novo tile layer
            this.#currentMetadata = metadata;
            this.#currentTilesBasePath = metadata.tilesBasePath;
            this.#currentTileLayer = this.#createPix4DTileLayer(metadata);

            // Adiciona tile layer ao mapa
            this.#map.overlayMapTypes.insertAt(0, this.#currentTileLayer);

            // Restaura ou ajusta view
            const savedState = this.#restoreViewState();
            if (savedState) {
                // Restaura posição/zoom salva
                this.#map.setCenter(savedState.center);
                this.#map.setZoom(savedState.zoom);
            } else {
                // Ajusta aos bounds do projeto
                this.#fitToBounds(metadata.bounds);
            }

            this.#dispatchEvent('mapviewer:loaded', {
                project: projectData,
                metadata: metadata
            });

        } catch (error) {
            console.error('Erro ao carregar projeto:', error);
            this.#dispatchEvent('mapviewer:error', {
                project: projectData,
                error: error.message || 'Erro desconhecido'
            });
            throw error;
        } finally {
            this.#isLoading = false;
        }
    }

    /**
     * Busca metadados do projeto
     * 
     * @param {string} htmlPath Caminho do HTML
     * @returns {Promise<Object>} Metadados do projeto
     * @private
     */
    async #fetchMetadata(htmlPath) {
        const url = `api/get_map_metadata.php?path=${encodeURIComponent(htmlPath)}`;

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`Erro ao buscar metadados: ${response.status}`);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Erro ao processar metadados');
        }

        return data.data;
    }

    /**
     * Cria ImageMapType customizado para tiles do Pix4D
     * 
     * @param {Object} metadata Metadados do projeto
     * @returns {google.maps.ImageMapType} ImageMapType customizado
     * @private
     */
    #createPix4DTileLayer(metadata) {
        const bounds = metadata.bounds;
        const minZoom = metadata.minZoom;
        const maxZoom = metadata.maxZoom;
        const tilesBasePath = metadata.tilesBasePath;

        // Cria bounds do Google Maps
        const sw = new google.maps.LatLng(bounds.sw.lat, bounds.sw.lng);
        const ne = new google.maps.LatLng(bounds.ne.lat, bounds.ne.lng);
        const mapBounds = new google.maps.LatLngBounds(sw, ne);

        const tileLayer = new google.maps.ImageMapType({
            getTileUrl: (coord, zoom) => {
                // Valida zoom
                if (zoom < minZoom || zoom > maxZoom) {
                    return null;
                }

                // Calcula bounds do tile
                const proj = this.#map.getProjection();
                const tileSize = 256 / Math.pow(2, zoom);
                const tileBounds = new google.maps.LatLngBounds(
                    proj.fromPointToLatLng(new google.maps.Point(coord.x * tileSize, (coord.y + 1) * tileSize)),
                    proj.fromPointToLatLng(new google.maps.Point((coord.x + 1) * tileSize, coord.y * tileSize))
                );

                // Verifica se tile está dentro dos bounds do projeto
                if (!mapBounds.intersects(tileBounds)) {
                    return null;
                }

                // Calcula URL do tile seguindo padrão do Pix4D
                // zoom + "/" + coord.x + "/" + (Math.pow(2,zoom)-coord.y-1) + ".png"
                const y = Math.pow(2, zoom) - coord.y - 1;
                const tilePath = `${zoom}/${coord.x}/${y}.png`;

                // Retorna URL via proxy
                const basePathEncoded = encodeURIComponent(tilesBasePath);
                const tilePathEncoded = encodeURIComponent(tilePath);
                return `api/view_project.php?path=${tilePathEncoded}&base=${basePathEncoded}`;
            },
            tileSize: new google.maps.Size(256, 256),
            isPng: true,
            opacity: 1.0,
            name: 'Pix4D Mosaic'
        });

        return tileLayer;
    }

    /**
     * Ajusta mapa aos bounds do projeto
     * 
     * @param {Object} bounds Bounds do projeto
     * @private
     */
    #fitToBounds(bounds) {
        const sw = new google.maps.LatLng(bounds.sw.lat, bounds.sw.lng);
        const ne = new google.maps.LatLng(bounds.ne.lat, bounds.ne.lng);
        const mapBounds = new google.maps.LatLngBounds(sw, ne);

        this.#map.fitBounds(mapBounds);
    }

    /**
     * Salva estado da view (posição e zoom) no sessionStorage
     * @private
     */
    #saveViewState() {
        if (!this.#map) {
            return;
        }

        const center = this.#map.getCenter();
        const zoom = this.#map.getZoom();

        if (center && zoom !== undefined) {
            try {
                sessionStorage.setItem('timeline_map_center', JSON.stringify({
                    lat: center.lat(),
                    lng: center.lng()
                }));
                sessionStorage.setItem('timeline_map_zoom', zoom.toString());
            } catch (e) {
                // Ignora erros de sessionStorage (pode estar desabilitado)
                console.warn('Não foi possível salvar estado do mapa:', e);
            }
        }
    }

    /**
     * Restaura estado da view (posição e zoom) do sessionStorage
     * 
     * @returns {Object|null} Estado restaurado ou null se não existir
     * @private
     */
    #restoreViewState() {
        try {
            const centerStr = sessionStorage.getItem('timeline_map_center');
            const zoomStr = sessionStorage.getItem('timeline_map_zoom');

            if (centerStr && zoomStr) {
                const center = JSON.parse(centerStr);
                const zoom = parseInt(zoomStr, 10);

                if (center && center.lat && center.lng && !isNaN(zoom)) {
                    return {
                        center: new google.maps.LatLng(center.lat, center.lng),
                        zoom: zoom
                    };
                }
            }
        } catch (e) {
            // Ignora erros de sessionStorage
            console.warn('Não foi possível restaurar estado do mapa:', e);
        }

        return null;
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
                viewer: this,
                ...detail
            },
            bubbles: true
        });

        this.#container.dispatchEvent(event);
    }

    /**
     * Retorna a instância do mapa
     * 
     * @returns {google.maps.Map|null} Instância do mapa
     */
    getMap() {
        return this.#map;
    }

    /**
     * Retorna os metadados do projeto atual
     * 
     * @returns {Object|null} Metadados ou null
     */
    getCurrentMetadata() {
        return this.#currentMetadata;
    }

    /**
     * Limpa recursos
     */
    destroy() {
        if (this.#currentTileLayer && this.#map) {
            this.#map.overlayMapTypes.removeAt(0);
        }
        this.#currentTileLayer = null;
        this.#currentMetadata = null;
        this.#currentTilesBasePath = null;
    }
}
