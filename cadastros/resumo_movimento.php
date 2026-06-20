<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
verificaAcesso();

require __DIR__ . '/../includes/menu.php';

$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$tipo_de_produto = $_GET['tipo_de_produto'] ?? '';

$stmtTipos = $pdo->query("
    SELECT DISTINCT tipo_de_produto
    FROM produtos
    WHERE tipo_de_produto IS NOT NULL
      AND tipo_de_produto <> ''
    ORDER BY tipo_de_produto
");
$tipos = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

$where = "
    WHERE m.data_movimento BETWEEN :data_inicio AND :data_fim
";

$params = [
    ':data_inicio' => $data_inicio,
    ':data_fim' => $data_fim
];

if ($tipo_de_produto !== '') {
    $where .= " AND p.tipo_de_produto = :tipo_de_produto";
    $params[':tipo_de_produto'] = $tipo_de_produto;
}

$sql = "
    SELECT
        p.id_produto,
        p.codigo,
        p.fornecedor,
        p.descricao,
        p.tipo_de_produto,
        p.preco,

        SUM(CASE 
            WHEN m.tipo IN ('Entrada', 'Retorno') THEN m.quantidade * p.preco
            ELSE 0 
        END) AS valor_entrada,

        SUM(CASE 
            WHEN m.tipo = 'Saída' THEN m.quantidade * p.preco
            ELSE 0 
        END) AS valor_saida

    FROM movimento m
    INNER JOIN produtos p
        ON m.id_produto = p.id_produto

    $where

    GROUP BY
        p.id_produto,
        p.codigo,
        p.fornecedor,
        p.descricao,
        p.tipo_de_produto,
        p.preco

    ORDER BY
        p.descricao
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resumos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_valor_entrada = 0;
$total_valor_saida = 0;

foreach ($resumos as $r) {
    $total_valor_entrada += (float)$r['valor_entrada'];
    $total_valor_saida += (float)$r['valor_saida'];
}

$total_saldo_valor = $total_valor_entrada - $total_valor_saida;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" charset="UTF-8">
<title>Resumo de Movimentação de Estoque</title>

<style>
    body { font-family: Arial; margin: 20px; }
    form { margin-bottom: 30px; }
    input, select { margin: 6px 0; padding: 6px; width: 360px; display: block; max-width: 100%; }
    table { border-collapse: collapse; width: 100%; }
    th, td { padding: 6px; font-size: 13px; }
    a { margin-right: 10px; }

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

</style>

</head>
<body>

<h2>Resumo Financeiro de Movimentação de Estoque</h2>

<form method="get">

<label>Data Inicial</label>
<input type="date" name="data_inicio" required value="<?= htmlspecialchars($data_inicio) ?>">

<label>Data Final</label>
<input type="date" name="data_fim" required value="<?= htmlspecialchars($data_fim) ?>">

<label>Tipo de Produto</label>
<select name="tipo_de_produto">
    <option value="">Todos</option>

    <?php foreach ($tipos as $tp): ?>
        <option value="<?= htmlspecialchars($tp['tipo_de_produto']) ?>"
            <?= ($tipo_de_produto === $tp['tipo_de_produto']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($tp['tipo_de_produto']) ?>
        </option>
    <?php endforeach; ?>
</select>

<button type="submit">Filtrar</button>
<a href="resumo_movimento.php">Limpar</a>

</form>

<h3>
    Período:
    <?= htmlspecialchars(date('d/m/Y', strtotime($data_inicio))) ?>
    até
    <?= htmlspecialchars(date('d/m/Y', strtotime($data_fim))) ?>
</h3>

<table border="1">
<tr>

    <th>Código</th>
    <th>Produto</th>
    <th>Fornecedor</th>
    <th>Tipo Produto</th>
    <th>Preço R$</th>
    <th>Entradas R$</th>
    <th>Saídas R$</th>
    <th>Saldo R$</th>
</tr>

<?php if (count($resumos) === 0): ?>
<tr>
    <td colspan="9">Nenhuma movimentação encontrada no período informado.</td>
</tr>
<?php endif; ?>

<?php foreach ($resumos as $r): ?>

<?php
$valor_entrada = (float)$r['valor_entrada'];
$valor_saida = (float)$r['valor_saida'];
$saldo_valor = $valor_entrada - $valor_saida;
?>

<tr>

    <td><?= htmlspecialchars($r['codigo']) ?></td>
    <td><?= htmlspecialchars($r['descricao']) ?></td>
    <td><?= htmlspecialchars($r['fornecedor']) ?></td>
    <td><?= htmlspecialchars($r['tipo_de_produto'] ?? '') ?></td>
    <td><?= htmlspecialchars(number_format((float)$r['preco'], 2, ',', '.')) ?></td>
    <td><?= htmlspecialchars(number_format($valor_entrada, 2, ',', '.')) ?></td>
    <td><?= htmlspecialchars(number_format($valor_saida, 2, ',', '.')) ?></td>
    <td><?= htmlspecialchars(number_format($saldo_valor, 2, ',', '.')) ?></td>
</tr>

<?php endforeach; ?>

<tr>
    <th colspan="6">TOTAL DO PERÍODO</th>
    <th><?= htmlspecialchars(number_format($total_valor_entrada, 2, ',', '.')) ?></th>
    <th><?= htmlspecialchars(number_format($total_valor_saida, 2, ',', '.')) ?></th>
    <th><?= htmlspecialchars(number_format($total_saldo_valor, 2, ',', '.')) ?></th>
</tr>

</table>

</body>
</html>

<?php ob_end_flush(); ?>