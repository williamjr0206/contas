<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/openai.php';
verificaAcesso();

require __DIR__ . '/../includes/menu.php';

$erro = "";
$resultado = "";
$pessoaSelecionada = null;

function classificarIMC($imc) {
    if ($imc < 18.5) return "Abaixo do peso";
    if ($imc < 25) return "Peso adequado";
    if ($imc < 30) return "Sobrepeso";
    if ($imc < 35) return "Obesidade grau I";
    if ($imc < 40) return "Obesidade grau II";
    return "Obesidade grau III";
}

function montarOrientacoesClinicas($pessoa) {
    $orientacoes = "";

    if (($pessoa['diabetico'] ?? 'Não') === 'Sim') {
        $orientacoes .= "
- A pessoa é diabética.
- Evitar açúcar refinado, doces, refrigerantes e sucos adoçados.
- Evitar excesso de carboidratos simples.
- Preferir alimentos ricos em fibras.
- Distribuir carboidratos ao longo do dia.
- Não recomendar jejum prolongado.
- Informar que o plano deve ser validado por médico ou nutricionista.
";
    }

    if (($pessoa['hipertenso'] ?? 'Não') === 'Sim') {
        $orientacoes .= "
- A pessoa é hipertensa.
- Reduzir alimentos ricos em sódio.
- Evitar embutidos, temperos prontos, macarrão instantâneo, salgadinhos e ultraprocessados.
- Priorizar temperos naturais como alho, cebola, cheiro-verde, limão e ervas.
";
    }

    if (($pessoa['colesterol_alto'] ?? 'Não') === 'Sim') {
        $orientacoes .= "
- A pessoa possui colesterol alto.
- Evitar excesso de frituras, carnes gordurosas, embutidos e gordura saturada.
- Priorizar feijão, aveia, frutas, verduras, legumes, peixes, frango, ovos com moderação e azeite em pequena quantidade.
";
    }

    if (($pessoa['intolerancia_lactose'] ?? 'Não') === 'Sim') {
        $orientacoes .= "
- A pessoa possui intolerância à lactose.
- Evitar leite comum e derivados com lactose.
- Sugerir alternativas sem lactose quando necessário.
- Não recomendar suplementos.
";
    }

    if (($pessoa['vegetariano'] ?? 'Não') === 'Sim') {
        $orientacoes .= "
- A pessoa é vegetariana.
- Não incluir carnes, frango ou peixe.
- Usar fontes vegetais de proteína como feijão, lentilha, grão-de-bico, ervilha, soja, tofu, ovos e leite/derivados apenas se compatíveis com a intolerância à lactose.
";
    }

    if (trim($orientacoes) === "") {
        $orientacoes = "
- A pessoa não possui restrições clínicas ou alimentares cadastradas.
";
    }

    return $orientacoes;
}

function chamarIA($prompt) {

    $apiKey = OPENAI_API_KEY;

    if (empty($apiKey)) {
        return "Erro: OPENAI_API_KEY não configurada em config/openai.php.";
    }

    $dados = [
        "model" => "gpt-4.1-mini",
        "input" => $prompt
    ];

    $ch = curl_init("https://api.openai.com/v1/responses");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer " . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode($dados),
        CURLOPT_TIMEOUT => 90
    ]);

    $resposta = curl_exec($ch);

    if (curl_errno($ch)) {
        return "Erro cURL: " . curl_error($ch);
    }

    curl_close($ch);

    $json = json_decode($resposta, true);

if (isset($json['output_text'])) {
    return $json['output_text'];
}

if (isset($json['output'][0]['content'][0]['text'])) {
    return $json['output'][0]['content'][0]['text'];
}

if (isset($json['output']) && is_array($json['output'])) {
    foreach ($json['output'] as $item) {
        if (isset($item['content']) && is_array($item['content'])) {
            foreach ($item['content'] as $content) {
                if (isset($content['text'])) {
                    return $content['text'];
                }
            }
        }
    }
}

return "Erro ao interpretar resposta da IA:<br><pre>" . htmlspecialchars($resposta) . "</pre>";}

