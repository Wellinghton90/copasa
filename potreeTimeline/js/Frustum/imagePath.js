/**
 * Gerenciamento de caminhos de imagens
 * Constrói caminhos para as imagens das câmeras usando o sistema Pix4D
 */

/**
 * Atualiza o nome do projeto na configuração Pix4D
 * @param {string} projectName - Nome do projeto
 */
export function updateProjectName(projectName) {
    if (window.appConfig && window.appConfig.pix4dConfig) {
        window.appConfig.pix4dConfig.projectName = projectName;
        console.log('📁 Nome do projeto atualizado para:', projectName);
    } else {
        console.warn('⚠️ Configuração Pix4D não encontrada para atualizar nome do projeto');
    }
}

/**
 * Carrega configuração do projeto de um JSON (função para implementação futura)
 * @param {string} jsonPath - Caminho do arquivo JSON com configurações
 * @returns {Promise<Object>} Configuração do projeto
 */
export async function loadProjectConfigFromJson(jsonPath) {
    try {
        const response = await fetch(jsonPath);
        if (!response.ok) {
            throw new Error(`Erro ao carregar configuração: ${response.statusText}`);
        }
        
        const config = await response.json();
        
        // Atualizar nome do projeto se fornecido
        if (config.projectName) {
            updateProjectName(config.projectName);
        }
        
        console.log('✅ Configuração do projeto carregada:', config);
        return config;
        
    } catch (error) {
        console.error('❌ Erro ao carregar configuração do projeto:', error);
        throw error;
    }
}

/**
 * Gera URL local da imagem para um projeto (ferramenta Fotos no ponto).
 * Path: {baseProjetos}/{projectId}/1_initial/images/undistorted_images/{imageName}
 * @param {string} projectId - ID do projeto (ex: Juatuba_15-01)
 * @param {string} imageName - Nome do arquivo (ex: DJI_0323.JPG) ou nome sem extensão (DJI_0323)
 * @returns {string} URL relativa à raiz do site
 */
export function getImageUrlForProject(projectId, imageName) {
    const baseProjetos = (window.NUVEM_CONFIG && window.NUVEM_CONFIG.baseProjetos) || "projetos";
    const base = imageName.match(/\.(jpg|jpeg|png|JPG|JPEG|PNG)$/i) ? imageName : imageName + ".JPG";
    return `${baseProjetos}/${projectId}/1_initial/images/undistorted_images/${base}`;
}

/**
 * Gera o caminho da imagem baseado no nome da câmera usando configuração Pix4D
 * @param {string} cameraName - Nome da câmera (ex: DJI_0001)
 * @returns {string} Caminho completo da imagem no servidor local
 */
export function generateImagePath(cameraName) {
    // Verificar se temos configuração Pix4D disponível
    if (window.appConfig && window.appConfig.pix4dConfig) {
        const config = window.appConfig.pix4dConfig;
        
        // Usar API Python para servir imagens (mais confiável)
        const apiBaseUrl = window.appConfig.apiBaseUrl || 'https://moduloautoma.ddns.net:82';
        const imageUrl = `${apiBaseUrl}/serve_image?project=${encodeURIComponent(config.projectName)}&image=${encodeURIComponent(cameraName + '.JPG')}`;
        
        return imageUrl;
    }
    
    // Fallback para o sistema antigo se não houver configuração Pix4D
    console.warn('⚠️ Configuração Pix4D não encontrada, usando sistema antigo');
    const basePath = window.fotosPath || 'https://moduloautoma.ddns.net:900/tcu/arquivosGemeos/fotos';
    const imageName = cameraName + '.JPG';
    return `${basePath}/${imageName}`;
}

/**
 * Gera caminho alternativo com extensão minúscula
 * @param {string} cameraName - Nome da câmera
 * @returns {string} Caminho com extensão .jpg
 */
export function generateImagePathLowercase(cameraName) {
    // Usar sistema Pix4D se disponível
    if (window.appConfig && window.appConfig.pix4dConfig) {
        const config = window.appConfig.pix4dConfig;
        const apiBaseUrl = window.appConfig.apiBaseUrl || 'https://moduloautoma.ddns.net:82';
        return `${apiBaseUrl}/serve_image?project=${encodeURIComponent(config.projectName)}&image=${encodeURIComponent(cameraName + '.jpg')}`;
    }
    
    // Fallback
    const basePath = window.fotosPath || 'https://moduloautoma.ddns.net:900/tcu/arquivosGemeos/fotos';
    const imageName = cameraName + '.jpg';
    return `${basePath}/${imageName}`;
}

/**
 * Tenta diferentes variações de caminho para a imagem usando sistema Pix4D
 * @param {string} cameraName - Nome da câmera
 * @returns {Array} Array com diferentes caminhos possíveis
 */
export function generateImagePathVariations(cameraName) {
    // Usar sistema Pix4D se disponível
    if (window.appConfig && window.appConfig.pix4dConfig) {
        const config = window.appConfig.pix4dConfig;
        const apiBaseUrl = window.appConfig.apiBaseUrl || 'https://moduloautoma.ddns.net:5000';
        const extensions = ['.JPG', '.jpg', '.jpeg', '.JPEG', '.png', '.PNG'];
        
        return extensions.map(ext => 
            `${apiBaseUrl}/serve_image?project=${encodeURIComponent(config.projectName)}&image=${encodeURIComponent(cameraName + ext)}`
        );
    }
    
    // Fallback para sistema antigo
    const basePath = window.fotosPath || 'https://moduloautoma.ddns.net:900/tcu/arquivosGemeos/fotos';
    
    return [
        `${basePath}/${cameraName}.JPG`,
        `${basePath}/${cameraName}.jpg`,
        `${basePath}/${cameraName}.jpeg`,
        `${basePath}/${cameraName}.JPEG`,
        `${basePath}/${cameraName}.png`,
        `${basePath}/${cameraName}.PNG`
    ];
}
