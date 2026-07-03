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

$mensagem = '';
$transcricao = '';

function localizarYtDlp()
{
    $possiveis = [
        'C:\\Users\\William\\AppData\\Local\\Microsoft\\WinGet\\Links\\yt-dlp.exe',
        getenv('LOCALAPPDATA') . '\\Microsoft\\WinGet\\Links\\yt-dlp.exe',
        'C:\\yt-dlp\\yt-dlp.exe'
    ];

    foreach ($possiveis as $caminho) {
        if ($caminho && file_exists($caminho)) {
            return $caminho;
        }
    }

    return null;
}


function localizarFFmpeg()
{
    $possiveis = [
        'C:\\ffmpeg\\bin\\ffmpeg.exe',
        'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe',
        'C:\\Program Files (x86)\\ffmpeg\\bin\\ffmpeg.exe'
    ];

    $userProfile = getenv('USERPROFILE');

    if ($userProfile) {
        $wingetBase = $userProfile . '\\AppData\\Local\\Microsoft\\WinGet\\Packages';

        if (is_dir($wingetBase)) {
            $pastas = glob($wingetBase . '\\Gyan.FFmpeg*', GLOB_ONLYDIR);

            foreach ($pastas as $pasta) {
                $achados = glob($pasta . '\\*\\bin\\ffmpeg.exe');

                foreach ($achados as $ffmpeg) {
                    if (file_exists($ffmpeg)) {
                        return $ffmpeg;
                    }
                }
            }
        }
    }

    foreach ($possiveis as $caminho) {
        if (file_exists($caminho)) {
            return $caminho;
        }
    }

    return null;
}

function transcreverAudioOpenAI($audioPath)
{
    if (!defined('OPENAI_API_KEY') || OPENAI_API_KEY === '') {
        throw new Exception('Chave da OpenAI não encontrada em config/openai.php');
    }

    $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');

    $postFields = [
        'model' => 'gpt-4o-mini-transcribe',
        'file' => new CURLFile($audioPath),
        'language' => 'pt',
        'response_format' => 'json'
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_POSTFIELDS => $postFields
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception($json['error']['message'] ?? $response);
    }

    return $json['text'] ?? '';
}

function interpretarReceitaIA($textoReceita)
{
    if (!defined('OPENAI_API_KEY') || OPENAI_API_KEY === '') {
        throw new Exception('Chave da OpenAI não encontrada em config/openai.php');
    }

    $prompt = "
Você é um assistente especializado em organizar receitas culinárias.

Leia o texto abaixo e extraia uma receita em JSON puro.

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

Texto/transcrição da receita:
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

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception($json['error']['message'] ?? $response);
    }

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

