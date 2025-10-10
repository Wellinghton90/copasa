<?php
session_start();
require_once 'connection.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_copasa'])) {
    header('Location: index.php');
    exit();
}

// Usar dados da sessão
$usuario = $_SESSION['user_copasa'];

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php?success=' . urlencode('Logout realizado com sucesso!'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - COPASA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gradient-bg);
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(0, 188, 212, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(38, 198, 218, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(0, 96, 100, 0.1) 0%, transparent 50%);
            animation: backgroundMove 20s ease-in-out infinite;
            z-index: -1;
        }

        @keyframes backgroundMove {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
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

        .navbar-brand:hover {
            color: var(--accent-color);
        }

        .navbar-nav .nav-link {
            color: var(--text-light);
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }

        .navbar-nav .nav-link:hover {
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        .navbar-nav .nav-link .fa-cog {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .navbar-nav .nav-link:hover .fa-cog {
            transform: rotate(90deg);
        }

        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }

        .navbar-nav .nav-link:hover::after {
            width: 100%;
        }

        .container-fluid {
            padding: 30px;
        }

        .welcome-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .welcome-title {
            color: var(--text-light);
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 188, 212, 0.3);
        }

        .welcome-subtitle {
            color: var(--accent-color);
            
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .dashboard-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            box-shadow: 
                0 25px 45px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            padding: 30px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 35px 55px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .card-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 188, 212, 0.3);
        }

        .card-icon i {
            font-size: 1.8rem;
            color: white;
        }

        .card-title {
            color: var(--text-light);
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .card-description {
            color: rgba(227, 242, 253, 0.8);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .card-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-number {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 700;
        }

        .stat-label {
            color: rgba(227, 242, 253, 0.6);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }


        /* Estatísticas Resumidas */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-item {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon i {
            font-size: 1.5rem;
            color: white;
        }

        .stat-info {
            flex: 1;
        }

        .stat-number {
            color: var(--text-light);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            color: rgba(227, 242, 253, 0.8);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Container da Tabela */
        .table-container {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            box-shadow: 
                0 25px 45px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            overflow: hidden;
            margin-bottom: 40px;
        }

        .table-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .table-header {
            padding: 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .table-title {
            color: var(--text-light);
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        .table-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .table-wrapper {
            padding: 0 30px 30px;
            overflow-x: auto;
            width: 100%;
            max-width: 100%;
        }

        .table-wrapper table {
            width: 100% !important;
        }

        .dataTables_wrapper {
            width: 100%;
        }

        .dataTables_scroll {
            width: 100%;
        }

        .dataTables_scrollHead,
        .dataTables_scrollBody {
            width: 100% !important;
        }

        .dataTables_scrollHeadInner,
        .dataTables_scrollBody table {
            width: 100% !important;
        }

        /* Estilização da Tabela */
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
            font-size: 0.8rem;
            padding: 20px 15px;
            border-bottom: 2px solid rgba(0, 188, 212, 0.2);
            white-space: nowrap;
        }

        /* Larguras específicas para cada coluna */
        .table thead th:nth-child(1), .table tbody td:nth-child(1) { /* Ações */
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .table thead th:nth-child(2), .table tbody td:nth-child(2) { /* Nome */
            width: auto;
            min-width: 150px;
            max-width: 200px;
        }

        .table thead th:nth-child(3), .table tbody td:nth-child(3) { /* Descrição */
            width: 250px;
            min-width: 200px;
            max-width: 300px;
            word-wrap: break-word;
            word-break: break-word;
            white-space: normal;
            line-height: 1.4;
        }

        .table thead th:nth-child(4), .table tbody td:nth-child(4) { /* Localização */
            width: 200px;
            min-width: 150px;
            max-width: 250px;
            word-wrap: break-word;
            word-break: break-word;
            white-space: normal;
            line-height: 1.4;
        }

        .table thead th:nth-child(5), .table tbody td:nth-child(5) { /* Cidade */
            width: auto;
            min-width: 120px;
            max-width: 150px;
        }

        .table thead th:nth-child(6), .table tbody td:nth-child(6) { /* UF */
            width: 60px;
            min-width: 60px;
            max-width: 60px;
            text-align: center;
        }

        .table thead th:nth-child(7), .table tbody td:nth-child(7) { /* Status */
            width: auto;
            min-width: 100px;
            max-width: 120px;
            text-align: center;
        }

        .table thead th:nth-child(8), .table tbody td:nth-child(8) { /* Situação */
            width: auto;
            min-width: 100px;
            max-width: 120px;
            text-align: center;
        }

        .table thead th:nth-child(9), .table tbody td:nth-child(9) { /* Latitude */
            width: 100px;
            min-width: 100px;
            max-width: 100px;
            text-align: center;
        }

        .table thead th:nth-child(10), .table tbody td:nth-child(10) { /* Longitude */
            width: 100px;
            min-width: 100px;
            max-width: 100px;
            text-align: center;
        }

        .table thead th:nth-child(11), .table tbody td:nth-child(11) { /* Data Início */
            width: 100px;
            min-width: 100px;
            max-width: 100px;
            text-align: center;
        }

        .table thead th:nth-child(12), .table tbody td:nth-child(12) { /* Data Prevista */
            width: 100px;
            min-width: 100px;
            max-width: 100px;
            text-align: center;
        }

        .table thead th:nth-child(13), .table tbody td:nth-child(13) { /* Data Conclusão */
            width: 100px;
            min-width: 100px;
            max-width: 100px;
            text-align: center;
        }

        .table thead th:nth-child(14), .table tbody td:nth-child(14) { /* Orçamento Total */
            width: 120px;
            min-width: 120px;
            max-width: 120px;
            text-align: right;
        }

        .table thead th:nth-child(15), .table tbody td:nth-child(15) { /* Orçamento Utilizado */
            width: 120px;
            min-width: 120px;
            max-width: 120px;
            text-align: right;
        }

        .table thead th:nth-child(16), .table tbody td:nth-child(16) { /* Responsável */
            width: auto;
            min-width: 150px;
            max-width: 200px;
            word-wrap: break-word;
            word-break: break-word;
            white-space: normal;
            line-height: 1.4;
        }

        .table tbody tr {
            border: none;
            transition: background-color 0.3s ease;
        }

        .table tbody tr:hover {
            background: rgba(0, 188, 212, 0.1);
        }

        .table tbody td {
            border: none;
            padding: 20px 15px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Badges */
        .badge {
            font-size: 0.75rem;
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Botões de Ação */
        .btn-group .btn {
            border-radius: 8px;
            margin: 0 2px;
            transition: all 0.3s ease;
        }

        .btn-group .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* DataTables Customização */
        .dataTables_wrapper {
            color: var(--text-light);
        }

        .dataTables_length,
        .dataTables_filter,
        .dataTables_info,
        .dataTables_paginate {
            color: var(--text-light);
        }

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
            box-shadow: 0 0 10px rgba(0, 188, 212, 0.3);
            outline: none;
        }

        .dataTables_paginate .paginate_button {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: var(--text-light);
            margin: 0 2px;
            padding: 8px 12px;
            transition: all 0.3s ease;
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

        /* Modal Customizado */
        .modal-custom {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            color: var(--text-light);
        }

        .modal-header-custom {
            background: rgba(0, 188, 212, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px 20px 0 0;
        }

        .modal-title {
            color: var(--text-light);
            font-weight: 600;
        }

        .btn-close-custom {
            filter: invert(1);
        }

        .modal-body-custom {
            background: transparent;
        }

        .modal-footer-custom {
            background: rgba(0, 188, 212, 0.05);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0 0 20px 20px;
        }

        .modal-custom .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: var(--text-light);
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .modal-custom .form-control:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--primary-color);
            box-shadow: 0 0 15px rgba(0, 188, 212, 0.2);
            color: var(--text-light);
            outline: none;
        }

        .modal-custom .form-control::placeholder {
            color: rgba(227, 242, 253, 0.6);
        }

        .modal-custom .form-label {
            color: var(--text-light);
            font-weight: 600;
            margin-bottom: 8px;
        }

        .modal-custom .form-text {
            color: rgba(227, 242, 253, 0.7);
        }

        .modal-custom .alert {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border-radius: 12px;
        }

        /* Accordion de Controle de Colunas */
        .accordion-container {
            margin: 30px 0;
        }

        .accordion-item-custom {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .accordion-button-custom {
            background: rgba(0, 188, 212, 0.1);
            border: none;
            color: var(--text-light);
            font-weight: 600;
            padding: 20px 25px;
            transition: all 0.3s ease;
        }

        .accordion-button-custom:not(.collapsed) {
            background: rgba(0, 188, 212, 0.2);
            color: var(--primary-color);
            box-shadow: none;
        }

        .accordion-button-custom:focus {
            box-shadow: 0 0 20px rgba(0, 188, 212, 0.3);
            border: none;
        }

        .accordion-button-custom::after {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
        }

        .accordion-body-custom {
            background: rgba(255, 255, 255, 0.02);
            padding: 30px;
        }

        .columns-section-title {
            color: var(--accent-color);
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .columns-grid-two {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 25px;
        }

        .column-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .column-item:hover {
            background: rgba(0, 188, 212, 0.1);
            border-color: var(--primary-color);
            box-shadow: 0 3px 10px rgba(0, 188, 212, 0.15);
        }

        .column-checkbox {
            display: flex;
            align-items: center;
            cursor: pointer;
            margin: 0;
            font-weight: 500;
            color: var(--text-light);
        }

        .column-checkbox input[type="checkbox"] {
            display: none;
        }

        .checkbox-custom {
            width: 18px;
            height: 18px;
            border: 2px solid var(--primary-color);
            border-radius: 4px;
            margin-right: 12px;
            position: relative;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .column-checkbox input[type="checkbox"]:checked + .checkbox-custom {
            background: var(--primary-color);
        }

        .column-checkbox input[type="checkbox"]:checked + .checkbox-custom::after {
            content: '\f00c';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 10px;
        }

        .column-checkbox input[type="checkbox"]:disabled + .checkbox-custom {
            opacity: 0.6;
            cursor: not-allowed;
            background: rgba(0, 188, 212, 0.3);
        }

        .column-checkbox span:not(.checkbox-custom) {
            font-size: 0.95rem;
        }

        .columns-controls {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .columns-controls .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .columns-controls .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 768px) {
            .container-fluid {
                padding: 20px;
            }
            
            .accordion-body-custom {
                padding: 20px;
            }
            
            .columns-grid-two {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .accordion-button-custom {
                padding: 15px 20px;
                font-size: 0.9rem;
            }
            
            .columns-controls {
                text-align: center;
            }
            
            .columns-controls .btn {
                display: block;
                width: 100%;
                margin-bottom: 10px;
            }
            
            /* Responsividade da tabela */
            .table-wrapper {
                padding: 0 15px 15px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table thead th, .table tbody td {
                padding: 10px 8px;
                font-size: 0.75rem;
            }
            
            /* Ajustar larguras em mobile */
            .table thead th:nth-child(1), .table tbody td:nth-child(1) { /* Ações */
                width: 80px;
                min-width: 80px;
                max-width: 80px;
            }
            
            .table thead th:nth-child(3), .table tbody td:nth-child(3) { /* Descrição */
                width: 150px;
                min-width: 120px;
                max-width: 180px;
            }
            
            .table thead th:nth-child(4), .table tbody td:nth-child(4) { /* Localização */
                width: 120px;
                min-width: 100px;
                max-width: 150px;
            }
            
            .welcome-title {
                font-size: 2rem;
            }
            
            .stats-overview {
                grid-template-columns: 1fr;
            }
            
            .table-header {
                flex-direction: column;
                align-items: stretch;
                padding: 20px;
            }
            
            .table-actions {
                justify-content: center;
            }
            
            .table-wrapper {
                padding: 0 20px 20px;
            }
            
            .table thead th,
            .table tbody td {
                padding: 15px 10px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-water me-2"></i>
                COPASA
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#dashboard">
                            <i class="fas fa-tachometer-alt me-1"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#projetos">
                            <i class="fas fa-project-diagram me-1"></i>
                            Projetos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#relatorios">
                            <i class="fas fa-chart-bar me-1"></i>
                            Relatórios
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="abrirModalAlteracao(); return false;" title="Configurações da Conta">
                            <i class="fas fa-cog me-1"></i>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="?logout=1">
                            <i class="fas fa-sign-out-alt me-1"></i>
                            Sair
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="welcome-section">
            <h3 class="welcome-subtitle">Sistema de Obras de Saneamento</h3>
        </div>

        <!-- Estatísticas Resumidas -->
        <div class="stats-overview">
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number" id="total-obras">-</div>
                    <div class="stat-label">Total de Obras</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number" id="obras-execucao">-</div>
                    <div class="stat-label">Em Execução</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number" id="obras-concluidas">-</div>
                    <div class="stat-label">Concluídas</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-number" id="obras-atrasadas">-</div>
                    <div class="stat-label">Atrasadas</div>
                </div>
            </div>
        </div>

        <!-- Accordion de Controle de Colunas -->
        <div class="accordion-container">
            <div class="accordion" id="accordionColunas">
                <div class="accordion-item accordion-item-custom">
                    <h2 class="accordion-header" id="headingColunas">
                        <button class="accordion-button accordion-button-custom collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseColunas" aria-expanded="false" aria-controls="collapseColunas">
                            <i class="fas fa-columns me-3"></i>
                            <span>Controle de Colunas da Tabela</span>
                        </button>
                    </h2>
                    <div id="collapseColunas" class="accordion-collapse collapse" aria-labelledby="headingColunas" data-bs-parent="#accordionColunas">
                        <div class="accordion-body accordion-body-custom">
                            <div class="row">
                                <div class="col-12">
                                    <div class="columns-grid-two">
                                        <div class="column-item">
                                            <label class="column-checkbox">
                                                <input type="checkbox" checked disabled data-column="0" class="column-toggle">
                                                <span class="checkbox-custom"></span>
                                                <span>Ações</span>
                                            </label>
                                        </div>
                                        <div class="column-item">
                                            <label class="column-checkbox">
                                                <input type="checkbox" checked disabled data-column="1" class="column-toggle">
                                                <span class="checkbox-custom"></span>
                                                <span>Nome</span>
                                            </label>
                                        </div>
                                        <div class="column-item">
                                            <label class="column-checkbox">
                                                <input type="checkbox" data-column="2" class="column-toggle">
                                                <span class="checkbox-custom"></span>
                                                <span>Descrição</span>
                                            </label>
                                        </div>
                                        <div class="column-item">
                                            <label class="column-checkbox">
                                                <input type="checkbox" data-column="3" class="column-toggle">
                                                <span class="checkbox-custom"></span>
                                                <span>Localização</span>
                                            </label>
                                        </div>
                                        <div class="column-item">
                                            <label class="column-checkbox">
                                                <input type="checkbox" checked data-column="4" class="column-toggle">
                                                <span class="checkbox-custom"></span>
                                                <span>Cidade</span>
                                            </label>
                                        </div>
                                        <div class="column-item">
                                            <label class="column-checkbox">
                                                <input type="checkbox" checked data-column="5" class="column-toggle">
                                                <span class="checkbox-custom"></span>
                                                <span>UF</span>
                                            </label>
                                        </div>
                                        <div class="column-item">
                                            <label class="column-checkbox">
                                                <input type="checkbox" checked data-column="6" class="column-toggle">
                                                <span class="checkbox-custom"></span>
                                                <span>Status</span>
                                            </label>
                                        </div>
                                        <div class="column-item">
                                            <label class="column-checkbox">
                                                <input type="checkbox" checked data-column="7" class="column-toggle">
                                                <span class="checkbox-custom"></span>
                                                <span>Situação</span>
                                            </label>
                                        </div>
                                        <div class="column-item">
                                            <label class="column-checkbox">
                                                <input type="checkbox" data-column="8" class="column-toggle">
                                                <span class="checkbox-custom"></span>
                                                <span>Latitude</span>
                                            </label>
                                        </div>
                                        <div class="column-item">
                                            <label class="column-checkbox">
                                                <input type="checkbox" data-column="9" class="column-toggle">
                                                <span class="checkbox-custom"></span>
                                                <span>Longitude</span>
                                            </label>
                                        </div>
                                        <div class="column-item">
                                            <label class="column-checkbox">
                                                <input type="checkbox" data-column="10" class="column-toggle">
                                                <span class="checkbox-custom"></span>
                                                <span>Data Início</span>
                                            </label>
                                        </div>
                                        <div class="column-item">
                                            <label class="column-checkbox">
                                                <input type="checkbox" data-column="11" class="column-toggle">
                                                <span class="checkbox-custom"></span>
                                                <span>Data Prevista</span>
                                            </label>
                                        </div>
                                        <div class="column-item">
                                            <label class="column-checkbox">
                                                <input type="checkbox" data-column="12" class="column-toggle">
                                                <span class="checkbox-custom"></span>
                                                <span>Data Conclusão</span>
                                            </label>
                                        </div>
                                        <div class="column-item">
                                            <label class="column-checkbox">
                                                <input type="checkbox" data-column="13" class="column-toggle">
                                                <span class="checkbox-custom"></span>
                                                <span>Orçamento Total</span>
                                            </label>
                                        </div>
                                        <div class="column-item">
                                            <label class="column-checkbox">
                                                <input type="checkbox" data-column="14" class="column-toggle">
                                                <span class="checkbox-custom"></span>
                                                <span>Orçamento Utilizado</span>
                                            </label>
                                        </div>
                                        <div class="column-item">
                                            <label class="column-checkbox">
                                                <input type="checkbox" data-column="15" class="column-toggle">
                                                <span class="checkbox-custom"></span>
                                                <span>Responsável</span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <!-- Botões de Controle -->
                                    <div class="columns-controls">
                                        <button class="btn btn-outline-primary btn-sm me-2" onclick="habilitarTodasColunas()">
                                            <i class="fas fa-eye me-1"></i>
                                            Habilitar Todas
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm" onclick="resetarColunas()">
                                            <i class="fas fa-undo me-1"></i>
                                            Configuração Padrão
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela de Obras -->
        <div class="table-container">
            <div class="table-header">
                <h3 class="table-title">
                    <i class="fas fa-list me-2"></i>
                    Gestão de Obras
                </h3>
                <div class="table-actions">
                    <button class="btn btn-primary me-2" onclick="novaObra()">
                        <i class="fas fa-plus me-2"></i>
                        Nova Obra
                    </button>
                    <button class="btn btn-outline-secondary" onclick="exportarDados()">
                        <i class="fas fa-download me-2"></i>
                        Exportar
                    </button>
                </div>
            </div>
            
            <div class="table-wrapper">
                <table id="obrasTable" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Ações</th>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Localização</th>
                            <th>Cidade</th>
                            <th>UF</th>
                            <th>Status</th>
                            <th>Situação</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th>Data Início</th>
                            <th>Data Prevista</th>
                            <th>Data Conclusão</th>
                            <th>Orçamento Total</th>
                            <th>Orçamento Utilizado</th>
                            <th>Responsável</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dados carregados via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de Alteração de Dados -->
    <div class="modal fade" id="modalAlteracao" tabindex="-1" aria-labelledby="modalAlteracaoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content modal-custom">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title" id="modalAlteracaoLabel">
                        <i class="fas fa-cog me-2"></i>
                        Configurações da Conta
                    </h5>
                    <button type="button" class="btn-close btn-close-custom" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body modal-body-custom">
                    <form id="formAlteracao" action="alterar_dados.php" method="POST">
                        <input type="hidden" name="action" value="alterar_dados">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Nome Completo</label>
                                    <input type="text" class="form-control" name="nome" id="modal_nome" value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>" required>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Apenas letras maiúsculas e espaços
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Login</label>
                                    <input type="text" class="form-control" name="login" id="modal_login" value="<?= htmlspecialchars($usuario['login'] ?? '') ?>" required>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Deve ser único no sistema
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" id="modal_email" value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" required>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Para confirmação de alterações
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Nova Senha</label>
                                    <input type="password" class="form-control" name="nova_senha" id="modal_senha" placeholder="Deixe em branco para manter a atual">
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Mínimo 6 caracteres
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group mb-3">
                                    <label class="form-label">Senha Atual</label>
                                    <input type="password" class="form-control" name="senha_atual" id="modal_senha_atual" required>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Necessária para confirmar as alterações
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Atenção:</strong> Após alterar seus dados, você será deslogado e precisará confirmar a alteração por email antes de fazer login novamente.
                        </div>
                    </form>
                </div>
                <div class="modal-footer modal-footer-custom">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="salvarAlteracoes()">
                        <i class="fas fa-save me-2"></i>
                        Salvar Alterações
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- DataTables JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        let obrasTable;
        
        // Inicializar DataTable quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar tabela
            obrasTable = $('#obrasTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'get_obras.php',
                    type: 'GET'
                },
                columns: [
                    { data: 0, orderable: false, searchable: false }, // Ações
                    { data: 1 }, // Nome
                    { data: 2 }, // Descrição
                    { data: 3 }, // Localização
                    { data: 4 }, // Cidade
                    { data: 5 }, // UF
                    { data: 6 }, // Status
                    { data: 7 }, // Situação
                    { data: 8 }, // Latitude
                    { data: 9 }, // Longitude
                    { data: 10 }, // Data Início
                    { data: 11 }, // Data Prevista
                    { data: 12 }, // Data Conclusão
                    { data: 13 }, // Orçamento Total
                    { data: 14 }, // Orçamento Utilizado
                    { data: 15 }  // Responsável
                ],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                },
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
                order: [[1, 'asc']], // Ordenar por nome
                responsive: false,
                autoWidth: false,
                scrollX: true,
                scrollCollapse: true,
                deferRender: true,
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                initComplete: function() {
                    // Carregar estatísticas após inicializar a tabela
                    carregarEstatisticas();
                    
                    // Carregar configurações de colunas salvas
                    carregarConfiguracaoColunas();
                }
            });
            
            // Animação dos elementos
            animarElementos();
        });
        
        // Ajustar colunas da tabela ao redimensionar a janela (com debounce)
        let resizeTimer;
        $(window).on('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (obrasTable) {
                    obrasTable.columns.adjust();
                }
            }, 250);
        });

        // Ajustar colunas quando o accordion for aberto/fechado
        $('#collapseColunas').on('shown.bs.collapse hidden.bs.collapse', function() {
            if (obrasTable) {
                setTimeout(function() {
                    obrasTable.columns.adjust();
                }, 300);
            }
        });

        // Carregar estatísticas das obras
        function carregarEstatisticas() {
            fetch('get_obras_stats.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('total-obras').textContent = data.total || 0;
                    document.getElementById('obras-execucao').textContent = data.execucao || 0;
                    document.getElementById('obras-concluidas').textContent = data.concluidas || 0;
                    document.getElementById('obras-atrasadas').textContent = data.atrasadas || 0;
                })
                .catch(error => {
                    console.error('Erro ao carregar estatísticas:', error);
                });
        }

        // Animar elementos da página
        function animarElementos() {
            const elementos = document.querySelectorAll('.stat-item, .table-container');
            
            elementos.forEach((elemento, index) => {
                elemento.style.opacity = '0';
                elemento.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    elemento.style.transition = 'all 0.6s ease';
                    elemento.style.opacity = '1';
                    elemento.style.transform = 'translateY(0)';
                }, index * 150);
            });
        }

        // Funções de ação
        function verObra(id) {
            alert('Ver obra ID: ' + id + '\n\nEsta funcionalidade será implementada em breve!');
        }

        function editarObra(id) {
            alert('Editar obra ID: ' + id + '\n\nEsta funcionalidade será implementada em breve!');
        }

        function excluirObra(id) {
            if (confirm('Tem certeza que deseja excluir esta obra?\n\nEsta ação não pode ser desfeita.')) {
                alert('Excluir obra ID: ' + id + '\n\nEsta funcionalidade será implementada em breve!');
            }
        }

        function novaObra() {
            alert('Nova Obra\n\nEsta funcionalidade será implementada em breve!');
        }

        function exportarDados() {
            if (obrasTable) {
                // Exportar dados usando DataTables
                obrasTable.button('.buttons-excel').trigger();
            } else {
                alert('Exportar Dados\n\nEsta funcionalidade será implementada em breve!');
            }
        }

        // Atualizar estatísticas periodicamente
        setInterval(carregarEstatisticas, 60000); // A cada 1 minuto

        // Funções de Controle de Colunas
        function carregarConfiguracaoColunas() {
            // Configuração padrão (colunas habilitadas)
            const colunasPadrao = {
                '0': true,  // Ações (obrigatório)
                '1': true,  // Nome (obrigatório)
                '2': false, // Descrição
                '3': false, // Localização
                '4': true,  // Cidade
                '5': true,  // UF
                '6': true,  // Status
                '7': true,  // Situação
                '8': false, // Latitude
                '9': false, // Longitude
                '10': false, // Data Início
                '11': false, // Data Prevista
                '12': false, // Data Conclusão
                '13': false, // Orçamento Total
                '14': false, // Orçamento Utilizado
                '15': false  // Responsável
            };
            
            // Usar configuração salva ou padrão
            const configuracaoSalva = localStorage.getItem('copasa_colunas_visiveis');
            const configuracao = configuracaoSalva ? JSON.parse(configuracaoSalva) : colunasPadrao;
            
            // Aplicar configuração aos checkboxes e colunas (sem redesenhar)
            Object.keys(configuracao).forEach(coluna => {
                const checkbox = document.querySelector(`input[data-column="${coluna}"]`);
                if (checkbox) {
                    checkbox.checked = configuracao[coluna];
                    if (obrasTable) {
                        obrasTable.column(coluna).visible(configuracao[coluna], false);
                    }
                }
            });
            
            // Redesenhar apenas uma vez após aplicar todas as configurações
            if (obrasTable) {
                obrasTable.columns.adjust().draw(false);
            }
            
            // Adicionar event listeners aos checkboxes
            document.querySelectorAll('.column-toggle').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const coluna = this.dataset.column;
                    const visivel = this.checked;
                    toggleColuna(coluna, visivel);
                    // Redesenhar após mudança individual
                    if (obrasTable) {
                        obrasTable.columns.adjust().draw(false);
                    }
                    salvarConfiguracaoColunas();
                });
            });
        }

        function toggleColuna(coluna, visivel) {
            if (obrasTable) {
                if (visivel) {
                    obrasTable.column(coluna).visible(true, false);
                } else {
                    // Verificar se não é a coluna nome (obrigatória)
                    if (coluna !== '0') {
                        obrasTable.column(coluna).visible(false, false);
                    }
                }
            }
        }

        function salvarConfiguracaoColunas() {
            const configuracao = {};
            document.querySelectorAll('.column-toggle').forEach(checkbox => {
                configuracao[checkbox.dataset.column] = checkbox.checked;
            });
            localStorage.setItem('copasa_colunas_visiveis', JSON.stringify(configuracao));
        }

        function habilitarTodasColunas() {
            document.querySelectorAll('.column-toggle').forEach(checkbox => {
                if (!checkbox.disabled) {
                    checkbox.checked = true;
                    toggleColuna(checkbox.dataset.column, true);
                }
            });
            salvarConfiguracaoColunas();
        }

        function resetarColunas() {
            // Configuração padrão
            const colunasPadrao = {
                '0': true,  // Ações (obrigatório)
                '1': true,  // Nome (obrigatório)
                '2': false, // Descrição
                '3': false, // Localização
                '4': true,  // Cidade
                '5': true,  // UF
                '6': true,  // Status
                '7': true,  // Situação
                '8': false, // Latitude
                '9': false, // Longitude
                '10': false, // Data Início
                '11': false, // Data Prevista
                '12': false, // Data Conclusão
                '13': false, // Orçamento Total
                '14': false, // Orçamento Utilizado
                '15': false  // Responsável
            };
            
            // Aplicar configuração padrão
            Object.keys(colunasPadrao).forEach(coluna => {
                const checkbox = document.querySelector(`input[data-column="${coluna}"]`);
                if (checkbox) {
                    checkbox.checked = colunasPadrao[coluna];
                    toggleColuna(coluna, colunasPadrao[coluna]);
                }
            });
            
            salvarConfiguracaoColunas();
        }

        // Funções do Modal de Alteração
        function abrirModalAlteracao() {
            const modal = new bootstrap.Modal(document.getElementById('modalAlteracao'));
            modal.show();
            
            // Aplicar validação do nome em tempo real
            document.getElementById('modal_nome').addEventListener('input', function() {
                let nome = this.value;
                
                // Converter para maiúsculo automaticamente
                nome = nome.toUpperCase();
                
                // Remover caracteres inválidos
                nome = nome.replace(/[^A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸ\s]/g, '');
                
                // Atualizar o valor do campo
                this.value = nome;
                
                // Validar
                const regex = /^[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸ\s]+$/;
                if (nome.length > 0 && !regex.test(nome)) {
                    this.style.borderColor = '#f44336';
                    this.style.boxShadow = '0 0 10px rgba(244, 67, 54, 0.2)';
                } else {
                    this.style.borderColor = 'var(--primary-color)';
                    this.style.boxShadow = '0 0 10px rgba(0, 188, 212, 0.2)';
                }
            });
        }

        function salvarAlteracoes() {
            const form = document.getElementById('formAlteracao');
            const formData = new FormData(form);
            
            // Validações
            const nome = formData.get('nome');
            const login = formData.get('login');
            const novaSenha = formData.get('nova_senha');
            const senhaAtual = formData.get('senha_atual');
            
            // Validar nome
            const nomeRegex = /^[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸ\s]+$/;
            if (!nomeRegex.test(nome) || nome.length < 2) {
                alert('O nome deve conter apenas letras maiúsculas e ter pelo menos 2 caracteres!');
                return;
            }
            
            // Validar senha atual
            if (!senhaAtual) {
                alert('Por favor, digite sua senha atual!');
                return;
            }
            
            // Validar nova senha se preenchida
            if (novaSenha && novaSenha.length < 6) {
                alert('A nova senha deve ter pelo menos 6 caracteres!');
                return;
            }
            
            // Confirmar alteração
            if (!confirm('Tem certeza que deseja alterar seus dados?\n\nVocê será deslogado e precisará confirmar por email.')) {
                return;
            }
            
            // Enviar dados
            fetch('alterar_dados.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Dados alterados com sucesso! Você será redirecionado para o login.');
                    window.location.href = 'index.php?success=' + encodeURIComponent(data.message);
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao processar solicitação. Tente novamente.');
            });
        }
    </script>
</body>
</html>
