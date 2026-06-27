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
    $tipo_de_produto = $_POST['tipo_de_produto'] ?? 'Alimentos';
    $preco = $_POST['preco'] ?? 0;
    $unidade = $_POST['unidade'] ?? '';
    $unidade_consumo = $_POST['unidade_consumo'] ?? '';

    $quantidade_embalagem = $_POST['quantidade_embalagem'] !== '' ? $_POST['quantidade_embalagem'] : 1;
    $peso_unidade_consumo = $_POST['peso_unidade_consumo'] !== '' ? $_POST['peso_unidade_consumo'] : 1;

    if ((float)$quantidade_embalagem <= 0) {
        $quantidade_embalagem = 1;
    }

    if ((float)$peso_unidade_consumo <= 0) {
        $peso_unidade_consumo = 1;
    }

    $fator_conversao_consumo = (float)$peso_unidade_consumo / (float)$quantidade_embalagem;

    $saldo = $_POST['saldo'] ?? 0;
    $cadastrado_em = $_POST['cadastrado_em'] ?? date('Y-m-d');
    $descricao_normalizada = normalizarDescricaoProduto($descricao);

    if ($id) {
        $sql = "UPDATE produtos 
        SET codigo = :codigo,
            fornecedor = :fornecedor,
            descricao = :descricao,
            tipo_de_produto = :tipo_de_produto,
            preco = :preco,
            unidade = :unidade,
            unidade_consumo = :unidade_consumo,
            quantidade_embalagem = :quantidade_embalagem,
            peso_unidade_consumo = :peso_unidade_consumo,
            fator_conversao_consumo = :fator_conversao_consumo,
            saldo = :saldo,
            cadastrado_em = :cadastrado_em,
            descricao_normalizada = :descricao_normalizada
        WHERE id_produto = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
    } else {
        $sql = "INSERT INTO produtos 
        (codigo, fornecedor, descricao, tipo_de_produto, preco, unidade, unidade_consumo, quantidade_embalagem, peso_unidade_consumo, fator_conversao_consumo, saldo, cadastrado_em, descricao_normalizada)
        VALUES 
        (:codigo, :fornecedor, :descricao, :tipo_de_produto, :preco, :unidade, :unidade_consumo, :quantidade_embalagem, :peso_unidade_consumo, :fator_conversao_consumo, :saldo, :cadastrado_em, :descricao_normalizada)";

        $stmt = $pdo->prepare($sql);
    }

    $stmt->bindParam(':codigo', $codigo);
    $stmt->bindParam(':fornecedor', $fornecedor);
    $stmt->bindParam(':descricao', $descricao);
    $stmt->bindParam(':tipo_de_produto', $tipo_de_produto);
    $stmt->bindParam(':preco', $preco);
    $stmt->bindParam(':unidade', $unidade);
    $stmt->bindParam(':unidade_consumo', $unidade_consumo);
    $stmt->bindParam(':quantidade_embalagem', $quantidade_embalagem);
    $stmt->bindParam(':peso_unidade_consumo', $peso_unidade_consumo);
    $stmt->bindParam(':fator_conversao_consumo', $fator_conversao_consumo);
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

    .info {
        background: #eef5ff;
        border: 1px solid #b7d6f2;
        padding: 10px;
        margin-bottom: 20px;
        max-width: 720px;
    }
</style>

</head>
<body>

<h2><?= $editar ? 'Editar Produto' : 'Novo Produto' ?></h2>

<div class="info">
    <strong>Regra de conversão:</strong><br>
    Fator = Peso/Volume de 1 unidade de consumo ÷ Quantidade da embalagem.<br><br>
    Exemplo ovos: 1 cartela com 20 ovos → 1 ÷ 20 = 0,05<br>
    Exemplo creme de leite: 1 caixa com 500 ml → 1 ÷ 500 = 0,002<br>
    Exemplo pão francês: compra em KG e consumo em unidade. 1 kg = 1000 g, 1 pão = 50 g → 50 ÷ 1000 = 0,05
</div>

<form method="post">

<input type="hidden" name="id" value="<?= htmlspecialchars($editar['id_produto'] ?? '') ?>">

<label>Código do Produto na NF / Fornecedor</label>
<input name="codigo" required value="<?= htmlspecialchars($editar['codigo'] ?? '') ?>">

<label>Fornecedor</label>
<input name="fornecedor" required value="<?= htmlspecialchars($editar['fornecedor'] ?? '') ?>">

<label>Descrição</label>
<input name="descricao" required value="<?= htmlspecialchars($editar['descricao'] ?? '') ?>">

