/**
 * Carregador de parâmetros de câmera
 * Parser do arquivo calibrated_external_camera_parameters
 * Usa path relativo ao projeto CopasaMeu (sem /tcu/...).
 */

import * as THREE from "../../potree/libs/three.js/build/three.module.js";

/**
 * Carrega parâmetros externos de câmera de um arquivo
 * @param {string} filePath - Caminho do arquivo de parâmetros
 * @param {number} limit - Limite de câmeras a carregar (padrão: sem limite; use número para limitar)
 * @returns {Promise<Array>} Array com dados das câmeras
 */
export async function loadExternalCameraParameters(filePath, limit = 0) {
    // console.log('🌐 Tentando carregar arquivo de câmera:', filePath);
    
    const response = await fetch(filePath);
    // console.log('📡 Resposta do servidor:', response.status, response.statusText);
    
    if (!response.ok) {
        console.error('❌ Erro ao carregar arquivo:', response.status, response.statusText);
        throw new Error(`Erro ao carregar o arquivo: ${response.statusText}`);
    }
    
    // console.log('✅ Arquivo carregado com sucesso');

    const text = await response.text();
    const lines = text.trim().split('\n');
    
    // Encontra o início dos dados (pula o cabeçalho)
    let startIndex = findDataStartIndex(lines);
    
    if (startIndex === -1) {
        throw new Error("Não encontrou o início dos dados de câmera no arquivo.");
    }

    const cameras = [];
    for (let i = startIndex; i < lines.length; i++) {
        if (limit > 0 && cameras.length >= limit) break;
        const cameraData = parseCameraLine(lines[i]);
        if (cameraData) {
            cameras.push(cameraData);
        }
    }

    return cameras;
}

/**
 * Encontra o índice onde começam os dados das câmeras
 * @param {Array<string>} lines - Linhas do arquivo
 * @returns {number} Índice de início ou -1 se não encontrado
 */
function findDataStartIndex(lines) {
    // Padrões comuns para identificar o início dos dados
    const patterns = ['DJI', 'dji', 'DJI_', 'IMG_', 'DSC_', 'IMG', 'DSC'];
    
    for (const pattern of patterns) {
        const found = lines.findIndex(line => line.trim().startsWith(pattern));
        if (found !== -1) {
            return found;
        }
    }
    
    return -1;
}

/**
 * Faz o parse de uma linha de dados de câmera
 * @param {string} line - Linha do arquivo
 * @returns {Object|null} Dados da câmera ou null se inválida
 */
function parseCameraLine(line) {
    const parts = line.trim().split(/\s+/);
    if (parts.length < 6) return null;

    // Extrair nome da imagem (ex: DJI_0424.JPG -> DJI_0424)
    const fullImageName = parts[0];
    const name = fullImageName.replace(/\.(jpg|jpeg|png|JPG|JPEG|PNG)$/i, '');
    
    const x = parseFloat(parts[1]);
    const y = parseFloat(parts[2]);
    const z = parseFloat(parts[3]);
    const omega = parseFloat(parts[4]); // Omega (radianos)
    const phi = parseFloat(parts[5]);   // Phi (radianos)
    const kappa = parseFloat(parts[6]); // Kappa (radianos)
    
    // console.log('📷 Processando câmera:', fullImageName, '-> Nome:', name);
    
    // O arquivo pix4d, geralmente, usa ângulos em graus.
    // Para garantir a conversão correta:
    const toRadians = angle => angle * Math.PI / 180;
    const omegaRad = toRadians(omega);
    const phiRad = toRadians(phi);
    const kappaRad = toRadians(kappa);

    const position = new THREE.Vector3(x, y, z);

    // Cria matrizes de rotação para cada eixo
    const Rz = new THREE.Matrix4().makeRotationZ(kappaRad);
    const Ry = new THREE.Matrix4().makeRotationY(phiRad);
    const Rx = new THREE.Matrix4().makeRotationX(omegaRad);

    // Pix4D OPK: rotações aplicadas na ordem Kappa (Z), depois Phi (Y), depois Omega (X).
    // Matriz resultado: R = Rx(ω) · Ry(φ) · Rz(κ) = Rx * Ry * Rz
    const R_matrix = new THREE.Matrix4().multiplyMatrices(Rx, Ry);
    R_matrix.multiply(Rz);

    // Cria um quaternion a partir da matriz de rotação
    const quaternion = new THREE.Quaternion().setFromRotationMatrix(R_matrix);
    
    // Sem isso, plota o frustum de cabeça para baixo. Apenas rotaciona em 180º
    const correction = new THREE.Quaternion().setFromAxisAngle(
        new THREE.Vector3(1, 0, 0), 
        Math.PI
    );
    quaternion.multiply(correction);

    return { name, position, quaternion };
}
