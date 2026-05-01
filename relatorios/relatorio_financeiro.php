<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
verificaAcesso();
require __DIR__ . '/../includes/menu.php';

/* =====================
   FILTROS
===================== */
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim    = $_GET['data_fim'] ?? '';
$tipo        = $_GET['tipo'] ?? '';
$status      = $_GET['status'] ?? '';
$id_grupo    = $_GET['id_grupo'] ?? '';
$forma       = $_GET['forma_de_pagamento_recebimento'] ?? '';

$where = [];
$params = [];

if ($data_inicio !== '') {
    $where[] = "l.data_lancamento >= :data_inicio";
    $params[':data_inicio'] = $data_inicio;
}

if ($data_fim !== '') {
    $where[] = "l.data_lancamento <= :data_fim";
    $params[':data_fim'] = $data_fim;
}

if ($tipo !== '') {
    $where[] = "l.tipo = :tipo";
    $params[':tipo'] = $tipo;
}

if ($status !== '') {
    $where[] = "l.status = :status";
    $params[':status'] = $status;
}

if ($id_grupo !== '') {
    $where[] = "l.id_grupo = :id_grupo";
    $params[':id_grupo'] = $id_grupo;
}

if ($forma !== '') {
    $where[] = "l.forma_de_pagamento_recebimento = :forma";
    $params[':forma'] = $forma;
}

$sqlWhere = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

/* =====================
   GRUPOS
===================== */
$stmt = $pdo->query("SELECT id_grupo, descricao FROM grupos ORDER BY descricao");
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   LANÇAMENTOS
===================== */
$sql = "SELECT 
            l.id_lancamento,
            l.documento_numero,
            l.data_lancamento,
            l.descricao,
            l.tipo,
            l.data_vencimento,
            l.valor_nominal,
            l.data_pagamento,
            l.valor_pago,
            l.status,
            l.forma_de_pagamento_recebimento,
            l.id_grupo,
            g.descricao AS grupo_descricao
        FROM lancamentos l
        LEFT JOIN grupos g ON g.id_grupo = l.id_grupo
        $sqlWhere
        ORDER BY l.data_lancamento DESC, l.id_lancamento DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   TOTAIS
===================== */
$total_receber_aberto = 0;
$total_recebido = 0;
$total_pagar_aberto = 0;
$total_pago = 0;

foreach ($lancamentos as $l) {
    $valor_nominal = (float)($l['valor_nominal'] ?? 0);
    $valor_pago = (float)($l['valor_pago'] ?? 0);

    if ($l['tipo'] === 'Receber') {
        if ($l['status'] === 'Recebido') {
            $total_recebido += $valor_pago > 0 ? $valor_pago : $valor_nominal;
        } else {
            $total_receber_aberto += $valor_nominal;
        }
    }

    if ($l['tipo'] === 'Pagar') {
        if ($l['status'] === 'Pago') {
            $total_pago += $valor_pago > 0 ? $valor_pago : $valor_nominal;
        } else {
            $total_pagar_aberto += $valor_nominal;
        }
    }
}

$saldo_previsto = ($total_receber_aberto + $total_recebido) - ($total_pagar_aberto + $total_pago);
$saldo_realizado = $total_recebido - $total_pago;