/* =====================
   BUSCAR PESSOAS
===================== */
$stmt = $pdo->query("
    SELECT 
        id_pessoa, 
        nome, 
        cidade, 
        estado, 
        altura, 
        peso,
        diabetico,
        hipertenso,
        colesterol_alto,
        intolerancia_lactose,
        vegetariano
    FROM pessoas
    ORDER BY nome
");

$pessoas = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   GERAR SUGESTÃO
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_pessoa = $_POST['id_pessoa'] ?? '';
    $periodo = $_POST['periodo'] ?? '';

    if (!$id_pessoa || !$periodo) {
        $erro = "Selecione a pessoa e o período.";
    } else {

        $stmt = $pdo->prepare("
            SELECT 
                id_pessoa,
                nome,
                data_nascimento,
                cidade,
                estado,
                altura,
                peso,
                diabetico,
                hipertenso,
                colesterol_alto,
                intolerancia_lactose,
                vegetariano
            FROM pessoas
            WHERE id_pessoa = ?
        ");

        $stmt->execute([$id_pessoa]);
        $pessoaSelecionada = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pessoaSelecionada) {
            $erro = "Pessoa não encontrada.";
        } else {

            $altura = (float)$pessoaSelecionada['altura'];
            $peso = (float)$pessoaSelecionada['peso'];

            if ($altura <= 0 || $peso <= 0) {
                $erro = "Altura e peso precisam estar cadastrados corretamente.";
            } else {

                $imc = $peso / ($altura * $altura);
                $classificacao = classificarIMC($imc);

                $nome = $pessoaSelecionada['nome'];
                $cidade = $pessoaSelecionada['cidade'];
                $estado = $pessoaSelecionada['estado'];

                $orientacoesClinicas = montarOrientacoesClinicas($pessoaSelecionada);

                $prompt = "
Você é um assistente de apoio alimentar educativo.

Monte uma sugestão de cardápio para o período: {$periodo}.

Dados da pessoa:
Nome: {$nome}
Cidade: {$cidade}
Estado: {$estado}
Peso: {$peso} kg
Altura: {$altura} m
IMC: " . number_format($imc, 2, ',', '.') . "
Classificação do IMC: {$classificacao}

Condições e preferências cadastradas:
Diabético: {$pessoaSelecionada['diabetico']}
Hipertenso: {$pessoaSelecionada['hipertenso']}
Colesterol alto: {$pessoaSelecionada['colesterol_alto']}
Intolerância à lactose: {$pessoaSelecionada['intolerancia_lactose']}
Vegetariano: {$pessoaSelecionada['vegetariano']}

Orientações específicas:
{$orientacoesClinicas}

Critérios obrigatórios:
1. Seguir os princípios do Guia Alimentar para a População Brasileira, do Ministério da Saúde.
2. Priorizar alimentos in natura ou minimamente processados.
3. Evitar alimentos ultraprocessados.
4. Considerar alimentos comuns e acessíveis na região de {$cidade}/{$estado}.
5. Usar alimentos comuns no Brasil, como arroz, feijão, verduras, legumes, frutas, ovos, mandioca, batata, aveia e alimentos regionais quando apropriado.
6. Adaptar o cardápio às condições cadastradas da pessoa.
7. Não prescrever dieta médica.
8. Não indicar restrição calórica extrema.
9. Não indicar suplementos, remédios ou produtos emagrecedores.
10. Não prometer emagrecimento, cura ou tratamento.
11. Organizar a resposta em formato claro, com refeições e observações.
12. Incluir observação final dizendo que pessoas com diabetes, hipertensão, colesterol alto, doença renal, câncer, gravidez, alergias ou uso de medicamentos devem consultar médico ou nutricionista.

Gere a resposta em português do Brasil.
";

                $resultado = chamarIA($prompt);

                $pessoaSelecionada['imc'] = $imc;
                $pessoaSelecionada['classificacao'] = $classificacao;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Sugestão Juca - IMC e Cardápio</title>

<style>
body {
    font-family: Arial;
    margin: 20px;
}

form {
    margin-bottom: 30px;
}

input, select, textarea {
    margin: 6px 0;
    padding: 6px;
    width: 360px;
    display: block;
}

button {
    padding: 8px 14px;
    cursor: pointer;
    margin-top: 10px;
}

table {
    border-collapse: collapse;
    width: 100%;
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

a {
    margin-right: 10px;
}

.caixa {
    border: 1px solid #ccc;
    padding: 15px;
    margin-top: 20px;
    background: #f9f9f9;
}

.erro {
    color: red;
    font-weight: bold;
}

.resultado {
    white-space: pre-wrap;
    line-height: 1.5;
}
</style>
</head>

<body>

<h2>Sugestão Juca - IMC e Cardápio com IA</h2>

<form method="post">

    <label>Pessoa:</label>
    <select name="id_pessoa" required>
        <option value="">Selecione...</option>

        <?php foreach ($pessoas as $p): ?>
            <option value="<?= $p['id_pessoa'] ?>"
                <?= (isset($_POST['id_pessoa']) && $_POST['id_pessoa'] == $p['id_pessoa']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['nome']) ?> - 
                <?= htmlspecialchars($p['cidade']) ?>/<?= htmlspecialchars($p['estado']) ?>
            </option>
        <?php endforeach; ?>

    </select>

    <label>Período do cardápio:</label>
    <select name="periodo" required>
        <option value="">Selecione...</option>
        <option value="uma refeição" <?= (($_POST['periodo'] ?? '') === 'uma refeição') ? 'selected' : '' ?>>Uma refeição</option>
        <option value="um dia" <?= (($_POST['periodo'] ?? '') === 'um dia') ? 'selected' : '' ?>>Um dia</option>
        <option value="uma semana" <?= (($_POST['periodo'] ?? '') === 'uma semana') ? 'selected' : '' ?>>Uma semana</option>
    </select>

    <button type="submit">Gerar sugestão</button>

</form>

<?php if ($erro): ?>
    <p class="erro"><?= htmlspecialchars($erro) ?></p>
<?php endif; ?>

<?php if ($pessoaSelecionada && !$erro): ?>
    <div class="caixa">
        <h3>Dados calculados</h3>

        <table>
            <tr>
                <th>Nome</th>
                <td><?= htmlspecialchars($pessoaSelecionada['nome']) ?></td>
            </tr>
            <tr>
                <th>Cidade/Estado</th>
                <td><?= htmlspecialchars($pessoaSelecionada['cidade']) ?>/<?= htmlspecialchars($pessoaSelecionada['estado']) ?></td>
            </tr>
            <tr>
                <th>Peso</th>
                <td><?= number_format($pessoaSelecionada['peso'], 2, ',', '.') ?> kg</td>
            </tr>
            <tr>
                <th>Altura</th>
                <td><?= number_format($pessoaSelecionada['altura'], 2, ',', '.') ?> m</td>
            </tr>
            <tr>
                <th>IMC</th>
                <td><?= number_format($pessoaSelecionada['imc'], 2, ',', '.') ?></td>
            </tr>
            <tr>
                <th>Classificação</th>
                <td><?= htmlspecialchars($pessoaSelecionada['classificacao']) ?></td>
            </tr>
            <tr>
                <th>Diabético</th>
                <td><?= htmlspecialchars($pessoaSelecionada['diabetico']) ?></td>
            </tr>
            <tr>
                <th>Hipertenso</th>
                <td><?= htmlspecialchars($pessoaSelecionada['hipertenso']) ?></td>
            </tr>
            <tr>
                <th>Colesterol alto</th>
                <td><?= htmlspecialchars($pessoaSelecionada['colesterol_alto']) ?></td>
            </tr>
            <tr>
                <th>Intolerância à lactose</th>
                <td><?= htmlspecialchars($pessoaSelecionada['intolerancia_lactose']) ?></td>
            </tr>
            <tr>
                <th>Vegetariano</th>
                <td><?= htmlspecialchars($pessoaSelecionada['vegetariano']) ?></td>
            </tr>
        </table>
    </div>
<?php endif; ?>

<?php if ($resultado): ?>
    <div class="caixa">
        <h3>Cardápio sugerido pela IA</h3>
        <div class="resultado"><?= nl2br(htmlspecialchars($resultado)) ?></div>
    </div>
<?php endif; ?>

</body>
</html>

<?php ob_end_flush(); ?>