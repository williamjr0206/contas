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
    $descricao = $_POST['descricao'] ?? '';

    if ($id) {
        $sql = "UPDATE grupos 
                SET descricao = :descricao
                WHERE id_grupo = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
    } else {
        $sql = "INSERT INTO grupos (descricao)
                VALUES (:descricao)";

        $stmt = $pdo->prepare($sql);
    }

    $stmt->bindParam(':descricao', $descricao);

    $stmt->execute();

    header("Location: " . BASE_URL . "cadastros/grupos.php");
    exit;
}

/* =====================
   EXCLUIR
===================== */
if (isset($_GET['delete'])) {

    $id = $_GET['delete'];


    $sql = "DELETE FROM grupos WHERE id_grupo = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    header("Location: " . BASE_URL . "cadastros/grupos.php");
    exit;
}

/* =====================
   EDITAR
===================== */
$editar = null;

if (isset($_GET['edit'])) {

    $id = $_GET['edit'];

    $stmt = $pdo->prepare("SELECT * FROM grupos WHERE id_grupo = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =====================
   LISTAR
===================== */
$stmt = $pdo->query("SELECT * FROM grupos ORDER BY descricao");
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" charset="UTF-8">
<title>Classificções de Receber / Pagar:</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        form { margin-bottom: 30px; }
        input, select { margin: 6px 0; padding: 6px; width: 360px; display: block; }
        table { border-collapse: collapse; width: 100%; }
        a { margin-right: 10px; }

    </style>


</head>
<body>

<h2><?= $editar ? 'Editar Classificação' : 'Nova Classificação' ?></h2>

<form method="post">

<input type="hidden" name="id" value="<?= $editar['id_grupo'] ?? '' ?>">

<label>Descrição do Evento</label>
<input name="descricao" required value="<?= htmlspecialchars($editar['descricao'] ?? '') ?>">

<button type="submit"><?= $editar ? 'Atualizar' : 'Salvar' ?></button>

<?php if ($editar): ?>
    <a href="grupos.php">Cancelar</a>
<?php endif; ?>

</form>

<h2>Lista de Classificações:</h2>

<table border="1">
<tr>
    <th>Descrição</th>
    <th>Ações</th>
</tr>

<?php foreach ($eventos as $e): ?>
<tr>
    <td><?= htmlspecialchars($e['descricao']) ?></td>
    <td>
        <a href="grupos.php?edit=<?= $e['id_grupo'] ?>">Editar</a>
        <a href="grupos.php?delete=<?= $e['id_grupo'] ?>"
           onclick="return confirm('Deseja excluir esta Classificação ?')">
           Excluir
        </a>
    </td>
</tr>
<?php endforeach; ?>

</table>

</body>
</html>