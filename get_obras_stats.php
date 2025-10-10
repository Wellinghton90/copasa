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
    // Buscar estatísticas das obras
    $stats = [];
    
    // Total de obras
    $stmt = $conn->query("SELECT COUNT(*) as total FROM obras");
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obras em execução
    $stmt = $conn->prepare("SELECT COUNT(*) as execucao FROM obras WHERE status = 'execucao'");
    $stmt->execute();
    $stats['execucao'] = $stmt->fetch(PDO::FETCH_ASSOC)['execucao'];
    
    // Obras concluídas
    $stmt = $conn->prepare("SELECT COUNT(*) as concluidas FROM obras WHERE status = 'concluida'");
    $stmt->execute();
    $stats['concluidas'] = $stmt->fetch(PDO::FETCH_ASSOC)['concluidas'];
    
    // Obras atrasadas
    $stmt = $conn->prepare("SELECT COUNT(*) as atrasadas FROM obras WHERE situacao = 'atrasada'");
    $stmt->execute();
    $stats['atrasadas'] = $stmt->fetch(PDO::FETCH_ASSOC)['atrasadas'];
    
    // Obras em planejamento
    $stmt = $conn->prepare("SELECT COUNT(*) as planejamento FROM obras WHERE status = 'planejamento'");
    $stmt->execute();
    $stats['planejamento'] = $stmt->fetch(PDO::FETCH_ASSOC)['planejamento'];
    
    // Obras suspensas
    $stmt = $conn->prepare("SELECT COUNT(*) as suspensas FROM obras WHERE status = 'suspensa'");
    $stmt->execute();
    $stats['suspensas'] = $stmt->fetch(PDO::FETCH_ASSOC)['suspensas'];
    
    // Obras canceladas
    $stmt = $conn->prepare("SELECT COUNT(*) as canceladas FROM obras WHERE status = 'cancelada'");
    $stmt->execute();
    $stats['canceladas'] = $stmt->fetch(PDO::FETCH_ASSOC)['canceladas'];
    
    // Obras prioritárias
    $stmt = $conn->prepare("SELECT COUNT(*) as prioritarias FROM obras WHERE situacao = 'prioritaria'");
    $stmt->execute();
    $stats['prioritarias'] = $stmt->fetch(PDO::FETCH_ASSOC)['prioritarias'];
    
    // Obras em emergência
    $stmt = $conn->prepare("SELECT COUNT(*) as emergencia FROM obras WHERE situacao = 'emergencia'");
    $stmt->execute();
    $stats['emergencia'] = $stmt->fetch(PDO::FETCH_ASSOC)['emergencia'];
    
    // Orçamento total
    $stmt = $conn->query("SELECT SUM(orcamento_total) as orcamento_total FROM obras WHERE orcamento_total IS NOT NULL");
    $stats['orcamento_total'] = $stmt->fetch(PDO::FETCH_ASSOC)['orcamento_total'] ?? 0;
    
    // Orçamento utilizado
    $stmt = $conn->query("SELECT SUM(orcamento_utilizado) as orcamento_utilizado FROM obras WHERE orcamento_utilizado IS NOT NULL");
    $stats['orcamento_utilizado'] = $stmt->fetch(PDO::FETCH_ASSOC)['orcamento_utilizado'] ?? 0;
    
    // Calcular percentual de utilização
    if ($stats['orcamento_total'] > 0) {
        $stats['orcamento_percentual'] = round(($stats['orcamento_utilizado'] / $stats['orcamento_total']) * 100, 2);
    } else {
        $stats['orcamento_percentual'] = 0;
    }
    
    // Obras por status (para gráficos)
    $stmt = $conn->query("SELECT status, COUNT(*) as count FROM obras GROUP BY status");
    $stats['por_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obras por situação (para gráficos)
    $stmt = $conn->query("SELECT situacao, COUNT(*) as count FROM obras GROUP BY situacao");
    $stats['por_situacao'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obras por UF
    $stmt = $conn->query("SELECT uf, COUNT(*) as count FROM obras GROUP BY uf ORDER BY count DESC");
    $stats['por_uf'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($stats);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?>
