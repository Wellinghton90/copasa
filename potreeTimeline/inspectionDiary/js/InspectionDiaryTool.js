/**
 * Ferramenta do Diário de fiscalização: UI do painel (cabeçalho, lista, formulário),
 * lista todas as anotações do usuário (sem filtro por projeto). Nova/editar anotação
 * com references por contexto (project + methodology). Usa diaryApi e getCurrentUserId().
 * Horários sempre em America/Sao_Paulo (getSaoPauloDateTimeString, formatDate).
 */

import { getCurrentUserId, getCurrentUserDisplay, getCurrentUserRole } from './currentUser.js';
import * as diaryApi from './diaryApi.js';
import { getImageUrlForProject } from '../../js/Frustum/imagePath.js';

/** Valor de metodologia no JSON quando o dropdown não estiver disponível. */
const FALLBACK_METHODOLOGY = 'Point cloud';

/** Cor padrão do pin (hex CSS). */
const DEFAULT_PIN_COLOR = '#025e73';

function getCurrentMethodology() {
    return (typeof window !== 'undefined' && window.currentPlatformValue) || FALLBACK_METHODOLOGY;
}

function getCurrentProjectId() {
    return typeof window.currentProjectId !== 'undefined' ? window.currentProjectId : '';
}

/**
 * Encontra ou cria referência para (project, methodology) no array. Usado ao compor nova anotação.
 * @param {Array} refsArray - Array de refs { project, methodology, images, pins, objects }
 * @returns {{ methodology: string, project: string, images: string[], pins: Array, objects: string }}
 */
function getOrCreateRef(refsArray, project, methodology) {
    const m = methodology || FALLBACK_METHODOLOGY;
    let ref = refsArray.find((r) => r.project === project && (r.methodology || '') === m);
    if (!ref) {
        ref = { methodology: m, project, images: [], pins: [], objects: '' };
        refsArray.push(ref);
    }
    return ref;
}

/**
 * Retorna a referência do contexto atual (project + methodology) em entry.references, ou null.
 * @param {Object} entry - Entrada com references[]
 * @returns {{ methodology: string, project: string, images: string[], pins: Array, objects: string }|null}
 */
function getCurrentContextRef(entry) {
    const projectId = typeof window.currentProjectId !== 'undefined' ? window.currentProjectId : '';
    const methodology = getCurrentMethodology();
    const refs = Array.isArray(entry.references) ? entry.references : [];
    return refs.find((r) => r.project === projectId && (r.methodology || '') === methodology) || null;
}

/**
 * Garante que entry.references existe e atualiza ou insere a referência do contexto atual.
 * Se não houver imagens, pins ou objetos, remove a referência do contexto (se existir) e não cria uma nova.
 * @param {Object} entry - Entrada a alterar
 * @param {{ images: string[], pins: Array, objects: string }} refData
 */
function setCurrentContextRef(entry, refData) {
    const projectId = typeof window.currentProjectId !== 'undefined' ? window.currentProjectId : '';
    const methodology = getCurrentMethodology();
    if (!Array.isArray(entry.references)) entry.references = [];
    const existing = entry.references.findIndex((r) => r.project === projectId && (r.methodology || '') === methodology);
    const images = Array.isArray(refData.images) ? [...refData.images] : [];
    const pins = Array.isArray(refData.pins)
        ? refData.pins.map((p) => ({ x: p.x, y: p.y, z: p.z, color: p.color || DEFAULT_PIN_COLOR }))
        : [];
    const objects = refData.objects || '';
    const hasContent =
        images.length > 0 ||
        pins.length > 0 ||
        (typeof objects === 'string' && objects.trim() !== '');

    // Se não há conteúdo, remove referência existente (se houver) e encerra.
    if (!hasContent) {
        if (existing >= 0) {
            entry.references.splice(existing, 1);
        }
        if (!entry.references.length) entry.references = [];
        return;
    }

    const ref = {
        methodology,
        project: projectId,
        images,
        pins,
        objects
    };
    if (existing >= 0) {
        entry.references[existing] = ref;
    } else {
        entry.references.push(ref);
    }
}

/**
 * Gera um id único para uma entrada.
 * @returns {string}
 */
function generateEntryId() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    return 'id_' + Date.now() + '_' + Math.random().toString(36).slice(2);
}

/** Fuso padrão: America/Sao_Paulo (todos os horários neste padrão). */
const TIMEZONE_SAO_PAULO = 'America/Sao_Paulo';

/**
 * Retorna data/hora no fuso America/Sao_Paulo (padrão único para todos).
 * @param {Date} [date] - Data a usar (default: agora)
 * @returns {string} Ex.: "2026-02-25T16:36:55.972"
 */
function getSaoPauloDateTimeString(date = new Date()) {
    const d = date instanceof Date ? date : new Date(date);
    const opts = {
        timeZone: TIMEZONE_SAO_PAULO,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    };
    const parts = new Intl.DateTimeFormat('en-CA', opts).formatToParts(d);
    const get = (type) => parts.find((p) => p.type === type)?.value ?? '';
    const ms = String(d.getUTCMilliseconds()).padStart(3, '0');
    return `${get('year')}-${get('month')}-${get('day')}T${get('hour')}:${get('minute')}:${get('second')}.${ms}`;
}

/**
 * Formata data para exibição (pt-BR) em horário America/Sao_Paulo.
 * Strings sem Z são interpretadas como America/Sao_Paulo (-03:00).
 * @param {string} iso - Data em ISO (com Z/offset ou sem = America/Sao_Paulo)
 * @returns {string}
 */
