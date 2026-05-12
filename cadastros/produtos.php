<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
verificaAcesso();

require __DIR__ . '/../includes/menu.php';

function normalizarDescricaoProduto($texto)
{
    $texto = mb_strtoupper(trim($texto), 'UTF-8');
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
    $texto = preg_replace('/[^A-Z0-9]/', '', $texto);
    return $texto;
}

/* =====================
   SALVAR / EDITAR
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = $_POST['id'] ?? null;
    $codigo = $_POST['codigo'] ?? '';
    $fornecedor = $_POST['fornecedor'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $preco = $_POST['preco'] ?? 0;
    $unidade = $_POST['unidade'] ?? '';
    $saldo = $_POST['saldo'] ?? 0;
    $cadastrado_em = $_POST['cadastrado_em'] ?? date('Y-m-d');
    $descricao_normalizada = normalizarDescricaoProduto($descricao);

    if ($id) {
        $sql = "UPDATE produtos 
        SET codigo = :codigo,
            fornecedor = :fornecedor,
            descricao = :descricao,
            preco = :preco,
            unidade = :unidade,
            saldo = :saldo,
            cadastrado_em = :cadastrado_em,
            descricao_normalizada = :descricao_normalizada
        WHERE id_produto = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
    } else {
        $sql = "INSERT INTO produtos 
        (codigo, fornecedor, descricao, preco, unidade, saldo, cadastrado_em, descricao_normalizada)
        VALUES 
        (:codigo, :fornecedor, :descricao, :preco, :unidade, :saldo, :cadastrado_em, :descricao_normalizada)";
        $stmt = $pdo->prepare($sql);
    }

    $stmt->bindParam(':codigo', $codigo);
    $stmt->bindParam(':fornecedor', $fornecedor);
    $stmt->bindParam(':descricao', $descricao);
    $stmt->bindParam(':preco', $preco);
    $stmt->bindParam(':unidade', $unidade);
    $stmt->bindParam(':saldo', $saldo);
    $stmt->bindParam(':cadastrado_em', $cadastrado_em);
    $stmt->bindParam(':descricao_normalizada', $descricao_normalizada);
    $stmt->execute();

    header("Location: " . BASE_URL . "cadastros/produtos.php");
    exit;
}

/* =====================
   EXCLUIR
===================== */
if (isset($_GET['delete'])) {

    $id = $_GET['delete'];

    $sql = "DELETE FROM produtos WHERE id_produto = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    header("Location: " . BASE_URL . "cadastros/produtos.php");
    exit;
}

/* =====================
   EDITAR
===================== */
$editar = null;

if (isset($_GET['edit'])) {

    $id = $_GET['edit'];

    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id_produto = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =====================
   LISTAR
===================== */
$stmt = $pdo->query("
    SELECT * 
    FROM produtos 
    ORDER BY descricao, fornecedor, codigo
");
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
    input, select { margin: 6px 0; padding: 6px; width: 360px; display: block; max-width: 100%; }
    table { border-collapse: collapse; width: 100%; }
    th, td { padding: 6px; }
    a { margin-right: 10px; }
</style>

</head>
<body>

<h2><?= $editar ? 'Editar Produto' : 'Novo Produto' ?></h2>

<form method="post">

<input type="hidden" name="id" value="<?= htmlspecialchars($editar['id_produto'] ?? '') ?>">

<label>Código do Produto na NF / Fornecedor</label>
<input name="codigo" required value="<?= htmlspecialchars($editar['codigo'] ?? '') ?>">

<label>Fornecedor</label>
<input name="fornecedor" required value="<?= htmlspecialchars($editar['fornecedor'] ?? '') ?>">

<label>Descrição</label>
<input name="descricao" required value="<?= htmlspecialchars($editar['descricao'] ?? '') ?>">

<label>Preço ou Custo</label>
<input type="number" name="preco" step="0.01" required value="<?= htmlspecialchars($editar['preco'] ?? '') ?>">

<label>Unidade</label>
<input name="unidade" required value="<?= htmlspecialchars($editar['unidade'] ?? '') ?>">

<label>Saldo</label>
<input type="number" name="saldo" step="0.0001" required value="<?= htmlspecialchars($editar['saldo'] ?? 0) ?>">

<label>Cadastrado em</label>
<input type="date" name="cadastrado_em" required value="<?= htmlspecialchars($editar['cadastrado_em'] ?? date('Y-m-d')) ?>">

<button type="submit"><?= $editar ? 'Atualizar' : 'Salvar' ?></button>

<?php if ($editar): ?>
    <a href="produtos.php">Cancelar</a>
<?php endif; ?>

</form>

<h2>Lista de Produtos Cadastrados:</h2>

<table border="1">
<tr>
    <th>ID</th>
    <th>Código NF</th>
    <th>Fornecedor</th>
    <th>Descrição</th>
    <th>Preço / Custo R$</th>
    <th>Unidade</th>
    <th>Saldo</th>
    <th>Ações</th>
</tr>

<?php foreach ($eventos as $e): ?>
<tr>
    <td><?= htmlspecialchars($e['id_produto']) ?></td>
    <td><?= htmlspecialchars($e['codigo']) ?></td>
    <td><?= htmlspecialchars($e['fornecedor']) ?></td>
    <td><?= htmlspecialchars($e['descricao']) ?></td>
    <td><?= htmlspecialchars(number_format((float)$e['preco'], 2, ',', '.')) ?></td>
    <td><?= htmlspecialchars($e['unidade']) ?></td>
    <td><?= htmlspecialchars(number_format((float)$e['saldo'], 4, ',', '.')) ?></td>
    <td>
        <a href="produtos.php?edit=<?= $e['id_produto'] ?>">Editar</a>
        <a href="produtos.php?delete=<?= $e['id_produto'] ?>"
           onclick="return confirm('Deseja excluir este Produto?')">
           Excluir
        </a>
    </td>
</tr>
<?php endforeach; ?>

</table>

</body>
</html>