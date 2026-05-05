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

            $produtos_processados = [];

            foreach ($dados['produtos'] as $p) {

                $codigo = trim((string)($p['codigo'] ?? ''));
                $fornecedor = trim((string)($p['fornecedor'] ?? ''));
                $descricao = trim((string)($p['descricao'] ?? ''));
                $preco = round((float)($p['preco'] ?? 0), 2);
                $unidade = trim((string)($p['unidade'] ?? 'UN'));
                $quantidade = (float)($p['quantidade'] ?? 0);
                $tipo_movimento = $p['tipo_movimento'] ?? 'Entrada';

                if ($descricao === '') {
                    continue;
                }

                if ($fornecedor === '') {
                    $fornecedor = 'Fornecedor não identificado';
                }

                // Evita duplicar o mesmo produto dentro da mesma nota
                $chave = strtoupper($fornecedor . '|' . $descricao . '|' . $unidade);

                if (isset($produtos_processados[$chave])) {
                    continue;
                }

                $produtos_processados[$chave] = true;

                // Corrige código vazio ou código inválido gerado pela IA
                if (
                    $codigo === '' ||
                    $codigo === '2147483647' ||
                    strlen($codigo) > 150
                ) {
                    $codigo = strtoupper(substr(md5($fornecedor . $descricao . $unidade), 0, 12));
                }

                if ($quantidade <= 0) {
                    $quantidade = 1;
                }

                // Procura produto pelo código + fornecedor
                $stmt = $pdo->prepare("
                    SELECT * FROM produtos 
                    WHERE codigo = :codigo 
                    AND fornecedor = :fornecedor 
                    LIMIT 1
                ");

                $stmt->execute([
                    ':codigo' => $codigo,
                    ':fornecedor' => $fornecedor
                ]);

                $produto = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($produto) {

                    $novo_saldo = (float)$produto['saldo'];

                    if ($tipo_movimento === 'Entrada' || $tipo_movimento === 'Retorno') {
                        $novo_saldo += $quantidade;
                    } elseif ($tipo_movimento === 'Saída') {
                        $novo_saldo -= $quantidade;
                    }

                    $stmtUpdate = $pdo->prepare("
                        UPDATE produtos 
                        SET descricao = :descricao,
                            preco = :preco,
                            unidade = :unidade,
                            saldo = :saldo
                        WHERE id_produto = :id_produto
                    ");

                    $stmtUpdate->execute([
                        ':descricao' => $descricao,
                        ':preco' => $preco,
                        ':unidade' => $unidade,
                        ':saldo' => $novo_saldo,
                        ':id_produto' => $produto['id_produto']
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
                            (codigo, fornecedor, descricao, preco, unidade, saldo, cadastrado_em)
                        VALUES
                            (:codigo, :fornecedor, :descricao, :preco, :unidade, :saldo, :cadastrado_em)
                    ");

                    $stmtInsert->execute([
                        ':codigo' => $codigo,
                        ':fornecedor' => $fornecedor,
                        ':descricao' => $descricao,
                        ':preco' => $preco,
                        ':unidade' => $unidade,
                        ':saldo' => $saldo_inicial,
                        ':cadastrado_em' => date('Y-m-d')
                    ]);
                }

                // Grava movimento depois de garantir que o produto existe
                $stmtMov = $pdo->prepare("
                    INSERT INTO movimento 
                        (data_movimento, documento, codigo, quantidade, tipo)
                    VALUES
                        (:data_movimento, :documento, :codigo, :quantidade, :tipo)
                ");

                $stmtMov->execute([
                    ':data_movimento' => $data_movimento,
                    ':documento' => $documento,
                    ':codigo' => $codigo,
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
                        "text" => "Leia esta nota fiscal ou comprovante e devolva SOMENTE um JSON válido, sem markdown, sem ```json, com esta estrutura:

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
      \"preco\": 0,
      \"unidade\": \"\",
      \"quantidade\": 0,
      \"tipo_movimento\": \"Entrada\"
    }
  ]
}

Regras:
- Use datas no formato YYYY-MM-DD.
- Para compras pagas, use tipo=Pagar e status=Pago.
- fornecedor deve ser o nome do estabelecimento emitente da nota.
- produtos deve conter os itens identificados na nota.
- codigo deve ser o código do produto, se aparecer.
- unidade deve ser UN, KG, LT, CX, PC ou outra unidade encontrada.
- quantidade deve ser a quantidade comprada.
- preco deve ser o preço unitário quando possível; se só existir total do item, use esse valor.
- tipo_movimento deve ser Entrada.
- Se não souber algum campo, deixe vazio ou zero.
- Nunca use o mesmo código para produtos diferentes.
- Se o código do produto não estiver claro, deixe codigo vazio.
- Não invente código numérico grande.
- Não repita produtos iguais.
- Cada item da nota deve aparecer apenas uma vez."
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

                // 🔥 CORREÇÃO DO PROBLEMA DO ```json
                $texto = trim($texto);
                $texto = preg_replace('/^```json\s*/i', '', $texto);
                $texto = preg_replace('/^```\s*/', '', $texto);
                $texto = preg_replace('/\s*```$/', '', $texto);

                $resultado = json_decode($texto, true);

                if (!$resultado) {
                    $erro = "A IA respondeu, mas não consegui converter para JSON.<br><br><b>Resposta:</b><br>" . htmlspecialchars($texto);
                }
            }
        }

        curl_close($ch);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Importar Nota com IA</title>

<style>
body { font-family: Arial; margin: 20px; }
input, button { padding: 8px; margin: 8px 0; display: block; }
.caixa { border: 1px solid #ccc; padding: 15px; margin-top: 20px; max-width: 700px; }
.erro { color: red; font-weight: bold; }
pre { background: #f4f4f4; padding: 10px; }
button { cursor: pointer; }
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
<?php if (!empty($mensagem_sucesso)): ?>
    <div style="color: green; font-weight: bold; margin-top:10px;">
        <?= $mensagem_sucesso ?>
    </div>
<?php endif; ?>

<?php if ($resultado): ?>

<?php
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
?>

<div class="caixa">
    <h3>Dados sugeridos pela IA</h3>

<?php foreach ($resultado as $campo => $valor): ?>

    <?php if ($campo === 'produtos' && is_array($valor)): ?>

        <h3>Produtos encontrados</h3>

        <table border="1" cellpadding="6" cellspacing="0">
            <tr>
                <th>Código</th>
                <th>Fornecedor</th>
                <th>Descrição</th>
                <th>Preço</th>
                <th>Unidade</th>
                <th>Quantidade</th>
                <th>Movimento</th>
            </tr>

            <?php foreach ($valor as $produto): ?>
                <tr>
                    <td><?= htmlspecialchars($produto['codigo'] ?? '') ?></td>
                    <td><?= htmlspecialchars($produto['fornecedor'] ?? '') ?></td>
                    <td><?= htmlspecialchars($produto['descricao'] ?? '') ?></td>
                    <td><?= htmlspecialchars((string)($produto['preco'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($produto['unidade'] ?? '') ?></td>
                    <td><?= htmlspecialchars((string)($produto['quantidade'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($produto['tipo_movimento'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        </table>

<form method="post">
    <input type="hidden" name="json_produtos"
    value="<?= htmlspecialchars(json_encode($resultado, JSON_UNESCAPED_UNICODE)) ?>">

    <button type="submit" name="importar_produtos" value="1">
        Importar Produtos para Estoque
    </button>
</form>

    <?php else: ?>

        <p>
            <strong><?= htmlspecialchars($campo) ?>:</strong>
            <?= htmlspecialchars(is_array($valor) ? json_encode($valor, JSON_UNESCAPED_UNICODE) : (string)$valor) ?>
        </p>

    <?php endif; ?>

<?php endforeach; ?>
    <br>
    

    <a href="<?= BASE_URL ?>cadastros/lancamentos.php?<?= $query ?>">
        <button type="button">Preencher Lançamento Automaticamente</button>
    </a>

    <h3>JSON bruto</h3>
    <pre><?= htmlspecialchars(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
</div>

<?php endif; ?>

</body>
</html>