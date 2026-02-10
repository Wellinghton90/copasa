<?php
session_start();
require_once 'connection.php';

$cidades = [];
$grupos = [];
try {
    $stmt = $conn->query("SELECT DISTINCT cidade FROM obras ORDER BY cidade");
    $cidades = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // ignora
}
try {
    $stmt = $conn->query("SELECT DISTINCT grupo_tipo FROM riscos_obra ORDER BY grupo_tipo");
    $grupos = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // ignora
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perguntas de Risco - COPASA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .toast-fixo {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            min-width: 280px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .toast-fixo.hide {
            animation: slideOut 0.3s ease forwards;
        }
        @keyframes slideOut {
            to { transform: translateX(100%); opacity: 0; }
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-water me-2"></i>COPASA</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <h2 class="mb-4"><i class="fas fa-plus-circle me-2"></i>Adicionar pergunta de risco</h2>

        <form id="formPergunta" class="card shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cidade <span class="text-danger">*</span></label>
                        <select name="cidade" id="cidade" class="form-select" required>
                            <option value="">— Selecione a cidade —</option>
                            <?php foreach ($cidades as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Grupo de perguntas <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select name="grupo_tipo" id="grupo_tipo" class="form-select" required>
                                <option value="">— Selecione o grupo —</option>
                                <?php foreach ($grupos as $g): ?>
                                    <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline-secondary" id="btnAddGrupo" title="Adicionar novo grupo">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="input-group mt-2 d-none" id="wrapNovoGrupo">
                            <input type="text" class="form-control" id="novoGrupo" placeholder="Nome do novo grupo">
                            <button type="button" class="btn btn-success" id="btnConfirmarGrupo">Adicionar</button>
                            <button type="button" class="btn btn-secondary" id="btnCancelarGrupo">Cancelar</button>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Pergunta <span class="text-danger">*</span></label>
                        <textarea name="pergunta" id="pergunta" class="form-control" rows="3" required placeholder="Digite a pergunta"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Resposta <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3 pt-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="resposta" id="respostaSim" value="Sim" required>
                                <label class="form-check-label" for="respostaSim">Sim</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="resposta" id="respostaNao" value="Não">
                                <label class="form-check-label" for="respostaNao">Não</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Evidência fotográfica (opcional)</label>
                        <input type="text" name="evidencia_fotografica" id="evidencia_fotografica" class="form-control" placeholder="Caminho ou link">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary" id="btnSalvar">
                            <i class="fas fa-save me-1"></i> Salvar pergunta
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div id="toast" class="toast-fixo alert d-none" role="alert"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            var form = document.getElementById('formPergunta');
            var toast = document.getElementById('toast');
            var btnAddGrupo = document.getElementById('btnAddGrupo');
            var wrapNovoGrupo = document.getElementById('wrapNovoGrupo');
            var novoGrupo = document.getElementById('novoGrupo');
            var grupoTipo = document.getElementById('grupo_tipo');
            var btnConfirmarGrupo = document.getElementById('btnConfirmarGrupo');
            var btnCancelarGrupo = document.getElementById('btnCancelarGrupo');

            function showToast(msg, isSuccess) {
                toast.textContent = msg;
                toast.className = 'toast-fixo alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
                toast.classList.remove('d-none');
                clearTimeout(toast._t);
                toast._t = setTimeout(function() {
                    toast.classList.add('hide');
                    setTimeout(function() {
                        toast.classList.add('d-none');
                        toast.classList.remove('hide');
                    }, 300);
                }, 4000);
            }

            btnAddGrupo.addEventListener('click', function() {
                wrapNovoGrupo.classList.remove('d-none');
                novoGrupo.value = '';
                novoGrupo.focus();
            });
            btnCancelarGrupo.addEventListener('click', function() {
                wrapNovoGrupo.classList.add('d-none');
            });
            btnConfirmarGrupo.addEventListener('click', function() {
                var nome = novoGrupo.value.trim();
                if (!nome) return;
                var opt = document.createElement('option');
                opt.value = nome;
                opt.textContent = nome;
                opt.selected = true;
                grupoTipo.appendChild(opt);
                wrapNovoGrupo.classList.add('d-none');
                novoGrupo.value = '';
            });

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var cidade = document.getElementById('cidade').value.trim();
                var grupo = document.getElementById('grupo_tipo').value.trim();
                var pergunta = document.getElementById('pergunta').value.trim();
                var resp = document.querySelector('input[name="resposta"]:checked');
                var resposta = resp ? resp.value : '';
                if (!cidade || !grupo || !pergunta || !resposta) {
                    showToast('Preencha ou selecione todos os campos obrigatórios.', false);
                    return;
                }
                var btn = document.getElementById('btnSalvar');
                btn.disabled = true;
                var fd = new FormData(form);
                fetch('salvar_pergunta_risco.php', {
                    method: 'POST',
                    body: fd
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    showToast(res.msg, res.ok);
                    if (res.ok) {
                        document.getElementById('pergunta').value = '';
                        document.getElementById('evidencia_fotografica').value = '';
                        var no = document.getElementById('respostaNao');
                        if (no) no.checked = false;
                        var si = document.getElementById('respostaSim');
                        if (si) si.checked = false;
                    }
                })
                .catch(function(err) {
                    showToast('Erro ao enviar: ' + err.message, false);
                })
                .finally(function() {
                    btn.disabled = false;
                });
            });
        })();
    </script>
</body>
</html>
