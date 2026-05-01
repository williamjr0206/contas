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
    $documento_numero = $_POST['documento_numero'] ?? '';
    $data_lancamento = $_POST['data_lancamento'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $data_vencimento = $_POST['data_vencimento'] ?? '';
    $valor_nominal = $_POST['valor_nominal'] ?? '';
    $data_pagamento = !empty($_POST['data_pagamento']) ? $_POST['data_pagamento'] : null;
    $valor_pago = $_POST['valor_pago'] !== '' ? $_POST['valor_pago'] : null;
    $status = $_POST['status'] ?? '';
    $forma_de_pagamento_recebimento = $_POST['forma_de_pagamento_recebimento'] ?? '';
    $id_grupo = $_POST['id_grupo'] ?? null;

    if ($id) {
        $sql = "UPDATE lancamentos 
                SET documento_numero = :documento_numero,
                    data_lancamento = :data_lancamento,
                    descricao = :descricao,
                    tipo = :tipo,
                    data_vencimento = :data_vencimento,
                    valor_nominal = :valor_nominal,
                    data_pagamento = :data_pagamento,
                    valor_pago = :valor_pago,
                    status = :status,
                    forma_de_pagamento_recebimento = :forma_de_pagamento_recebimento,
                    id_grupo = :id_grupo
                WHERE id_lancamento = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id);

    } else {
        $sql = "INSERT INTO lancamentos (
                    documento_numero,
                    data_lancamento,
                    descricao,
                    tipo,
                    data_vencimento,
                    valor_nominal,
                    data_pagamento,
                    valor_pago,
                    status,
                    forma_de_pagamento_recebimento,
                    id_grupo
                ) VALUES (
                    :documento_numero,
                    :data_lancamento,
                    :descricao,
                    :tipo,
                    :data_vencimento,
                    :valor_nominal,
                    :data_pagamento,
                    :valor_pago,
                    :status,
                    :forma_de_pagamento_recebimento,
                    :id_grupo
                )";

        $stmt = $pdo->prepare($sql);
    }

    $stmt->bindParam(':documento_numero', $documento_numero);
    $stmt->bindParam(':data_lancamento', $data_lancamento);
    $stmt->bindParam(':descricao', $descricao);
    $stmt->bindParam(':tipo', $tipo);
    $stmt->bindParam(':data_vencimento', $data_vencimento);
    $stmt->bindParam(':valor_nominal', $valor_nominal);
    $stmt->bindParam(':data_pagamento', $data_pagamento);
    $stmt->bindParam(':valor_pago', $valor_pago);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':forma_de_pagamento_recebimento', $forma_de_pagamento_recebimento);
    $stmt->bindParam(':id_grupo', $id_grupo);

    $stmt->execute();

    header("Location: " . BASE_URL . "cadastros/lancamentos.php");
    exit;
}

/* =====================
   EXCLUIR
===================== */
if (isset($_GET['delete'])) {

    $id = $_GET['delete'];

    $sql = "DELETE FROM lancamentos WHERE id_lancamento = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    header("Location: " . BASE_URL . "cadastros/lancamentos.php");
    exit;
}

/* =====================
   EDITAR
===================== */
$editar = null;