function dinheiro($valor): string
{
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

function dataBr($data): string
{
    if (empty($data) || $data === '0000-00-00') {
        return '';
    }
    return date('d/m/Y', strtotime($data));
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório Financeiro</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f4f6f8;
            color: #222;
        }

        h2, h3 {
            margin-top: 0;
        }

        .container {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .filtros {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        label {
            font-weight: bold;
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 8px;
            margin-top: 4px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }

        .botoes {
            margin: 15px 0 25px 0;
        }

        button, .btn {
            background: #2c3e50;
            color: white;
            padding: 9px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 8px;
        }

        .btn-limpar {
            background: #777;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }

        .card {
            padding: 15px;
            border-radius: 10px;
            color: white;
            font-weight: bold;
        }

        .card span {
            display: block;
            font-size: 13px;
            margin-bottom: 8px;
            font-weight: normal;
        }

        .receber { background: #2980b9; }
        .recebido { background: #27ae60; }
        .pagar { background: #c0392b; }
        .pago { background: #8e44ad; }
        .saldo-previsto { background: #d35400; }
        .saldo-realizado { background: #16a085; }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th {
            background: #2c3e50;
            color: white;
            padding: 9px;
            font-size: 14px;
        }

        td {
            border: 1px solid #ddd;
            padding: 8px;
            font-size: 14px;
        }

        tr:nth-child(even) {
            background: #f8f8f8;
        }

        .direita {
            text-align: right;
        }

        .centro {
            text-align: center;
        }

        .sem-registro {
            padding: 20px;
            text-align: center;
            color: #777;
        }

        @media print {
            .botoes, form, nav, header, .menu {
                display: none !important;
            }
            body {
                background: white;
                margin: 0;
            }
            .container {
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Relatório Financeiro</h2>

    <form method="get">
        <div class="filtros">
            <div>
                <label>Data Inicial</label>
                <input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>">
            </div>

            <div>
                <label>Data Final</label>
                <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">
            </div>

            <div>
                <label>Tipo</label>
                <select name="tipo">
                    <option value="">Todos</option>
                    <?php foreach (['Pagar','Receber'] as $tp): ?>
                        <option value="<?= $tp ?>" <?= $tipo === $tp ? 'selected' : '' ?>><?= $tp ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Status</label>
                <select name="status">
                    <option value="">Todos</option>
                    <?php foreach (['Aberto','Recebido','Pago'] as $st): ?>
                        <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Classificação</label>
                <select name="id_grupo">
                    <option value="">Todas</option>
                    <?php foreach ($grupos as $grupo): ?>
                        <option value="<?= $grupo['id_grupo'] ?>" <?= $id_grupo == $grupo['id_grupo'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($grupo['descricao']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Forma de Pagamento/Recebimento</label>
                <select name="forma_de_pagamento_recebimento">
                    <?php foreach ([
                        '' => 'Todas',
                        'Pix Recebido' => 'Pix Recebido',
                        'Pix QR Code' => 'Pix QR Code',
                        'Aplicação' => 'Aplicação',
                        'Cartão Débito' => 'Cartão Débito',
                        'Débito Automático' => 'Débito Automático',
                        'Crédito em Conta' => 'Crédito em Conta',
                        'Débito em Conta' => 'Débito em Conta',
                        'Pagamento Boleto' => 'Pagamento Boleto',
                        'Pix Pagamento' => 'Pix Pagamento',
                        'Transação Bancária' => 'Transação Bancária'
                    ] as $valor => $texto): ?>
                        <option value="<?= htmlspecialchars($valor) ?>" <?= $forma === $valor ? 'selected' : '' ?>>
                            <?= htmlspecialchars($texto) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="botoes">
            <button type="submit">Filtrar</button>
            <a class="btn btn-limpar" href="<?= BASE_URL ?>relatorios/relatorio_financeiro.php">Limpar</a>
            <button type="button" onclick="window.print()">Imprimir / Salvar PDF</button>
        </div>
    </form>

    <div class="cards">
        <div class="card receber">
            <span>A Receber em Aberto</span>
            <?= dinheiro($total_receber_aberto) ?>
        </div>

        <div class="card recebido">
            <span>Total Recebido</span>
            <?= dinheiro($total_recebido) ?>
        </div>

        <div class="card pagar">
            <span>A Pagar em Aberto</span>
            <?= dinheiro($total_pagar_aberto) ?>
        </div>

        <div class="card pago">
            <span>Total Pago</span>
            <?= dinheiro($total_pago) ?>
        </div>

        <div class="card saldo-previsto">
            <span>Saldo Previsto</span>
            <?= dinheiro($saldo_previsto) ?>
        </div>

        <div class="card saldo-realizado">
            <span>Saldo Realizado</span>
            <?= dinheiro($saldo_realizado) ?>
        </div>
    </div>

    <h3>Lançamentos Encontrados</h3>

    <?php if (empty($lancamentos)): ?>
        <div class="sem-registro">Nenhum lançamento encontrado para os filtros informados.</div>
    <?php else: ?>
        <table>
            <tr>
                <th>Documento</th>
                <th>Data</th>
                <th>Descrição</th>
                <th>Grupo</th>
                <th>Tipo</th>
                <th>Vencimento</th>
                <th>Status</th>
                <th>Forma</th>
                <th>Valor Nominal</th>
                <th>Valor Pago</th>
            </tr>

            <?php foreach ($lancamentos as $l): ?>
                <tr>
                    <td><?= htmlspecialchars($l['documento_numero'] ?? '') ?></td>
                    <td class="centro"><?= dataBr($l['data_lancamento'] ?? '') ?></td>
                    <td><?= htmlspecialchars($l['descricao'] ?? '') ?></td>
                    <td><?= htmlspecialchars($l['grupo_descricao'] ?? 'Sem grupo') ?></td>
                    <td class="centro"><?= htmlspecialchars($l['tipo'] ?? '') ?></td>
                    <td class="centro"><?= dataBr($l['data_vencimento'] ?? '') ?></td>
                    <td class="centro"><?= htmlspecialchars($l['status'] ?? '') ?></td>
                    <td><?= htmlspecialchars($l['forma_de_pagamento_recebimento'] ?? '') ?></td>
                    <td class="direita"><?= dinheiro($l['valor_nominal'] ?? 0) ?></td>
                    <td class="direita"><?= dinheiro($l['valor_pago'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
