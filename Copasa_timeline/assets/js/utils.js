/**
 * Funções utilitárias reutilizáveis
 * 
 * Segue o princípio DRY (Don't Repeat Yourself)
 */

/**
 * Formata data para exibição
 * 
 * @param {string} dateString Data no formato YYYY-MM-DD
 * @returns {string} Data formatada
 */
function formatDateForDisplay(dateString) {
    if (!dateString) {
        return '';
    }

    const date = new Date(dateString + 'T00:00:00');
    
    if (isNaN(date.getTime())) {
        return dateString;
    }

    const months = [
        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
    ];

    const day = date.getDate().toString().padStart(2, '0');
    const month = months[date.getMonth()];
    const year = date.getFullYear();

    return `${day} de ${month} de ${year}`;
}

/**
 * Debounce - Executa função após delay, cancelando execuções anteriores
 * 
 * @param {Function} func Função a ser executada
 * @param {number} delay Delay em milissegundos
 * @returns {Function} Função com debounce aplicado
 */
function debounce(func, delay) {
    let timeoutId;
    
    return function (...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func.apply(this, args), delay);
    };
}

/**
 * Throttle - Limita execução de função a uma vez por período
 * 
 * @param {Function} func Função a ser executada
 * @param {number} limit Limite em milissegundos
 * @returns {Function} Função com throttle aplicado
 */
function throttle(func, limit) {
    let inThrottle;
    
    return function (...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Verifica se um elemento está visível na viewport
 * 
 * @param {HTMLElement} element Elemento a verificar
 * @returns {boolean} True se visível
 */
function isElementVisible(element) {
    if (!element) {
        return false;
    }

    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

/**
 * Remove acentos de uma string
 * 
 * @param {string} str String com acentos
 * @returns {string} String sem acentos
 */
function removeAccents(str) {
    return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}
