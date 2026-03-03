<!DOCTYPE html>
<html lang="pt-BR" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Alertas</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        /* Porting variables from globals.css */
        :root {
            --background: 228 14% 8%;
            --foreground: 210 20% 95%;
            --card: 225 14% 11%;
            --card-foreground: 210 20% 95%;
            --popover: 225 14% 11%;
            --popover-foreground: 210 20% 95%;
            --primary: 210 100% 56%;
            --primary-foreground: 0 0% 100%;
            --secondary: 225 12% 16%;
            --secondary-foreground: 210 20% 85%;
            --muted: 225 12% 16%;
            --muted-foreground: 215 15% 55%;
            --accent: 225 12% 20%;
            --accent-foreground: 210 20% 95%;
            --destructive: 0 72% 51%;
            --destructive-foreground: 0 0% 100%;
            --border: 225 12% 18%;
            --input: 225 12% 18%;
            --ring: 210 100% 56%;
            --radius: 0.625rem;
        }

        .dark {
            /* Variables are already dark mode by default in globals.css for this project */
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: hsl(var(--background));
            color: hsl(var(--foreground));
        }

        /* Custom scrollbar to match dark theme usually */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: hsl(var(--background));
        }

        ::-webkit-scrollbar-thumb {
            background: hsl(var(--muted));
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: hsl(var(--muted-foreground));
        }
    </style>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        border: "hsl(var(--border))",
                        input: "hsl(var(--input))",
                        ring: "hsl(var(--ring))",
                        background: "hsl(var(--background))",
                        foreground: "hsl(var(--foreground))",
                        primary: {
                            DEFAULT: "hsl(var(--primary))",
                            foreground: "hsl(var(--primary-foreground))",
                        },
                        secondary: {
                            DEFAULT: "hsl(var(--secondary))",
                            foreground: "hsl(var(--secondary-foreground))",
                        },
                        destructive: {
                            DEFAULT: "hsl(var(--destructive))",
                            foreground: "hsl(var(--destructive-foreground))",
                        },
                        muted: {
                            DEFAULT: "hsl(var(--muted))",
                            foreground: "hsl(var(--muted-foreground))",
                        },
                        accent: {
                            DEFAULT: "hsl(var(--accent))",
                            foreground: "hsl(var(--accent-foreground))",
                        },
                        popover: {
                            DEFAULT: "hsl(var(--popover))",
                            foreground: "hsl(var(--popover-foreground))",
                        },
                        card: {
                            DEFAULT: "hsl(var(--card))",
                            foreground: "hsl(var(--card-foreground))",
                        },
                    },
                    borderRadius: {
                        lg: "var(--radius)",
                        md: "calc(var(--radius) - 2px)",
                        sm: "calc(var(--radius) - 4px)",
                    },
                }
            }
        }
    </script>
</head>

