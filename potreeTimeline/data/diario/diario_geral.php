<?php
session_start();
$base = dirname(__DIR__, 3); // raiz do projeto (copasa)
if (file_exists($base . '/connection.php')) {
    require_once $base . '/connection.php';
}

if (!isset($_SESSION['user_copasa'])) {
    header('Location: ' . $base . '/index.php');
    exit();
}

// Caminho do JSON (pode parametrizar o usuário depois)
$jsonPath = __DIR__ . '/MatheusPrates.json';
$diarioData = ['user' => '', 'entries' => []];
if (file_exists($jsonPath)) {
    $raw = file_get_contents($jsonPath);
    $diarioData = json_decode($raw, true) ?: $diarioData;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diário Geral - COPASA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --primary-color: #00bcd4;
            --secondary-color: #006064;
            --accent-color: #26c6da;
            --dark-bg: #0a1929;
            --card-bg: rgba(255, 255, 255, 0.05);
            --text-light: #e3f2fd;
            --gradient-primary: linear-gradient(135deg, #00bcd4 0%, #006064 100%);
            --gradient-bg: linear-gradient(135deg, #0a1929 0%, #1a237e 50%, #0a1929 100%);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gradient-bg);
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background:
                radial-gradient(circle at 20% 80%, rgba(0, 188, 212, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(38, 198, 218, 0.1) 0%, transparent 50%);
            z-index: -1;
        }

        .navbar {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 15px 0;
        }

        .navbar-brand {
            color: var(--text-light);
            font-weight: 700;
            font-size: 1.5rem;
            text-decoration: none;
        }

        .navbar-brand:hover { color: var(--accent-color); }

        .navbar-nav .nav-link {
            color: var(--text-light);
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover { color: var(--primary-color); }

        .container-fluid { padding: 30px; }

        .page-title {
            color: var(--text-light);
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: var(--accent-color);
            font-size: 0.95rem;
            margin-bottom: 24px;
        }

        .table-container {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.1);
            overflow: hidden;
            margin-bottom: 40px;
            position: relative;
        }

        .table-container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .table-header {
            padding: 24px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .table-title {
            color: var(--text-light);
            font-size: 1.35rem;
            font-weight: 600;
            margin: 0;
        }

        .table-wrapper {
            padding: 0 30px 30px;
            overflow-x: auto;
        }

        .table {
            margin: 0;
            background: transparent;
            color: var(--text-light);
        }

        .table thead th {
            background: rgba(0, 188, 212, 0.1);
            border: none;
            color: var(--accent-color);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.75rem;
            padding: 16px 12px;
            border-bottom: 2px solid rgba(0, 188, 212, 0.2);
        }

        .table tbody tr { transition: background-color 0.2s ease; }
        .table tbody tr:hover { background: rgba(0, 188, 212, 0.08); }

        .table tbody td {
            border: none;
            padding: 14px 12px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.9rem;
        }

        .col-id { width: 1%; white-space: nowrap; max-width: 100px; }
        .col-usuario { min-width: 110px; max-width: 140px; }
        .col-data { white-space: nowrap; min-width: 115px; max-width: 130px; }
        .col-metodo { min-width: 75px; max-width: 95px; }
        .col-projeto { min-width: 90px; max-width: 110px; }
        .col-coords { font-family: monospace; font-size: 0.8rem; min-width: 200px; word-break: break-all; }
.col-coords .point-line { padding: 2px 0; border-bottom: 1px solid rgba(255,255,255,0.06); line-height: 1.4; white-space: nowrap; }
.col-coords .point-line:last-child { border-bottom: none; }
        .col-anotacao { min-width: 380px; max-width: 480px; word-wrap: break-word; white-space: normal; line-height: 1.4; }

        .dataTables_wrapper { color: var(--text-light); }

        .dataTables_length select,
        .dataTables_filter input {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: var(--text-light);
            padding: 8px 12px;
        }

        .dataTables_length select:focus,
        .dataTables_filter input:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--primary-color);
            outline: none;
        }

        .dataTables_paginate .paginate_button {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: var(--text-light);
            margin: 0 2px;
            padding: 8px 12px;
        }

        .dataTables_paginate .paginate_button:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .dataTables_paginate .paginate_button.current {
            background: var(--gradient-primary);
            border-color: var(--primary-color);
            color: white;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--text-light);
            border-radius: 8px;
            padding: 8px 16px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        @media (max-width: 768px) {
            .container-fluid { padding: 20px; }
            .table-wrapper { padding: 0 16px 20px; }
            .table thead th, .table tbody td { padding: 10px 8px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="<?= htmlspecialchars($base) ?>/dashboard.php">
                <i class="fas fa-water me-2"></i>COPASA
            </a>
            <div class="collapse navbar-collapse justify-content-end">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= htmlspecialchars($base) ?>/dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="?logout=1">
                            <i class="fas fa-sign-out-alt me-1"></i>Sair
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <h1 class="page-title"><i class="fas fa-book me-2"></i>Diário Geral</h1>
        <p class="page-subtitle">Registros do diário de inspeção</p>

        <div class="table-container">
            <div class="table-header">
                <h3 class="table-title"><i class="fas fa-list me-2"></i>Entradas do diário</h3>
                <a href="<?= htmlspecialchars($base) ?>/dashboard.php" class="btn btn-back">
                    <i class="fas fa-arrow-left me-2"></i>Voltar
                </a>
            </div>
            <div class="table-wrapper">
                <table id="diarioTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th class="col-id">Id</th>
                            <th class="col-usuario">Usuário</th>
                            <th class="col-data">Data/Hora</th>
                            <th class="col-metodo">Método</th>
                            <th class="col-projeto">Projeto</th>
                            <th class="col-coords">Pixel coords</th>
                            <th class="col-anotacao">Anotação</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        (function() {
            var diarioData = <?= json_encode($diarioData) ?>;

            function formatDateTime(iso) {
                if (!iso) return '-';
                var d = new Date(iso);
                if (isNaN(d.getTime())) return iso;
                return d.toLocaleString('pt-BR', {
                    day: '2-digit', month: '2-digit', year: 'numeric',
                    hour: '2-digit', minute: '2-digit'
                });
            }

            function getMethodology(refs) {
                if (!refs || !refs.length) return '-';
                var methods = [];
                refs.forEach(function(r) {
                    if (r.methodology && methods.indexOf(r.methodology) === -1)
                        methods.push(r.methodology);
                });
                return methods.length ? methods.join(', ') : '-';
            }

            function getProject(refs) {
                if (!refs || !refs.length) return '-';
                var projects = [];
                refs.forEach(function(r) {
                    if (r.project && projects.indexOf(r.project) === -1)
                        projects.push(r.project);
                });
                return projects.length ? projects.join(', ') : '-';
            }

            function getAllPoints(refs) {
                if (!refs || !refs.length) return '-';
                var list = [];
                refs.forEach(function(r) {
                    if (r.points && Array.isArray(r.points)) {
                        r.points.forEach(function(p) {
                            list.push((p.x != null ? Number(p.x).toFixed(2) : '') + ', ' +
                                      (p.y != null ? Number(p.y).toFixed(2) : '') + ', ' +
                                      (p.z != null ? Number(p.z).toFixed(2) : ''));
                        });
                    }
                });
                return list.length ? list.join('\n') : '-';
            }

            var rows = (diarioData.entries || []).map(function(entry) {
                var refs = entry.references || [];
                var text = entry.text || '-';
                var short = text.length > 200 ? text.substring(0, 200) + '…' : text;
                return [
                    entry.id || '-',
                    entry.createdBy || '-',
                    entry.createdAt || '',  // ISO para ordenação; exibição no render
                    getMethodology(refs),
                    getProject(refs),
                    getAllPoints(refs),
                    { display: short, full: text }
                ];
            });

            $('#diarioTable').DataTable({
                data: rows,
                columns: [
                    { title: 'Id', data: 0 },
                    { title: 'Usuário', data: 1 },
                    { title: 'Data/Hora', data: 2, render: function(v) { return v ? formatDateTime(v) : '-'; } },
                    { title: 'Método', data: 3 },
                    { title: 'Projeto', data: 4 },
                    {
                        title: 'Pixel coords',
                        data: 5,
                        render: function(val) {
                            if (!val || val === '-') return '-';
                            var lines = String(val).split('\n');
                            return lines.map(function(line) {
                                var esc = (line || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                return '<div class="point-line">' + esc + '</div>';
                            }).join('');
                        }
                    },
                    {
                        title: 'Anotação',
                        data: 6,
                        render: function(v) {
                            var t = typeof v === 'object' ? (v.display || v.full || '-') : (v || '-');
                            var title = typeof v === 'object' && v.full ? ' title="' + (v.full.replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '"' : '';
                            return '<span' + title + '>' + (t.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '</span>';
                        }
                    }
                ],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                },
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Todos']],
                order: [[2, 'desc']],
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
            });
        })();
    </script>
</body>
</html>
