<?php
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
verificaAcesso();
require __DIR__ . '/../includes/menu.php';

$data_inicio = $_GET['inicio'] ?? date('Y-m-01');
$data_fim    = $_GET['fim'] ?? date('Y-m-t');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Prestação de Contas</title>

<style>
body { font-family: Arial; margin: 20px; }
.box {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccc;
    border-radius: 8px;
    max-width: 400px;
}
input, button {
    display: block;
    width: 100%;
    margin-top: 10px;
    padding: 8px;
}
button {
    cursor: pointer;
}
</style>
</head>
<body>

<h2>Prestação de Contas</h2>

<div class="box">
    <form method="get">

        <label>Data inicial</label>
        <input type="date" name="inicio" value="<?= $data_inicio ?>" required>

        <label>Data final</label>
        <input type="date" name="fim" value="<?= $data_fim ?>" required>

        <button type="submit">Atualizar</button>

    </form>

    <br>

    <a href="<?= BASE_URL ?>relatorios/prestacao_contas_pdf.php?inicio=<?= $data_inicio ?>&fim=<?= $data_fim ?>" target="_blank">
        <button>Gerar PDF</button>
    </a>
</div>

</body>
</html>