<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
verificaAcesso();
require __DIR__ . '/../includes/menu.php';

$data_inicio = $_GET['inicio'] ?? date('Y-m-01');
$data_fim    = $_GET['fim'] ?? date('Y-m-t');
$status      = $_GET['status'] ?? 'Aberto';

$where = [
    "tipo = 'Pagar'",
    "data_vencimento BETWEEN :inicio AND :fim"
];

$params = [
    ':inicio' => $data_inicio,
    ':fim'    => $data_fim
];

if ($status !== 'Todos') {
    $where[] = "status = :status";
    $params[':status'] = $status;
}

$whereSQL = "WHERE " . implode(" AND ", $where);

$sql = "SELECT 
            l.id_lancamento,
            l.documento_numero,
            l.data_lancamento,
            l.descricao,
            l.data_vencimento,
            l.valor_nominal,
            l.data_pagamento,
            l.valor_pago,
            l.status,
            l.forma_de_pagamento_recebimento,
            g.descricao AS grupo
        FROM lancamentos l
        LEFT JOIN grupos g ON g.id_grupo = l.id_grupo
        $whereSQL
        ORDER BY l.data_vencimento ASC, l.descricao ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_aberto = 0;
$total_pago = 0;
$total_geral = 0;

foreach ($lancamentos as $l) {
    $valor = (float)($l['valor_nominal'] ?? 0);
    $total_geral += $valor;

    if ($l['status'] === 'Pago') {
        $total_pago += (float)($l['valor_pago'] ?: $l['valor_nominal']);
    } else {
        $total_aberto += $valor;
    }
}

function dataBR($data) {
    if (empty($data)) return '';
    return date('d/m/Y', strtotime($data));
}

function moedaBR($valor) {
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Compromissos de Pagamentos</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background: #f4f6f8;
    color: #222;
}

h2 {
    margin-bottom: 10px;
}

.filtros, .resumo {
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #ddd;
}

label {
    font-weight: bold;
    margin-right: 5px;
}

input, select, button {
    padding: 7px;
    margin: 5px 10px 5px 0;
}

button {
    cursor: pointer;
}

.cards {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.card {
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #ddd;
    min-width: 180px;
}

.card strong {
    display: block;
    font-size: 14px;
    color: #555;
}

.card span {
    font-size: 22px;
    font-weight: bold;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
}

th, td {
    border: 1px solid #ccc;
    padding: 8px;
    font-size: 14px;
}

th {
    background: #2c3e50;
    color: #fff;
}

tr:nth-child(even) {
    background: #f9f9f9;
}

.aberto {
    color: #c0392b;
    font-weight: bold;
}

.pago {
    color: #207245;
    font-weight: bold;
}

.acoes {
    margin-top: 15px;
}

@media print {
    .filtros, .acoes, .menu, nav {
        display: none !important;
    }

    body {
        background: #fff;
        margin: 10px;
    }
}
</style>
</head>

<body>

<h2>Compromissos de Pagamentos</h2>

<div class="filtros">
    <form method="get">
        <label>Data inicial:</label>
        <input type="date" name="inicio" value="<?= htmlspecialchars($data_inicio) ?>" required>

        <label>Data final:</label>
        <input type="date" name="fim" value="<?= htmlspecialchars($data_fim) ?>" required>

        <label>Status:</label>
        <select name="status">
            <?php foreach (['Todos', 'Aberto', 'Pago'] as $st): ?>
                <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>>
                    <?= $st ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Filtrar</button>
    </form>
</div>

<div class="cards">
    <div class="card">
        <strong>Total de compromissos</strong>
        <span><?= moedaBR($total_geral) ?></span>
    </div>

    <div class="card">
        <strong>Total em aberto</strong>
        <span><?= moedaBR($total_aberto) ?></span>
    </div>

    <div class="card">
        <strong>Total pago</strong>
        <span><?= moedaBR($total_pago) ?></span>
    </div>
</div>

<br>

<table>
    <tr>
        <th>Vencimento</th>
        <th>Documento</th>
        <th>Descrição</th>
        <th>Grupo</th>
        <th>Valor</th>
        <th>Status</th>
        <th>Data Pagamento</th>
        <th>Valor Pago</th>
        <th>Forma</th>
    </tr>

    <?php if (empty($lancamentos)): ?>
        <tr>
            <td colspan="9" style="text-align:center;">
                Nenhum compromisso encontrado para o período selecionado.
            </td>
        </tr>
    <?php endif; ?>

    <?php foreach ($lancamentos as $l): ?>
        <tr>
            <td><?= dataBR($l['data_vencimento']) ?></td>
            <td><?= htmlspecialchars($l['documento_numero'] ?? '') ?></td>
            <td><?= htmlspecialchars($l['descricao'] ?? '') ?></td>
            <td><?= htmlspecialchars($l['grupo'] ?? '') ?></td>
            <td><?= moedaBR($l['valor_nominal'] ?? 0) ?></td>
            <td class="<?= $l['status'] === 'Pago' ? 'pago' : 'aberto' ?>">
                <?= htmlspecialchars($l['status'] ?? '') ?>
            </td>
            <td><?= dataBR($l['data_pagamento'] ?? '') ?></td>
            <td><?= !empty($l['valor_pago']) ? moedaBR($l['valor_pago']) : '' ?></td>
            <td><?= htmlspecialchars($l['forma_de_pagamento_recebimento'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<div class="acoes">
    <button onclick="window.print()">Imprimir / Salvar em PDF</button>
</div>

</body>
</html>