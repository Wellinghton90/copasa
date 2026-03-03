/**
 * Ponto de entrada principal do viewer de nuvens.
 * Inicializa o TimelineController com a configuração do sistema.
 */

import { ConfigService } from '../config/ConfigService.js';
import { TimelineController } from './controllers/TimelineController.js';

// Cria instância de ConfigService e inicializa o controller
const configService = new ConfigService();
const timelineController = new TimelineController(configService);

// Inicializa quando o módulo é carregado
timelineController.init();

// Exporta o controller para uso externo (se necessário)
export { timelineController };
