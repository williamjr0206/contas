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
                        "text" => "Leia este comprovante ou nota fiscal e devolva SOMENTE um JSON válido (sem ```json) com estes campos: documento_numero, data_lancamento, descricao, tipo, data_vencimento, valor_nominal, data_pagamento, valor_pago, status, forma_de_pagamento_recebimento. Datas no formato YYYY-MM-DD."
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
        <p><strong><?= htmlspecialchars($campo) ?>:</strong>
        <?= htmlspecialchars((string)$valor) ?></p>
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