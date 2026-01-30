/**
 * Cliente API para comunicação com o backend
 * 
 * Abstrai as chamadas fetch e fornece tratamento
 * centralizado de erros HTTP.
 */

class ApiClient {
    /**
     * Timeout padrão para requisições (em milissegundos)
     * 
     * @type {number}
     */
    static DEFAULT_TIMEOUT = 10000;

    /**
     * Busca projetos da API
     * 
     * @returns {Promise<Array>} Array de projetos
     * @throws {Error} Se a requisição falhar
     */
    static async fetchProjects() {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.DEFAULT_TIMEOUT);

        try {
            const response = await fetch('api/get_projects.php', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                throw this.handleApiError(response);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Erro desconhecido ao buscar projetos');
            }

            return data.data || [];

        } catch (error) {
            clearTimeout(timeoutId);

            if (error.name === 'AbortError') {
                throw new Error('Tempo de requisição excedido. Tente novamente.');
            }

            if (error instanceof Error) {
                throw error;
            }

            throw new Error('Erro ao comunicar com o servidor');
        }
    }

    /**
     * Trata erros da API
     * 
     * @param {Response} response Resposta HTTP
     * @returns {Error} Erro formatado
     */
    static handleApiError(response) {
        let message = 'Erro ao processar requisição';

        switch (response.status) {
            case 400:
                message = 'Requisição inválida';
                break;
            case 404:
                message = 'Endpoint não encontrado';
                break;
            case 500:
                message = 'Erro interno do servidor';
                break;
            case 503:
                message = 'Serviço temporariamente indisponível';
                break;
            default:
                message = `Erro HTTP ${response.status}`;
        }

        return new Error(message);
    }
}
