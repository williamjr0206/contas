<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
verificaAcesso();

require __DIR__ . '/../includes/menu.php';

$openaiPath = __DIR__ . '/../config/openai.php';
if (file_exists($openaiPath)) {
    require_once $openaiPath;
}

function moeda($valor) {
    return number_format((float)$valor, 2, ',', '.');
}

function salvarFotoReceita($campoArquivo) {
    if (!isset($_FILES[$campoArquivo]) || $_FILES[$campoArquivo]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $pasta = __DIR__ . '/../uploads/receitas/';

    if (!is_dir($pasta)) {
        mkdir($pasta, 0777, true);
    }

    $extensao = strtolower(pathinfo($_FILES[$campoArquivo]['name'], PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($extensao, $permitidas)) {
        return null;
    }

    $nomeArquivo = 'receita_' . date('YmdHis') . '_' . rand(1000, 9999) . '.' . $extensao;
    $destino = $pasta . $nomeArquivo;

    if (move_uploaded_file($_FILES[$campoArquivo]['tmp_name'], $destino)) {
        return 'uploads/receitas/' . $nomeArquivo;
    }

    return null;
}

function chamarOpenAIReceita($textoReceita) {
    if (!defined('OPENAI_API_KEY') || OPENAI_API_KEY === '') {
        throw new Exception('Chave da OpenAI não encontrada em config/openai.php');
    }

    $prompt = "
Você é um assistente especializado em organizar receitas culinárias.

Leia o texto abaixo e extraia os dados em JSON puro.

Categorias permitidas:
Refeições, Sobremesas, Petiscos, Bebidas, Massas, Saladas, Chás e receitas caseiras.

Retorne exatamente este formato:
{
  \"titulo\": \"\",
  \"categoria\": \"\",
  \"descricao\": \"\",
  \"tempo_preparo_minutos\": 0,
  \"rendimento\": \"\",
  \"dificuldade\": \"Fácil\",
  \"modo_preparo\": \"\",
  \"ingredientes\": [
    {
      \"descricao\": \"\",
      \"quantidade\": 0,
      \"unidade\": \"\",
      \"observacao\": \"\"
    }
  ],
  \"etapas\": [
    {
      \"sequencia\": 1,
      \"descricao\": \"\",
      \"tempo_estimado_minutos\": 0,
      \"observacao\": \"\"
    }
  ]
}

Texto da receita:
" . $textoReceita;

    $data = [
        "model" => "gpt-4.1-mini",
        "input" => $prompt
    ];

    $ch = curl_init("https://api.openai.com/v1/responses");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer " . OPENAI_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }

    curl_close($ch);

    $json = json_decode($response, true);
    $texto = $json['output'][0]['content'][0]['text'] ?? '';

    $texto = trim($texto);
    $texto = preg_replace('/^```json/i', '', $texto);
    $texto = preg_replace('/```$/', '', $texto);
    $texto = trim($texto);

    $dados = json_decode($texto, true);

    if (!$dados) {
        throw new Exception('A IA não retornou um JSON válido.');
    }

    return $dados;
}

$mensagem = '';

