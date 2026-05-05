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
    $codigo = $_POST['codigo'] ?? '';
    $fornecedor = $_POST['fornecedor'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $preco = $_POST['preco'] ?? '';
    $unidade = $_POST['unidade'] ?? '';
    $saldo = $_POST['saldo'] ?? '';
    $cadastrado_em = $_POST['cadastrado_em'] ?? '';

    if ($id) {
        $sql = "UPDATE produtos 
                SET codigo = :codigo, fornecedor = :fornecedor, descricao = :descricao, preco = :preco,
                    unidade = :unidade, saldo = :saldo, cadastrado_em = :cadastrado_em
                WHERE id_produto = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
    } else {
        $sql = "INSERT INTO produtos (codigo, fornecedor, descricao, preco, unidade, saldo,
                                    cadastrado_em)
                VALUES (:codigo, :fornecedor, :descricao, :preco, :unidade, :saldo,
                        :cadastrado_em)";

        $stmt = $pdo->prepare($sql);
    }

    $stmt->bindParam(':codigo', $codigo);
    $stmt->bindParam(':fornecedor',$fornecedor);
    $stmt->bindParam(':descricao', $descricao);
    $stmt->bindParam(':preco', $preco);
    $stmt->bindParam(':unidade', $unidade);
    $stmt->bindParam(':saldo', $saldo);
    $stmt->bindParam(':cadastrado_em', $cadastrado_em);
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
$stmt = $pdo->query("SELECT * FROM produtos ORDER BY descricao");
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

<h2><?= $editar ? 'Editar Produto' : 'Novo Produto' ?></h2>

<form method="post">

<input type="hidden" name="id" value="<?= $editar['id_produto'] ?? '' ?>">

<label>Código do Produto</label>
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
<input type="number" name="saldo" step="0.0001" required value="<?= htmlspecialchars($editar['saldo'] ?? '') ?>">

<label>Cadastrado em</label>
<input type="date" name="cadastrado_em" required value="<?= htmlspecialchars($editar['cadastrado_em'] ?? '') ?>">


<button type="submit"><?= $editar ? 'Atualizar' : 'Salvar' ?></button>

<?php if ($editar): ?>
    <a href="produtos.php">Cancelar</a>
<?php endif; ?>

</form>

<h2>Lista de Produtos Cadastrados:</h2>

<table border="1">
<tr>
    <th>Código</th>
    <th>Fornecedor</th>
    <th>Descrição</th>
    <th>Preço / Custo em R$</th>
    <th>Unidade</th>
    <th>Saldo</th>
    <th>Ações</th>
</tr>

<?php foreach ($eventos as $e): ?>
<tr>
    <td><?= htmlspecialchars($e['codigo']) ?></td>
    <td><?= htmlspecialchars($e['fornecedor']) ?></td>
    <td><?= htmlspecialchars($e['descricao']) ?></td>
    <td><?= htmlspecialchars($e['preco']) ?></td>
    <td><?= htmlspecialchars($e['unidade']) ?></td>
    <td><?= htmlspecialchars($e['saldo']) ?></td>
    <td>
        <a href="produtos.php?edit=<?= $e['id_produto'] ?>">Editar</a>
        <a href="produtos.php?delete=<?= $e['id_produto'] ?>"
           onclick="return confirm('Deseja excluir este Produto ?')">
           Excluir
        </a>
    </td>
</tr>
<?php endforeach; ?>

</table>

</body>
</html>
