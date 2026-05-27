<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
verificaAcesso();

require __DIR__ . '/../includes/menu.php';

/* =====================
   FUNÇÃO PARA APLICAR SALDO
===================== */
function aplicarSaldoProduto(PDO $pdo, int $id_produto, float $quantidade, string $tipo): void
{
    if ($tipo === 'Entrada' || $tipo === 'Retorno') {
        $sql = "UPDATE produtos 
                SET saldo = COALESCE(saldo, 0) + :quantidade 
                WHERE id_produto = :id_produto";
    } elseif ($tipo === 'Saída') {
        $sql = "UPDATE produtos 
                SET saldo = COALESCE(saldo, 0) - :quantidade 
                WHERE id_produto = :id_produto";
    } else {
        return;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':quantidade' => $quantidade,
        ':id_produto' => $id_produto
    ]);
}

/* =====================
   FUNÇÃO PARA DESFAZER SALDO
===================== */
function desfazerSaldoProduto(PDO $pdo, int $id_produto, float $quantidade, string $tipo): void
{
    if ($tipo === 'Entrada' || $tipo === 'Retorno') {
        $sql = "UPDATE produtos 
                SET saldo = COALESCE(saldo, 0) - :quantidade 
                WHERE id_produto = :id_produto";
    } elseif ($tipo === 'Saída') {
        $sql = "UPDATE produtos 
                SET saldo = COALESCE(saldo, 0) + :quantidade 
                WHERE id_produto = :id_produto";
    } else {
        return;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':quantidade' => $quantidade,
        ':id_produto' => $id_produto
    ]);
}

