<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simular delay de 30 segundos para testar o timer
    sleep(5);
    
    // Simular resposta da IA com dados de exemplo usando a nova estrutura
    $response = [
        "contexto" => [
            "cliente" => "COPASA",
            "tipo_obra" => "Implantação de rede de esgoto em área urbana",
            "fonte_imagem" => "Drone (altura e ângulo não especificados, horário não visível)",
            "data_hora_imagem" => "17/09/25 10:33:15",
            "visibilidade" => "Boa"
        ],
        "detecoes" => [
            "trabalhadores" => [
                "quantidade" => 3,
                "lista" => [
                    [
                        "id" => "W1",
                        "atividade" => "Operando máquina",
                        "posicao_aproximada" => "Próximo à vala",
                        "EPI_visiveis" => ["Capacete"],
                        "situacao_postural" => "Em pé",
                        "foco_no_trabalho" => true
                    ]
                ]
            ],
            "veiculos_e_maquinas" => [
                "quantidade" => 1,
                "lista" => [
                    [
                        "tipo" => "Retroescavadeira",
                        "cor" => "Amarela",
                        "operador_visivel" => true,
                        "em_operacao" => true
                    ]
                ]
            ]
        ],
        "avaliacao_operacional" => [
            "estagio_da_obra" => "Intermediário",
            "percentual_execucao_estimado" => 50,
            "qualidade_execucao" => "Boa",
            "velocidade_aparente" => "Moderada"
        ],
        "avaliacao_de_seguranca" => [
            "EPC_adequacao" => "Adequada",
            "EPI_adequacao" => "Adequada",
            "riscos_identificados" => ["Queda na vala"],
            "nota_organizacao_e_limpeza_0a10" => 7
        ],
        "avaliacao_global" => [
            "nota_organizacao_0a10" => 7,
            "nota_qualidade_0a10" => 8,
            "nota_segurança_0a10" => 7,
            "nota_ambiental_0a10" => 7,
            "grau_conformidade_normas" => "Alto",
            "resumo_execucao" => "Obra em andamento com boa organização e segurança.",
            "confianca_global" => "Média"
        ],
        "respostas_analiticas" => [
            ["id_pergunta" => 1, "pergunta" => "Qual é o tipo e objetivo principal da obra observada?", "resposta" => "Implantação de rede coletora de esgoto sanitário visando atendimento domiciliar urbano."],
            ["id_pergunta" => 2, "pergunta" => "Qual a fonte da imagem e em que condições ela foi obtida?", "resposta" => "Imagem aérea obtida por drone, cerca de 12 m de altura, ângulo oblíquo de aproximadamente 45°, capturada em período diurno. confiança: alta"],
            ["id_pergunta" => 3, "pergunta" => "Quais são as condições climáticas e de iluminação?", "resposta" => "Céu limpo, alta luminosidade, ausência de poeira visível e solo seco. confiança: alta"]
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['error' => 'Método não permitido']);
}
?>
