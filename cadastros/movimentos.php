<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
verificaAcesso();

require __DIR__ . '/../includes/menu.php';
/* =====================
   SALVAR / EDITAR
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id        = $_POST['id'] ?? null;
    $data_movimento = $_POST['data_movimento'] ?? '';
    $documento = $_POST['documento'] ?? '';
    $codigo = $_POST['codigo'] ?? '';
    $quantidade = $_POST['quantidade'] ?? '';
    $tipo = $_POST['tipo'] ?? '';

    if ($id) {
        $sql = "UPDATE movimento 
                SET data_movimento = :data_movimento, documento = :documento, codigo = :codigo,
                    quantidade = :quantidade, tipo = :tipo
                WHERE id_movimento = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
    } else {
        $sql = "INSERT INTO movimento (data_movimento, documento, codigo, quantidade, tipo)
                VALUES (:data_movimento, :documento, :codigo, :quantidade, :tipo)";

        $stmt = $pdo->prepare($sql);
    }

    $stmt->bindParam(':data_movimento', $data_movimento);
    $stmt->bindParam(':documento',$documento);
    $stmt->bindParam(':codigo', $codigo);
    $stmt->bindParam(':quantidade', $quantidade);
    $stmt->bindParam(':tipo', $tipo);
    $stmt->execute();

    header("Location: " . BASE_URL . "cadastros/movimentos.php");
    exit;
}


/* =====================
   GRUPOS
===================== */
$stmt = $pdo->query("SELECT codigo, descricao FROM produtos ORDER BY descricao");
$codigos = $stmt->fetchAll(PDO::FETCH_ASSOC);



/* =====================
   EDITAR
===================== */
$editar = null;

if (isset($_GET['edit'])) {

    $id = $_GET['edit'];

    $stmt = $pdo->prepare("SELECT * FROM movimento WHERE id_movimento = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =====================
   LISTAR
===================== */
$stmt = $pdo->query("SELECT m.id_movimento, m.data_movimento, m.documento, m.codigo, p.codigo as codigo_interno, p.descricao,
m.quantidade, m.tipo FROM movimento as m inner join produtos as p on m.codigo = 
p.codigo  ORDER BY m.data_movimento desc");
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" charset="UTF-8">
<title>Cadastro de Produtos</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        form { margin-bottom: 30px; }
        input, select { margin: 6px 0; padding: 6px; width: 360px; display: block; }
        table { border-collapse: collapse; width: 100%; }
        a { margin-right: 10px; }

    </style>


</head>
<body>

<h2><?= $editar ? 'Editar Lançamento Estoque' : 'Novo Lançamento Estoque' ?></h2>

<form method="post">

<input type="hidden" name="id" value="<?= $editar['id_movimento'] ?? '' ?>">

<label>Data de Lançamento</label>
<input type="date"  name="data_movimento" required value="<?= htmlspecialchars($editar['data_movimento'] ?? '') ?>">

<label>Documento</label>
<input name="documento" required value="<?= htmlspecialchars($editar['documento'] ?? '') ?>">

<label>Produtos</label>
<select name="codigo">
<?php foreach ($codigos as $c): ?>
<option value="<?= $c['codigo'] ?>"
<?= (isset($editar['codigo']) && $editar['codigo'] == $c['codigo']) ? 'selected' : '' ?>>
<?= htmlspecialchars($c['descricao']) ?>
</option>
<?php endforeach; ?>
</select>

<label>Quantidade</label>
<input type="number" step="0.0001" name="quantidade" required value="<?= htmlspecialchars($editar['quantidade'] ?? '') ?>">

<label>Tipo de Lançamento</label>
<select name="tipo">
<?php

foreach (['Entrada','Saída','Retorno'] as $tp):
?>
<option value="<?= $tp ?>" <?= (isset($editar['tipo']) && $editar['tipo'] == $tp) ? 'selected' : '' ?>>
    <?= $tp ?>
</option>
<?php endforeach; ?>
</select>


<button type="submit"><?= $editar ? 'Atualizar' : 'Salvar' ?></button>

<?php if ($editar): ?>
    <a href="produtos.php">Cancelar</a>
<?php endif; ?>

</form>

<h2>Lista de Lançamentos:</h2>

<table border="1">
<tr>
    <th>Data Movimento</th>
    <th>Documento</th>
    <th>Código Fornecedor</th>
    <th>Código Interno</th>
    <th>Produto</th>
    <th>Quantidade</th>
    <th>Movimento</th>
    <th>Ações</th>
</tr>

<?php foreach ($eventos as $e): ?>
<tr>
    <td><?= htmlspecialchars($e['data_movimento']) ?></td>
    <td><?= htmlspecialchars($e['documento']) ?></td>
    <td><?= htmlspecialchars($e['codigo']) ?></td>
    <td><?= htmlspecialchars($e['codigo_interno']) ?></td>
    <td><?= htmlspecialchars($e['descricao']) ?></td>
    <td><?= htmlspecialchars($e['quantidade']) ?></td>
    <td><?= htmlspecialchars($e['tipo']) ?></td>
    <td>
        <a href="movimentos.php?edit=<?= $e['id_movimento'] ?>">Editar</a>
    </td>
</tr>
<?php endforeach; ?>

</table>

</body>
</html>