/* =====================
   SALVAR / EDITAR
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = $_POST['id'] ?? null;
    $data_movimento = $_POST['data_movimento'] ?? '';
    $documento = $_POST['documento'] ?? '';
    $id_produto = (int) ($_POST['id_produto'] ?? 0);

    $quantidade_digitada = str_replace(',', '.', $_POST['quantidade'] ?? 0);
    $quantidade_digitada = (float) $quantidade_digitada;

    $tipo = $_POST['tipo'] ?? '';

    try {
        $pdo->beginTransaction();

        $stmtProduto = $pdo->prepare("
            SELECT 
                codigo,
                unidade,
                unidade_consumo,
                fator_conversao_consumo
            FROM produtos 
            WHERE id_produto = :id_produto
        ");
        $stmtProduto->execute([':id_produto' => $id_produto]);
        $produto = $stmtProduto->fetch(PDO::FETCH_ASSOC);

        if (!$produto) {
            throw new Exception("Produto não encontrado.");
        }

        $codigo = $produto['codigo'];

        $fator = (float)($produto['fator_conversao_consumo'] ?? 1);

        if ($fator <= 0) {
            $fator = 1;
        }

        $quantidade = $quantidade_digitada * $fator;

        if ($id) {
            $stmtOld = $pdo->prepare("
                SELECT id_produto, quantidade, tipo 
                FROM movimento 
                WHERE id_movimento = :id
            ");
            $stmtOld->execute([':id' => $id]);
            $movAntigo = $stmtOld->fetch(PDO::FETCH_ASSOC);

            if ($movAntigo) {
                desfazerSaldoProduto(
                    $pdo,
                    (int)$movAntigo['id_produto'],
                    (float)$movAntigo['quantidade'],
                    $movAntigo['tipo']
                );
            }

            $sql = "UPDATE movimento 
                    SET data_movimento = :data_movimento,
                        documento = :documento,
                        id_produto = :id_produto,
                        codigo = :codigo,
                        quantidade = :quantidade,
                        tipo = :tipo
                    WHERE id_movimento = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $id);

        } else {
            $sql = "INSERT INTO movimento 
                    (data_movimento, documento, id_produto, codigo, quantidade, tipo)
                    VALUES 
                    (:data_movimento, :documento, :id_produto, :codigo, :quantidade, :tipo)";

            $stmt = $pdo->prepare($sql);
        }

        $stmt->bindParam(':data_movimento', $data_movimento);
        $stmt->bindParam(':documento', $documento);
        $stmt->bindParam(':id_produto', $id_produto);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->execute();

        aplicarSaldoProduto($pdo, $id_produto, $quantidade, $tipo);

        $pdo->commit();

        header("Location: " . BASE_URL . "cadastros/movimentos.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro ao salvar movimento: " . $e->getMessage());
    }
}

/* =====================
   EXCLUIR MOVIMENTO
===================== */
if (isset($_GET['delete'])) {

    $id = $_GET['delete'];

    try {
        $pdo->beginTransaction();

        $stmtOld = $pdo->prepare("
            SELECT id_produto, quantidade, tipo 
            FROM movimento 
            WHERE id_movimento = :id
        ");
        $stmtOld->execute([':id' => $id]);
        $movAntigo = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if ($movAntigo) {
            desfazerSaldoProduto(
                $pdo,
                (int)$movAntigo['id_produto'],
                (float)$movAntigo['quantidade'],
                $movAntigo['tipo']
            );

            $stmtDelete = $pdo->prepare("
                DELETE FROM movimento 
                WHERE id_movimento = :id
            ");
            $stmtDelete->execute([':id' => $id]);
        }

        $pdo->commit();

        header("Location: " . BASE_URL . "cadastros/movimentos.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro ao excluir movimento: " . $e->getMessage());
    }
}

/* =====================
   PRODUTOS PARA SELECT
===================== */
$stmt = $pdo->query("
    SELECT 
        id_produto,
        codigo,
        fornecedor,
        descricao,
        tipo_de_produto,
        unidade,
        unidade_consumo,
        fator_conversao_consumo,
        saldo
    FROM produtos
    ORDER BY descricao, fornecedor, codigo
");
$codigos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   EDITAR
===================== */
$editar = null;

if (isset($_GET['edit'])) {

    $id = $_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT * 
        FROM movimento 
        WHERE id_movimento = :id
    ");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =====================
   LISTAR
===================== */
$stmt = $pdo->query("
    SELECT 
        m.id_movimento,
        m.data_movimento,
        m.documento,
        m.codigo,
        m.quantidade,
        m.tipo,
        p.id_produto,
        p.codigo AS codigo_nf,
        p.fornecedor,
        p.descricao,
        p.tipo_de_produto,
        p.unidade,
        p.unidade_consumo,
        p.fator_conversao_consumo,
        p.saldo
    FROM movimento AS m
    INNER JOIN produtos AS p 
        ON m.id_produto = p.id_produto
    ORDER BY m.data_movimento DESC, m.id_movimento DESC
");
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" charset="UTF-8">
<title>Movimentação de Estoque</title>

<style>
    body { font-family: Arial; margin: 20px; }
    form { margin-bottom: 30px; }
    input, select { margin: 6px 0; padding: 6px; width: 460px; display: block; max-width: 100%; }
    table { border-collapse: collapse; width: 100%; }
    th, td { padding: 6px; font-size: 13px; }
    a { margin-right: 10px; }
</style>

</head>
<body>

<h2><?= $editar ? 'Editar Lançamento Estoque' : 'Novo Lançamento Estoque' ?></h2>

<form method="post">

<input type="hidden" name="id" value="<?= htmlspecialchars($editar['id_movimento'] ?? '') ?>">

<label>Data de Lançamento</label>
<input type="date" name="data_movimento" required value="<?= htmlspecialchars($editar['data_movimento'] ?? date('Y-m-d')) ?>">

<label>Documento</label>
<input name="documento" required value="<?= htmlspecialchars($editar['documento'] ?? '') ?>">

<label>Produto</label>
<select name="id_produto" required>
<option value="">Selecione...</option>

<?php foreach ($codigos as $c): ?>
<option value="<?= $c['id_produto'] ?>"
<?= (isset($editar['id_produto']) && $editar['id_produto'] == $c['id_produto']) ? 'selected' : '' ?>>
<?= htmlspecialchars(
    $c['descricao'] .
    ' | Tipo: ' . ($c['tipo_de_produto'] ?? '') .
    ' | Fornecedor: ' . $c['fornecedor'] .
    ' | Cód. NF: ' . $c['codigo'] .
    ' | Unid. Estoque: ' . ($c['unidade'] ?? '') .
    ' | Unid. Consumo: ' . (($c['unidade_consumo'] ?? '') ?: ($c['unidade'] ?? '')) .
    ' | Fator: ' . number_format((float)($c['fator_conversao_consumo'] ?? 1), 4, ',', '.') .
    ' | Saldo: ' . number_format((float)$c['saldo'], 4, ',', '.')
) ?>
</option>
<?php endforeach; ?>

</select>

<label>Quantidade movimentada na unidade de compra / estoque</label>
<input type="number" step="0.0001" name="quantidade" required value="<?= htmlspecialchars($editar['quantidade'] ?? '') ?>">

<label>Tipo de Lançamento</label>
<select name="tipo" required>
<option value="">Selecione...</option>

<?php foreach (['Entrada', 'Saída', 'Retorno'] as $tp): ?>
<option value="<?= $tp ?>" <?= (isset($editar['tipo']) && $editar['tipo'] == $tp) ? 'selected' : '' ?>>
    <?= $tp ?>
</option>
<?php endforeach; ?>

</select>

<button type="submit"><?= $editar ? 'Atualizar' : 'Salvar' ?></button>

<?php if ($editar): ?>
    <a href="movimentos.php">Cancelar</a>
<?php endif; ?>

</form>

<h2>Lista de Lançamentos:</h2>

<table border="1">
<tr>
    <th>Data Movimento</th>
    <th>Documento</th>
    <th>ID Produto</th>
    <th>Código NF</th>
    <th>Fornecedor</th>
    <th>Produto</th>
    <th>Tipo Produto</th>
    <th>Quantidade</th>
    <th>Unid. Estoque</th>
    <th>Unid. Consumo</th>
    <th>Fator</th>
    <th>Movimento</th>
    <th>Saldo Atual</th>
    <th>Ações</th>
</tr>

<?php foreach ($eventos as $e): ?>
<tr>
    <td><?= htmlspecialchars($e['data_movimento']) ?></td>
    <td><?= htmlspecialchars($e['documento']) ?></td>
    <td><?= htmlspecialchars($e['id_produto']) ?></td>
    <td><?= htmlspecialchars($e['codigo_nf']) ?></td>
    <td><?= htmlspecialchars($e['fornecedor']) ?></td>
    <td><?= htmlspecialchars($e['descricao']) ?></td>
    <td><?= htmlspecialchars($e['tipo_de_produto'] ?? '') ?></td>
    <td><?= htmlspecialchars(number_format((float)$e['quantidade'], 4, ',', '.')) ?></td>
    <td><?= htmlspecialchars($e['unidade'] ?? '') ?></td>
    <td><?= htmlspecialchars(($e['unidade_consumo'] ?? '') ?: ($e['unidade'] ?? '')) ?></td>
    <td><?= htmlspecialchars(number_format((float)($e['fator_conversao_consumo'] ?? 1), 4, ',', '.')) ?></td>
    <td><?= htmlspecialchars($e['tipo']) ?></td>
    <td><?= htmlspecialchars(number_format((float)$e['saldo'], 4, ',', '.')) ?></td>
    <td>
        <a href="movimentos.php?edit=<?= $e['id_movimento'] ?>">Editar</a>
        <a href="movimentos.php?delete=<?= $e['id_movimento'] ?>"
           onclick="return confirm('Deseja excluir este lançamento? O saldo do produto será ajustado.')">
           Excluir
        </a>
    </td>
</tr>
<?php endforeach; ?>

</table>

</body>
</html>

<?php ob_end_flush(); ?>