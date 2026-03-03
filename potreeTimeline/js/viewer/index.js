/**
 * Barrel exports para o módulo viewer.
 * Exporta todas as classes e funções de forma organizada.
 * 
 * Uso:
 *   import { TimelineController, CloudCache } from './viewer/index.js';
 */

// Core classes
export { CloudCache } from './core/CloudCache.js';
export { CloudLoader } from './core/CloudLoader.js';
export { CameraController } from './core/CameraController.js';

// Controllers
export { TimelineController } from './controllers/TimelineController.js';
export { LeftPanelController } from './controllers/LeftPanelController.js';
export { PointSizeController } from './controllers/PointSizeController.js';

// Services
export { OffsetService } from './services/OffsetService.js';

// UI (funções puras - mantém como está)
export * from './timeline-ui.js';
