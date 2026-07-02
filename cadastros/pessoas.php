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

    $id = $_POST['id'] ?? null;
    $nome = $_POST['nome'] ?? '';
    $data_nascimento = $_POST['data_nascimento'] ?? '';
    $cidade = $_POST['cidade'] ?? '';
    $estado = $_POST['estado'] ?? '';
    $altura = $_POST['altura'] ?? '';
    $peso = $_POST['peso'] ?? '';

    $diabetico = $_POST['diabetico'] ?? 'Não';
    $hipertenso = $_POST['hipertenso'] ?? 'Não';
    $colesterol_alto = $_POST['colesterol_alto'] ?? 'Não';
    $intolerancia_lactose = $_POST['intolerancia_lactose'] ?? 'Não';
    $vegetariano = $_POST['vegetariano'] ?? 'Não';

    if ($id) {

        $sql = "UPDATE pessoas 
                SET nome = :nome,
                    data_nascimento = :data_nascimento,
                    cidade = :cidade,
                    estado = :estado,
                    altura = :altura,
                    peso = :peso,
                    diabetico = :diabetico,
                    hipertenso = :hipertenso,
                    colesterol_alto = :colesterol_alto,
                    intolerancia_lactose = :intolerancia_lactose,
                    vegetariano = :vegetariano
                WHERE id_pessoa = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    } else {

        $sql = "INSERT INTO pessoas 
                (nome, data_nascimento, cidade, estado, altura, peso,
                 diabetico, hipertenso, colesterol_alto, intolerancia_lactose, vegetariano)
                VALUES 
                (:nome, :data_nascimento, :cidade, :estado, :altura, :peso,
                 :diabetico, :hipertenso, :colesterol_alto, :intolerancia_lactose, :vegetariano)";

        $stmt = $pdo->prepare($sql);
    }

    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':data_nascimento', $data_nascimento);
    $stmt->bindParam(':cidade', $cidade);
    $stmt->bindParam(':estado', $estado);
    $stmt->bindParam(':altura', $altura);
    $stmt->bindParam(':peso', $peso);
    $stmt->bindParam(':diabetico', $diabetico);
    $stmt->bindParam(':hipertenso', $hipertenso);
    $stmt->bindParam(':colesterol_alto', $colesterol_alto);
    $stmt->bindParam(':intolerancia_lactose', $intolerancia_lactose);
    $stmt->bindParam(':vegetariano', $vegetariano);

    $stmt->execute();

    header("Location: " . BASE_URL . "cadastros/pessoas.php");
    exit;
}

/* =====================
   EXCLUIR
===================== */
if (isset($_GET['delete'])) {

    $id = $_GET['delete'];

    $sql = "DELETE FROM pessoas WHERE id_pessoa = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    header("Location: " . BASE_URL . "cadastros/pessoas.php");
    exit;
}

/* =====================
   EDITAR
===================== */
$editar = null;

