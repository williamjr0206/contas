<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
verificaAcesso();
require __DIR__ . '/../includes/menu.php';

/* =====================
   UPLOAD DA IMAGEM
===================== */
function uploadNota($file) {
    if (empty($file['name'])) return null;

    $dir = __DIR__ . '/../uploads/notas/';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $nome = 'nota_' . time() . '.' . $ext;
    $caminho = $dir . $nome;

    move_uploaded_file($file['tmp_name'], $caminho);

    return 'uploads/notas/' . $nome;
}

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
    $forma = $_POST['forma_de_pagamento_recebimento'] ?? '';
    $id_grupo = $_POST['id_grupo'] ?? null;

    $foto_nota = uploadNota($_FILES['foto_nota'] ?? []);

    if ($id) {

        // Se não enviou nova foto, mantém a antiga
        if (!$foto_nota) {
            $stmt = $pdo->prepare("SELECT foto_nota FROM lancamentos WHERE id_lancamento = :id");
            $stmt->execute([':id' => $id]);
            $foto_nota = $stmt->fetchColumn();
        }

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
                    forma_de_pagamento_recebimento = :forma,
                    id_grupo = :id_grupo,
                    foto_nota = :foto
                WHERE id_lancamento = :id";

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
                    id_grupo,
                    foto_nota
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
                    :forma,
                    :id_grupo,
                    :foto
                )";
    }

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':documento_numero' => $documento_numero,
        ':data_lancamento' => $data_lancamento,
        ':descricao' => $descricao,
        ':tipo' => $tipo,
        ':data_vencimento' => $data_vencimento,
        ':valor_nominal' => $valor_nominal,
        ':data_pagamento' => $data_pagamento,
        ':valor_pago' => $valor_pago,
        ':status' => $status,
        ':forma' => $forma,
        ':id_grupo' => $id_grupo,
        ':foto' => $foto_nota,
        ':id' => $id
    ]);

    header("Location: " . BASE_URL . "cadastros/lancamentos.php");
    exit;
}

/* =====================
   EXCLUIR
===================== */
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    $stmt = $pdo->prepare("DELETE FROM lancamentos WHERE id_lancamento = :id");
    $stmt->execute([':id' => $id]);

    header("Location: " . BASE_URL . "cadastros/lancamentos.php");
    exit;
}

/* =====================
   EDITAR
===================== */
$editar = null;

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM lancamentos WHERE id_lancamento = :id");
    $stmt->execute([':id' => $_GET['edit']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =====================
   GRUPOS
===================== */
$stmt = $pdo->query("SELECT id_grupo, descricao FROM grupos ORDER BY descricao");
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   LISTAR
===================== */
$stmt = $pdo->query("SELECT l.*, g.descricao AS grupo_descricao
                    FROM lancamentos l
                    LEFT JOIN grupos g ON g.id_grupo = l.id_grupo
                    ORDER BY l.documento_numero DESC");

$lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Lançamentos</title>
<style>
body { font-family: Arial; margin: 20px; }
input, select { width: 360px; margin: 6px 0; padding: 6px; display:block; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 6px; border: 1px solid #ccc; }
img { max-width: 80px; }
</style>
</head>
<body>

<h2><?= $editar ? 'Editar' : 'Novo' ?> Lançamento</h2>

<form method="post" enctype="multipart/form-data">

<input type="hidden" name="id" value="<?= $editar['id_lancamento'] ?? '' ?>">
<label>Documento</label>
<input name="documento_numero" placeholder="Documento" value="<?= $editar['documento_numero'] ?? '' ?>">

<label>Data Lançamento</label>
<input type="date" name="data_lancamento" required value="<?= $editar['data_lancamento'] ?? '' ?>">

<label>Descrição</label>
<input name="descricao" required value="<?= $editar['descricao'] ?? '' ?>">

<label>Tipo de Lançamento</label>
<select name="tipo">
<option>Pagar</option>
<option>Receber</option>
</select>

<label>Data do Vencimento</label>
<input type="date" name="data_vencimento" required value="<?= $editar['data_vencimento'] ?? '' ?>">

<label>Valor Nominal</label>
<input type="number" name="valor_nominal" step="0.01" value="<?= $editar['valor_nominal'] ?? '' ?>">

<label>Data de Pagamento</label>
<input type="date" name="data_pagamento" value="<?= $editar['data_pagamento'] ?? '' ?>">

<label>Valor Pago</label>
<input type="number" name="valor_pago" step="0.01" value="<?= $editar['valor_pago'] ?? '' ?>">

<label>Status</label>
<select name="status">
<option>Aberto</option>
<option>Pago</option>
<option>Recebido</option>
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

<label>Grupo</label>
<select name="id_grupo">
<?php foreach ($grupos as $g): ?>
<option value="<?= $g['id_grupo'] ?>"><?= $g['descricao'] ?></option>
<?php endforeach; ?>
</select>

<label>Foto da Nota</label>
<input type="file" name="foto_nota">

<button type="submit">Salvar</button>

</form>

<h2>Lista</h2>

<table>
<tr>
<th>Documento</th>    
<th>Descrição</th>
<th>Valor</th>
<th>Nota</th>
<th>Ações</th>
</tr>

<?php foreach ($lancamentos as $l): ?>
<tr>
<td><?= $l['documento_numero'] ?></td>    
<td><?= $l['descricao'] ?></td>
<td>R$ <?= number_format($l['valor_nominal'],2,',','.') ?></td>
<td>
<?php if (!empty($l['foto_nota'])): ?>
<a href="<?= BASE_URL . $l['foto_nota'] ?>" target="_blank">Ver Nota</a>
<?php endif; ?>
</td>
<td>
<a href="?edit=<?= $l['id_lancamento'] ?>">Editar</a>
<a href="?delete=<?= $l['id_lancamento'] ?>">Excluir</a>
</td>
</tr>
<?php endforeach; ?>

</table>

</body>
</html>