<label>Tipo de Produto</label>
<select name="tipo_de_produto" required>
    <?php
    $tipoAtual = $editar['tipo_de_produto'] ?? 'Alimentos';

    $tipos = [
        'Cozinha',
        'Banheiro',
        'Alimentos',
        'Remédios',
        'Vestuários',
        'Outros'
    ];

    foreach ($tipos as $tipo):
    ?>
        <option value="<?= htmlspecialchars($tipo) ?>" <?= ($tipoAtual === $tipo) ? 'selected' : '' ?>>
            <?= htmlspecialchars($tipo) ?>
        </option>
    <?php endforeach; ?>
</select>

<label>Preço ou Custo da Unidade de Compra</label>
<input type="number" name="preco" step="0.01" required value="<?= htmlspecialchars($editar['preco'] ?? '') ?>">

<label>Unidade de Compra / Estoque</label>
<input name="unidade" required placeholder="Ex: CARTELA, CX, KG, PCT, LT, UN" value="<?= htmlspecialchars($editar['unidade'] ?? '') ?>">

<label>Unidade de Consumo / Receita</label>
<input name="unidade_consumo" required placeholder="Ex: UN, ML, G, KG" value="<?= htmlspecialchars($editar['unidade_consumo'] ?? '') ?>">

<label>Quantidade da Embalagem</label>
<input type="number" name="quantidade_embalagem" step="0.0001" required
       placeholder="Ex: 20 ovos, 500 ml, 1000 g"
       value="<?= htmlspecialchars($editar['quantidade_embalagem'] ?? 1) ?>">

<label>Peso / Volume de 1 Unidade de Consumo</label>
<input type="number" name="peso_unidade_consumo" step="0.0001" required
       placeholder="Ex: 1 para ovos/ml/g, 50 para pão francês"
       value="<?= htmlspecialchars($editar['peso_unidade_consumo'] ?? 1) ?>">

<label>Fator de Conversão para Consumo</label>
<input type="number" name="fator_conversao_consumo" step="0.0001" readonly
       value="<?= htmlspecialchars($editar['fator_conversao_consumo'] ?? 1) ?>">

<label>Saldo na Unidade de Compra / Estoque</label>
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
    <th>Descrição</th>
    <th>Fornecedor</th>
    <th>Tipo</th>
    <th>Preço / Custo R$</th>
    <th>Unid. Compra</th>
    <th>Unid. Consumo</th>
    <th>Qtd. Embalagem</th>
    <th>Peso Unid. Consumo</th>
    <th>Fator Conversão</th>
    <th>Saldo Compra</th>
    <th>Saldo Consumo</th>
    <th>Ações</th>
</tr>

<?php foreach ($eventos as $e): ?>
<?php
$fator_consumo = (float)($e['fator_conversao_consumo'] ?? 1);

if ($fator_consumo <= 0) {
    $fator_consumo = 1;
}

$saldo_consumo = (float)$e['saldo'] / $fator_consumo;
$unidade_consumo_exibir = ($e['unidade_consumo'] ?? '') ?: ($e['unidade'] ?? '');
?>
<tr>
    <td><?= htmlspecialchars($e['id_produto']) ?></td>
    <td><?= htmlspecialchars($e['codigo']) ?></td>
    <td><?= htmlspecialchars($e['descricao']) ?></td>
    <td><?= htmlspecialchars($e['fornecedor']) ?></td>
    <td><?= htmlspecialchars($e['tipo_de_produto'] ?? 'Alimentos') ?></td>
    <td><?= htmlspecialchars(number_format((float)$e['preco'], 2, ',', '.')) ?></td>
    <td><?= htmlspecialchars($e['unidade']) ?></td>
    <td><?= htmlspecialchars($e['unidade_consumo'] ?? '') ?></td>
    <td><?= htmlspecialchars(number_format((float)($e['quantidade_embalagem'] ?? 1), 4, ',', '.')) ?></td>
    <td><?= htmlspecialchars(number_format((float)($e['peso_unidade_consumo'] ?? 1), 4, ',', '.')) ?></td>
    <td><?= htmlspecialchars(number_format((float)($e['fator_conversao_consumo'] ?? 1), 4, ',', '.')) ?></td>
    <td><?= htmlspecialchars(number_format((float)$e['saldo'], 4, ',', '.')) ?> <?= htmlspecialchars($e['unidade']) ?></td>
    <td>
        <?= htmlspecialchars(number_format($saldo_consumo, 4, ',', '.')) ?>
        <?= htmlspecialchars($unidade_consumo_exibir) ?>
    </td>
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

<?php ob_end_flush(); ?>