<body class="min-h-screen antialiased selection:bg-primary/20">

    <?php
    // Mock Data from lib/alerts-data.ts
    $mockAlerts = [
        [
            "id" => "ALT-001",
            "titulo" => "Atraso critico na concretagem do Bloco B",
            "descricao" => "A atividade de concretagem do Bloco B esta com 12 dias de atraso em relacao ao cronograma previsto no MS Project. A inspecao visual confirma que a armacao ainda nao foi concluida.",
            "tipo" => "Desvio de Cronograma",
            "condicaoDisparo" => "Atraso > 10 dias uteis em atividade do caminho critico",
            "destinatarios" => ["Eng. Carlos Silva", "Coord. Maria Santos"],
            "severidade" => "critico",
            "acaoEsperada" => "Revisar cronograma e alocar equipe adicional imediatamente",
            "objeto" => "BLC-B-CONC-004",
            "dataGeracao" => "2026-02-06T10:30:00",
            "previsto" => "2026-01-20",
            "observado" => "Em execucao (55%)",
            "desvio" => "+12 dias",
            "status" => "aberto",
            "evidencias" => [
                ["label" => "Foto da armacao incompleta - Bloco B", "tipo" => "foto", "url" => "#foto-bloco-b-armacao"],
                ["label" => "Cronograma MS Project v12 - Linha base", "tipo" => "documento", "url" => "#ms-project-v12"],
                ["label" => "Relatorio de Inspecao #047", "tipo" => "relatorio", "url" => "#relatorio-inspecao-047"]
            ]
        ],
        [
            "id" => "ALT-002",
            "titulo" => "Fundacao Bloco A nao conforme com DXF",
            "descricao" => "O posicionamento das estacas do Bloco A apresenta desvio de 15cm em relacao ao projeto DXF. A inspecao visual detectou desalinhamento nos eixos 3 e 4.",
            "tipo" => "Nao Conformidade Geometrica",
            "condicaoDisparo" => "Desvio posicional > 10cm em relacao ao DXF de referencia",
            "destinatarios" => ["Eng. Roberto Lima", "Proj. Ana Oliveira"],
            "severidade" => "critico",
            "acaoEsperada" => "Parar execucao e solicitar parecer estrutural",
            "objeto" => "BLC-A-FUND-012",
            "dataGeracao" => "2026-02-05T14:15:00",
            "previsto" => "Alinhamento conforme DXF eixo 3-4",
            "observado" => "Desvio de 15cm no eixo 3",
            "desvio" => "+15cm lateral",
            "status" => "em_andamento",
            "evidencias" => [
                ["label" => "Foto do desalinhamento - Eixo 3", "tipo" => "foto", "url" => "#foto-eixo3-desalinhamento"],
                ["label" => "DXF de referencia - Bloco A Fundacao", "tipo" => "documento", "url" => "#dxf-bloco-a-fundacao"],
                ["label" => "Levantamento topografico 05/02", "tipo" => "relatorio", "url" => "#levantamento-topo-0502"],
                ["label" => "Parecer estrutural preliminar", "tipo" => "documento", "url" => "#parecer-estrutural-001"]
            ]
        ],
        [
            "id" => "ALT-003",
            "titulo" => "Alvenaria Bloco C com execucao parcial",
            "descricao" => "A alvenaria do 2o pavimento do Bloco C apresenta execucao de apenas 30% quando o cronograma previa 70%. Possivel impacto em atividades subsequentes.",
            "tipo" => "Desvio de Progresso",
            "condicaoDisparo" => "Progresso observado < 50% do previsto para a data atual",
            "destinatarios" => ["Mestre Joao", "Eng. Patricia Souza"],
            "severidade" => "medio",
            "acaoEsperada" => "Verificar causa do atraso e reportar plano de recuperacao",
            "objeto" => "BLC-C-ALV-008",
            "dataGeracao" => "2026-02-06T08:00:00",
            "previsto" => "70% concluido",
            "observado" => "30% concluido",
            "desvio" => "-40pp",
            "status" => "aberto",
            "evidencias" => [
                ["label" => "Foto panoramica - 2o pav Bloco C", "tipo" => "foto", "url" => "#foto-2pav-bloco-c"],
                ["label" => "Relatorio de Medicao Semanal #12", "tipo" => "relatorio", "url" => "#relatorio-medicao-12"]
            ]
        ],
        [
            "id" => "ALT-004",
            "titulo" => "Material de acabamento entregue fora do prazo",
            "descricao" => "O lote de revestimento ceramico para o Bloco D foi entregue com 5 dias de atraso. O estoque atual cobre apenas 2 dias de producao.",
            "tipo" => "Desvio de Suprimentos",
            "condicaoDisparo" => "Atraso de entrega > 3 dias uteis em material critico",
            "destinatarios" => ["Compras - Felipe", "Eng. Patricia Souza"],
            "severidade" => "medio",
            "acaoEsperada" => "Contatar fornecedor e avaliar fornecedor alternativo",
            "objeto" => "BLC-D-REV-003",
            "dataGeracao" => "2026-02-04T16:45:00",
            "previsto" => "Entrega em 2026-01-30",
            "observado" => "Entregue em 2026-02-04",
            "desvio" => "+5 dias",
            "status" => "resolvido",
            "evidencias" => [
                ["label" => "Nota fiscal de entrega #8834", "tipo" => "documento", "url" => "#nf-8834"],
                ["label" => "Planilha de controle de estoque", "tipo" => "planilha", "url" => "#planilha-estoque-blocoD"]
            ]
        ],
        [
            "id" => "ALT-005",
            "titulo" => "Divergencia entre versoes do DXF",
            "descricao" => "Foram detectadas 3 versoes diferentes do arquivo DXF para o Bloco E em circulacao. A versao utilizada em campo pode nao ser a mais recente.",
            "tipo" => "Qualidade de Dados",
            "condicaoDisparo" => "Multiplas versoes de DXF detectadas para o mesmo objeto",
            "destinatarios" => ["BIM Manager - Lucas", "Coord. Maria Santos"],
            "severidade" => "leve",
            "acaoEsperada" => "Consolidar versoes e distribuir arquivo oficial atualizado",
            "objeto" => "BLC-E-DXF-REV03",
            "dataGeracao" => "2026-02-06T09:00:00",
            "previsto" => "1 versao oficial vigente",
            "observado" => "3 versoes em circulacao",
            "desvio" => "Conflito de versoes",
            "status" => "aberto",
            "evidencias" => [
                ["label" => "Log de versoes do DXF - Bloco E", "tipo" => "relatorio", "url" => "#log-versoes-dxf-blocoE"],
                ["label" => "Comparativo entre versoes (overlay)", "tipo" => "documento", "url" => "#comparativo-dxf-overlay"]
            ]
        ],
        [
            "id" => "ALT-006",
            "titulo" => "Cronograma MS Project desatualizado ha 15 dias",
            "descricao" => "O arquivo do MS Project nao recebe atualizacao desde 22/01/2026. As comparacoes de cronograma podem estar imprecisas.",
            "tipo" => "Qualidade de Dados",
            "condicaoDisparo" => "Ultima atualizacao do MS Project > 10 dias uteis",
            "destinatarios" => ["Planejamento - Renata", "Coord. Maria Santos"],
            "severidade" => "leve",
            "acaoEsperada" => "Atualizar cronograma MS Project com dados de campo",
            "objeto" => "PRJ-CRONOGRAMA-MASTER",
            "dataGeracao" => "2026-02-06T07:00:00",
            "previsto" => "Atualizacao semanal",
            "observado" => "15 dias sem atualizacao",
            "desvio" => "+10 dias sem update",
            "status" => "aberto",
            "evidencias" => [
                ["label" => "Historico de modificacoes MS Project", "tipo" => "relatorio", "url" => "#historico-ms-project"]
            ]
        ],
        [
            "id" => "ALT-007",
            "titulo" => "Bloco A - Estrutura concluida antes do prazo",
            "descricao" => "A estrutura do Bloco A foi concluida 8 dias antes do previsto no cronograma. Possibilita antecipacao de atividades de alvenaria e instalacoes.",
            "tipo" => "Antecipacao de Cronograma",
            "condicaoDisparo" => "Conclusao de atividade > 5 dias antes do previsto",
            "destinatarios" => ["Coord. Maria Santos", "Eng. Carlos Silva"],
            "severidade" => "positivo",
            "acaoEsperada" => "Avaliar antecipacao de atividades subsequentes e realocar equipes",
            "objeto" => "BLC-A-EST-001",
            "dataGeracao" => "2026-02-05T17:00:00",
            "previsto" => "Conclusao em 2026-02-13",
            "observado" => "Concluido em 2026-02-05",
            "desvio" => "-8 dias",
            "status" => "resolvido",
            "evidencias" => [
                ["label" => "Foto da estrutura concluida - Bloco A", "tipo" => "foto", "url" => "#foto-estrutura-blocoA"],
                ["label" => "Termo de conclusao assinado", "tipo" => "documento", "url" => "#termo-conclusao-blocoA"],
                ["label" => "Video drone - Vista aerea Bloco A", "tipo" => "video", "url" => "#video-drone-blocoA"]
            ]
        ],
        [
            "id" => "ALT-008",
            "titulo" => "Bloco D - Produtividade acima da meta",
            "descricao" => "A equipe de alvenaria do Bloco D esta produzindo 20% acima da meta diaria nos ultimos 5 dias consecutivos.",
            "tipo" => "Performance Positiva",
            "condicaoDisparo" => "Produtividade > 115% da meta por 5+ dias consecutivos",
            "destinatarios" => ["Mestre Joao", "RH - Fernanda"],
            "severidade" => "positivo",
            "acaoEsperada" => "Registrar boas praticas e avaliar bonificacao da equipe",
            "objeto" => "BLC-D-ALV-EQUIPE02",
            "dataGeracao" => "2026-02-06T11:00:00",
            "previsto" => "12m2/dia",
            "observado" => "14.4m2/dia",
            "desvio" => "+20%",
            "status" => "aberto",
            "evidencias" => [
                ["label" => "Planilha de produtividade diaria", "tipo" => "planilha", "url" => "#planilha-produtividade-blocoD"],
                ["label" => "Relatorio de desempenho semanal #09", "tipo" => "relatorio", "url" => "#relatorio-desempenho-09"]
            ]
        ],
    ];
    ?>

    <!-- App Container -->
    <div id="app" class="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-6 md:px-6 lg:py-8">
        <div id="header-container"></div>
        <div id="summary-container"></div>
        <div id="list-container"></div>
    </div>

    <!-- Scripts -->
    <script>
        // Data injected from PHP
        const alerts = <?php echo json_encode($mockAlerts); ?>;

        // Severity Configuration
        const SEVERITY_CONFIG = {
            positivo: {
                label: "Positivo",
                nivel: "Nivel 0",
                nivelNumero: 0,
                color: "text-emerald-400",
                bgColor: "bg-emerald-500/10",
                borderColor: "border-emerald-500/30",
                dotColor: "bg-emerald-500",
                description: "Alertas Positivos",
            },
            leve: {
                label: "Leve",
                nivel: "Nivel 1",
                nivelNumero: 1,
                color: "text-slate-300",
                bgColor: "bg-slate-400/10",
                borderColor: "border-slate-400/30",
                dotColor: "bg-slate-300",
                description: "Alertas de Risco Leve e Qualidade de Dados",
            },
            medio: {
                label: "Medio",
                nivel: "Nivel 2",
                nivelNumero: 2,
                color: "text-amber-400",
                bgColor: "bg-amber-500/10",
                borderColor: "border-amber-500/30",
                dotColor: "bg-amber-500",
                description: "Alertas de Risco Medio (Nao Conformidade Aparente)",
            },
            critico: {
                label: "Critico",
                nivel: "Nivel 3",
                nivelNumero: 3,
                color: "text-red-400",
                bgColor: "bg-red-500/10",
                borderColor: "border-red-500/30",
                dotColor: "bg-red-500",
                description: "Alertas Criticos (Automaticos e Imediatos)",
            },
        };

        const SEVERITY_ORDER = ["critico", "medio", "leve", "positivo"];

        const EVIDENCE_ICONS = {
            foto: 'image',
            documento: 'file-text',
            relatorio: 'bar-chart-3',
            planilha: 'table-2',
            video: 'video',
            link: 'external-link',
        };

        // State
        const state = {
            activeFilter: 'todos',
            searchQuery: '',
            statusFilter: 'todos',
            riskLevelFilter: 'todos',
            expandedAlerts: {}
        };

        // Render Functions
        function getHeaderHTML() {
            return `
                <header class="flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                <i data-lucide="bell" class="h-5 w-5 text-primary"></i>
                            </div>
                            <div>
                                <h1 class="text-xl font-bold text-foreground">Central de Alertas</h1>
                                <p class="text-xs text-muted-foreground">Monitoramento de cronograma, execucao e qualidade de dados</p>
                            </div>
                        </div>
                        <div style="width: 40%; display: flex; justify-content: right;">
                            <button style="border: 1px solid white; border-radius: 6px; padding: 4px 6px">Download</button>
                        </div>
                        <div class="hidden items-center gap-2 text-xs text-muted-foreground md:flex">
                            <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                            Atualizado em tempo real
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 md:flex-row md:items-center">
                        <div class="relative flex-1">
                            <i data-lucide="search" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"></i>
                            <input
                                type="text"
                                placeholder="Buscar por titulo, objeto ou ID..."
                                value="${state.searchQuery}"
                                oninput="handleSearch(this.value)"
                                class="flex h-10 w-full rounded-md border border-input bg-secondary/50 px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 pl-9"
                            />
                        </div>
                         <div class="flex items-center gap-2">
                            <i data-lucide="filter" class="h-4 w-4 shrink-0 text-muted-foreground"></i>
                            
                            <select onchange="handleRiskChange(this.value)" class="flex h-10 items-center justify-between rounded-md border border-input bg-secondary/50 px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 w-[180px]">
                                <option value="todos" ${state.riskLevelFilter === 'todos' ? 'selected' : ''}>Todos os Niveis</option>
                                ${SEVERITY_ORDER.map(key => `
                                    <option value="${key}" ${state.riskLevelFilter === key ? 'selected' : ''}>
                                        ${SEVERITY_CONFIG[key].nivel} - ${SEVERITY_CONFIG[key].label}
                                    </option>
                                `).join('')}
                            </select>

                             <select onchange="handleStatusChange(this.value)" class="flex h-10 items-center justify-between rounded-md border border-input bg-secondary/50 px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 w-[160px]">
                                <option value="todos" ${state.statusFilter === 'todos' ? 'selected' : ''}>Todos os Status</option>
                                <option value="aberto" ${state.statusFilter === 'aberto' ? 'selected' : ''}>Aberto</option>
                                <option value="em_andamento" ${state.statusFilter === 'em_andamento' ? 'selected' : ''}>Em Andamento</option>
                                <option value="resolvido" ${state.statusFilter === 'resolvido' ? 'selected' : ''}>Resolvido</option>
                            </select>
                        </div>
                    </div>
                </header>
            `;
        }

        function getSummaryHTML() {
            const counts = {
                todos: alerts.length,
                critico: alerts.filter((a) => a.severidade === "critico").length,
                medio: alerts.filter((a) => a.severidade === "medio").length,
                leve: alerts.filter((a) => a.severidade === "leve").length,
                positivo: alerts.filter((a) => a.severidade === "positivo").length,
            };
            const abertos = alerts.filter((a) => a.status === "aberto").length;

            return `
                <div class="flex flex-col gap-4">
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                        <button
                            type="button"
                            onclick="handleFilterClick('todos')"
                            class="flex flex-col gap-2 rounded-lg border p-4 text-left transition-all ${state.activeFilter === "todos"
                    ? "border-primary/50 bg-primary/10"
                    : "border-border bg-card hover:border-border/80 hover:bg-accent"
                }"
                        >
                            <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Total de Alertas</span>
                            <div class="flex items-end gap-2">
                                <span class="text-2xl font-bold text-foreground">${counts.todos}</span>
                                <span class="mb-0.5 text-xs text-muted-foreground">${abertos} abertos</span>
                            </div>
                        </button>

                        ${SEVERITY_ORDER.map(key => {
                    const config = SEVERITY_CONFIG[key];
                    return `
                                <button
                                    type="button"
                                    onclick="handleFilterClick('${key}')"
                                    class="flex flex-col gap-2 rounded-lg border p-4 text-left transition-all ${state.activeFilter === key
                            ? `${config.borderColor} ${config.bgColor}`
                            : "border-border bg-card hover:border-border/80 hover:bg-accent"
                        }"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block h-2.5 w-2.5 rounded-full ${config.dotColor}"></span>
                                        <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">${config.nivel}</span>
                                    </div>
                                    <div class="flex items-end gap-2">
                                        <span class="text-2xl font-bold ${config.color}">${counts[key]}</span>
                                        <span class="mb-0.5 text-xs text-muted-foreground">${config.label}</span>
                                    </div>
                                </button>
                            `
                }).join('')}
                    </div>
                </div>
            `;
        }

        function getListHTML() {
            const filteredAlerts = alerts.filter(alert => {
                if (state.activeFilter !== "todos" && alert.severidade !== state.activeFilter) return false;
                if (state.statusFilter !== "todos" && alert.status !== state.statusFilter) return false;

                if (state.searchQuery) {
                    const query = state.searchQuery.toLowerCase();
                    return (
                        alert.titulo.toLowerCase().includes(query) ||
                        alert.id.toLowerCase().includes(query) ||
                        alert.objeto.toLowerCase().includes(query) ||
                        alert.descricao.toLowerCase().includes(query)
                    );
                }
                return true;
            });

            return `
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">
                        ${filteredAlerts.length} ${filteredAlerts.length === 1 ? "alerta" : "alertas"}
                        ${state.activeFilter !== "todos" ? "neste nivel de risco" : "no total"}
                    </p>
                    <p class="text-xs text-muted-foreground">Clique em um alerta para expandir detalhes</p>
                </div>

                <div class="flex flex-col gap-3">
                    ${filteredAlerts.length === 0
                    ? `<div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-border py-16">
                             <p class="text-sm text-muted-foreground">Nenhum alerta encontrado para os filtros selecionados.</p>
                           </div>`
                    : filteredAlerts.map(getAlertCardHTML).join('')
                }
                </div>
            `;
        }

        function getAlertCardHTML(alert) {
            const expanded = state.expandedAlerts[alert.id] || false;
            const config = SEVERITY_CONFIG[alert.severidade];

            const formatDate = (dateStr) => {
                const date = new Date(dateStr);
                return date.toLocaleDateString("pt-BR", {
                    day: "2-digit",
                    month: "2-digit",
                    year: "numeric",
                    hour: "2-digit",
                    minute: "2-digit",
                });
            };

            const statusConfig = {
                aberto: { label: "Aberto", className: "border-red-500/30 bg-red-500/10 text-red-400", icon: "circle" },
                em_andamento: { label: "Em Andamento", className: "border-amber-500/30 bg-amber-500/10 text-amber-400", icon: "loader-2" },
                resolvido: { label: "Resolvido", className: "border-emerald-500/30 bg-emerald-500/10 text-emerald-400", icon: "check-circle-2" },
            };

            const statusInfo = statusConfig[alert.status];

            let severityIcon = '';
            if (alert.severidade === 'critico') severityIcon = `<i data-lucide="alert-triangle" class="h-4 w-4 ${config.color}"></i>`;
            else if (alert.severidade === 'medio') severityIcon = `<i data-lucide="clock" class="h-4 w-4 ${config.color}"></i>`;
            else if (alert.severidade === 'leve') severityIcon = `<i data-lucide="shield" class="h-4 w-4 ${config.color}"></i>`;
            else severityIcon = `<i data-lucide="zap" class="h-4 w-4 ${config.color}"></i>`;

            return `
                <div class="group overflow-hidden rounded-lg border transition-all ${config.borderColor} ${expanded ? config.bgColor : "bg-card hover:bg-accent/50"}">
                    <button type="button" class="flex w-full items-start gap-4 p-4 text-left" onclick="toggleExpand('${alert.id}')">
                        <div class="flex flex-col items-center gap-1 pt-0.5">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${config.bgColor}">
                                ${severityIcon}
                            </span>
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-mono text-muted-foreground">${alert.id}</span>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 ${config.borderColor} ${config.bgColor} ${config.color}">
                                    ${config.nivel}
                                </span>
                                <div class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 gap-1 ${statusInfo.className}">
                                    <i data-lucide="${statusInfo.icon}" class="h-3 w-3"></i>
                                    ${statusInfo.label}
                                </div>
                            </div>
                            <h3 class="text-sm font-semibold text-foreground leading-snug">${alert.titulo}</h3>
                            <p class="text-xs text-muted-foreground line-clamp-1">${alert.descricao}</p>
                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <span class="text-[10px] text-muted-foreground">${formatDate(alert.dataGeracao)}</span>
                            <span class="text-sm font-bold ${config.color}">${alert.desvio}</span>
                            <i data-lucide="${expanded ? 'chevron-up' : 'chevron-down'}" class="mt-1 h-4 w-4 text-muted-foreground"></i>
                        </div>
                    </button>
                    
                    ${expanded ? `
                        <div class="border-t border-border/50 px-4 pb-4 pt-3">
                            <div class="flex flex-col gap-4">
                                <p class="text-sm text-muted-foreground leading-relaxed">${alert.descricao}</p>

                                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <div class="rounded-md bg-secondary/50 p-3">
                                        <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Previsto</span>
                                        <p class="mt-1 text-sm font-medium text-foreground">${alert.previsto}</p>
                                    </div>
                                    <div class="rounded-md bg-secondary/50 p-3">
                                        <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Observado</span>
                                        <p class="mt-1 text-sm font-medium text-foreground">${alert.observado}</p>
                                    </div>
                                    <div class="rounded-md p-3 ${config.bgColor}">
                                        <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Desvio</span>
                                        <p class="mt-1 text-sm font-bold ${config.color}">${alert.desvio}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div class="flex flex-col gap-2">
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="zap" class="h-3.5 w-3.5 text-muted-foreground"></i>
                                            <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Condicao de Disparo</span>
                                        </div>
                                        <p class="text-xs text-foreground/80 leading-relaxed">${alert.condicaoDisparo}</p>
                                    </div>

                                    <div class="flex flex-col gap-2">
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="target" class="h-3.5 w-3.5 text-muted-foreground"></i>
                                            <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Acao Esperada</span>
                                        </div>
                                        <p class="text-xs text-foreground/80 leading-relaxed">${alert.acaoEsperada}</p>
                                    </div>
                                </div>

                                ${alert.evidencias.length > 0 ? `
                                    <div class="flex flex-col gap-2">
                                        <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Evidencias</span>
                                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            ${alert.evidencias.map(ev => {
                const iconName = EVIDENCE_ICONS[ev.tipo] || 'external-link';
                return `
                                                    <a href="${ev.url}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 rounded-md border p-3 transition-colors hover:bg-secondary/80 ${config.borderColor}">
                                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md ${config.bgColor}">
                                                            <i data-lucide="${iconName}" class="h-4 w-4 ${config.color}"></i>
                                                        </span>
                                                        <div class="flex min-w-0 flex-col">
                                                            <span class="truncate text-xs font-medium text-foreground">${ev.label}</span>
                                                            <span class="text-[10px] capitalize text-muted-foreground">${ev.tipo}</span>
                                                        </div>
                                                        <i data-lucide="external-link" class="ml-auto h-3 w-3 shrink-0 text-muted-foreground"></i>
                                                    </a>
                                                `;
            }).join('')}
                                        </div>
                                    </div>
                                ` : ''}

                                <div class="flex flex-wrap items-center gap-4 border-t border-border/50 pt-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Objeto:</span>
                                        <code class="rounded bg-secondary px-1.5 py-0.5 font-mono text-xs text-foreground">${alert.objeto}</code>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Tipo:</span>
                                        <span class="text-xs text-foreground/80">${alert.tipo}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <i data-lucide="user" class="h-3 w-3 text-muted-foreground"></i>
                                        <span class="text-xs text-foreground/80">${alert.destinatarios.join(", ")}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
        }

        // Render Helpers
        function renderHeader() {
            document.getElementById('header-container').innerHTML = getHeaderHTML();
            lucide.createIcons();
        }

        function renderSummary() {
            document.getElementById('summary-container').innerHTML = getSummaryHTML();
            lucide.createIcons();
        }

        function renderList() {
            document.getElementById('list-container').innerHTML = getListHTML();
            lucide.createIcons();
        }

        // Action Handlers
        function handleSearch(value) {
            state.searchQuery = value;
            renderList(); // Only re-render list
        }

        function handleRiskChange(value) {
            state.riskLevelFilter = value;
            state.activeFilter = value; // Sync active filter
            renderSummary(); // Summary highlights change
            renderList();    // List contents change
        }

        function handleStatusChange(value) {
            state.statusFilter = value;
            renderList(); // Only list changes
        }

        function handleFilterClick(value) {
            state.activeFilter = value;
            state.riskLevelFilter = value;

            // We need to update the dropdown in header if we click the summary buttons
            // This is "Cross-Component" state update
            renderHeader(); // Re-render header to update dropdown selection
            renderSummary();
            renderList();
        }

        function toggleExpand(id) {
            state.expandedAlerts[id] = !state.expandedAlerts[id];
            renderList();
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            renderHeader();
            renderSummary();
            renderList();
        });

    </script>
</body>

</html>