/* =====================
   IMPORTAR COM IA
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'importar_ia') {
    try {
        $textoReceita = trim($_POST['texto_receita'] ?? '');
        $fonte_site = trim($_POST['fonte_site_ia'] ?? '');

        if ($textoReceita === '') {
            throw new Exception('Cole o texto da receita antes de importar.');
        }

        $dados = chamarOpenAIReceita($textoReceita);

        $categoriaNome = $dados['categoria'] ?? 'Refeições';

        $stmt = $pdo->prepare("SELECT id_categoria FROM receitas_categorias WHERE nome_categoria = :nome LIMIT 1");
        $stmt->execute([':nome' => $categoriaNome]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cat) {
            $stmt = $pdo->prepare("INSERT INTO receitas_categorias (nome_categoria, descricao) VALUES (:nome, 'Categoria criada automaticamente pela IA')");
            $stmt->execute([':nome' => $categoriaNome]);
            $id_categoria = $pdo->lastInsertId();
        } else {
            $id_categoria = $cat['id_categoria'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO receitas
            (id_categoria, titulo, descricao, fonte_site, tempo_preparo_minutos, rendimento, dificuldade, modo_preparo, observacoes, favorita)
            VALUES
            (:id_categoria, :titulo, :descricao, :fonte_site, :tempo, :rendimento, :dificuldade, :modo_preparo, :observacoes, 'Não')
        ");

        $stmt->execute([
            ':id_categoria' => $id_categoria,
            ':titulo' => $dados['titulo'] ?? 'Receita importada',
            ':descricao' => $dados['descricao'] ?? '',
            ':fonte_site' => $fonte_site,
            ':tempo' => $dados['tempo_preparo_minutos'] ?? null,
            ':rendimento' => $dados['rendimento'] ?? '',
            ':dificuldade' => $dados['dificuldade'] ?? 'Fácil',
            ':modo_preparo' => $dados['modo_preparo'] ?? '',
            ':observacoes' => 'Receita importada com IA.'
        ]);

        $id_receita = $pdo->lastInsertId();

        foreach (($dados['ingredientes'] ?? []) as $ing) {
            $descricaoIng = $ing['descricao'] ?? '';

            if ($descricaoIng !== '') {
                $stmt = $pdo->prepare("
                    INSERT INTO receitas_ingredientes
                    (id_receita, descricao_ingrediente, quantidade, unidade, custo_unitario, custo_total, observacao)
                    VALUES
                    (:id_receita, :descricao, :quantidade, :unidade, 0, 0, :obs)
                ");

                $stmt->execute([
                    ':id_receita' => $id_receita,
                    ':descricao' => $descricaoIng,
                    ':quantidade' => $ing['quantidade'] ?? 0,
                    ':unidade' => $ing['unidade'] ?? '',
                    ':obs' => $ing['observacao'] ?? ''
                ]);
            }
        }

        foreach (($dados['etapas'] ?? []) as $etapa) {
            $desc = $etapa['descricao'] ?? '';

            if ($desc !== '') {
                $stmt = $pdo->prepare("
                    INSERT INTO receitas_processos
                    (id_receita, sequencia, descricao_etapa, tempo_estimado_minutos, observacao)
                    VALUES
                    (:id_receita, :seq, :desc, :tempo, :obs)
                ");

                $stmt->execute([
                    ':id_receita' => $id_receita,
                    ':seq' => $etapa['sequencia'] ?? 1,
                    ':desc' => $desc,
                    ':tempo' => $etapa['tempo_estimado_minutos'] ?? null,
                    ':obs' => $etapa['observacao'] ?? ''
                ]);
            }
        }

        header("Location: " . BASE_URL . "cadastros/receitas.php?edit=" . $id_receita);
        exit;

    } catch (Exception $e) {
        $mensagem = "Erro na importação com IA: " . $e->getMessage();
    }
}

/* =====================
   SALVAR RECEITA
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_receita') {
    $id_receita = $_POST['id_receita'] ?? null;
    $foto_receita = salvarFotoReceita('foto_receita');

    if ($id_receita) {
        if ($foto_receita) {
            $sqlFoto = ", foto_receita = :foto_receita";
        } else {
            $sqlFoto = "";
        }

        $sql = "UPDATE receitas SET
                id_categoria=:id_categoria,
                titulo=:titulo,
                descricao=:descricao,
                fonte_site=:fonte_site,
                tempo_preparo_minutos=:tempo,
                rendimento=:rendimento,
                dificuldade=:dificuldade,
                modo_preparo=:modo_preparo,
                observacoes=:observacoes,
                favorita=:favorita,
                data_atualizacao=NOW()
                $sqlFoto
                WHERE id_receita=:id_receita";

        $dados = [
            ':id_categoria' => $_POST['id_categoria'],
            ':titulo' => $_POST['titulo'],
            ':descricao' => $_POST['descricao'] ?? '',
            ':fonte_site' => $_POST['fonte_site'] ?? '',
            ':tempo' => $_POST['tempo_preparo_minutos'] !== '' ? $_POST['tempo_preparo_minutos'] : null,
            ':rendimento' => $_POST['rendimento'] ?? '',
            ':dificuldade' => $_POST['dificuldade'] ?? 'Fácil',
            ':modo_preparo' => $_POST['modo_preparo'] ?? '',
            ':observacoes' => $_POST['observacoes'] ?? '',
            ':favorita' => $_POST['favorita'] ?? 'Não',
            ':id_receita' => $id_receita
        ];

        if ($foto_receita) {
            $dados[':foto_receita'] = $foto_receita;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($dados);

        header("Location: " . BASE_URL . "cadastros/receitas.php?edit=" . $id_receita);
        exit;

    } else {
        $stmt = $pdo->prepare("
            INSERT INTO receitas
            (id_categoria, titulo, descricao, fonte_site, tempo_preparo_minutos, rendimento, dificuldade, modo_preparo, observacoes, favorita, foto_receita)
            VALUES
            (:id_categoria, :titulo, :descricao, :fonte_site, :tempo, :rendimento, :dificuldade, :modo_preparo, :observacoes, :favorita, :foto_receita)
        ");

        $stmt->execute([
            ':id_categoria' => $_POST['id_categoria'],
            ':titulo' => $_POST['titulo'],
            ':descricao' => $_POST['descricao'] ?? '',
            ':fonte_site' => $_POST['fonte_site'] ?? '',
            ':tempo' => $_POST['tempo_preparo_minutos'] !== '' ? $_POST['tempo_preparo_minutos'] : null,
            ':rendimento' => $_POST['rendimento'] ?? '',
            ':dificuldade' => $_POST['dificuldade'] ?? 'Fácil',
            ':modo_preparo' => $_POST['modo_preparo'] ?? '',
            ':observacoes' => $_POST['observacoes'] ?? '',
            ':favorita' => $_POST['favorita'] ?? 'Não',
            ':foto_receita' => $foto_receita
        ]);

        header("Location: " . BASE_URL . "cadastros/receitas.php");
        exit;
    }
}

/* =====================
   INGREDIENTE
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'adicionar_ingrediente') {
    $id_receita = $_POST['id_receita'];
    $quantidade = $_POST['quantidade'] ?? 0;
    $custo_unitario = $_POST['custo_unitario'] ?? 0;
    $custo_total = (float)$quantidade * (float)$custo_unitario;

    $stmt = $pdo->prepare("
        INSERT INTO receitas_ingredientes
        (id_receita, descricao_ingrediente, quantidade, unidade, custo_unitario, custo_total, observacao)
        VALUES
        (:id_receita, :descricao, :quantidade, :unidade, :custo_unitario, :custo_total, :obs)
    ");

    $stmt->execute([
        ':id_receita' => $id_receita,
        ':descricao' => $_POST['descricao_ingrediente'],
        ':quantidade' => $quantidade,
        ':unidade' => $_POST['unidade'] ?? '',
        ':custo_unitario' => $custo_unitario,
        ':custo_total' => $custo_total,
        ':obs' => $_POST['observacao'] ?? ''
    ]);

    header("Location: " . BASE_URL . "cadastros/receitas.php?edit=" . $id_receita);
    exit;
}

/* =====================
   ETAPA
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'adicionar_etapa') {
    $id_receita = $_POST['id_receita'];

    $stmt = $pdo->prepare("
        INSERT INTO receitas_processos
        (id_receita, sequencia, descricao_etapa, tempo_estimado_minutos, observacao)
        VALUES
        (:id_receita, :sequencia, :descricao, :tempo, :obs)
    ");

    $stmt->execute([
        ':id_receita' => $id_receita,
        ':sequencia' => $_POST['sequencia'],
        ':descricao' => $_POST['descricao_etapa'],
        ':tempo' => $_POST['tempo_estimado_minutos'] !== '' ? $_POST['tempo_estimado_minutos'] : null,
        ':obs' => $_POST['observacao'] ?? ''
    ]);

    header("Location: " . BASE_URL . "cadastros/receitas.php?edit=" . $id_receita);
    exit;
}

/* =====================
   EXCLUSÕES
===================== */
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM receitas WHERE id_receita = :id");
    $stmt->execute([':id' => $_GET['delete']]);
    header("Location: " . BASE_URL . "cadastros/receitas.php");
    exit;
}

