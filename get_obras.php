<?php
session_start();
require_once 'connection.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_copasa'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit();
}

header('Content-Type: application/json');

try {
    // Parâmetros do DataTables
    $draw = intval($_GET['draw'] ?? 1);
    $start = intval($_GET['start'] ?? 0);
    $length = intval($_GET['length'] ?? 10);
    $searchValue = $_GET['search']['value'] ?? '';
    $orderColumn = intval($_GET['order'][0]['column'] ?? 0);
    $orderDir = $_GET['order'][0]['dir'] ?? 'asc';
    
    // Mapear colunas para campos do banco (índices correspondem à ordem no DataTables)
    $columns = [
        0 => 'id',              // Ações (não ordenável)
        1 => 'nome',            // Nome
        2 => 'descricao',       // Descrição
        3 => 'localizacao',     // Localização
        4 => 'cidade',          // Cidade
        5 => 'uf',              // UF
        6 => 'status',          // Status
        7 => 'situacao',        // Situação
        8 => 'latitude',        // Latitude
        9 => 'longitude',       // Longitude
        10 => 'data_inicio',    // Data Início
        11 => 'data_prevista',  // Data Prevista
        12 => 'data_conclusao', // Data Conclusão
        13 => 'orcamento_total', // Orçamento Total
        14 => 'orcamento_utilizado', // Orçamento Utilizado
        15 => 'responsavel'     // Responsável
    ];
    
    // Validar coluna de ordenação
    $orderBy = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'nome';
    $orderDirection = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
    
    // Query base
    $baseQuery = "FROM obras WHERE 1=1";
    $params = [];
    
    // Filtro de busca
    if (!empty($searchValue)) {
        $searchQuery = " AND (nome LIKE ? OR descricao LIKE ? OR localizacao LIKE ? OR cidade LIKE ? OR uf LIKE ? OR status LIKE ? OR situacao LIKE ?)";
        $searchParam = "%$searchValue%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
        $baseQuery .= $searchQuery;
    }
    
    // Contar total de registros
    $countQuery = "SELECT COUNT(*) as total $baseQuery";
    $stmt = $conn->prepare($countQuery);
    $stmt->execute($params);
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Contar registros filtrados
    $filteredRecords = $totalRecords;
    
    // Query principal com paginação e ordenação
    $mainQuery = "SELECT 
        id,
        nome,
        descricao,
        localizacao,
        latitude,
        longitude,
        cidade,
        uf,
        status,
        situacao,
        data_cadastro,
        data_inicio,
        data_prevista,
        data_conclusao,
        orcamento_total,
        orcamento_utilizado,
        responsavel
        $baseQuery 
        ORDER BY $orderBy $orderDirection 
        LIMIT $start, $length";
    
    $stmt = $conn->prepare($mainQuery);
    $stmt->execute($params);
    $obras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar dados para o DataTables
    $data = [];
    foreach ($obras as $obra) {
        // Formatar status
        $statusBadge = '';
        switch ($obra['status']) {
            case 'planejamento':
                $statusBadge = '<span class="badge bg-secondary">Planejamento</span>';
                break;
            case 'execucao':
                $statusBadge = '<span class="badge bg-primary">Execução</span>';
                break;
            case 'concluida':
                $statusBadge = '<span class="badge bg-success">Concluída</span>';
                break;
            case 'suspensa':
                $statusBadge = '<span class="badge bg-warning">Suspensa</span>';
                break;
            case 'cancelada':
                $statusBadge = '<span class="badge bg-danger">Cancelada</span>';
                break;
            default:
                $statusBadge = '<span class="badge bg-secondary">' . htmlspecialchars($obra['status']) . '</span>';
                break;
        }
        
        // Formatar situação
        $situacaoBadge = '';
        switch ($obra['situacao']) {
            case 'normal':
                $situacaoBadge = '<span class="badge bg-light text-dark">Normal</span>';
                break;
            case 'atrasada':
                $situacaoBadge = '<span class="badge bg-warning">Atrasada</span>';
                break;
            case 'emergencia':
                $situacaoBadge = '<span class="badge bg-danger">Emergência</span>';
                break;
            case 'prioritaria':
                $situacaoBadge = '<span class="badge bg-info">Prioritária</span>';
                break;
            default:
                $situacaoBadge = '<span class="badge bg-secondary">' . htmlspecialchars($obra['situacao']) . '</span>';
                break;
        }
        
        // Formatar orçamento
        $orcamento = '';
        if ($obra['orcamento_total']) {
            $orcamento = 'R$ ' . number_format($obra['orcamento_total'], 2, ',', '.');
        }
        
        // Formatar data
        $dataCadastro = date('d/m/Y H:i', strtotime($obra['data_cadastro']));
        
        // Botões de ação
        $acoes = '
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="verObra(' . $obra['id'] . ')" title="Ver Detalhes">
                    <i class="fas fa-eye"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-warning" onclick="editarObra(' . $obra['id'] . ')" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="excluirObra(' . $obra['id'] . ')" title="Excluir">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        ';
        
        // Formatar datas
        $dataInicio = $obra['data_inicio'] ? date('d/m/Y', strtotime($obra['data_inicio'])) : '-';
        $dataPrevista = $obra['data_prevista'] ? date('d/m/Y', strtotime($obra['data_prevista'])) : '-';
        $dataConclusao = $obra['data_conclusao'] ? date('d/m/Y', strtotime($obra['data_conclusao'])) : '-';
        
        // Formatar orçamentos
        $orcamentoTotal = $obra['orcamento_total'] ? 'R$ ' . number_format($obra['orcamento_total'], 2, ',', '.') : '-';
        $orcamentoUtilizado = $obra['orcamento_utilizado'] ? 'R$ ' . number_format($obra['orcamento_utilizado'], 2, ',', '.') : '-';
        
        $rowData = [
            $acoes, // Ações como primeira coluna
            htmlspecialchars($obra['nome']),
            substr(htmlspecialchars($obra['descricao']), 0, 50) . (strlen($obra['descricao']) > 50 ? '...' : ''),
            substr(htmlspecialchars($obra['localizacao']), 0, 30) . (strlen($obra['localizacao']) > 30 ? '...' : ''),
            htmlspecialchars($obra['cidade']),
            htmlspecialchars($obra['uf']),
            $statusBadge,
            $situacaoBadge,
            $obra['latitude'] ?? '-',
            $obra['longitude'] ?? '-',
            $dataInicio,
            $dataPrevista,
            $dataConclusao,
            $orcamentoTotal,
            $orcamentoUtilizado,
            htmlspecialchars($obra['responsavel'] ?? '-')
        ];
        
        
        $data[] = $rowData;
    }
    
    // Resposta para o DataTables
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'data' => $data
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?>
