<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/openai.php';

verificaAcesso();
require __DIR__ . '/../includes/menu.php';

$resultado = null;
$erro = null;
$mensagem_sucesso = null;

/* =====================
   NORMALIZAR DESCRIÇÃO
===================== */
function normalizarDescricaoProduto($texto)
{
    $texto = mb_strtoupper(trim($texto), 'UTF-8');
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
    $texto = preg_replace('/[^A-Z0-9]/', '', $texto);

    if (strpos($texto, 'PAOFRANCES') !== false || strpos($texto, 'FRANCES') !== false) {
        return 'PAOFRANCES';
    }

    if (strpos($texto, 'PAODEQUEIJO') !== false || strpos($texto, 'QUEIJO') !== false) {
        return 'PAODEQUEIJO';
    }

    if (strpos($texto, 'BISCO') !== false) {
        return 'BISCOITAO';
    }

    return trim($texto);
}

/* =====================
   IMPORTAR PRODUTOS
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importar_produtos'])) {

    $json_produtos = $_POST['json_produtos'] ?? '';
    $dados = json_decode($json_produtos, true);

    if (!$dados || empty($dados['produtos'])) {
        $erro = "Nenhum produto encontrado para importar.";
    } else {

        try {
            $pdo->beginTransaction();

            $documento = $dados['documento_numero'] ?? '';
            $data_movimento = $dados['data_lancamento'] ?? date('Y-m-d');

            if ($data_movimento === '') {
                $data_movimento = date('Y-m-d');
            }

            $produtos_processados = [];

            foreach ($dados['produtos'] as $p) {

                $codigo = trim((string)($p['codigo'] ?? ''));
                $fornecedor = trim((string)($p['fornecedor'] ?? ''));
                $descricao = trim((string)($p['descricao'] ?? ''));
                $descricao_normalizada = normalizarDescricaoProduto($descricao);

                $unidade = trim((string)($p['unidade'] ?? 'UN'));
                $quantidade = (float)($p['quantidade'] ?? 0);
                $valor_total = (float)($p['valor_total'] ?? 0);

                $preco = (float)($p['preco_unitario'] ?? 0);

                if ($preco <= 0 && isset($p['preco'])) {
                    $preco = (float)$p['preco'];
                }

                if ($preco <= 0 && $valor_total > 0 && $quantidade > 0) {
                    $preco = $valor_total / $quantidade;
                }

                $preco = round($preco, 4);

                $tipo_movimento = $p['tipo_movimento'] ?? 'Entrada';

                if ($descricao === '') {
                    continue;
                }

                if ($fornecedor === '') {
                    $fornecedor = 'Fornecedor não identificado';
                }

                if ($unidade === '') {
                    $unidade = 'UN';
                }

                if ($quantidade <= 0) {
                    $quantidade = 1;
                }

                if (!in_array($tipo_movimento, ['Entrada', 'Saída', 'Retorno'])) {
                    $tipo_movimento = 'Entrada';
                }

                if ($codigo === '' || $codigo === '2147483647' || strlen($codigo) > 150) {
                    $codigo = strtoupper(substr(md5($fornecedor . $descricao . $unidade), 0, 12));
                }

                $chave = strtoupper($fornecedor . '|' . $descricao_normalizada);

                if (isset($produtos_processados[$chave])) {
                    continue;
                }

                $produtos_processados[$chave] = true;

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM produtos
                    WHERE descricao_normalizada = :descricao_normalizada
                    AND fornecedor = :fornecedor
                    LIMIT 1
                ");

                $stmt->execute([
                    ':descricao_normalizada' => $descricao_normalizada,
                    ':fornecedor' => $fornecedor
                ]);

                $produto = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$produto) {
                    $stmt = $pdo->prepare("
                        SELECT *
                        FROM produtos
                        WHERE codigo = :codigo
                        AND fornecedor = :fornecedor
                        LIMIT 1
                    ");

                    $stmt->execute([
                        ':codigo' => $codigo,
                        ':fornecedor' => $fornecedor
                    ]);

                    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                if ($produto) {

                    $id_produto = (int)$produto['id_produto'];
                    $novo_saldo = (float)$produto['saldo'];

                    if ($tipo_movimento === 'Entrada' || $tipo_movimento === 'Retorno') {
                        $novo_saldo += $quantidade;
                    } elseif ($tipo_movimento === 'Saída') {
                        $novo_saldo -= $quantidade;
                    }

                    $stmtUpdate = $pdo->prepare("
                        UPDATE produtos
                        SET descricao = :descricao,
                            descricao_normalizada = :descricao_normalizada,
                            preco = :preco,
                            unidade = :unidade,
                            saldo = :saldo
                        WHERE id_produto = :id_produto
                    ");

                    $stmtUpdate->execute([
                        ':descricao' => $descricao,
                        ':descricao_normalizada' => $descricao_normalizada,
                        ':preco' => $preco,
                        ':unidade' => $unidade,
                        ':saldo' => $novo_saldo,
                        ':id_produto' => $id_produto
                    ]);

                } else {

                    $saldo_inicial = 0;

                    if ($tipo_movimento === 'Entrada' || $tipo_movimento === 'Retorno') {
                        $saldo_inicial = $quantidade;
                    } elseif ($tipo_movimento === 'Saída') {
                        $saldo_inicial = -$quantidade;
                    }

                    $stmtInsert = $pdo->prepare("
                        INSERT INTO produtos
                        (
                            codigo,
                            fornecedor,
                            descricao,
                            descricao_normalizada,
                            preco,
                            unidade,
                            saldo,
                            cadastrado_em
                        )
                        VALUES
                        (
                            :codigo,
                            :fornecedor,
                            :descricao,
                            :descricao_normalizada,
                            :preco,
                            :unidade,
                            :saldo,
                            :cadastrado_em
                        )
                    ");

                    $stmtInsert->execute([
                        ':codigo' => $codigo,
                        ':fornecedor' => $fornecedor,
                        ':descricao' => $descricao,
                        ':descricao_normalizada' => $descricao_normalizada,
                        ':preco' => $preco,
                        ':unidade' => $unidade,
                        ':saldo' => $saldo_inicial,
                        ':cadastrado_em' => date('Y-m-d')
                    ]);

                    $id_produto = (int)$pdo->lastInsertId();
                }

                $stmtMov = $pdo->prepare("
                    INSERT INTO movimento
                    (
                        data_movimento,
                        documento,
                        id_produto,
                        codigo,
                        quantidade_digitada,
                        quantidade,
                        tipo
                    )
                    VALUES
                    (
                        :data_movimento,
                        :documento,
                        :id_produto,
                        :codigo,
                        :quantidade_digitada,
                        :quantidade,
                        :tipo
                    )
                ");

                $stmtMov->execute([
                    ':data_movimento' => $data_movimento,
                    ':documento' => $documento,
                    ':id_produto' => $id_produto,
                    ':codigo' => $codigo,
                    'quantidade_digitada' =>$quantidade,
                    ':quantidade' => $quantidade,
                    ':tipo' => $tipo_movimento
                ]);
            }

            $pdo->commit();
            $mensagem_sucesso = "Produtos e movimentos importados com sucesso.";

        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = "Erro ao importar produtos: " . $e->getMessage();
        }
    }
}

/* =====================
   LEITURA IA
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['imagem'])) {

    if ($_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
        $erro = "Erro no upload da imagem.";
    } else {

        $tmp = $_FILES['imagem']['tmp_name'];
        $mime = mime_content_type($tmp);
        $base64 = base64_encode(file_get_contents($tmp));

        $payload = [
            "model" => "gpt-4.1",
            "input" => [[
                "role" => "user",
                "content" => [
                    [
                        "type" => "input_text",
                        "text" => "Leia esta nota fiscal, cupom ou comprovante e devolva SOMENTE um JSON válido, sem markdown, sem ```json, com esta estrutura:

{
  \"documento_numero\": \"\",
  \"data_lancamento\": \"\",
  \"descricao\": \"\",
  \"tipo\": \"\",
  \"data_vencimento\": \"\",
  \"valor_nominal\": 0,
  \"data_pagamento\": \"\",
  \"valor_pago\": 0,
  \"status\": \"\",
  \"forma_de_pagamento_recebimento\": \"\",
  \"produtos\": [
    {
      \"codigo\": \"\",
      \"fornecedor\": \"\",
      \"descricao\": \"\",
      \"preco_unitario\": 0,
      \"valor_total\": 0,
      \"unidade\": \"\",
      \"quantidade\": 0,
      \"tipo_movimento\": \"Entrada\"
    }
  ]
}

Regras:
- Use datas no formato YYYY-MM-DD.
- Se a data estiver ilegível ou duvidosa, deixe data_lancamento vazio.
- Nunca invente ano.
- Se não tiver certeza da data, deixe vazio.
- fornecedor deve ser o nome do estabelecimento emitente.
- descricao geral deve resumir a nota, exemplo: Compra Padaria Macaúbas.
- tipo deve ser Pagar.
- status deve ser Pago quando houver pagamento na nota.
- forma_de_pagamento_recebimento deve ser Pix, Dinheiro, Cartão Débito, Cartão Crédito ou outra forma encontrada.
- valor_nominal deve ser o total da nota.
- valor_pago deve ser o valor pago.
- data_pagamento deve ser a data da nota quando estiver pago.
- data_vencimento pode ser igual à data da nota quando estiver pago.
- produtos deve conter os itens identificados na nota.
- codigo deve ser o código do produto somente se estiver claro.
- se o código do produto não estiver claro, deixe codigo vazio.
- não invente código de produto.
- unidade deve ser UN, KG, LT, CX, PC, G, ML ou outra unidade encontrada.
- quantidade deve ser a quantidade comprada.
- valor_total deve ser o valor total daquele item na nota.
- preco_unitario deve ser o preço unitário somente se estiver claramente impresso na nota.
- Se a nota apresentar somente o valor total do item, deixe preco_unitario igual a 0.
- Nunca use o valor total como se fosse preço unitário.
- tipo_movimento deve ser Entrada.
- não repita produtos iguais.
- cada item da nota deve aparecer apenas uma vez."
                    ],
                    [
                        "type" => "input_image",
                        "image_url" => "data:$mime;base64,$base64"
                    ]
                ]
            ]]
        ];

        $ch = curl_init("https://api.openai.com/v1/responses");

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . OPENAI_API_KEY,
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);

        $resposta = curl_exec($ch);

        if (curl_errno($ch)) {
            $erro = "Erro cURL: " . curl_error($ch);
        } else {

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $json = json_decode($resposta, true);

            if ($httpCode !== 200) {
                $erro = "Erro da API: " . htmlspecialchars($resposta);
            } else {

                $texto = $json['output'][0]['content'][0]['text'] ?? '';

                $texto = trim($texto);
                $texto = preg_replace('/^```json\s*/i', '', $texto);
                $texto = preg_replace('/^```\s*/', '', $texto);
                $texto = preg_replace('/\s*```$/', '', $texto);

                $resultado = json_decode($texto, true);

                if (!$resultado) {
                    $erro = "A IA respondeu mas não consegui converter o JSON.<br><br>" . htmlspecialchars($texto);
                }
            }
        }

        curl_close($ch);
    }
}