function gravarReceitaNoBanco($pdo, $dados, $transcricao)
{
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
        (:id_categoria, :titulo, :descricao, '', :tempo, :rendimento, :dificuldade, :modo_preparo, :observacoes, 'Não')
    ");

    $stmt->execute([
        ':id_categoria' => $id_categoria,
        ':titulo' => $dados['titulo'] ?? 'Receita importada por vídeo',
        ':descricao' => $dados['descricao'] ?? '',
        ':tempo' => $dados['tempo_preparo_minutos'] ?? null,
        ':rendimento' => $dados['rendimento'] ?? '',
        ':dificuldade' => $dados['dificuldade'] ?? 'Fácil',
        ':modo_preparo' => $dados['modo_preparo'] ?? '',
        ':observacoes' => "Receita importada por vídeo com IA.\n\nTranscrição original:\n" . $transcricao
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

    return $id_receita;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $origem_video = $_POST['origem_video'] ?? 'arquivo';

        $ffmpeg = localizarFFmpeg();

        if (!$ffmpeg) {
            throw new Exception('FFmpeg não encontrado pelo PHP/XAMPP.');
        }

if ($origem_video === 'arquivo') {

    if (!isset($_FILES['video_receita']) || $_FILES['video_receita']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erro ao enviar o vídeo.');
    }

} elseif ($origem_video === 'youtube') {

    $link_youtube = trim($_POST['link_youtube'] ?? '');

    if ($link_youtube === '') {
        throw new Exception('Informe o link do YouTube.');
    }

} elseif ($origem_video === 'facebook' || $origem_video === 'instagram') {

    throw new Exception('Importação por Facebook e Instagram ainda será implementada. Por enquanto use arquivo de vídeo ou YouTube.');

} else {
    throw new Exception('Origem do vídeo inválida.');
}
        $pastaTemp = __DIR__ . '/../uploads/temp_receitas/';

        if (!is_dir($pastaTemp)) {
            mkdir($pastaTemp, 0777, true);
        }

$baseNome = 'receita_video_' . date('YmdHis') . '_' . rand(1000, 9999);
$videoPath = $pastaTemp . $baseNome . '.mp4';
$audioPath = $pastaTemp . $baseNome . '.mp3';

if (file_exists($audioPath)) {
    unlink($audioPath);
}

if ($origem_video === 'arquivo') {

    $extensao = strtolower(pathinfo($_FILES['video_receita']['name'], PATHINFO_EXTENSION));
    $permitidas = ['mp4', 'mov', 'webm', 'mkv'];

    if (!in_array($extensao, $permitidas)) {
        throw new Exception('Formato não permitido. Use MP4, MOV, WEBM ou MKV.');
    }

    $videoPath = $pastaTemp . $baseNome . '.' . $extensao;

    if (!move_uploaded_file($_FILES['video_receita']['tmp_name'], $videoPath)) {
        throw new Exception('Não foi possível salvar o vídeo temporário.');
    }

} elseif ($origem_video === 'youtube') {

    $ytDlp = localizarYtDlp();

    if (!$ytDlp) {
        throw new Exception('yt-dlp não encontrado pelo PHP/XAMPP.');
    }

    $link_youtube = trim($_POST['link_youtube'] ?? '');

    $comandoYt = escapeshellarg($ytDlp) .
        ' -f "bv*[height<=720]+ba/b[height<=720]/b" ' .
        ' --merge-output-format mp4 ' .
        ' -o ' . escapeshellarg($videoPath) . ' ' .
        escapeshellarg($link_youtube) . ' 2>&1';

    exec($comandoYt, $saidaYt, $codigoYt);

    if ($codigoYt !== 0 || !file_exists($videoPath)) {
        throw new Exception('Erro ao baixar vídeo do YouTube com yt-dlp:<br><pre>' . htmlspecialchars(implode("\n", $saidaYt)) . '</pre>');
    }
}
        $comando = escapeshellarg($ffmpeg) .
                   ' -y -i ' . escapeshellarg($videoPath) .
                   ' -vn -acodec libmp3lame -ar 44100 -ac 2 -b:a 128k ' .
                   escapeshellarg($audioPath) . ' 2>&1';

        exec($comando, $saida, $codigoRetorno);

        if ($codigoRetorno !== 0 || !file_exists($audioPath)) {
            throw new Exception('Erro ao extrair áudio com FFmpeg:<br><pre>' . htmlspecialchars(implode("\n", $saida)) . '</pre>');
        }

        $transcricao = transcreverAudioOpenAI($audioPath);
        $dadosReceita = interpretarReceitaIA($transcricao);
        $id_receita = gravarReceitaNoBanco($pdo, $dadosReceita, $transcricao);

        if (file_exists($videoPath)) {
            unlink($videoPath);
        }

        if (file_exists($audioPath)) {
            unlink($audioPath);
        }

        header("Location: " . BASE_URL . "cadastros/receitas.php?edit=" . $id_receita);
        exit;

    } catch (Exception $e) {
        $mensagem = 'Erro: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Importar Receita por Vídeo</title>

<style>
body { font-family: Arial; margin: 20px; }
form { margin-bottom: 30px; }
input, textarea, select { margin: 6px 0; padding: 6px; width: 620px; display: block; max-width: 100%; }
textarea { height: 260px; }
button { padding: 8px 14px; cursor: pointer; }
.box { border: 1px solid #ddd; padding: 15px; background: #fafafa; margin-bottom: 20px; }
.erro { color: red; font-weight: bold; }
</style>
</head>

<body>

<h2>Importar Receita por Vídeo</h2>

<div class="box">
    <form method="post" enctype="multipart/form-data">

        <label>Origem do vídeo</label>
        <select name="origem_video" id="origem_video" required>
            <option value="arquivo">Arquivo de vídeo no computador</option>
            <option value="youtube">Link do YouTube</option>
            <option value="facebook">Link do Facebook</option>
            <option value="instagram">Link do Instagram</option>
        </select>

        <div id="campo_arquivo">
            <label>Selecione um vídeo da receita</label>
            <input 
                type="file" 
                name="video_receita" 
                accept="video/mp4,video/quicktime,video/webm,video/x-matroska"
            >
        </div>

        <div id="campo_youtube" style="display:none;">
            <label>Link do YouTube</label>
            <input 
                type="url" 
                name="link_youtube" 
                placeholder="Cole aqui o link do vídeo do YouTube"
            >
        </div>

        <button type="submit">Importar Receita do Vídeo</button>
    </form>
</div>

<?php if ($mensagem): ?>
    <div class="box">
        <p class="erro"><?= $mensagem ?></p>
    </div>
<?php endif; ?>

<p>
    <a href="receitas.php">Voltar para Receitas</a>
</p>

<script>
document.getElementById('origem_video').addEventListener('change', function () {
    const origem = this.value;

    document.getElementById('campo_arquivo').style.display = origem === 'arquivo' ? 'block' : 'none';
    document.getElementById('campo_youtube').style.display = origem === 'youtube' ? 'block' : 'none';
});
</script>
</body>
</html>

<?php ob_end_flush(); ?>