if (isset($_GET['edit'])) {

    $id = $_GET['edit'];

    $stmt = $pdo->prepare("SELECT * FROM lancamentos WHERE id_lancamento = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =====================
   SELECT GRUPO
===================== */
$stmt = $pdo->query("SELECT id_grupo, descricao FROM grupos ORDER BY descricao");
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   LISTAR
===================== */
$stmt = $pdo->query("SELECT 
                        l.*,
                        g.descricao AS grupo_descricao
                    FROM lancamentos l
                    LEFT JOIN grupos g ON g.id_grupo = l.id_grupo
                    ORDER BY l.data_vencimento DESC, l.id_lancamento DESC");
$lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lançamentos</title>

    <style>
        body { font-family: Arial; margin: 20px; }
        form { margin-bottom: 30px; }
        input, select { margin: 6px 0; padding: 6px; width: 360px; display: block; }
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 6px; }
        a { margin-right: 10px; }
    </style>
</head>
<body>

<h2><?= $editar ? 'Editar Lançamento' : 'Novo Lançamento' ?></h2>

<form method="post">

    <input type="hidden" name="id" value="<?= htmlspecialchars($editar['id_lancamento'] ?? '') ?>">

    <label>Número do Documento</label>
    <input name="documento_numero" value="<?= htmlspecialchars($editar['documento_numero'] ?? '') ?>">

    <label>Data do Lançamento</label>
    <input type="date" name="data_lancamento" required
        value="<?= !empty($editar['data_lancamento']) ? date('Y-m-d', strtotime($editar['data_lancamento'])) : '' ?>">

    <label>Descrição do Lançamento</label>
    <input name="descricao" required
        value="<?= htmlspecialchars($editar['descricao'] ?? '') ?>">

    <label>Tipo do Lançamento</label>
    <select name="tipo" required>
        <?php foreach (['Pagar','Receber'] as $tp): ?>
            <option value="<?= $tp ?>" <?= (isset($editar['tipo']) && $editar['tipo'] == $tp) ? 'selected' : '' ?>>
                <?= $tp ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Data do Vencimento</label>
    <input type="date" name="data_vencimento" required
        value="<?= !empty($editar['data_vencimento']) ? date('Y-m-d', strtotime($editar['data_vencimento'])) : '' ?>">

    <label>Valor Nominal do Lançamento</label>
    <input type="number" name="valor_nominal" step="0.01" required
        value="<?= htmlspecialchars($editar['valor_nominal'] ?? '') ?>">
    
    <label>Data do Pagamento</label>
    <input type="date" name="data_pagamento"
        value="<?= !empty($editar['data_pagamento']) ? date('Y-m-d', strtotime($editar['data_pagamento'])) : '' ?>">

    <label>Valor Pago</label>
    <input type="number" name="valor_pago" step="0.01"
        value="<?= htmlspecialchars($editar['valor_pago'] ?? '') ?>">

    <label>Status do Lançamento</label>
    <select name="status" required>
        <?php foreach (['Aberto','Pago','Recebido'] as $st): ?>
            <option value="<?= $st ?>" <?= (isset($editar['status']) && $editar['status'] == $st) ? 'selected' : '' ?>>
                <?= $st ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Forma do Pagamento ou Recebimento</label>
    <select name="forma_de_pagamento_recebimento">
        <?php foreach ([
            '',
            'Pix Recebido',
            'Pix QR Code',
            'Aplicação',
            'Cartão Débito',
            'Débito Automático',
            'Crédito em Conta',
            'Débito em Conta',
            'Pagamento Boleto',
            'Pix Pagamento',
            'Transação Bancária'
        ] as $fpr): ?>
            <option value="<?= $fpr ?>" <?= (isset($editar['forma_de_pagamento_recebimento']) && $editar['forma_de_pagamento_recebimento'] == $fpr) ? 'selected' : '' ?>>
                <?= $fpr ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Classificação de Lançamentos</label>
    <select name="id_grupo" required>
        <option value="">Selecione</option>
        <?php foreach ($grupos as $grupo): ?>
            <option value="<?= $grupo['id_grupo'] ?>"
                <?= (isset($editar['id_grupo']) && $editar['id_grupo'] == $grupo['id_grupo']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($grupo['descricao']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit"><?= $editar ? 'Atualizar' : 'Salvar' ?></button>

    <?php if ($editar): ?>
        <a href="lancamentos.php">Cancelar</a>
    <?php endif; ?>

</form>

<h2>Lista de Lançamentos</h2>

<table border="1">
    <tr>
        <th>Documento</th>
        <th>Descrição</th>
        <th>Receber / Pagar</th>
        <th>Grupo</th>
        <th>Vencimento</th>
        <th>Valor Nominal</th>
        <th>Valor Pago</th>
        <th>Situação</th>
        <th>Ações</th>
    </tr>

    <?php foreach ($lancamentos as $l): ?>
        <tr>
            <td><?= htmlspecialchars($l['documento_numero'] ?? '') ?></td>
            <td><?= htmlspecialchars($l['descricao'] ?? '') ?></td>
            <td><?= htmlspecialchars($l['tipo'] ?? '') ?></td>
            <td><?= htmlspecialchars($l['grupo_descricao'] ?? '') ?></td>
            <td><?= !empty($l['data_vencimento']) ? htmlspecialchars(date('d/m/Y', strtotime($l['data_vencimento']))) : '' ?></td>
            <td><?= 'R$ ' . number_format((float)($l['valor_nominal'] ?? 0), 2, ',', '.') ?></td>
            <td><?= $l['valor_pago'] !== null ? 'R$ ' . number_format((float)$l['valor_pago'], 2, ',', '.') : '' ?></td>
            <td><?= htmlspecialchars($l['status'] ?? '') ?></td>
            <td>
                <a href="<?= BASE_URL ?>cadastros/lancamentos.php?edit=<?= $l['id_lancamento'] ?>">Editar</a>

                <a href="<?= BASE_URL ?>cadastros/lancamentos.php?delete=<?= $l['id_lancamento'] ?>"
                   onclick="return confirm('Deseja excluir este lançamento?')">
                   Excluir
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
