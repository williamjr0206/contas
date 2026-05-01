<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
verificaAcesso();
require __DIR__ . '/../includes/menu.php';

/* =====================
   FILTROS
===================== */
$data_inicio = $_GET['inicio'] ?? '';
$data_fim    = $_GET['fim'] ?? '';

$where = [];
$params = [];

if ($data_inicio && $data_fim) {
    $where[] = "DATE(COALESCE(data_pagamento, data_lancamento)) BETWEEN :inicio AND :fim";
    $params[':inicio'] = $data_inicio;
    $params[':fim'] = $data_fim;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* =====================
   BUSCA DADOS
===================== */
$sql = "SELECT 
            documento_numero,
            descricao,
            tipo,
            valor_nominal,
            valor_pago,
            data_lancamento,
            data_pagamento,
            status
        FROM lancamentos
        $whereSQL
        ORDER BY COALESCE(data_pagamento, data_lancamento) ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   CALCULAR FLUXO
===================== */
$saldo = 0;
$total_entradas = 0;
$total_saidas = 0;

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Fluxo de Caixa</title>

<style>
body { font-family: Arial; margin: 20px; }
table { border-collapse: collapse; width: 100%; }
th, td { padding: 8px; border: 1px solid #ccc; }
th { background: #eee; }
.entrada { color: green; font-weight: bold; }
.saida { color: red; font-weight: bold; }
</style>
</head>
<body>

<h2>Fluxo de Caixa</h2>

<form method="get">
    <label>Data Inicial</label>
    <input type="date" name="inicio" value="<?= $data_inicio ?>">

    <label>Data Final</label>
    <input type="date" name="fim" value="<?= $data_fim ?>">

    <button type="submit">Filtrar</button>
</form>

<br>

<table>
<tr>
    <th>Data</th>
    <th>Documento</th>
    <th>Descrição</th>
    <th>Entrada</th>
    <th>Saída</th>
    <th>Saldo</th>
</tr>

<?php foreach ($lancamentos as $l): 

    $data = $l['data_pagamento'] ?: $l['data_lancamento'];

    $entrada = 0;
    $saida = 0;

    if ($l['tipo'] == 'Receber') {
        $entrada = $l['valor_pago'] ?: $l['valor_nominal'];
        $saldo += $entrada;
        $total_entradas += $entrada;
    } else {
        $saida = $l['valor_pago'] ?: $l['valor_nominal'];
        $saldo -= $saida;
        $total_saidas += $saida;
    }
?>

<tr>
    <td><?= htmlspecialchars($data) ?></td>
    <td><?= htmlspecialchars($l['documento_numero']) ?></td>
    <td><?= htmlspecialchars($l['descricao']) ?></td>

    <td class="entrada">
        <?= $entrada ? number_format($entrada, 2, ',', '.') : '' ?>
    </td>

    <td class="saida">
        <?= $saida ? number_format($saida, 2, ',', '.') : '' ?>
    </td>

    <td>
        <?= number_format($saldo, 2, ',', '.') ?>
    </td>
</tr>

<?php endforeach; ?>

<tr>
    <th colspan="3">Totais</th>
    <th class="entrada"><?= number_format($total_entradas, 2, ',', '.') ?></th>
    <th class="saida"><?= number_format($total_saidas, 2, ',', '.') ?></th>
    <th><?= number_format($saldo, 2, ',', '.') ?></th>
</tr>

</table>

</body>
</html>