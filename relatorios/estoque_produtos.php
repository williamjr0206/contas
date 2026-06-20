<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
verificaAcesso();

require __DIR__ . '/../includes/menu.php';

/* =====================
   BUSCAR ESTOQUE
===================== */
$stmt = $pdo->query("
    SELECT 
        id_produto,
        descricao,
        fornecedor,
        preco,
        saldo,
        unidade,
        unidade_consumo,
        fator_conversao_consumo,
        (preco * saldo) AS valor_total
    FROM produtos
    ORDER BY descricao, fornecedor
");

$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_estoque = 0;

foreach ($produtos as $p) {
    $total_estoque += (float)$p['valor_total'];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Relatório de Estoque de Produtos</title>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
}

h2 {
    margin-bottom: 20px;
}

.botoes {
    margin-bottom: 20px;
}

button {
    padding: 8px 14px;
    margin-right: 8px;
    cursor: pointer;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    border: 1px solid #999;
    padding: 7px;
}

th {
    background: #f0f0f0;
}

.numero {
    text-align: right;
}

.total-geral {
    font-weight: bold;
    background: #f5f5f5;
}

@media print {
    .botoes,
    nav,
    .menu,
    a {
        display: none !important;
    }

    body {
        margin: 10px;
    }
}
</style>

</head>
<body>

<h2>Relatório de Estoque de Produtos</h2>

<div class="botoes">
    <button onclick="window.print()">Imprimir / Salvar em PDF</button>
</div>

<table>
<tr>
    <th>Produto</th>
    <th>Fornecedor</th>
    <th>Preço R$</th>
    <th>Saldo Estoque</th>
    <th>Unid. Estoque</th>
    <th>Saldo Consumo</th>
    <th>Unid. Consumo</th>
    <th>Valor Total R$</th>
</tr>

<?php foreach ($produtos as $p): ?>

<?php
$fator = (float)($p['fator_conversao_consumo'] ?? 1);

if ($fator <= 0) {
    $fator = 1;
}

$saldo_consumo = (float)$p['saldo'] / $fator;

$unidade_consumo = !empty($p['unidade_consumo'])
    ? $p['unidade_consumo']
    : $p['unidade'];
?>

<tr>
    <td><?= htmlspecialchars($p['descricao']) ?></td>
    <td><?= htmlspecialchars($p['fornecedor']) ?></td>
    <td class="numero"><?= number_format((float)$p['preco'], 2, ',', '.') ?></td>
    <td class="numero"><?= number_format((float)$p['saldo'], 4, ',', '.') ?></td>
    <td><?= htmlspecialchars($p['unidade']) ?></td>
    <td class="numero"><?= number_format($saldo_consumo, 4, ',', '.') ?></td>
    <td><?= htmlspecialchars($unidade_consumo) ?></td>
    <td class="numero"><?= number_format((float)$p['valor_total'], 2, ',', '.') ?></td>
</tr>

<?php endforeach; ?>

<tr class="total-geral">
    <td colspan="7" class="numero">TOTAL EM ESTOQUE R$</td>
    <td class="numero"><?= number_format($total_estoque, 2, ',', '.') ?></td>
</tr>

</table>

</body>
</html>