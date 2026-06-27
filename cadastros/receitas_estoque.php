<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
verificaAcesso();

require __DIR__ . '/../includes/menu.php';

function normalizarTexto($texto)
{
    $texto = mb_strtoupper(trim($texto ?? ''), 'UTF-8');
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
    $texto = preg_replace('/[^A-Z0-9 ]/', ' ', $texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    return trim($texto);
}

function singularizarPalavra($palavra)
{
    if (strlen($palavra) > 4 && substr($palavra, -2) === 'ES') {
        return substr($palavra, 0, -2);
    }

    if (strlen($palavra) > 3 && substr($palavra, -1) === 'S') {
        return substr($palavra, 0, -1);
    }

    return $palavra;
}

function palavrasRelevantes($texto)
{
    $texto = normalizarTexto($texto);
    $palavras = explode(' ', $texto);

    $ignorar = [
        'DE','DA','DO','DAS','DOS','COM','SEM','PARA','POR','EM','A','O','AS','OS','E',
        'UN','UND','UNIDADE','UNIDADES','KG','G','GR','ML','L','LT',
        'PACOTE','PCT','CAIXA','CX','LATA','VIDRO','SACHE','SACHET',
        'CARTELA','BANDEJA','POTE','TIPO','TRADICIONAL','ESPECIAL',
        'MEDIA','MEDIO','PEQUENA','PEQUENO','GRANDE'
    ];

    $resultado = [];

    foreach ($palavras as $p) {
        $p = singularizarPalavra($p);

        if (strlen($p) >= 3 && !in_array($p, $ignorar)) {
            $resultado[] = $p;
        }
    }

    return array_unique($resultado);
}

function palavrasCompativeis($palavraIng, $palavraProd)
{
    $sinonimos = [
        'OVO' => ['OVO','OVOS'],
        'ACUCAR' => ['ACUCAR','REFINADO','CRISTAL'],
        'OLEO' => ['OLEO'],
        'FUBA' => ['FUBA','FLOCAO','MILHO'],
        'FLOCAO' => ['FLOCAO','MILHO'],
        'FERMENTO' => ['FERMENTO'],
        'CENOURA' => ['CENOURA'],
        'SAL' => ['SAL'],
        'AGUA' => ['AGUA']
    ];

    if ($palavraIng === $palavraProd) {
        return true;
    }

    foreach ($sinonimos as $grupo) {
        if (in_array($palavraIng, $grupo) && in_array($palavraProd, $grupo)) {
            return true;
        }
    }

    return false;
}

function calcularPontuacaoProduto($ingrediente, $produto)
{
    $palavrasIng = palavrasRelevantes($ingrediente);
    $palavrasProd = palavrasRelevantes($produto);

    $pontos = 0;
    $temCompatibilidadeReal = false;

    foreach ($palavrasIng as $palavraIng) {
        foreach ($palavrasProd as $palavraProd) {

            if (palavrasCompativeis($palavraIng, $palavraProd)) {
                $pontos += 100;
                $temCompatibilidadeReal = true;
            }
        }
    }

    if (!$temCompatibilidadeReal) {
        return 0;
    }

    similar_text(normalizarTexto($ingrediente), normalizarTexto($produto), $similaridade);
    $pontos += ($similaridade * 0.2);

    return $pontos;
}function moeda($valor)
{
    return number_format((float)$valor, 2, ',', '.');
}

function numero($valor)
{
    return number_format((float)$valor, 3, ',', '.');
}

$id_receita = $_GET['id_receita'] ?? null;

if (!$id_receita) {
    die('Receita não informada.');
}

/* =====================
   BUSCAR RECEITA
===================== */
$stmt = $pdo->prepare("
    SELECT r.*, c.nome_categoria
    FROM receitas r
    INNER JOIN receitas_categorias c ON c.id_categoria = r.id_categoria
    WHERE r.id_receita = :id
");
$stmt->execute([':id' => $id_receita]);
$receita = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$receita) {
    die('Receita não encontrada.');
}

/* =====================
   BUSCAR INGREDIENTES
===================== */
$stmt = $pdo->prepare("
    SELECT *
    FROM receitas_ingredientes
    WHERE id_receita = :id
    ORDER BY id_ingrediente
");
$stmt->execute([':id' => $id_receita]);
$ingredientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   BUSCAR PRODUTOS
===================== */
$stmt = $pdo->query("
    SELECT *
    FROM produtos
    ORDER BY descricao
");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   COMPARAR INGREDIENTES COM ESTOQUE
===================== */
$resultado = [];

foreach ($ingredientes as $ing) {

$produtoEncontrado = null;
$melhorPontuacao = 0;

foreach ($produtos as $p) {
    $pontuacao = calcularPontuacaoProduto(
        $ing['descricao_ingrediente'],
        $p['descricao'] ?? ''
    );

    if ($pontuacao > $melhorPontuacao) {
        $melhorPontuacao = $pontuacao;
        $produtoEncontrado = $p;
    }
}
    $encontrado = false;
    $saldo_estoque = 0;
    $unidade_estoque = '';
    $unidade_consumo = '';
    $fator = 1;
    $saldo_consumo = 0;
    $necessario = (float)$ing['quantidade'];
    $faltante = $necessario;
    $suficiente = false;
    $preco = 0;
    $custo_estimado = 0;

    if ($produtoEncontrado && $melhorPontuacao >= 80) {
        $encontrado = true;

        $saldo_estoque = (float)($produtoEncontrado['saldo'] ?? 0);
        $unidade_estoque = $produtoEncontrado['unidade'] ?? '';
        $unidade_consumo = $produtoEncontrado['unidade_consumo'] ?: $unidade_estoque;
        $fator = (float)($produtoEncontrado['fator_conversao_consumo'] ?? 1);

        if ($fator <= 0) {
            $fator = 1;
        }

        $saldo_consumo = $saldo_estoque / $fator;
        $faltante = $necessario - $saldo_consumo;

        if ($faltante <= 0) {
            $faltante = 0;
            $suficiente = true;
        }

$preco = (float)($produtoEncontrado['preco'] ?? 0);

/*
    O preço está na unidade de compra/estoque.
    O fator_conversao_consumo representa quanto da unidade de compra
    corresponde a 1 unidade de consumo.

    Exemplo:
    Compra: 1 KG por R$ 20,00
    Consumo: 1 unidade de 50g
    Fator: 0,05
    Custo unitário consumo = 20,00 * 0,05 = 1,00
*/

$custo_unitario_consumo = $preco * $fator;
$custo_estimado = $necessario * $custo_unitario_consumo;    }

    $resultado[] = [
        'ingrediente' => $ing,
        'produto' => $produtoEncontrado,
        'pontuacao' => $melhorPontuacao,
        'encontrado' => $encontrado,
        'saldo_estoque' => $saldo_estoque,
        'unidade_estoque' => $unidade_estoque,
        'unidade_consumo' => $unidade_consumo,
        'saldo_consumo' => $saldo_consumo,
        'necessario' => $necessario,
        'faltante' => $faltante,
        'suficiente' => $suficiente,
        'preco' => $preco,
        'custo_estimado' => $custo_estimado
    ];
}

$total_custo = 0;
$total_faltantes = 0;

foreach ($resultado as $r) {
    $total_custo += (float)$r['custo_estimado'];

    if (!$r['encontrado'] || !$r['suficiente']) {
        $total_faltantes++;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receita x Estoque</title>

<style>
body { font-family: Arial; margin: 20px; }
a { margin-right: 10px; }

table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    margin-top: 20px;
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
    vertical-align: top;
}

tr:nth-child(even) {
    background: #f8f8f8;
}

.box {
    border: 1px solid #ddd;
    padding: 15px;
    background: #fafafa;
    margin-bottom: 20px;
}

.ok {
    color: green;
    font-weight: bold;
}

.falta {
    color: red;
    font-weight: bold;
}

.alerta {
    color: #b9770e;
    font-weight: bold;
}
</style>
</head>

<body>

<h2>Receita x Estoque</h2>

<div class="box">
    <p><strong>Receita:</strong> <?= htmlspecialchars($receita['titulo']) ?></p>
    <p><strong>Categoria:</strong> <?= htmlspecialchars($receita['nome_categoria']) ?></p>
    <p><strong>Rendimento:</strong> <?= htmlspecialchars($receita['rendimento'] ?? '') ?></p>
    <p><strong>Custo estimado pelos produtos encontrados:</strong> R$ <?= moeda($total_custo) ?></p>
    <p><strong>Itens faltantes ou não localizados:</strong> <?= $total_faltantes ?></p>

    <a href="receitas.php?edit=<?= $id_receita ?>">Voltar para Receita</a>
    <a href="receitas_pdf.php?id_receita=<?= $id_receita ?>" target="_blank">Gerar PDF</a>
</div>

<table>
<tr>
    <th>Ingrediente da Receita</th>
    <th>Quantidade Necessária</th>
    <th>Produto Encontrado no Estoque</th>
    <th>Saldo no Estoque</th>
    <th>Situação</th>
    <th>Faltante</th>
    <th>Custo Estimado R$</th>
</tr>

<?php foreach ($resultado as $r): ?>
<tr>
    <td>
        <?= htmlspecialchars($r['ingrediente']['descricao_ingrediente']) ?>
    </td>

    <td>
        <?= numero($r['necessario']) ?>
        <?= htmlspecialchars($r['ingrediente']['unidade']) ?>
    </td>

    <td>
        <?php if ($r['encontrado']): ?>
            <?= htmlspecialchars($r['produto']['descricao']) ?><br>
            <small>Similaridade: <?= number_format($r['pontuacao'], 1, ',', '.') ?>%</small>
        <?php else: ?>
            <span class="alerta">Não localizado</span>
        <?php endif; ?>
    </td>

    <td>
        <?php if ($r['encontrado']): ?>
            <?= numero($r['saldo_consumo']) ?>
            <?= htmlspecialchars($r['unidade_consumo']) ?>
            <br>
            <small>
                Estoque original:
                <?= numero($r['saldo_estoque']) ?>
                <?= htmlspecialchars($r['unidade_estoque']) ?>
            </small>
        <?php else: ?>
            -
        <?php endif; ?>
    </td>

    <td>
        <?php if (!$r['encontrado']): ?>
            <span class="alerta">Produto não encontrado</span>
        <?php elseif ($r['suficiente']): ?>
            <span class="ok">Suficiente</span>
        <?php else: ?>
            <span class="falta">Falta comprar</span>
        <?php endif; ?>
    </td>

    <td>
        <?php if (!$r['encontrado']): ?>
            <?= numero($r['necessario']) ?>
            <?= htmlspecialchars($r['ingrediente']['unidade']) ?>
        <?php else: ?>
            <?= numero($r['faltante']) ?>
            <?= htmlspecialchars($r['unidade_consumo']) ?>
        <?php endif; ?>
    </td>

    <td>
        <?php if ($r['encontrado']): ?>
            <?= moeda($r['custo_estimado']) ?>
        <?php else: ?>
            -
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>

</table>

</body>
</html>

<?php ob_end_flush(); ?>