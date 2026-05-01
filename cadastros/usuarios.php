<?php
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
verificaAcesso();
require __DIR__ . '/../includes/menu.php';

/* =====================
   1) SALVAR / EDITAR
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id     = $_POST['id'] ?? null;
    $nome   = $_POST['nome'] ?? '';
    $email  = $_POST['email'] ?? '';
    $perfil = $_POST['perfil'] ?? '';

    $senha = !empty($_POST['senha'])
        ? password_hash($_POST['senha'], PASSWORD_DEFAULT)
        : null;

    if ($id) {

        if ($senha) {
            $stmt = $pdo->prepare("
                UPDATE usuarios
                SET nome_usuario = :nome,
                    email = :email,
                    perfil = :perfil,
                    senha = :senha
                WHERE id_usuario = :id
            ");
            $stmt->bindParam(':senha', $senha);

        } else {
            $stmt = $pdo->prepare("
                UPDATE usuarios
                SET nome_usuario = :nome,
                    email = :email,
                    perfil = :perfil
                WHERE id_usuario = :id
            ");
        }

        $stmt->bindParam(':id', $id);

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO usuarios
            (nome_usuario, email, senha, perfil)
            VALUES (:nome, :email, :senha, :perfil)
        ");

        $stmt->bindParam(':senha', $senha);
    }

    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':perfil', $perfil);

    $stmt->execute();

    header("Location: " . BASE_URL . "cadastros/usuarios.php");
    exit;
}

/* =====================
   2) EXCLUIR
===================== */
if (isset($_GET['delete'])) {

    $id = $_GET['delete'];

    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    header("Location: " . BASE_URL . "cadastros/usuarios.php");
    exit;
}

/* =====================
   3) CARREGAR EDIÇÃO
===================== */
$editar = null;

if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =====================
   4) LISTAR USUÁRIOS
===================== */
$stmt = $pdo->query("SELECT * FROM usuarios ORDER BY nome_usuario");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" charset="UTF-8">
    <title>Usuários</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        form { margin-bottom: 30px; }
        input, select { margin: 6px 0; padding: 6px; width: 360px; display: block; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background: #eee; }
        a { margin-right: 10px; }
    </style>
</head>
<body>

<h2><?= $editar ? 'Editar Usuário' : 'Novo Usuário' ?></h2>

<form method="post">
    <input type="hidden" name="id" value="<?= $editar['id_usuario'] ?? '' ?>">

    <label>Nome</label>
    <input name="nome" required value="<?= htmlspecialchars($editar['nome_usuario'] ?? '') ?>">

    <label>Email</label>
    <input type="email" name="email" required value="<?= htmlspecialchars($editar['email'] ?? '') ?>">

    <label>Senha <?= $editar ? '(deixe em branco para manter)' : '' ?></label>
    <input type="password" name="senha" <?= $editar ? '' : 'required' ?>>

    <label>Perfil</label>
    <select name="perfil" required>
        <?php foreach (['ADMIN','OPERADOR','LIDER','CONSULTA'] as $u): ?>
            <option value="<?= $u ?>" <?= ($editar && $editar['perfil'] === $u) ? 'selected' : '' ?>>
                <?= $u ?>
            </option>
        <?php endforeach; ?>
    </select>

    <br><br>
    <button type="submit">
        <?= $editar ? 'Atualizar' : 'Salvar' ?>
    </button>

    <?php if ($editar): ?>
        <a href="usuarios.php">Cancelar</a>
    <?php endif; ?>
</form>

<h2>Lista de Usuários</h2>

<table>
    <tr>
        <th>Nome</th>
        <th>Email</th>
        <th>Perfil</th>
        <th>Ações</th>
    </tr>

    <?php foreach ($usuarios as $u): ?>
        <tr>
            <td><?= htmlspecialchars($u['nome_usuario']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['perfil']) ?></td>
            <td>
                <a href="usuarios.php?edit=<?= $u['id_usuario'] ?>">Editar</a>
                <a href="usuarios.php?delete=<?= $u['id_usuario'] ?>" onclick="return confirm('Deseja excluir este usuário ?')">
                   Excluir
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>