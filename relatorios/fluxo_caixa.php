<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
verificaAcesso();
require __DIR__ . '/../includes/menu.php';

$data_inicio = $_GET['inicio'] ?? date('Y-m-01');
$data_fim    = $_GET['fim'] ?? date('Y-m-t');
$id_autor    = $_GET['id_autor'] ?? '';

$params = [
    ':inicio' => $data_inicio,
    ':fim'    => $data_fim
];

$where = [
    "l.data_vencimento BETWEEN :inicio AND :fim"
];

if ($id_autor !== '') {
    $where[] = "l.id_autor = :id_autor";
    $params[':id_autor'] = $id_autor;
}

$whereSQL = "WHERE " . implode(" AND ", $where);

/* =====================
   AUTORES
===================== */
$stmt = $pdo->query("SELECT id_autor, nome FROM autores ORDER BY nome");
$autores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT 
            l.documento_numero,
            l.descricao,
            l.tipo,
            l.valor_nominal,
            l.valor_pago,
            l.data_lancamento,
            l.data_vencimento,
            l.data_pagamento,
            l.status,
            l.id_autor,
            a.nome AS autor_nome
        FROM lancamentos l
        LEFT JOIN autores a ON a.id_autor = l.id_autor
        $whereSQL
        ORDER BY l.data_vencimento ASC, l.descricao ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$saldo_previsto = 0;
$total_entradas_previstas = 0;
$total_saidas_previstas = 0;

function dataBR($data) {
    if (empty($data)) return '';
    return date('d/m/Y', strtotime($data));
}

function moedaBR($valor) {
    return number_format((float)$valor, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Previsão de Fluxo de Caixa</title>

<style>
body { font-family: Arial; margin: 20px; background: #f4f6f8; }
form { background: #fff; padding: 15px; border: 1px solid #ddd; margin-bottom: 20px; }
input, select, button { padding: 7px; margin: 5px; }
table { border-collapse: collapse; width: 100%; background: #fff; }
th, td { padding: 8px; border: 1px solid #ccc; }
th { background: #2c3e50; color: white; }
.entrada { color: green; font-weight: bold; }
.saida { color: red; font-weight: bold; }
.saldo { font-weight: bold; }
.aberto { color: #c0392b; font-weight: bold; }
.pago, .recebido { color: #207245; font-weight: bold; }
</style>
</head>
<body>

<h2>Previsão de Fluxo de Caixa</h2>

<form method="get">
    <label>Data Inicial</label>
    <input type="date" name="inicio" value="<?= htmlspecialchars($data_inicio) ?>" required>

    <label>Data Final</label>
    <input type="date" name="fim" value="<?= htmlspecialchars($data_fim) ?>" required>

    <label>Autor / Favorecido</label>
    <select name="id_autor">
        <option value="">Todos</option>
        <?php foreach ($autores as $autor): ?>
            <option value="<?= $autor['id_autor'] ?>" <?= $id_autor == $autor['id_autor'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($autor['nome']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Filtrar</button>
</form>

<table>
<tr>
    <th>Vencimento</th>
    <th>Documento</th>
    <th>Descrição</th>
    <th>Autor / Favorecido</th>
    <th>Status</th>
    <th>Entrada Prevista</th>
    <th>Saída Prevista</th>
    <th>Saldo Previsto</th>
</tr>

<?php if (empty($lancamentos)): ?>
<tr>
    <td colspan="8" style="text-align:center;">
        Nenhum lançamento encontrado no período.
    </td>
</tr>
<?php endif; ?>

<?php foreach ($lancamentos as $l): 

    $entrada = 0;
    $saida = 0;

    $valor = !empty($l['valor_pago']) ? $l['valor_pago'] : $l['valor_nominal'];

    if ($l['tipo'] === 'Receber') {
        $entrada = (float)$valor;
        $saldo_previsto += $entrada;
        $total_entradas_previstas += $entrada;
    }

    if ($l['tipo'] === 'Pagar') {
        $saida = (float)$valor;
        $saldo_previsto -= $saida;
        $total_saidas_previstas += $saida;
    }

    $classeStatus = strtolower($l['status'] ?? '');
?>

<tr>
    <td><?= dataBR($l['data_vencimento']) ?></td>
    <td><?= htmlspecialchars($l['documento_numero'] ?? '') ?></td>
    <td><?= htmlspecialchars($l['descricao'] ?? '') ?></td>
    <td><?= htmlspecialchars($l['autor_nome'] ?? 'Sem autor') ?></td>
    <td class="<?= $classeStatus ?>"><?= htmlspecialchars($l['status'] ?? '') ?></td>

    <td class="entrada">
        <?= $entrada ? 'R$ ' . moedaBR($entrada) : '' ?>
    </td>

    <td class="saida">
        <?= $saida ? 'R$ ' . moedaBR($saida) : '' ?>
    </td>

    <td class="saldo">
        R$ <?= moedaBR($saldo_previsto) ?>
    </td>
</tr>

<?php endforeach; ?>

<tr>
    <th colspan="5">Totais do Período</th>
    <th class="entrada">R$ <?= moedaBR($total_entradas_previstas) ?></th>
    <th class="saida">R$ <?= moedaBR($total_saidas_previstas) ?></th>
    <th>R$ <?= moedaBR($saldo_previsto) ?></th>
</tr>

</table>

</body>
</html>