if (isset($_GET['delete_ingrediente'], $_GET['id_receita'])) {
    $stmt = $pdo->prepare("DELETE FROM receitas_ingredientes WHERE id_ingrediente = :id");
    $stmt->execute([':id' => $_GET['delete_ingrediente']]);
    header("Location: " . BASE_URL . "cadastros/receitas.php?edit=" . $_GET['id_receita']);
    exit;
}

if (isset($_GET['delete_etapa'], $_GET['id_receita'])) {
    $stmt = $pdo->prepare("DELETE FROM receitas_processos WHERE id_processo = :id");
    $stmt->execute([':id' => $_GET['delete_etapa']]);
    header("Location: " . BASE_URL . "cadastros/receitas.php?edit=" . $_GET['id_receita']);
    exit;
}

/* =====================
   CONSULTAS
===================== */
$editar = null;

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM receitas WHERE id_receita = :id");
    $stmt->execute([':id' => $_GET['edit']]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

$categorias = $pdo->query("SELECT * FROM receitas_categorias WHERE ativo='Sim' ORDER BY nome_categoria")->fetchAll(PDO::FETCH_ASSOC);

$ingredientes = [];
$etapas = [];
$total_receita = 0;

if ($editar) {
    $stmt = $pdo->prepare("SELECT * FROM receitas_ingredientes WHERE id_receita=:id ORDER BY id_ingrediente");
    $stmt->execute([':id' => $editar['id_receita']]);
    $ingredientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ingredientes as $i) {
        $total_receita += (float)$i['custo_total'];
    }

    $stmt = $pdo->prepare("SELECT * FROM receitas_processos WHERE id_receita=:id ORDER BY sequencia");
    $stmt->execute([':id' => $editar['id_receita']]);
    $etapas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$filtroCategoria = $_GET['filtro_categoria'] ?? '';
$filtroFavorita = $_GET['filtro_favorita'] ?? '';

$sqlFiltro = "
    SELECT r.*, c.nome_categoria, COALESCE(SUM(i.custo_total),0) AS custo_total
    FROM receitas r
    INNER JOIN receitas_categorias c ON c.id_categoria = r.id_categoria
    LEFT JOIN receitas_ingredientes i ON i.id_receita = r.id_receita
    WHERE 1=1
";

$params = [];

if ($filtroCategoria !== '') {
    $sqlFiltro .= " AND r.id_categoria = :filtro_categoria";
    $params[':filtro_categoria'] = $filtroCategoria;
}

if ($filtroFavorita !== '') {
    $sqlFiltro .= " AND r.favorita = :filtro_favorita";
    $params[':filtro_favorita'] = $filtroFavorita;
}

$sqlFiltro .= " GROUP BY r.id_receita ORDER BY r.titulo";

$stmt = $pdo->prepare($sqlFiltro);
$stmt->execute($params);
$receitas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" charset="UTF-8">
<title>Caderno de Receitas</title>

<style>
body { font-family: Arial; margin: 20px; }
form { margin-bottom: 30px; }
input, select, textarea { margin: 6px 0; padding: 6px; width: 520px; display: block; max-width: 100%; }
textarea { height: 100px; }
a { margin-right: 10px; }
table { width: 100%; border-collapse: collapse; background: #fff; margin-bottom: 30px; }
th { background: #2c3e50; color: white; padding: 9px; font-size: 14px; }
td { border: 1px solid #ddd; padding: 8px; font-size: 14px; vertical-align: top; }
tr:nth-child(even) { background: #f8f8f8; }
.box { border: 1px solid #ddd; padding: 15px; margin-bottom: 25px; background: #fafafa; }
.erro { color: red; font-weight: bold; }
.resumo { font-weight: bold; color: #2c3e50; margin-bottom: 20px; }
button { padding: 8px 14px; cursor: pointer; }
.foto-receita { max-width: 280px; border: 1px solid #ccc; padding: 4px; background: white; margin-bottom: 15px; }
.filtros { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; }
.filtros select { width: 260px; }
.card-receita { border: 1px solid #ddd; background: #fff; padding: 12px; margin-bottom: 15px; }
</style>
</head>

<body>

<h2>Caderno de Receitas da Zenilda</h2>

<?php if ($mensagem): ?>
<p class="erro"><?= htmlspecialchars($mensagem) ?></p>
<?php endif; ?>

<div class="box">
<h3>Importar Receita com IA</h3>

<form method="post">
<input type="hidden" name="acao" value="importar_ia">

<label>Fonte / Site da receita</label>
<input name="fonte_site_ia" placeholder="Cole aqui o endereço do site, se quiser">

<label>Cole aqui o texto da receita encontrada na internet</label>
<textarea name="texto_receita" style="height:220px;" required></textarea>

<button type="submit">Importar Receita com IA</button>
</form>
</div>

<h3><?= $editar ? 'Editar Receita' : 'Cadastro Manual de Receita' ?></h3>

<div class="box">
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="acao" value="salvar_receita">
<input type="hidden" name="id_receita" value="<?= htmlspecialchars($editar['id_receita'] ?? '') ?>">

<?php if (!empty($editar['foto_receita'])): ?>
<label>Foto atual</label>
<img class="foto-receita" src="<?= BASE_URL . htmlspecialchars($editar['foto_receita']) ?>">
<?php endif; ?>

<label>Foto da Receita</label>
<input type="file" name="foto_receita" accept="image/*">

<label>Categoria</label>
<select name="id_categoria" required>
<option value="">Selecione...</option>
<?php foreach ($categorias as $cat): ?>
<option value="<?= $cat['id_categoria'] ?>" <?= (($editar['id_categoria'] ?? '') == $cat['id_categoria']) ? 'selected' : '' ?>>
<?= htmlspecialchars($cat['nome_categoria']) ?>
</option>
<?php endforeach; ?>
</select>

<label>Título</label>
<input name="titulo" required value="<?= htmlspecialchars($editar['titulo'] ?? '') ?>">

<label>Descrição</label>
<textarea name="descricao"><?= htmlspecialchars($editar['descricao'] ?? '') ?></textarea>

<label>Fonte / Site</label>
<input name="fonte_site" value="<?= htmlspecialchars($editar['fonte_site'] ?? '') ?>">

<label>Tempo de Preparo em Minutos</label>
<input type="number" name="tempo_preparo_minutos" value="<?= htmlspecialchars($editar['tempo_preparo_minutos'] ?? '') ?>">

<label>Rendimento</label>
<input name="rendimento" value="<?= htmlspecialchars($editar['rendimento'] ?? '') ?>">

<label>Dificuldade</label>
<select name="dificuldade">
<?php $difAtual = $editar['dificuldade'] ?? 'Fácil'; ?>
<?php foreach (['Fácil','Média','Difícil'] as $dif): ?>
<option value="<?= $dif ?>" <?= $difAtual === $dif ? 'selected' : '' ?>><?= $dif ?></option>
<?php endforeach; ?>
</select>

<label>Modo de Preparo Geral</label>
<textarea name="modo_preparo"><?= htmlspecialchars($editar['modo_preparo'] ?? '') ?></textarea>

<label>Observações</label>
<textarea name="observacoes"><?= htmlspecialchars($editar['observacoes'] ?? '') ?></textarea>

<label>Favorita?</label>
<select name="favorita">
<?php $fav = $editar['favorita'] ?? 'Não'; ?>
<option value="Não" <?= $fav === 'Não' ? 'selected' : '' ?>>Não</option>
<option value="Sim" <?= $fav === 'Sim' ? 'selected' : '' ?>>Sim</option>
</select>

<button type="submit"><?= $editar ? 'Atualizar Receita' : 'Salvar Receita' ?></button>

<?php if ($editar): ?>
<a href="receitas.php">Nova Receita</a>
<a href="receitas_pdf.php?id_receita=<?= $editar['id_receita'] ?>" target="_blank">Gerar PDF</a>
<?php endif; ?>
</form>
</div>

<?php if ($editar): ?>

<h3>Ingredientes</h3>
<p class="resumo">Custo total estimado: R$ <?= moeda($total_receita) ?></p>

<div class="box">
<form method="post">
<input type="hidden" name="acao" value="adicionar_ingrediente">
<input type="hidden" name="id_receita" value="<?= $editar['id_receita'] ?>">

<label>Ingrediente</label>
<input name="descricao_ingrediente" required>

<label>Quantidade</label>
<input type="number" name="quantidade" step="0.001" required>

<label>Unidade</label>
<input name="unidade" placeholder="g, kg, ml, l, un">

<label>Custo Unitário R$</label>
<input type="number" name="custo_unitario" step="0.01" value="0">

<label>Observação</label>
<input name="observacao">

<button type="submit">Adicionar Ingrediente</button>
</form>
</div>

<table>
<tr>
<th>Ingrediente</th><th>Quantidade</th><th>Unidade</th><th>Custo Unit.</th><th>Total</th><th>Obs.</th><th>Ações</th>
</tr>
<?php foreach ($ingredientes as $ing): ?>
<tr>
<td><?= htmlspecialchars($ing['descricao_ingrediente']) ?></td>
<td><?= number_format((float)$ing['quantidade'], 3, ',', '.') ?></td>
<td><?= htmlspecialchars($ing['unidade']) ?></td>
<td>R$ <?= moeda($ing['custo_unitario']) ?></td>
<td>R$ <?= moeda($ing['custo_total']) ?></td>
<td><?= htmlspecialchars($ing['observacao']) ?></td>
<td>
<a href="receitas.php?delete_ingrediente=<?= $ing['id_ingrediente'] ?>&id_receita=<?= $editar['id_receita'] ?>" onclick="return confirm('Excluir ingrediente?')">Excluir</a>
</td>
</tr>
<?php endforeach; ?>
</table>

<h3>Etapas do Preparo</h3>

<div class="box">
<form method="post">
<input type="hidden" name="acao" value="adicionar_etapa">
<input type="hidden" name="id_receita" value="<?= $editar['id_receita'] ?>">

<label>Sequência</label>
<input type="number" name="sequencia" value="<?= count($etapas) + 1 ?>" required>

<label>Descrição da etapa</label>
<textarea name="descricao_etapa" required></textarea>

<label>Tempo estimado em minutos</label>
<input type="number" name="tempo_estimado_minutos">

<label>Observação</label>
<input name="observacao">

<button type="submit">Adicionar Etapa</button>
</form>
</div>

<table>
<tr>
<th>Seq.</th><th>Etapa</th><th>Tempo</th><th>Obs.</th><th>Ações</th>
</tr>
<?php foreach ($etapas as $etapa): ?>
<tr>
<td><?= $etapa['sequencia'] ?></td>
<td><?= nl2br(htmlspecialchars($etapa['descricao_etapa'])) ?></td>
<td><?= htmlspecialchars($etapa['tempo_estimado_minutos']) ?> min</td>
<td><?= htmlspecialchars($etapa['observacao']) ?></td>
<td>
<a href="receitas.php?delete_etapa=<?= $etapa['id_processo'] ?>&id_receita=<?= $editar['id_receita'] ?>" onclick="return confirm('Excluir etapa?')">Excluir</a>
</td>
</tr>
<?php endforeach; ?>
</table>

<?php endif; ?>

<h3>Filtros</h3>

<div class="box">
<form method="get" class="filtros">
<div>
<label>Categoria</label>
<select name="filtro_categoria">
<option value="">Todas</option>
<?php foreach ($categorias as $cat): ?>
<option value="<?= $cat['id_categoria'] ?>" <?= ($filtroCategoria == $cat['id_categoria']) ? 'selected' : '' ?>>
<?= htmlspecialchars($cat['nome_categoria']) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div>
<label>Favorita</label>
<select name="filtro_favorita">
<option value="">Todas</option>
<option value="Sim" <?= $filtroFavorita === 'Sim' ? 'selected' : '' ?>>Sim</option>
<option value="Não" <?= $filtroFavorita === 'Não' ? 'selected' : '' ?>>Não</option>
</select>
</div>

<div>
<button type="submit">Filtrar</button>
<a href="receitas.php">Limpar</a>
<a href="receitas_estoque.php?id_receita=<?= $editar['id_receita'] ?>" target="_blank">Verificar Estoque</a>
</div>
</form>
</div>

<h3>Receitas Cadastradas</h3>

<table>
<tr>
<th>ID</th>
<th>Foto</th>
<th>Receita</th>
<th>Categoria</th>
<th>Tempo</th>
<th>Rendimento</th>
<th>Dificuldade</th>
<th>Favorita</th>
<th>Custo</th>
<th>Ações</th>
</tr>

<?php foreach ($receitas as $r): ?>
<tr>
<td><?= $r['id_receita'] ?></td>
<td>
<?php if (!empty($r['foto_receita'])): ?>
<img src="<?= BASE_URL . htmlspecialchars($r['foto_receita']) ?>" style="max-width:80px; max-height:60px;">
<?php endif; ?>
</td>
<td><?= htmlspecialchars($r['titulo']) ?></td>
<td><?= htmlspecialchars($r['nome_categoria']) ?></td>
<td><?= htmlspecialchars($r['tempo_preparo_minutos']) ?> min</td>
<td><?= htmlspecialchars($r['rendimento']) ?></td>
<td><?= htmlspecialchars($r['dificuldade']) ?></td>
<td><?= htmlspecialchars($r['favorita']) ?></td>
<td>R$ <?= moeda($r['custo_total']) ?></td>
<td>
<a href="receitas.php?edit=<?= $r['id_receita'] ?>">Editar</a>
<a href="receitas_pdf.php?id_receita=<?= $r['id_receita'] ?>" target="_blank">PDF</a>
<a href="receitas.php?delete=<?= $r['id_receita'] ?>" onclick="return confirm('Excluir receita completa?')">Excluir</a>
<a href="receitas_estoque.php?id_receita=<?= $r['id_receita'] ?>" target="_blank">Estoque</a>
</td>
</tr>
<?php endforeach; ?>
</table>

</body>
</html>

<?php ob_end_flush(); ?>