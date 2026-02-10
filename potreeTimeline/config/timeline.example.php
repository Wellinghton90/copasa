<?php
/**
 * Configuração da página timeline (nuvem de pontos).
 * Copie para timeline.php e ajuste para seu ambiente (PC ou servidor).
 */

// Ajuste os paths para seu ambiente (PC ou servidor)
$CONFIG = [
    'jsonProjetos' => __DIR__ . '/../data/projetos.json',
    'baseProjetos' => 'projetos',
    'suffixPotree' => 'potree',
];

// Lista de projetos (gerada por scriptsPython/build_projetos_json.py)
$projetosDisponiveis = [];
if (file_exists($CONFIG['jsonProjetos'])) {
    $raw = file_get_contents($CONFIG['jsonProjetos']);
    $decoded = json_decode($raw, true);
    $projetosDisponiveis = is_array($decoded) ? $decoded : [];
}

// Projeto inicial: ?projeto=... se estiver na lista, senão primeiro da lista
$ids = array_column($projetosDisponiveis, 'id');
$nomeProjeto = $_GET['projeto'] ?? null;
$projetoInicial = ($nomeProjeto !== null && in_array($nomeProjeto, $ids, true))
    ? $nomeProjeto
    : (isset($projetosDisponiveis[0]['id']) ? $projetosDisponiveis[0]['id'] : '');

// Config injetada no JS (objeto único)
$NUVEM_CONFIG = [
    'projetosDisponiveis' => $projetosDisponiveis,
    'projetoInicial'      => $projetoInicial,
    'baseProjetos'        => $CONFIG['baseProjetos'],
    'suffixPotree'        => $CONFIG['suffixPotree'],
    'obra'                => $projetoInicial,
    'developerMode'       => false,
];