if (isset($_GET['edit'])) {

    $id = $_GET['edit'];

    $stmt = $pdo->prepare("SELECT * FROM pessoas WHERE id_pessoa = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =====================
   LISTAR
===================== */
$stmt = $pdo->query("
    SELECT * 
    FROM pessoas
    ORDER BY nome
");
$pessoas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" charset="UTF-8">
<title>Cadastro de Pessoas para Cálculo de IMC e Sugestões do Juca para Refeições</title>

<style>
        body { font-family: Arial; margin: 20px; }
        form { margin-bottom: 30px; }
        input, select, textarea { margin: 6px 0; padding: 6px; width: 360px; display: block; }
        textarea { width: 100%; max-width: 1000px; min-height: 260px; resize: vertical; }
        table { border-collapse: collapse; width: 100%; }
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

        .grupo {
            margin-top: 20px;
            margin-bottom: 10px;
            font-weight: bold;
            color: #2c3e50;
        }

</style>

</head>
<body>

<h2><?= $editar ? 'Editar Pessoa' : 'Nova Pessoa' ?></h2>

<form method="post">

<input type="hidden" name="id" value="<?= htmlspecialchars($editar['id_pessoa'] ?? '') ?>">

<label for="nome">Nome</label>
<input type="text" name="nome" required value="<?= htmlspecialchars($editar['nome'] ?? '') ?>">

<label>Data de Nascimento</label>
<input type="date" name="data_nascimento" required
        value="<?= isset($editar['data_nascimento']) && !empty($editar['data_nascimento']) ? date('Y-m-d', strtotime($editar['data_nascimento'])) : '' ?>">

<label for="cidade">Cidade onde Mora</label>
<input type="text" name="cidade" required value="<?= htmlspecialchars($editar['cidade'] ?? '') ?>">

<label for="estado">Estado</label>
<input type="text" name="estado" maxlength="2" required value="<?= htmlspecialchars($editar['estado'] ?? '') ?>">

<label>Altura em metros</label>
<input type="number" name="altura" step="0.001" value="<?= htmlspecialchars($editar['altura'] ?? '') ?>">

<label for="peso">Peso</label>
<input type="number" name="peso" step="0.01" value="<?= htmlspecialchars($editar['peso'] ?? '') ?>">

<div class="grupo">Condições / Preferências Alimentares</div>

<label>Diabético?</label>
<select name="diabetico" required>
    <option value="Não" <?= (($editar['diabetico'] ?? 'Não') === 'Não') ? 'selected' : '' ?>>Não</option>
    <option value="Sim" <?= (($editar['diabetico'] ?? 'Não') === 'Sim') ? 'selected' : '' ?>>Sim</option>
</select>

<label>Hipertenso?</label>
<select name="hipertenso" required>
    <option value="Não" <?= (($editar['hipertenso'] ?? 'Não') === 'Não') ? 'selected' : '' ?>>Não</option>
    <option value="Sim" <?= (($editar['hipertenso'] ?? 'Não') === 'Sim') ? 'selected' : '' ?>>Sim</option>
</select>

<label>Colesterol alto?</label>
<select name="colesterol_alto" required>
    <option value="Não" <?= (($editar['colesterol_alto'] ?? 'Não') === 'Não') ? 'selected' : '' ?>>Não</option>
    <option value="Sim" <?= (($editar['colesterol_alto'] ?? 'Não') === 'Sim') ? 'selected' : '' ?>>Sim</option>
</select>

<label>Intolerância à lactose?</label>
<select name="intolerancia_lactose" required>
    <option value="Não" <?= (($editar['intolerancia_lactose'] ?? 'Não') === 'Não') ? 'selected' : '' ?>>Não</option>
    <option value="Sim" <?= (($editar['intolerancia_lactose'] ?? 'Não') === 'Sim') ? 'selected' : '' ?>>Sim</option>
</select>

<label>Vegetariano?</label>
<select name="vegetariano" required>
    <option value="Não" <?= (($editar['vegetariano'] ?? 'Não') === 'Não') ? 'selected' : '' ?>>Não</option>
    <option value="Sim" <?= (($editar['vegetariano'] ?? 'Não') === 'Sim') ? 'selected' : '' ?>>Sim</option>
</select>

<button type="submit"><?= $editar ? 'Atualizar' : 'Salvar' ?></button>

<?php if ($editar): ?>
    <a href="pessoas.php">Cancelar</a>
<?php endif; ?>

</form>

<h2>Lista de Pessoas Cadastradas:</h2>

<table border="1">
<tr>
    <th>Nome</th>
    <th>Cidade/UF</th>
    <th>Altura</th>
    <th>Peso</th>
    <th>Diabético</th>
    <th>Hipertenso</th>
    <th>Colesterol Alto</th>
    <th>Lactose</th>
    <th>Vegetariano</th>
    <th>Ações</th>
</tr>

<?php foreach ($pessoas as $e): ?>
<tr>
    <td><?= htmlspecialchars($e['nome']) ?></td>
    <td><?= htmlspecialchars($e['cidade']) ?>/<?= htmlspecialchars($e['estado']) ?></td>
    <td><?= htmlspecialchars($e['altura']) ?></td>
    <td><?= htmlspecialchars($e['peso']) ?></td>
    <td><?= htmlspecialchars($e['diabetico'] ?? 'Não') ?></td>
    <td><?= htmlspecialchars($e['hipertenso'] ?? 'Não') ?></td>
    <td><?= htmlspecialchars($e['colesterol_alto'] ?? 'Não') ?></td>
    <td><?= htmlspecialchars($e['intolerancia_lactose'] ?? 'Não') ?></td>
    <td><?= htmlspecialchars($e['vegetariano'] ?? 'Não') ?></td>
    <td>
        <a href="pessoas.php?edit=<?= $e['id_pessoa'] ?>">Editar</a>
        <a href="pessoas.php?delete=<?= $e['id_pessoa'] ?>"
           onclick="return confirm('Deseja excluir esta Pessoa?')">
           Excluir
        </a>
    </td>
</tr>
<?php endforeach; ?>

</table>

</body>
</html>

<?php ob_end_flush(); ?>