function formatDate(iso) {
    if (!iso) return '';
    try {
        const isoWithTz = iso.endsWith('Z') || /[+-]\d{2}:\d{2}$/.test(iso) ? iso : iso + '-03:00';
        const d = new Date(isoWithTz);
        return d.toLocaleString('pt-BR', {
            timeZone: TIMEZONE_SAO_PAULO,
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch {
        return iso;
    }
}

export class InspectionDiaryTool {
    constructor() {
        this.containerEl = null;
        this.listEl = null;
        this.formSectionEl = null;
        this.newEntryBtnEl = null;
        this.formBodyEl = null;
        this.formTitleEl = null;
        this.formDescriptionEl = null;
        // Campos opcionais (armazenados em memória; editados via modal)
        this.optionalRegulatoryFramework = '';
        this.optionalAssociatedRisk = '';
        this.optionalRiskLevel = '';
        this.optionalTechnicalRecommendation = '';
        this.optionalModalEl = null;
        this.optionalModalRegulatoryEl = null;
        this.optionalModalAssociatedRiskEl = null;
        this.optionalModalRiskLevelEl = null;
        this.optionalModalTechnicalEl = null;
        this.addBtn = null;
        this.cancelBtn = null;
        this.data = null;
        this.editingEntryId = null;
        /** Array de nomes de imagens da anotação atual (usado em edição = ref do contexto atual). */
        this.currentEntryImages = [];
        /** Array de pins UTM da anotação atual (usado em edição = ref do contexto atual). */
        this.currentEntryPins = [];
        /** Ao compor nova anotação: refs por projeto/metodologia. Cada ref = { project, methodology, images, pins, objects }. */
        this.currentEntryRefs = [];
        /** Cor atual usada para novos pins. */
        this.currentPinColor = DEFAULT_PIN_COLOR;
        /** Índice do pin atualmente exibido/arrastado (edição). */
        this.currentActivePinIndex = null;
        this.plusMenuEl = null;
        this.refsRowEl = null;
        this.refsModalEl = null;
        this.refsModalBodyEl = null;
        /** Caixa flutuante "Cor do pin" (canto inf. direito), visível só ao clicar Pin até colocar o pin. */
        this.pinColorFloatEl = null;
        /** Índice do pin selecionado na nuvem (modo edição de cor/posição). null = nenhum. */
        this._selectedPinIndexForEditing = null;
    }

    /**
     * Inicializa a ferramenta: monta o painel e registra callbacks.
     */
    init() {
        const container = document.getElementById('right_panel_diario');
        if (!container) return;

        this.containerEl = container;
        this._buildPanel();
        this._bindProjectChange();

        window.addEventListener('projectchange', () => this._refreshList());

        window.onInspectionDiaryPanelOpen = () => this._onPanelOpen();
    }

    /**
     * Monta o DOM do painel (cabeçalho, usuário, lista, formulário).
     * @private
     */
    _buildPanel() {
        const role = getCurrentUserRole();
        const canEdit = role === 'admin' || role === 'editor';
        const canAdd = canEdit;
        this.containerEl.innerHTML = `
            <div class="diary-panel-header">
                <h3 class="diary-panel-title">Diário de fiscalização</h3>
                <button type="button" class="diary-panel-close-btn" title="Fechar" aria-label="Fechar">×</button>
            </div>
            <div class="diary-panel-list" data-diary-list></div>
            <div class="diary-form-section" data-diary-form-section style="${canAdd ? '' : 'display:none;'}">
                <button type="button" class="diary-btn diary-btn-new-entry" data-diary-new-entry>Nova anotação</button>
                <div class="diary-form-body" data-diary-form-body style="display:none;">
                    <div class="diary-form-fields">
                        <label for="diary_title" class="diary-field-label">Título</label>
                        <input type="text" id="diary_title" data-diary-title placeholder="Título (obrigatório)" class="diary-input" />
                        <label for="diary_description" class="diary-field-label">Descrição</label>
                        <textarea id="diary_description" data-diary-description placeholder="Digite sua descrição..." class="diary-textarea"></textarea>
                    </div>
                    <div class="diary-refs-row" data-diary-refs-row style="display:none;"></div>
                    <div class="diary-form-actions">
                        <div class="diary-form-actions-left">
                            <button type="button" class="diary-btn diary-btn-plus" data-diary-plus title="Adicionar referência" aria-label="Adicionar referência">+</button>
                            <div class="diary-plus-menu" data-diary-plus-menu role="menu" aria-hidden="true">
                                <button type="button" class="diary-plus-menu-item" data-diary-ref-photo-point role="menuitem">
                                    <span class="diary-plus-menu-icon">&#128247;</span>
                                    <span class="diary-plus-menu-label">Foto do ponto</span>
                                </button>
                                <button type="button" class="diary-plus-menu-item" data-diary-ref-photo-area role="menuitem">
                                    <span class="diary-plus-menu-icon">&#128992;</span>
                                    <span class="diary-plus-menu-label">Foto área</span>
                                </button>
                                <button type="button" class="diary-plus-menu-item" data-diary-ref-select-point role="menuitem">
                                    <span class="diary-plus-menu-icon">&#128205;</span>
                                    <span class="diary-plus-menu-label">Pin</span>
                                </button>
                                <button type="button" class="diary-plus-menu-item" data-diary-optional-fields role="menuitem">
                                    <span class="diary-plus-menu-icon">⚙️</span>
                                    <span class="diary-plus-menu-label">Campos adicionais</span>
                                </button>
                            </div>
                        </div>
                        <div class="diary-form-actions-right">
                            <button type="button" class="diary-btn" data-diary-cancel style="display:none;">Cancelar</button>
                            <button type="button" class="diary-btn diary-btn-primary" data-diary-add>Adicionar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.listEl = this.containerEl.querySelector('[data-diary-list]');
        this.formSectionEl = this.containerEl.querySelector('[data-diary-form-section]');
        this.newEntryBtnEl = this.containerEl.querySelector('[data-diary-new-entry]');
        this.formBodyEl = this.containerEl.querySelector('[data-diary-form-body]');
        this.formTitleEl = this.containerEl.querySelector('[data-diary-title]');
        this.formDescriptionEl = this.containerEl.querySelector('[data-diary-description]');
        this.refsRowEl = this.containerEl.querySelector('[data-diary-refs-row]');
        this.addBtn = this.containerEl.querySelector('[data-diary-add]');
        this.cancelBtn = this.containerEl.querySelector('[data-diary-cancel]');
        this.plusMenuEl = this.containerEl.querySelector('[data-diary-plus-menu]');

        this._buildPinColorFloatingBox();
        this._buildRefsModal();
        this._buildOptionalFieldsModal();

        // Ref ao input de cor (dentro da caixa flutuante) para _startEdit/_cancelEdit
        this.pinColorInputEl = this.pinColorFloatEl ? this.pinColorFloatEl.querySelector('[data-diary-pin-color]') : null;

        // Callbacks quando o usuário seleciona/deseleciona um pin na nuvem (contorno + caixa de cor)
        window.onDiaryPinSelected = (pinIndex) => {
            this._selectedPinIndexForEditing = pinIndex;
            const pins = this._getCurrentPinsForViewer();
            const pin = pins[pinIndex];
            const color = (pin && (pin.color || DEFAULT_PIN_COLOR)) || DEFAULT_PIN_COLOR;
            this.currentPinColor = color;
            if (this.pinColorInputEl) this.pinColorInputEl.value = color;
            this._showPinColorFloat();
        };
        window.onDiaryPinDeselected = () => {
            this._selectedPinIndexForEditing = null;
            this._hidePinColorFloat();
        };

        const plusBtn = this.containerEl.querySelector('[data-diary-plus]');
        if (plusBtn) plusBtn.addEventListener('click', (e) => this._togglePlusMenu(e));
        const photoPointBtn = this.containerEl.querySelector('[data-diary-ref-photo-point]');
        if (photoPointBtn) photoPointBtn.addEventListener('click', () => this._onAddRefPhotoPoint());
        const photoAreaBtn = this.containerEl.querySelector('[data-diary-ref-photo-area]');
        if (photoAreaBtn) photoAreaBtn.addEventListener('click', () => this._onAddRefPhotoArea());
        const selectPointBtn = this.containerEl.querySelector('[data-diary-ref-select-point]');
        if (selectPointBtn) selectPointBtn.addEventListener('click', () => this._onAddRefSelectPoint());
        const optionalFieldsBtn = this.containerEl.querySelector('[data-diary-optional-fields]');
        if (optionalFieldsBtn) optionalFieldsBtn.addEventListener('click', () => this._onOptionalFieldsClick());

        this._bindClosePlusMenu();

        if (this.addBtn) this.addBtn.addEventListener('click', () => this._addEntry());
        if (this.cancelBtn) this.cancelBtn.addEventListener('click', () => this._cancelEdit());
        if (this.newEntryBtnEl) {
            this.newEntryBtnEl.addEventListener('click', () => {
                if (this.newEntryBtnEl) this.newEntryBtnEl.style.display = 'none';
                if (this.formBodyEl) this.formBodyEl.style.display = 'block';
                if (this.cancelBtn) this.cancelBtn.style.display = 'none';
            });
        }
    }

    /**
     * Abre ou fecha o menu do +.
     * @private
     */
    _togglePlusMenu(e) {
        e.stopPropagation();
        if (!this.plusMenuEl) return;
        const isOpen = this.plusMenuEl.getAttribute('aria-hidden') !== 'true';
        this.plusMenuEl.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        this.plusMenuEl.classList.toggle('diary-plus-menu-open', !isOpen);
    }

    /**
     * Fecha o menu do + ao clicar fora.
     * @private
     */
    _bindClosePlusMenu() {
        const close = () => {
            if (this.plusMenuEl && this.plusMenuEl.classList.contains('diary-plus-menu-open')) {
                this.plusMenuEl.setAttribute('aria-hidden', 'true');
                this.plusMenuEl.classList.remove('diary-plus-menu-open');
            }
        };
        document.addEventListener('click', close);
        // Guardar referência para possível remoção; no init não é crítico
    }

    /**
     * Modo "Foto do ponto": ativa ferramenta e espera seleção no painel.
     * @private
     */
    _onAddRefPhotoPoint() {
        if (this.plusMenuEl) {
            this.plusMenuEl.setAttribute('aria-hidden', 'true');
            this.plusMenuEl.classList.remove('diary-plus-menu-open');
        }
        window.diaryReferenceMode = 'photoPoint';
        window.onDiaryReferencePhotoPointSelected = (name) => {
            if (typeof name === 'string' && name.trim()) {
                const n = name.trim();
                if (this.editingEntryId) {
                    this.currentEntryImages = this.currentEntryImages || [];
                    if (!this.currentEntryImages.includes(n)) {
                        this.currentEntryImages.push(n);
                        this._refreshRefsRow();
                    }
                } else {
                    const ref = getOrCreateRef(this.currentEntryRefs, getCurrentProjectId(), getCurrentMethodology());
                    if (!ref.images.includes(n)) {
                        ref.images.push(n);
                        this._refreshRefsRow();
                    }
                }
            }
        };
        if (typeof window.setFotosToolActive === 'function') {
            window.setFotosToolActive(true);
        }
        if (typeof window.setLeftPanelMode === 'function') {
            window.setLeftPanelMode('fotos');
        }
    }

    /**
     * Modo "Foto área": espera clique em frustum.
     * @private
     */
    _onAddRefPhotoArea() {
        if (this.plusMenuEl) {
            this.plusMenuEl.setAttribute('aria-hidden', 'true');
            this.plusMenuEl.classList.remove('diary-plus-menu-open');
        }
        // Sair do modo "Foto do ponto" se estiver ativo: fechar painel lateral e limpar callbacks.
        if (window.diaryReferenceMode === 'photoPoint') {
            window.diaryReferenceMode = null;
            window.onDiaryReferencePhotoPointSelected = null;
            if (typeof window.setFotosToolActive === 'function') {
                window.setFotosToolActive(false);
            }
            if (typeof window.setLeftPanelMode === 'function') {
                window.setLeftPanelMode(null);
            }
        }
        window.diaryReferenceMode = 'photoArea';
        window.onFrustumClickAddToDiaryReference = (cameraInfoWithImage) => {
            const name = cameraInfoWithImage && (cameraInfoWithImage.name || cameraInfoWithImage.cameraName);
            if (typeof name === 'string' && name.trim()) {
                const n = name.trim();
                if (this.editingEntryId) {
                    this.currentEntryImages = this.currentEntryImages || [];
                    if (!this.currentEntryImages.includes(n)) {
                        this.currentEntryImages.push(n);
                        this._refreshRefsRow();
                    }
                } else {
                    const ref = getOrCreateRef(this.currentEntryRefs, getCurrentProjectId(), getCurrentMethodology());
                    if (!ref.images.includes(n)) {
                        ref.images.push(n);
                        this._refreshRefsRow();
                    }
                }
            }
            window.diaryReferenceMode = null;
            window.onFrustumClickAddToDiaryReference = null;
            if (typeof window.restoreStatusMessage === 'function') {
                window.restoreStatusMessage();
            }
        };
        if (typeof window.setStatusMessage === 'function' && typeof window.STATUS_MESSAGE_PHOTO_AREA !== 'undefined') {
            window.setStatusMessage(window.STATUS_MESSAGE_PHOTO_AREA);
        }
    }

    /**
     * Modo "Selecionar ponto": ativa ferramenta e espera cliques na nuvem; exibe UTM na status bar. Esc para cancelar.
     * @private
     */
    _onAddRefSelectPoint() {
        if (this.plusMenuEl) {
            this.plusMenuEl.setAttribute('aria-hidden', 'true');
            this.plusMenuEl.classList.remove('diary-plus-menu-open');
        }
        // Sair dos modos "Foto do ponto" e "Foto área" se estiverem ativos
        if (window.diaryReferenceMode === 'photoPoint') {
            window.diaryReferenceMode = null;
            window.onDiaryReferencePhotoPointSelected = null;
            if (typeof window.setFotosToolActive === 'function') {
                window.setFotosToolActive(false);
            }
            if (typeof window.setLeftPanelMode === 'function') {
                window.setLeftPanelMode(null);
            }
        }
        if (window.diaryReferenceMode === 'photoArea') {
            window.diaryReferenceMode = null;
            window.onFrustumClickAddToDiaryReference = null;
            if (typeof window.restoreStatusMessage === 'function') {
                window.restoreStatusMessage();
            }
        }
        this._showPinColorFloat();
        window.diaryReferenceMode = 'selectPoint';
        window.onSelectPointPointAdded = (point) => {
            if (point && typeof point.x === 'number' && typeof point.y === 'number' && typeof point.z === 'number') {
                this._hidePinColorFloat();
                const color = this.currentPinColor || DEFAULT_PIN_COLOR;
                const pt = { x: point.x, y: point.y, z: point.z, color };
                if (this.editingEntryId) {
                    if (!Array.isArray(this.currentEntryPins)) this.currentEntryPins = [];
                    this.currentEntryPins.push(pt);
                    this.currentActivePinIndex = this.currentEntryPins.length - 1;
                } else {
                    const ref = getOrCreateRef(this.currentEntryRefs, getCurrentProjectId(), getCurrentMethodology());
                    if (!Array.isArray(ref.pins)) ref.pins = [];
                    ref.pins.push(pt);
                }
                this._refreshRefsRow();
                this._updateDiaryPinsInViewer();
                if (this.editingEntryId) {
                    this._setDiaryPinDragCallback();
                }
            }
        };
        window.onSelectPointToolDeactivate = () => {
            this._hidePinColorFloat();
        };
        if (typeof window.setSelectPointToolActive === 'function') {
            window.setSelectPointToolActive(true);
        }
        if (typeof window.setStatusMessage === 'function' && typeof window.STATUS_MESSAGE_SELECT_POINT !== 'undefined') {
            window.setStatusMessage(window.STATUS_MESSAGE_SELECT_POINT);
        }
    }

    /**
     * Clique em "Campos adicionais" no menu +: abre o modal de campos opcionais.
     * @private
     */
    _onOptionalFieldsClick() {
        if (this.plusMenuEl) {
            this.plusMenuEl.setAttribute('aria-hidden', 'true');
            this.plusMenuEl.classList.remove('diary-plus-menu-open');
        }
        this._openOptionalFieldsModal();
    }

    /**
     * Atualiza a linha de chips de referências. Em edição: uma ref (contexto atual). Nova anotação: uma ref por projeto.
     * @private
     */
    _refreshRefsRow() {
        if (!this.refsRowEl) return;
        if (this.editingEntryId) {
            const list = this.currentEntryImages || [];
            const pins = this.currentEntryPins || [];
            const hasOptional =
                (this.optionalRegulatoryFramework && this.optionalRegulatoryFramework.trim() !== '') ||
                (this.optionalAssociatedRisk && this.optionalAssociatedRisk.trim() !== '') ||
                (this.optionalRiskLevel && this.optionalRiskLevel.trim() !== '') ||
                (this.optionalTechnicalRecommendation && this.optionalTechnicalRecommendation.trim() !== '');
            const hasRefs = list.length > 0 || pins.length > 0 || hasOptional;
            if (!hasRefs) {
                this.refsRowEl.style.display = 'none';
                this.refsRowEl.innerHTML = '';
                return;
            }
            this.refsRowEl.style.display = 'flex';
            this.refsRowEl.innerHTML = '';
            for (const name of list) {
                const chip = document.createElement('span');
                chip.className = 'diary-ref-chip';
                chip.innerHTML = '<span class="diary-ref-chip-label">' + escapeHtml(name) + '</span><button type="button" class="diary-ref-chip-remove" title="Remover" aria-label="Remover">×</button>';
                const removeBtn = chip.querySelector('.diary-ref-chip-remove');
                removeBtn.addEventListener('click', () => {
                    this.currentEntryImages = this.currentEntryImages.filter((n) => n !== name);
                    this._refreshRefsRow();
                });
                this.refsRowEl.appendChild(chip);
            }
            if (pins.length > 0) {
                const chip = document.createElement('span');
                chip.className = 'diary-ref-chip';
                const label = pins.length === 1 ? '1 Pin' : pins.length + ' Pins';
                chip.innerHTML = '<span class="diary-ref-chip-label">' + label + '</span><button type="button" class="diary-ref-chip-remove" title="Remover" aria-label="Remover">×</button>';
                const removeBtn = chip.querySelector('.diary-ref-chip-remove');
                removeBtn.addEventListener('click', () => {
                    this.currentEntryPins = [];
                    this.currentActivePinIndex = null;
                    this._updateDiaryPinsInViewer();
                    window.onDiaryPinPositionUpdated = null;
                    this._refreshRefsRow();
                });
                this.refsRowEl.appendChild(chip);
            }
            if (hasOptional) {
                const optChip = document.createElement('span');
                optChip.className = 'diary-ref-chip';
                optChip.innerHTML = '<span class="diary-ref-chip-label">Campos adicionais</span>';
                optChip.title = 'Editar campos adicionais';
                optChip.addEventListener('click', () => this._openOptionalFieldsModal());
                this.refsRowEl.appendChild(optChip);
            }
            const verTodas = document.createElement('button');
            verTodas.type = 'button';
            verTodas.className = 'diary-refs-ver-todas';
            verTodas.innerHTML = '&#8599;';
            verTodas.title = 'Ver todas as referências';
            verTodas.setAttribute('aria-label', 'Ver todas as referências');
            verTodas.addEventListener('click', () => this._openRefsModal());
            this.refsRowEl.appendChild(verTodas);
            return;
        }
        const refs = this.currentEntryRefs || [];
        const hasOptional =
            (this.optionalRegulatoryFramework && this.optionalRegulatoryFramework.trim() !== '') ||
            (this.optionalAssociatedRisk && this.optionalAssociatedRisk.trim() !== '') ||
            (this.optionalRiskLevel && this.optionalRiskLevel.trim() !== '') ||
            (this.optionalTechnicalRecommendation && this.optionalTechnicalRecommendation.trim() !== '');
        const hasRefs = hasOptional || refs.some((r) => (r.images && r.images.length > 0) || (r.pins && r.pins.length > 0));
        if (!hasRefs) {
            this.refsRowEl.style.display = 'none';
            this.refsRowEl.innerHTML = '';
            return;
        }
        this.refsRowEl.style.display = 'flex';
        this.refsRowEl.innerHTML = '';
        refs.forEach((ref, refIndex) => {
            const ni = (ref.images && ref.images.length) || 0;
            const np = (ref.pins && ref.pins.length) || 0;
            if (ni === 0 && np === 0) return;
            const pinLabel = np ? (np === 1 ? '1 Pin' : np + ' Pins') : '';
            const label =
                (ref.project || 'Projeto') +
                ': ' +
                (ni ? ni + ' foto' + (ni !== 1 ? 's' : '') : '') +
                (ni && pinLabel ? ', ' : '') +
                pinLabel;
            const chip = document.createElement('span');
            chip.className = 'diary-ref-chip';
            chip.innerHTML = '<span class="diary-ref-chip-label">' + escapeHtml(label) + '</span><button type="button" class="diary-ref-chip-remove" title="Remover referências deste projeto" aria-label="Remover">×</button>';
            const removeBtn = chip.querySelector('.diary-ref-chip-remove');
            removeBtn.addEventListener('click', () => {
                this.currentEntryRefs.splice(refIndex, 1);
                this._refreshRefsRow();
            });
            this.refsRowEl.appendChild(chip);
        });
        if (hasOptional) {
            const optChip = document.createElement('span');
            optChip.className = 'diary-ref-chip';
            optChip.innerHTML = '<span class="diary-ref-chip-label">Campos adicionais</span>';
            optChip.title = 'Editar campos adicionais';
            optChip.addEventListener('click', () => this._openOptionalFieldsModal());
            this.refsRowEl.appendChild(optChip);
        }
        const verTodas = document.createElement('button');
        verTodas.type = 'button';
        verTodas.className = 'diary-refs-ver-todas';
        verTodas.innerHTML = '&#8599;';
        verTodas.title = 'Ver todas as referências';
        verTodas.setAttribute('aria-label', 'Ver todas as referências');
        verTodas.addEventListener('click', () => this._openRefsModal());
        this.refsRowEl.appendChild(verTodas);
    }

    /**
     * Cria a caixa flutuante "Cor do pin" centralizada no topo da área do Potree.
     * Visível só após clicar em Pin até colocar o pin.
     * @private
     */
    _buildPinColorFloatingBox() {
        const box = document.createElement('div');
        box.className = 'diary-pin-color-float';
        box.setAttribute('aria-hidden', 'true');
        box.innerHTML = `
            <span class="diary-pin-color-float-label">Cor do pin</span>
            <input type="color" data-diary-pin-color value="${DEFAULT_PIN_COLOR}" />
        `;
        const input = box.querySelector('[data-diary-pin-color]');
        if (input) {
            input.addEventListener('input', () => {
                const v = input.value;
                this.currentPinColor = typeof v === 'string' && v.trim() ? v : DEFAULT_PIN_COLOR;
                // Se há um pin selecionado na nuvem, atualiza a cor dele em tempo real
                if (this._selectedPinIndexForEditing != null && typeof window.setDiaryPinColor === 'function') {
                    this._setPinColorAtCurrentRef(this._selectedPinIndexForEditing, this.currentPinColor);
                    window.setDiaryPinColor(this._selectedPinIndexForEditing, this.currentPinColor);
                }
            });
        }
        this.pinColorFloatEl = box;
        document.body.appendChild(box);
    }

    _showPinColorFloat() {
        if (!this.pinColorFloatEl) return;
        if (this.pinColorInputEl) this.pinColorInputEl.value = this.currentPinColor || DEFAULT_PIN_COLOR;
        this.pinColorFloatEl.classList.add('diary-pin-color-float-visible');
        this.pinColorFloatEl.setAttribute('aria-hidden', 'false');
    }

    _hidePinColorFloat() {
        if (!this.pinColorFloatEl) return;
        this.pinColorFloatEl.classList.remove('diary-pin-color-float-visible');
        this.pinColorFloatEl.setAttribute('aria-hidden', 'true');
    }

    /**
     * Atualiza a cor do pin no índice da referência atual (edição ou nova anotação).
     * @param {number} pinIndex
     * @param {string} color
     * @private
     */
    _setPinColorAtCurrentRef(pinIndex, color) {
        if (this.editingEntryId) {
            if (Array.isArray(this.currentEntryPins) && this.currentEntryPins[pinIndex]) {
                this.currentEntryPins[pinIndex].color = color;
            }
        } else {
            const ref = getOrCreateRef(this.currentEntryRefs, getCurrentProjectId(), getCurrentMethodology());
            if (Array.isArray(ref.pins) && ref.pins[pinIndex]) {
                ref.pins[pinIndex].color = color;
            }
        }
    }

    /**
     * Cria o modal de referências (Imagens, Vídeos, Objetos) e anexa ao body.
     * @private
     */
    _buildRefsModal() {
        const overlay = document.createElement('div');
        overlay.className = 'diary-refs-modal';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML = `
            <div class="diary-refs-modal-backdrop"></div>
            <div class="diary-refs-modal-content">
                <div class="diary-refs-modal-header">
                    <h3 class="diary-refs-modal-title">Referências da anotação</h3>
                    <button type="button" class="diary-refs-modal-close" title="Fechar" aria-label="Fechar">×</button>
                </div>
                <div class="diary-refs-modal-body" data-diary-refs-modal-body></div>
            </div>
        `;
        const backdrop = overlay.querySelector('.diary-refs-modal-backdrop');
        const closeBtn = overlay.querySelector('.diary-refs-modal-close');
        const close = () => this._closeRefsModal();
        if (backdrop) backdrop.addEventListener('click', close);
        if (closeBtn) closeBtn.addEventListener('click', close);
        this.refsModalEl = overlay;
        this.refsModalBodyEl = overlay.querySelector('[data-diary-refs-modal-body]');
        document.body.appendChild(overlay);
    }

    /**
     * Cria o modal de campos opcionais (Enquadramento, Risco, etc.) e anexa ao body.
     * @private
     */
    _buildOptionalFieldsModal() {
        const overlay = document.createElement('div');
        overlay.className = 'diary-refs-modal diary-optional-modal';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML = `
            <div class="diary-refs-modal-backdrop"></div>
            <div class="diary-refs-modal-content">
                <div class="diary-refs-modal-header">
                    <h3 class="diary-refs-modal-title">Campos adicionais da anotação</h3>
                    <button type="button" class="diary-refs-modal-close" title="Fechar" aria-label="Fechar">×</button>
                </div>
                <div class="diary-refs-modal-body diary-optional-modal-body">
                    <div class="diary-optional-field-row">
                        <label class="diary-field-label" for="diary_optional_regulatory">Enquadramento Normativo</label>
                        <input type="text" id="diary_optional_regulatory" class="diary-input" />
                    </div>
                    <div class="diary-optional-field-row">
                        <label class="diary-field-label" for="diary_optional_technical">Recomendação Técnica</label>
                        <input type="text" id="diary_optional_technical" class="diary-input" />
                    </div>
                    <div class="diary-optional-field-row">
                        <label class="diary-field-label" for="diary_optional_risk_level">Nível de Risco</label>
                        <select id="diary_optional_risk_level" class="diary-select">
                            <option value="">Selecione</option>
                            <option value="Positivo">Positivo</option>
                            <option value="Leve">Leve</option>
                            <option value="Médio">Médio</option>
                            <option value="Crítico">Crítico</option>
                        </select>
                    </div>
                    <div class="diary-optional-field-row">
                        <span class="diary-field-label">Risco Associado</span>
                        <div class="diary-optional-checkbox-group" id="diary_optional_associated_risk_group">
                            <label class="diary-optional-checkbox">
                                <input type="checkbox" value="Saúde e Segurança do Trabalho">
                                <span>Saúde e Segurança do Trabalho</span>
                            </label>
                            <label class="diary-optional-checkbox">
                                <input type="checkbox" value="Sinalização">
                                <span>Sinalização</span>
                            </label>
                            <label class="diary-optional-checkbox">
                                <input type="checkbox" value="Qualidade da Obra">
                                <span>Qualidade da Obra</span>
                            </label>
                            <label class="diary-optional-checkbox">
                                <input type="checkbox" value="Qualidade da Documentação">
                                <span>Qualidade da Documentação</span>
                            </label>
                            <label class="diary-optional-checkbox">
                                <input type="checkbox" value="Impacto Ambiental">
                                <span>Impacto Ambiental</span>
                            </label>
                            <label class="diary-optional-checkbox">
                                <input type="checkbox" value="Impacto de Vizinhança">
                                <span>Impacto de Vizinhança</span>
                            </label>
                            <label class="diary-optional-checkbox">
                                <input type="checkbox" value="Materiais e Insumos">
                                <span>Materiais e Insumos</span>
                            </label>
                            <label class="diary-optional-checkbox">
                                <input type="checkbox" value="Veículos, Máquinas e Equipamentos">
                                <span>Veículos, Máquinas e Equipamentos</span>
                            </label>
                            <label class="diary-optional-checkbox">
                                <input type="checkbox" value="Produtividade e Eficiência">
                                <span>Produtividade e Eficiência</span>
                            </label>
                            <label class="diary-optional-checkbox">
                                <input type="checkbox" value="Cronograma">
                                <span>Cronograma</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="diary-optional-modal-footer">
                    <button type="button" class="diary-btn" data-diary-optional-cancel>Cancelar</button>
                    <button type="button" class="diary-btn diary-btn-primary" data-diary-optional-save>Salvar</button>
                </div>
            </div>
        `;
        const backdrop = overlay.querySelector('.diary-refs-modal-backdrop');
        const closeBtn = overlay.querySelector('.diary-refs-modal-close');
        const cancelBtn = overlay.querySelector('[data-diary-optional-cancel]');
        const saveBtn = overlay.querySelector('[data-diary-optional-save]');
        const close = () => this._closeOptionalFieldsModal();
        if (backdrop) backdrop.addEventListener('click', close);
        if (closeBtn) closeBtn.addEventListener('click', close);
        if (cancelBtn) cancelBtn.addEventListener('click', close);
        if (saveBtn) saveBtn.addEventListener('click', () => this._applyOptionalFieldsFromModal());
        this.optionalModalEl = overlay;
        this.optionalModalRegulatoryEl = overlay.querySelector('#diary_optional_regulatory');
        this.optionalModalAssociatedRiskEl = overlay.querySelector('#diary_optional_associated_risk_group');
        this.optionalModalRiskLevelEl = overlay.querySelector('#diary_optional_risk_level');
        this.optionalModalTechnicalEl = overlay.querySelector('#diary_optional_technical');
        document.body.appendChild(overlay);
    }

    /**
     * Abre o modal de referências e preenche com dados atuais.
     * @private
     */
    _openRefsModal() {
        if (!this.refsModalEl || !this.refsModalBodyEl) return;
        this.refsModalEl.classList.add('diary-refs-modal-open');
        this.refsModalEl.setAttribute('aria-hidden', 'false');
        this._renderRefsModalBody();
    }

    /**
     * Fecha o modal de referências.
     * @private
     */
    _closeRefsModal() {
        if (!this.refsModalEl) return;
        this.refsModalEl.classList.remove('diary-refs-modal-open');
        this.refsModalEl.setAttribute('aria-hidden', 'true');
    }

    /**
     * Abre o modal de campos opcionais preenchendo com o estado atual.
     * @private
     */
    _openOptionalFieldsModal() {
        if (!this.optionalModalEl) return;
        if (this.optionalModalRegulatoryEl) this.optionalModalRegulatoryEl.value = this.optionalRegulatoryFramework || '';
        if (this.optionalModalAssociatedRiskEl) {
            const current = (this.optionalAssociatedRisk || '').split(';').map((s) => s.trim()).filter(Boolean);
            const checkboxes = this.optionalModalAssociatedRiskEl.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach((cb) => {
                cb.checked = current.includes(cb.value);
            });
        }
        if (this.optionalModalRiskLevelEl) this.optionalModalRiskLevelEl.value = this.optionalRiskLevel || '';
        if (this.optionalModalTechnicalEl) this.optionalModalTechnicalEl.value = this.optionalTechnicalRecommendation || '';
        this.optionalModalEl.classList.add('diary-refs-modal-open');
        this.optionalModalEl.setAttribute('aria-hidden', 'false');
    }

    /**
     * Fecha o modal de campos opcionais.
     * @private
     */
    _closeOptionalFieldsModal() {
        if (!this.optionalModalEl) return;
        this.optionalModalEl.classList.remove('diary-refs-modal-open');
        this.optionalModalEl.setAttribute('aria-hidden', 'true');
    }

    /**
     * Lê valores do modal de campos opcionais e aplica ao estado atual.
     * @private
     */
    _applyOptionalFieldsFromModal() {
        if (this.optionalModalRegulatoryEl) {
            this.optionalRegulatoryFramework = this.optionalModalRegulatoryEl.value || '';
        }
        if (this.optionalModalAssociatedRiskEl) {
            const selected = Array.from(this.optionalModalAssociatedRiskEl.querySelectorAll('input[type="checkbox"]:checked')).map((o) => o.value);
            this.optionalAssociatedRisk = selected.join('; ');
        }
        if (this.optionalModalRiskLevelEl) {
            this.optionalRiskLevel = this.optionalModalRiskLevelEl.value || '';
        }
        if (this.optionalModalTechnicalEl) {
            this.optionalTechnicalRecommendation = this.optionalModalTechnicalEl.value || '';
        }
        this._refreshRefsRow();
        this._closeOptionalFieldsModal();
    }

    /**
     * Preenche o corpo do modal: seções Imagens, Vídeos, Objetos.
     * @private
     */
    /**
     * Formata coordenada de ponto para exibição (UTM).
     * @param {{ x: number, y: number, z: number }} pt
     * @param {number} [decimals]
     * @returns {string}
     */
    _formatPointCoords(pt, decimals = 2) {
        if (!pt || typeof pt.x !== 'number' || typeof pt.y !== 'number' || typeof pt.z !== 'number') return '—';
        const f = (n) => Number(n).toFixed(decimals);
        return `UTM: ${f(pt.x)}, ${f(pt.y)}, ${f(pt.z)}`;
    }

    /**
     * Formata ponto como "X: xxx, Y: yyy, Z: zzz" para o modal.
     * @param {{ x: number, y: number, z: number }} pt
     * @param {number} [decimals]
     * @returns {string}
     */
    _formatPointCoordsXyz(pt, decimals = 2) {
        if (!pt || typeof pt.x !== 'number' || typeof pt.y !== 'number' || typeof pt.z !== 'number') return '—';
        const f = (n) => Number(n).toFixed(decimals);
        return `X: ${f(pt.x)}, Y: ${f(pt.y)}, Z: ${f(pt.z)}`;
    }

    _renderRefsModalBody() {
        if (!this.refsModalBodyEl) return;
        const projectId = typeof window.currentProjectId !== 'undefined' ? window.currentProjectId : '';
        const methodology = getCurrentMethodology();

        const sectionImages = (title, items, refProject, editable, refIndex) => {
            if (items.length === 0) {
                return '<section class="diary-refs-modal-section"><h4 class="diary-refs-modal-section-title">' + escapeHtml(title) + '</h4><p class="diary-refs-modal-empty">Nenhum item.</p></section>';
            }
            const dataRef = refIndex !== undefined ? ' data-ref-index="' + refIndex + '"' : '';
            const lis = items
                .map((name, index) => {
                    const proj = refProject != null ? refProject : projectId;
                    const imgUrl = proj ? getImageUrlForProject(proj, name + '.JPG') : '';
                    const safeUrl = imgUrl ? imgUrl.replace(/"/g, '&quot;') : '';
                    const removeBtn = editable
                        ? '<button type="button" class="diary-refs-modal-item-remove" title="Excluir" data-index="' + index + '"' + dataRef + '>Excluir</button>'
                        : '';
                    return (
                        '<li class="diary-refs-modal-item diary-refs-modal-item-image">' +
                        '<div class="diary-refs-modal-item-image-wrap">' +
                        (safeUrl ? '<img src="' + safeUrl + '" alt="' + escapeHtml(name) + '" loading="lazy" />' : '') +
                        '<span class="diary-refs-modal-item-name">' + escapeHtml(name) + '</span>' +
                        '</div>' +
                        removeBtn +
                        '</li>'
                    );
                })
                .join('');
            return '<section class="diary-refs-modal-section"><h4 class="diary-refs-modal-section-title">' + escapeHtml(title) + '</h4><ul class="diary-refs-modal-list">' + lis + '</ul></section>';
        };
        const sectionPoints = (title, pts, editable, refIndex) => {
            if (!Array.isArray(pts) || pts.length === 0) {
                return '<section class="diary-refs-modal-section"><h4 class="diary-refs-modal-section-title">' + escapeHtml(title) + '</h4><p class="diary-refs-modal-empty">Nenhum ponto.</p></section>';
            }
            const dataRef = refIndex !== undefined ? ' data-ref-index="' + refIndex + '"' : '';
            const lis = pts
                .map((pt, index) => {
                    const coords = this._formatPointCoordsXyz(pt);
                    const removeBtn = editable
                        ? '<button type="button" class="diary-refs-modal-item-remove" title="Excluir" data-point-index="' + index + '"' + dataRef + '>Excluir</button>'
                        : '';
                    return (
                        '<li class="diary-refs-modal-item diary-refs-modal-item-point">' +
                        '<span class="diary-refs-modal-item-index">' + (index + 1) + '</span>' +
                        '<span class="diary-refs-modal-item-name">' + escapeHtml(coords) + '</span>' +
                        removeBtn +
                        '</li>'
                    );
                })
                .join('');
            return '<section class="diary-refs-modal-section"><h4 class="diary-refs-modal-section-title">' + escapeHtml(title) + '</h4><ul class="diary-refs-modal-list">' + lis + '</ul></section>';
        };
        const section = (title, items, editable, refIndex) => {
            if (items.length === 0) {
                return '<section class="diary-refs-modal-section"><h4 class="diary-refs-modal-section-title">' + escapeHtml(title) + '</h4><p class="diary-refs-modal-empty">Nenhum item.</p></section>';
            }
            const dataRef = refIndex !== undefined ? ' data-ref-index="' + refIndex + '"' : '';
            const lis = items
                .map(
                    (name, index) =>
                        '<li class="diary-refs-modal-item">' +
                        '<span class="diary-refs-modal-item-name">' + escapeHtml(name) + '</span>' +
                        (editable ? '<button type="button" class="diary-refs-modal-item-remove" title="Excluir" data-index="' + index + '"' + dataRef + '>Excluir</button>' : '') +
                        '</li>'
                )
                .join('');
            return '<section class="diary-refs-modal-section"><h4 class="diary-refs-modal-section-title">' + escapeHtml(title) + '</h4><ul class="diary-refs-modal-list">' + lis + '</ul></section>';
        };

        if (this.editingEntryId && this.data && Array.isArray(this.data.entries)) {
            const entry = this.data.entries.find((e) => e.id === this.editingEntryId);
            const refs = Array.isArray(entry && entry.references) ? entry.references : [];
            let html = '';
            for (const ref of refs) {
                const refTitle = (ref.project || 'Projeto') + ' (' + (ref.methodology || '') + ')';
                const isCurrentContext = ref.project === projectId && (ref.methodology || '') === methodology;
                const images = Array.isArray(ref.images) ? ref.images : [];
                const points = Array.isArray(ref.pins) ? ref.pins : [];
                const objects = ref.objects ? [ref.objects] : [];
                html +=
                    '<div class="diary-refs-modal-ref-block" data-ref-project="' +
                    escapeHtml(ref.project || '') +
                    '" data-ref-methodology="' +
                    escapeHtml(ref.methodology || '') +
                    '">' +
                    '<h4 class="diary-refs-modal-ref-title">' +
                    escapeHtml(refTitle) +
                    '</h4>' +
                    sectionImages('Imagens', images, ref.project, isCurrentContext, undefined) +
                    // Em edição, o modal mostra pins apenas para leitura; remoção é feita pela linha de chips.
                    sectionPoints('Pontos', points, false, undefined) +
                    section('Objetos', objects, isCurrentContext, undefined) +
                    '</div>';
            }
            if (refs.length === 0) {
                html = '<p class="diary-refs-modal-empty">Nenhuma referência.</p>';
            }
            this.refsModalBodyEl.innerHTML = html;
            this.refsModalBodyEl.querySelectorAll('.diary-refs-modal-ref-block').forEach((block) => {
                const isCurrent =
                    block.getAttribute('data-ref-project') === projectId &&
                    block.getAttribute('data-ref-methodology') === methodology;
                if (!isCurrent) return;
                block.querySelectorAll('.diary-refs-modal-item-remove[data-index]').forEach((btn) => {
                    const index = parseInt(btn.getAttribute('data-index'), 10);
                    if (Number.isNaN(index)) return;
                    btn.addEventListener('click', () => {
                        this.currentEntryImages = this.currentEntryImages.filter((_, i) => i !== index);
                        this._refreshRefsRow();
                        this._renderRefsModalBody();
                    });
                });
            });
        } else {
            const refs = this.currentEntryRefs || [];
            let html = '';
            refs.forEach((ref, refIndex) => {
                const refTitle = (ref.project || 'Projeto') + ' (' + (ref.methodology || '') + ')';
                const images = Array.isArray(ref.images) ? ref.images : [];
                const points = Array.isArray(ref.pins) ? ref.pins : [];
                const objects = ref.objects ? [ref.objects] : [];
                html +=
                    '<div class="diary-refs-modal-ref-block" data-ref-index="' +
                    refIndex +
                    '">' +
                    '<h4 class="diary-refs-modal-ref-title">' +
                    escapeHtml(refTitle) +
                    '</h4>' +
                    sectionImages('Imagens', images, ref.project, true, refIndex) +
                    sectionPoints('Pontos', points, true, refIndex) +
                    section('Objetos', objects, true, refIndex) +
                    '</div>';
            });
            if (refs.length === 0) {
                html = '<p class="diary-refs-modal-empty">Nenhuma referência.</p>';
            }
            this.refsModalBodyEl.innerHTML = html;
            this.refsModalBodyEl.querySelectorAll('.diary-refs-modal-item-remove[data-index][data-ref-index]').forEach((btn) => {
                const index = parseInt(btn.getAttribute('data-index'), 10);
                const refIdx = parseInt(btn.getAttribute('data-ref-index'), 10);
                if (Number.isNaN(index) || Number.isNaN(refIdx) || !this.currentEntryRefs[refIdx]) return;
                btn.addEventListener('click', () => {
                    this.currentEntryRefs[refIdx].images.splice(index, 1);
                    this._dropEmptyRefs();
                    this._refreshRefsRow();
                    this._renderRefsModalBody();
                });
            });
            this.refsModalBodyEl.querySelectorAll('.diary-refs-modal-item-remove[data-point-index][data-ref-index]').forEach((btn) => {
                const index = parseInt(btn.getAttribute('data-point-index'), 10);
                const refIdx = parseInt(btn.getAttribute('data-ref-index'), 10);
                if (Number.isNaN(index) || Number.isNaN(refIdx) || !this.currentEntryRefs[refIdx]) return;
                btn.addEventListener('click', () => {
                    const ref = this.currentEntryRefs[refIdx];
                    if (!Array.isArray(ref.pins)) ref.pins = [];
                    ref.pins.splice(index, 1);
                    this._dropEmptyRefs();
                    if (ref.project === getCurrentProjectId()) {
                        this._updateDiaryPinsInViewer();
                        if (!ref.pins || ref.pins.length === 0) window.onDiaryPinPositionUpdated = null;
                    }
                    this._refreshRefsRow();
                    this._renderRefsModalBody();
                });
            });
        }
    }

    /**
     * Define o callback de arraste do pin para atualizar o ponto ao soltar.
     * @private
     */
    _setDiaryPinDragCallback() {
        window.onDiaryPinPositionUpdated = (pinIndex, utm) => {
            const index = typeof pinIndex === 'number' && pinIndex >= 0 ? pinIndex : 0;
            const color = this.currentPinColor || DEFAULT_PIN_COLOR;

            if (this.editingEntryId && Array.isArray(this.currentEntryPins) && this.currentEntryPins.length > 0) {
                const i = index < this.currentEntryPins.length ? index : 0;
                const existing = this.currentEntryPins[i] || {};
                this.currentEntryPins[i] = { x: utm.x, y: utm.y, z: utm.z, color: existing.color || color };
            } else {
                const ref = getOrCreateRef(this.currentEntryRefs, getCurrentProjectId(), getCurrentMethodology());
                if (Array.isArray(ref.pins) && ref.pins[index]) {
                    const existing = ref.pins[index];
                    ref.pins[index] = { x: utm.x, y: utm.y, z: utm.z, color: existing.color || color };
                }
            }
            this._updateDiaryPinsInViewer();
            this._refreshRefsRow();
        };
    }

    /**
     * Retorna o array de pins do contexto atual (edição = currentEntryPins; nova = ref.pins do projeto atual).
     * @private
     */
    _getCurrentPinsForViewer() {
        if (this.editingEntryId) {
            return Array.isArray(this.currentEntryPins) ? this.currentEntryPins : [];
        }
        const refs = this.currentEntryRefs || [];
        const projectId = getCurrentProjectId();
        const methodology = getCurrentMethodology();
        const ref = refs.find((r) => r.project === projectId && (r.methodology || '') === (methodology || FALLBACK_METHODOLOGY));
        return ref && Array.isArray(ref.pins) ? ref.pins : [];
    }

    /**
     * Atualiza os pins exibidos na nuvem (todos os pins da anotação atual).
     * @private
     */
    _updateDiaryPinsInViewer() {
        if (typeof window.setDiaryPins !== 'function') return;
        const pins = this._getCurrentPinsForViewer();
        window.setDiaryPins(
            pins.map((p) => ({
                x: p.x,
                y: p.y,
                z: p.z,
                color: p.color || DEFAULT_PIN_COLOR
            })),
            getCurrentProjectId()
        );
        // Garante que o callback de arraste está definido sempre que há pins (edição ou nova anotação).
        if (pins.length > 0) this._setDiaryPinDragCallback();
    }

    _dropEmptyRefs() {
        if (!Array.isArray(this.currentEntryRefs)) return;
        this.currentEntryRefs = this.currentEntryRefs.filter(
            (r) => ((r.images && r.images.length) || (r.pins && r.pins.length))
        );
    }

    /**
     * Escuta mudança de projeto para re-renderizar a lista.
     * @private
     */
    _bindProjectChange() {
        const sel = document.getElementById('seletor_projeto');
        if (sel) {
            sel.addEventListener('change', () => this._refreshList());
        }
    }

    /**
     * Chamado quando o painel é aberto: carrega dados e renderiza a lista.
     * @private
     */
    async _onPanelOpen() {
        const userId = getCurrentUserId();
        try {
            this.data = await diaryApi.get(userId);
            this._refreshList();
        } catch (err) {
            this._showError(err.message);
        }
    }

    /**
     * Re-renderiza a lista com todas as anotações do usuário (sem filtro por projeto).
     * @private
     */
    _refreshList() {
        if (!this.listEl) return;
        if (!this.data || !Array.isArray(this.data.entries)) {
            this.listEl.innerHTML = '<p class="diary-empty">Nenhuma anotação.</p>';
            return;
        }
        if (this.data.entries.length === 0) {
            this.listEl.innerHTML = '<p class="diary-empty">Nenhuma anotação.</p>';
            return;
        }
        this.listEl.innerHTML = '';
        for (const entry of this.data.entries) {
            const card = this._createEntryCard(entry);
            this.listEl.appendChild(card);
        }
    }

    /**
     * Retorna o título para exibição na lista (entry.title ou fallback do início da descrição).
     * @param {Object} entry
     * @returns {string}
     * @private
     */
    _getEntryDisplayTitle(entry) {
        if (entry.title && String(entry.title).trim()) return String(entry.title).trim();
        const desc = entry.description ?? entry.text ?? '';
        const str = String(desc).trim();
        if (!str) return '(Sem título)';
        const firstLine = str.split('\n')[0];
        return firstLine.length > 50 ? firstLine.slice(0, 50) + '…' : firstLine;
    }

    /**
     * Cria o elemento card de uma entrada (título + expansão com campos em leitura).
     * @param {Object} entry - Entrada com id, title, description, createdAt, createdBy, references, etc.
     * @returns {HTMLElement}
     * @private
     */
    _createEntryCard(entry) {
        const role = getCurrentUserRole();
        const canEdit = role === 'admin' || role === 'editor';
        const canDelete = role === 'admin';
        const card = document.createElement('div');
        card.className = 'diary-entry-card';
        card.dataset.entryId = entry.id || '';
        const displayTitle = this._getEntryDisplayTitle(entry);
        const meta = formatDate(entry.createdAt) + (entry.createdBy ? ' · ' + entry.createdBy : '');
        const refs = Array.isArray(entry.references) ? entry.references : [];
        const projectLabels = refs.map((r) => r.project).filter(Boolean);
        const badgesHtml =
            projectLabels.length > 0
                ? '<div class="diary-entry-badges">' +
                  projectLabels.map((p) => '<span class="diary-entry-badge">' + escapeHtml(p) + '</span>').join('') +
                  '</div>'
                : '';
        const desc = entry.description ?? entry.text ?? '';
        const expandedRows = [
            desc ? { label: 'Descrição', value: desc } : null,
            entry.regulatoryFramework ? { label: 'Enquadramento Normativo', value: entry.regulatoryFramework } : null,
            entry.associatedRisk ? { label: 'Risco Associado', value: entry.associatedRisk } : null,
            entry.riskLevel ? { label: 'Nível de Risco', value: entry.riskLevel } : null,
            entry.technicalRecommendation ? { label: 'Recomendação Técnica', value: entry.technicalRecommendation } : null
        ].filter(Boolean);
        const expandedHtml =
            expandedRows.length > 0
                ? '<div class="diary-entry-expanded" data-diary-expanded style="display:none;">' +
                  expandedRows
                      .map(
                          (r) =>
                              '<div class="diary-entry-expanded-row">' +
                              '<span class="diary-entry-expanded-label">' +
                              escapeHtml(r.label) +
                              ':</span>' +
                              '<span class="diary-entry-expanded-value">' +
                              escapeHtml(r.value).replace(/\n/g, '<br>') +
                              '</span></div>'
                      )
                      .join('') +
                  '</div>'
                : '';
        const actionsHtml = [
            canEdit ? '<button type="button" class="diary-entry-btn-icon" data-diary-edit title="Editar" aria-label="Editar"><span aria-hidden="true">&#9998;</span></button>' : '',
            canDelete ? '<button type="button" class="diary-entry-btn-icon" data-diary-delete title="Excluir" aria-label="Excluir"><span aria-hidden="true">&#128465;</span></button>' : ''
        ].filter(Boolean).join('');
        card.innerHTML = `
            <div class="diary-entry-body">
                <div class="diary-entry-title" data-diary-toggle>${escapeHtml(displayTitle)}</div>
                ${expandedHtml}
                <div class="diary-entry-meta">${escapeHtml(meta)}</div>
                ${badgesHtml}
            </div>
            <div class="diary-entry-actions">${actionsHtml}</div>
        `;
        const toggleEl = card.querySelector('[data-diary-toggle]');
        const expandedEl = card.querySelector('[data-diary-expanded]');
        if (toggleEl && expandedEl) {
            toggleEl.addEventListener('click', (e) => {
                if (e.target.closest('[data-diary-edit], [data-diary-delete]')) return;
                card.classList.toggle('expanded');
                expandedEl.style.display = card.classList.contains('expanded') ? 'block' : 'none';
            });
        }
        const editBtn = card.querySelector('[data-diary-edit]');
        if (editBtn) editBtn.addEventListener('click', (e) => { e.stopPropagation(); this._startEdit(entry); });
        const deleteBtn = card.querySelector('[data-diary-delete]');
        if (deleteBtn) deleteBtn.addEventListener('click', (e) => { e.stopPropagation(); this._deleteEntry(entry); });
        return card;
    }

    /**
     * Exclui uma entrada e salva o JSON.
     * @param {Object} entry - Entrada a excluir
     * @private
     */
    async _deleteEntry(entry) {
        if (!this.data || !Array.isArray(this.data.entries)) return;
        if (this.editingEntryId === entry.id) this._cancelEdit();
        this.data.entries = this.data.entries.filter((e) => e.id !== entry.id);
        const userId = getCurrentUserId();
        try {
            await diaryApi.save(userId, this.data);
            this._refreshList();
        } catch (err) {
            this._showError(err.message);
        }
    }

    /**
     * Inicia edição de uma entrada (coloca texto no textarea e carrega ref do contexto atual).
     * @param {Object} entry - Entrada a editar
     * @private
     */
    _startEdit(entry) {
        this.editingEntryId = entry.id;
        const desc = entry.description ?? entry.text ?? '';
        if (this.formTitleEl) this.formTitleEl.value = entry.title ?? '';
        if (this.formDescriptionEl) this.formDescriptionEl.value = desc;
        this.optionalRegulatoryFramework = entry.regulatoryFramework ?? '';
        this.optionalAssociatedRisk = entry.associatedRisk ?? '';
        this.optionalRiskLevel = entry.riskLevel ?? '';
        this.optionalTechnicalRecommendation = entry.technicalRecommendation ?? '';
        const ref = getCurrentContextRef(entry);
        const raw = ref && ref.images;
        this.currentEntryImages = Array.isArray(raw)
            ? raw.map((item) => (typeof item === 'string' ? item : (item && item.name) || '')).filter(Boolean)
            : [];
        const rawPins = ref && (Array.isArray(ref.pins) ? ref.pins : Array.isArray(ref.points) ? ref.points : []);
        this.currentEntryPins = Array.isArray(rawPins)
            ? rawPins
                  .filter((p) => p && typeof p.x === 'number' && typeof p.y === 'number' && typeof p.z === 'number')
                  .map((p) => ({
                      x: p.x,
                      y: p.y,
                      z: p.z,
                      color: p.color || DEFAULT_PIN_COLOR
                  }))
            : [];
        this.currentActivePinIndex = this.currentEntryPins.length > 0 ? 0 : null;
        if (this.pinColorInputEl) {
            const colorForNewPins =
                this.currentEntryPins.length > 0
                    ? this.currentEntryPins[this.currentEntryPins.length - 1].color || DEFAULT_PIN_COLOR
                    : DEFAULT_PIN_COLOR;
            this.currentPinColor = colorForNewPins;
            this.pinColorInputEl.value = colorForNewPins;
        } else {
            this.currentPinColor = DEFAULT_PIN_COLOR;
        }
        this._refreshRefsRow();
        if (this.newEntryBtnEl) this.newEntryBtnEl.style.display = 'none';
        if (this.formBodyEl) this.formBodyEl.style.display = 'block';
        this._updateDiaryPinsInViewer();
        if (this.currentEntryPins.length > 0) this._setDiaryPinDragCallback();
        if (this.addBtn) {
            this.addBtn.textContent = 'Salvar';
            this.addBtn.dataset.diaryAction = 'save';
        }
        if (this.cancelBtn) this.cancelBtn.style.display = 'block';
    }

    /**
     * Cancela edição e limpa o formulário.
     * @private
     */
    _cancelEdit() {
        this.editingEntryId = null;
        this.currentEntryImages = [];
        this.currentEntryPins = [];
        this.currentEntryRefs = [];
        this.currentActivePinIndex = null;
        this.currentPinColor = DEFAULT_PIN_COLOR;
        this._selectedPinIndexForEditing = null;
        this._hidePinColorFloat();
        this.optionalRegulatoryFramework = '';
        this.optionalAssociatedRisk = '';
        this.optionalRiskLevel = '';
        this.optionalTechnicalRecommendation = '';
        this._refreshRefsRow();
        this._updateDiaryPinsInViewer();
        window.onDiaryPinPositionUpdated = null;
        if (this.formTitleEl) this.formTitleEl.value = '';
        if (this.formDescriptionEl) this.formDescriptionEl.value = '';
        if (this.pinColorInputEl) {
            this.pinColorInputEl.value = DEFAULT_PIN_COLOR;
        }
        if (this.addBtn) {
            this.addBtn.textContent = 'Adicionar';
            this.addBtn.removeAttribute('data-diary-action');
        }
        if (this.cancelBtn) this.cancelBtn.style.display = 'none';
        if (this.newEntryBtnEl) this.newEntryBtnEl.style.display = 'block';
        if (this.formBodyEl) this.formBodyEl.style.display = 'none';
    }

    /**
     * Adiciona nova entrada ou salva edição.
     * @private
     */
    async _addEntry() {
        const title = this.formTitleEl ? this.formTitleEl.value.trim() : '';
        const description = this.formDescriptionEl ? this.formDescriptionEl.value.trim() : '';
        const userId = getCurrentUserId();
        const projectId = typeof window.currentProjectId !== 'undefined' ? window.currentProjectId : '';

        if (this.addBtn && this.addBtn.dataset.diaryAction === 'save' && this.editingEntryId) {
            const entry = this.data.entries.find((e) => e.id === this.editingEntryId);
            if (!entry) {
                this._cancelEdit();
                return;
            }
            const oldDesc = entry.description ?? entry.text ?? '';
            const currentRef = getCurrentContextRef(entry);
            const oldImages = currentRef && Array.isArray(currentRef.images) ? [...currentRef.images] : [];
            const newImages = Array.isArray(this.currentEntryImages) ? [...this.currentEntryImages] : [];
            const newPins = Array.isArray(this.currentEntryPins)
                ? this.currentEntryPins.map((p) => ({
                      x: p.x,
                      y: p.y,
                      z: p.z,
                      color: p.color || DEFAULT_PIN_COLOR
                  }))
                : [];
            const sameDesc = description === oldDesc;
            const sameImages = oldImages.length === newImages.length && oldImages.every((n, i) => n === newImages[i]);
            const oldPinsRaw =
                currentRef && (Array.isArray(currentRef.pins) ? currentRef.pins : Array.isArray(currentRef.points) ? currentRef.points : []);
            const oldPins = Array.isArray(oldPinsRaw)
                ? oldPinsRaw.map((p) => ({
                      x: p.x,
                      y: p.y,
                      z: p.z,
                      color: p.color || DEFAULT_PIN_COLOR
                  }))
                : [];
            const samePins =
                newPins.length === oldPins.length &&
                newPins.every(
                    (p, i) =>
                        oldPins[i] &&
                        p.x === oldPins[i].x &&
                        p.y === oldPins[i].y &&
                        p.z === oldPins[i].z &&
                        (p.color || DEFAULT_PIN_COLOR) === (oldPins[i].color || DEFAULT_PIN_COLOR)
                );
            const sameTitle = (entry.title ?? '') === title;
            const sameRest =
                (entry.regulatoryFramework ?? '') === this.optionalRegulatoryFramework &&
                (entry.associatedRisk ?? '') === this.optionalAssociatedRisk &&
                (entry.riskLevel ?? '') === this.optionalRiskLevel &&
                (entry.technicalRecommendation ?? '') === this.optionalTechnicalRecommendation;
            if (sameTitle && sameDesc && sameImages && samePins && sameRest) {
                this._cancelEdit();
                return;
            }
            entry.title = title;
            entry.description = description;
            entry.regulatoryFramework = this.optionalRegulatoryFramework.trim();
            entry.associatedRisk = this.optionalAssociatedRisk;
            entry.riskLevel = this.optionalRiskLevel;
            entry.technicalRecommendation = this.optionalTechnicalRecommendation.trim();
            setCurrentContextRef(entry, {
                images: newImages,
                pins: newPins,
                objects: (currentRef && currentRef.objects) || ''
            });
            if (!entry.history) entry.history = [];
            entry.history.push({
                at: getSaoPauloDateTimeString(),
                by: getCurrentUserDisplay().display,
                previousText: oldDesc,
                newText: description
            });
            try {
                await diaryApi.save(userId, this.data);
                this._updateDiaryPinsInViewer();
                window.onDiaryPinPositionUpdated = null;
                this._cancelEdit();
                this._refreshList();
            } catch (err) {
                this._showError(err.message);
            }
            return;
        }

        if (!title) return;
        const now = getSaoPauloDateTimeString();
        const displayName = getCurrentUserDisplay().display;
        const refs = (this.currentEntryRefs || [])
            .map((r) => ({
                methodology: r.methodology || getCurrentMethodology(),
                project: r.project || '',
                images: Array.isArray(r.images) ? [...r.images] : [],
                pins: Array.isArray(r.pins)
                    ? r.pins
                          .filter((p) => p && typeof p.x === 'number' && typeof p.y === 'number' && typeof p.z === 'number')
                          .map((p) => ({
                              x: p.x,
                              y: p.y,
                              z: p.z,
                              color: p.color || DEFAULT_PIN_COLOR
                          }))
                    : [],
                objects: r.objects || ''
            }))
            .filter(
                (r) =>
                    (r.images && r.images.length > 0) ||
                    (r.pins && r.pins.length > 0) ||
                    (typeof r.objects === 'string' && r.objects.trim() !== '')
            );
        const newEntry = {
            id: generateEntryId(),
            title,
            description,
            regulatoryFramework: this.optionalRegulatoryFramework.trim(),
            associatedRisk: this.optionalAssociatedRisk,
            riskLevel: this.optionalRiskLevel,
            technicalRecommendation: this.optionalTechnicalRecommendation.trim(),
            createdAt: now,
            createdBy: displayName,
            history: [{ at: now, by: displayName, previousText: '', newText: description }],
            references: refs
        };
        if (!this.data) this.data = { user: userId, entries: [] };
        this.data.entries = this.data.entries || [];
        this.data.entries.push(newEntry);
        try {
            await diaryApi.save(userId, this.data);
            if (this.formTitleEl) this.formTitleEl.value = '';
            if (this.formDescriptionEl) this.formDescriptionEl.value = '';
            this.optionalRegulatoryFramework = '';
            this.optionalAssociatedRisk = '';
            this.optionalRiskLevel = '';
            this.optionalTechnicalRecommendation = '';
            this.currentEntryImages = [];
            this.currentEntryPins = [];
            this.currentEntryRefs = [];
            this.currentActivePinIndex = null;
            this.currentPinColor = DEFAULT_PIN_COLOR;
            this._refreshRefsRow();
            this._updateDiaryPinsInViewer();
            window.onDiaryPinPositionUpdated = null;
            this._cancelEdit();
            this._refreshList();
        } catch (err) {
            this._showError(err.message);
        }
    }

    /**
     * Exibe mensagem de erro na lista.
     * @param {string} msg
     * @private
     */
    _showError(msg) {
        if (!this.listEl) return;
        this.listEl.innerHTML = '<p class="diary-error">' + escapeHtml(msg) + '</p>';
    }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