/* =====================
   QUERY PARA LANÇAMENTOS
===================== */
$query = '';

if ($resultado) {
    $query = http_build_query([
        'documento_numero' => $resultado['documento_numero'] ?? '',
        'data_lancamento' => $resultado['data_lancamento'] ?? '',
        'descricao' => $resultado['descricao'] ?? '',
        'tipo' => $resultado['tipo'] ?? '',
        'data_vencimento' => $resultado['data_vencimento'] ?? '',
        'valor_nominal' => $resultado['valor_nominal'] ?? '',
        'data_pagamento' => $resultado['data_pagamento'] ?? '',
        'valor_pago' => $resultado['valor_pago'] ?? '',
        'status' => $resultado['status'] ?? '',
        'forma_de_pagamento_recebimento' => $resultado['forma_de_pagamento_recebimento'] ?? ''
    ]);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Importar Nota com IA</title>

<style>
body {
    font-family: Arial;
    margin: 20px;
}

input,
button {
    padding: 8px;
    margin: 8px 0;
    display: block;
}

.caixa {
    border: 1px solid #ccc;
    padding: 15px;
    margin-top: 20px;
    max-width: 1050px;
}

.erro {
    color: red;
    font-weight: bold;
}

.sucesso {
    color: green;
    font-weight: bold;
}

pre {
    background: #f4f4f4;
    padding: 10px;
    overflow-x: auto;
}

table {
    border-collapse: collapse;
    width: 100%;
    margin-top: 10px;
}

th,
td {
    padding: 6px;
}
</style>
</head>

<body>

<h2>Importar Nota / Comprovante com IA</h2>

<form method="post" enctype="multipart/form-data">
    <label>Selecione a imagem</label>
    <input type="file" name="imagem" accept="image/*" required>
    <button type="submit">Ler com IA</button>
</form>

<?php if ($erro): ?>
    <div class="erro"><?= $erro ?></div>
<?php endif; ?>

<?php if ($mensagem_sucesso): ?>
    <div class="sucesso"><?= $mensagem_sucesso ?></div>
<?php endif; ?>

<?php if ($resultado): ?>

<div class="caixa">

<h3>Dados da nota encontrados pela IA</h3>

<table border="1">
<tr>
    <th>Campo</th>
    <th>Valor</th>
</tr>

<?php foreach ($resultado as $campo => $valor): ?>
    <?php if ($campo !== 'produtos'): ?>
        <tr>
            <td><?= htmlspecialchars($campo) ?></td>
            <td><?= htmlspecialchars(is_array($valor) ? json_encode($valor, JSON_UNESCAPED_UNICODE) : (string)$valor) ?></td>
        </tr>
    <?php endif; ?>
<?php endforeach; ?>

</table>

<h3>Produtos encontrados</h3>

<table border="1">
<tr>
    <th>Código</th>
    <th>Fornecedor</th>
    <th>Descrição</th>
    <th>Preço Unitário</th>
    <th>Valor Total</th>
    <th>UN</th>
    <th>Qtd</th>
    <th>Movimento</th>
</tr>

<?php foreach (($resultado['produtos'] ?? []) as $produto): ?>
<tr>
    <td><?= htmlspecialchars($produto['codigo'] ?? '') ?></td>
    <td><?= htmlspecialchars($produto['fornecedor'] ?? '') ?></td>
    <td><?= htmlspecialchars($produto['descricao'] ?? '') ?></td>
    <td><?= htmlspecialchars((string)($produto['preco_unitario'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($produto['valor_total'] ?? '')) ?></td>
    <td><?= htmlspecialchars($produto['unidade'] ?? '') ?></td>
    <td><?= htmlspecialchars((string)($produto['quantidade'] ?? '')) ?></td>
    <td><?= htmlspecialchars($produto['tipo_movimento'] ?? '') ?></td>
</tr>
<?php endforeach; ?>

</table>

<form method="post">
    <input
        type="hidden"
        name="json_produtos"
        value="<?= htmlspecialchars(json_encode($resultado, JSON_UNESCAPED_UNICODE)) ?>"
    >

    <button type="submit" name="importar_produtos" value="1">
        Importar Produtos para Estoque
    </button>
</form>

<br>

<a href="<?= BASE_URL ?>cadastros/lancamentos.php?<?= $query ?>">
    <button type="button">
        Preencher Lançamento Automaticamente
    </button>
</a>

<h3>JSON bruto</h3>

<pre><?= htmlspecialchars(json_encode(
    $resultado,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
)) ?></pre>

</div>

<?php endif; ?>

</body>
</html>
