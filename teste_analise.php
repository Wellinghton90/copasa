<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simular delay de 30 segundos para testar o timer
    sleep(5);
    
    // Simular resposta da IA com dados de exemplo usando a nova estrutura
    $response = [
        "contexto_geral" => [
            "cliente" => "COPASA",
            "tipo_obra" => "Implantação de rede de esgoto em área urbana",
            "fonte_imagem" => "Drone, altura aproximada de 10-15 m, ângulo oblíquo de cima para baixo (cerca de 45°)",
            "data_e_horario_estimado" => "Diurno"
        ],
        "respostas_analiticas" => [
            ["id_pergunta" => 1, "pergunta" => "Qual é o tipo e objetivo principal da obra observada?", "resposta" => "Implantação de rede coletora de esgoto sanitário visando atendimento domiciliar urbano."],
            ["id_pergunta" => 2, "pergunta" => "Qual a fonte da imagem e em que condições ela foi obtida?", "resposta" => "Imagem aérea obtida por drone, cerca de 12 m de altura, ângulo oblíquo de aproximadamente 45°, capturada em período diurno. confiança: alta"],
            ["id_pergunta" => 3, "pergunta" => "Quais são as condições climáticas e de iluminação?", "resposta" => "Céu limpo, alta luminosidade, ausência de poeira visível e solo seco. confiança: alta"],
            ["id_pergunta" => 4, "pergunta" => "Há indícios de chuva recente ou condições adversas?", "resposta" => "Não há indícios de chuva ou ventos fortes; o solo parece seco e firme. confiança: alta"],
            ["id_pergunta" => 5, "pergunta" => "A imagem mostra boa visibilidade para análise?", "resposta" => "Sim, a visibilidade é muito boa, sem sombras que prejudiquem a análise. confiança: alta"],
            ["id_pergunta" => 6, "pergunta" => "Quantos trabalhadores estão visíveis na imagem?", "resposta" => "Dois trabalhadores visíveis, próximos à área de trabalho. confiança: alta"],
            ["id_pergunta" => 7, "pergunta" => "Quais atividades específicas cada trabalhador está realizando?", "resposta" => "Um parece manipular ferramentas ou materiais; o outro supervisiona ou auxilia o trabalho. confiança: média"],
            ["id_pergunta" => 8, "pergunta" => "Cada trabalhador utiliza corretamente os EPIs?", "resposta" => "Ambos usam capacete e uniforme com faixas refletivas. Não é possível confirmar todos os EPIs. confiança: média"]
        ],
        "avaliacao_global" => [
            "nota_organizacao_0a10" => 7,
            "nota_qualidade_0a10" => 8,
            "nota_seguranca_0a10" => 6,
            "nota_ambiental_0a10" => 7,
            "grau_conformidade_normas" => "Parcialmente conforme (boa execução técnica, mas com pontos de melhoria)",
            "resumo_execucao" => "Obra em estágio intermediário, bem organizada e tecnicamente adequada, com trabalhadores devidamente uniformizados e condições climáticas favoráveis.",
            "confianca_global" => "alta"
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['error' => 'Método não permitido']);
}
?>
