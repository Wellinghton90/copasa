/**
 * Opções do dropdown de plataforma na barra superior.
 * label = texto exibido na UI (pt-BR); value = valor usado no JSON (methodology).
 * Permite alterar as labels depois sem mudar o valor no JSON.
 */
export const PLATFORM_OPTIONS = [
    { label: 'Nuvem de pontos', value: 'Point cloud' },
    { label: '2D', value: '2D' },
    { label: 'Vídeos', value: 'Videos' }
];

/** Valor padrão (primeira opção). */
export const DEFAULT_PLATFORM_VALUE = PLATFORM_OPTIONS[0]?.value ?? 'Point cloud';
