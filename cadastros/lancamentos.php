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

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    if (!in_array($ext, $permitidas)) {
        die("Tipo de arquivo não permitido.");
    }

    $nome = 'nota_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.' . $ext;
    $caminho = $dir . $nome;

    if (!move_uploaded_file($file['tmp_name'], $caminho)) {
        die("Erro ao salvar a imagem da nota.");
    }

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

    $params = [
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
        ':foto' => $foto_nota
    ];

    if ($id) {
        $params[':id'] = $id;
    }

    $stmt->execute($params);

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
   VALORES VINDOS DA IA / GET
===================== */
$documento_numero_get = $_GET['documento_numero'] ?? '';
$data_lancamento_get  = $_GET['data_lancamento'] ?? '';
$descricao_get        = $_GET['descricao'] ?? '';
$tipo_get             = $_GET['tipo'] ?? 'Pagar';
$data_vencimento_get  = $_GET['data_vencimento'] ?? '';
$valor_nominal_get    = $_GET['valor_nominal'] ?? '';
$data_pagamento_get   = $_GET['data_pagamento'] ?? '';
$valor_pago_get       = $_GET['valor_pago'] ?? '';
$status_get           = $_GET['status'] ?? 'Aberto';
$forma_get            = $_GET['forma_de_pagamento_recebimento'] ?? '';

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

<input type="hidden" name="id" value="<?= htmlspecialchars($editar['id_lancamento'] ?? '') ?>">

<label>Documento</label>
<input name="documento_numero" placeholder="Documento"
value="<?= htmlspecialchars($editar['documento_numero'] ?? $documento_numero_get) ?>">

<label>Data Lançamento</label>
<input type="date" name="data_lancamento" required
value="<?= htmlspecialchars($editar['data_lancamento'] ?? $data_lancamento_get) ?>">

<label>Descrição</label>
<input name="descricao" required
value="<?= htmlspecialchars($editar['descricao'] ?? $descricao_get) ?>">

<label>Tipo de Lançamento</label>
<select name="tipo">
<?php
$tipoSelecionado = $editar['tipo'] ?? $tipo_get;
foreach (['Pagar','Receber'] as $tp):
?>
<option value="<?= $tp ?>" <?= $tipoSelecionado == $tp ? 'selected' : '' ?>>
<?= $tp ?>
</option>
<?php endforeach; ?>
</select>

<label>Data do Vencimento</label>
<input type="date" name="data_vencimento" required
value="<?= htmlspecialchars($editar['data_vencimento'] ?? $data_vencimento_get) ?>">

<label>Valor Nominal</label>
<input type="number" name="valor_nominal" step="0.01"
value="<?= htmlspecialchars($editar['valor_nominal'] ?? $valor_nominal_get) ?>">

<label>Data de Pagamento</label>
<input type="date" name="data_pagamento"
value="<?= htmlspecialchars($editar['data_pagamento'] ?? $data_pagamento_get) ?>">

<label>Valor Pago</label>
<input type="number" name="valor_pago" step="0.01"
value="<?= htmlspecialchars($editar['valor_pago'] ?? $valor_pago_get) ?>">

<label>Status</label>
<select name="status">
<?php
$statusSelecionado = $editar['status'] ?? $status_get;
foreach (['Aberto','Pago','Recebido'] as $st):
?>
<option value="<?= $st ?>" <?= $statusSelecionado == $st ? 'selected' : '' ?>>
<?= $st ?>
</option>
<?php endforeach; ?>
</select>

<label>Forma do Pagamento ou Recebimento</label>
<select name="forma_de_pagamento_recebimento">
<?php
$formaSelecionada = $editar['forma_de_pagamento_recebimento'] ?? $forma_get;
foreach ([
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
] as $fpr):
?>
<option value="<?= $fpr ?>" <?= $formaSelecionada == $fpr ? 'selected' : '' ?>>
<?= $fpr ?>
</option>
<?php endforeach; ?>
</select>

<label>Grupo</label>
<select name="id_grupo">
<?php foreach ($grupos as $g): ?>
<option value="<?= $g['id_grupo'] ?>"
<?= (isset($editar['id_grupo']) && $editar['id_grupo'] == $g['id_grupo']) ? 'selected' : '' ?>>
<?= htmlspecialchars($g['descricao']) ?>
</option>
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
<td><?= htmlspecialchars($l['documento_numero'] ?? '') ?></td>    
<td><?= htmlspecialchars($l['descricao'] ?? '') ?></td>
<td>R$ <?= number_format((float)$l['valor_nominal'],2,',','.') ?></td>
<td>
<?php if (!empty($l['foto_nota'])): ?>
<a href="<?= BASE_URL . $l['foto_nota'] ?>" target="_blank">Ver Nota</a>
<?php endif; ?>
</td>
<td>
<a href="?edit=<?= $l['id_lancamento'] ?>">Editar</a>
<a href="?delete=<?= $l['id_lancamento'] ?>" onclick="return confirm('Deseja excluir este lançamento?')">
Excluir
</a>
</td>
</tr>
<?php endforeach; ?>

</table>

</body>
</html>