<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

verificaAcesso();

require __DIR__ . '/../includes/menu.php';

/* =========================================================
   FUNÇÕES AUXILIARES
   Compatíveis com PHP 7.4
========================================================= */

/**
 * Escapa textos para exibição no HTML.
 */
function h($valor)
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * Valida uma data no formato Y-m-d.
 */
function dataValida($data)
{
    if (empty($data)) {
        return false;
    }

    $objeto = DateTime::createFromFormat('Y-m-d', $data);

    return $objeto
        && $objeto->format('Y-m-d') === $data;
}

/**
 * Converte uma data Y-m-d para d/m/Y.
 */
function formatarData($data)
{
    if (empty($data)) {
        return '';
    }

    $objeto = DateTime::createFromFormat('Y-m-d', $data);

    if (!$objeto) {
        return $data;
    }

    return $objeto->format('d/m/Y');
}

/**
 * Calcula a data de vencimento da fatura.
 *
 * Exemplo:
 * fechamento dia 03 e vencimento dia 10:
 * período terminando em 03/08 -> vencimento 10/08.
 *
 * fechamento dia 25 e vencimento dia 02:
 * período terminando em 25/08 -> vencimento 02/09.
 */
function calcularVencimentoFatura(
    $dataFinal,
    $diaFechamento,
    $diaVencimento
) {
    if (!dataValida($dataFinal)) {
        return '';
    }

    $diaFechamento = (int) $diaFechamento;
    $diaVencimento = (int) $diaVencimento;

    if (
        $diaFechamento < 1
        || $diaFechamento > 31
        || $diaVencimento < 1
        || $diaVencimento > 31
    ) {
        return '';
    }

    $dataBase = new DateTime($dataFinal);

    $ano = (int) $dataBase->format('Y');
    $mes = (int) $dataBase->format('m');

    /*
     * Quando o vencimento é posterior ao fechamento,
     * utiliza o mesmo mês.
     *
     * Quando o vencimento é anterior ou igual ao fechamento,
     * utiliza o mês seguinte.
     */
    if ($diaVencimento <= $diaFechamento) {
        $mes++;

        if ($mes > 12) {
            $mes = 1;
            $ano++;
        }
    }

    /*
     * Evita datas inválidas, como 31 de fevereiro.
     */
    $ultimoDiaMes = (int) date(
        't',
        strtotime(
            sprintf('%04d-%02d-01', $ano, $mes)
        )
    );

    if ($diaVencimento > $ultimoDiaMes) {
        $diaVencimento = $ultimoDiaMes;
    }

    return sprintf(
        '%04d-%02d-%02d',
        $ano,
        $mes,
        $diaVencimento
    );
}

/**
 * Cria placeholders para consultas SQL com IN.
 */
function criarPlaceholders($quantidade)
{
    if ($quantidade <= 0) {
        return '';
    }

    return implode(
        ',',
        array_fill(0, $quantidade, '?')
    );
}

/* =========================================================
   SINCRONIZAR FATURAS PAGAS
========================================================= */

/*
 * Quando a conta da fatura for marcada como Pago
 * na tabela lancamentos, as compras relacionadas também
 * passam automaticamente para Pago ao acessar esta tela.
 */
try {
    $pdo->exec("
        UPDATE lancamentos_cartoes lc

        INNER JOIN lancamentos l
            ON l.id_lancamento =
               lc.id_lancamento_fatura

        SET lc.status = 'Pago'

        WHERE lc.status = 'Faturado'
          AND l.status = 'Pago'
    ");
} catch (PDOException $e) {
    /*
     * Não interrompe a página caso a sincronização falhe.
     * Durante os testes, o restante da tela continua funcionando.
     */
}

/* =========================================================
   CARREGAR CARTÕES
========================================================= */
$stmtCartoes = $pdo->query("
    SELECT
        id_cartao,
        descricao,
        dia_vencimento,
        dia_fechamento,
        limite
    FROM cartoes
    ORDER BY descricao
");

$cartoes = $stmtCartoes->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   CARREGAR GRUPOS
========================================================= */
$stmtGrupos = $pdo->query("
    SELECT
        id_grupo,
        descricao
    FROM grupos
    ORDER BY descricao
");

$grupos = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   FILTROS PADRÃO
========================================================= */
$primeiroDiaMes = date('Y-m-01');
$dataHoje = date('Y-m-d');

$idCartaoFiltro = isset($_GET['id_cartao'])
    ? (int) $_GET['id_cartao']
    : 0;

$dataInicial = isset($_GET['data_inicial'])
    ? trim($_GET['data_inicial'])
    : $primeiroDiaMes;

$dataFinal = isset($_GET['data_final'])
    ? trim($_GET['data_final'])
    : $dataHoje;

$statusFiltro = isset($_GET['status'])
    ? trim($_GET['status'])
    : 'Aberto';

/*
 * Caso nenhum cartão tenha sido selecionado,
 * utiliza o primeiro cartão cadastrado.
 */
if ($idCartaoFiltro <= 0 && count($cartoes) > 0) {
    $idCartaoFiltro = (int) $cartoes[0]['id_cartao'];
}

/* =========================================================
   MENSAGENS
========================================================= */
$mensagem = '';
$tipoMensagem = '';

if (isset($_GET['sucesso'])) {

    if ($_GET['sucesso'] === 'fatura_gerada') {

        $numeroFatura = isset($_GET['fatura'])
            ? (int) $_GET['fatura']
            : 0;

        $mensagem = 'Conta a pagar gerada com sucesso.';

        if ($numeroFatura > 0) {
            $mensagem .=
                ' Número do lançamento: '
                . $numeroFatura
                . '.';
        }

        $tipoMensagem = 'sucesso';
    }
}

if (isset($_GET['erro'])) {

    if ($_GET['erro'] === 'dados_invalidos') {
        $mensagem =
            'Confira o cartão, o período, o vencimento e o grupo.';
        $tipoMensagem = 'erro';

    } elseif ($_GET['erro'] === 'sem_selecao') {
        $mensagem =
            'Selecione pelo menos uma compra para gerar a fatura.';
        $tipoMensagem = 'erro';

    } elseif ($_GET['erro'] === 'sem_lancamentos') {
        $mensagem =
            'Nenhum lançamento aberto válido foi encontrado.';
        $tipoMensagem = 'erro';

    } elseif ($_GET['erro'] === 'alterado') {
        $mensagem =
            'Um ou mais lançamentos foram alterados durante o fechamento. Tente novamente.';
        $tipoMensagem = 'erro';
    }
}

/* =========================================================
   GERAR CONTA A PAGAR
========================================================= */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['acao'])
    && $_POST['acao'] === 'gerar_fatura'
) {
    $idCartaoPost = isset($_POST['id_cartao'])
        ? (int) $_POST['id_cartao']
        : 0;

    $dataInicialPost = isset($_POST['data_inicial'])
        ? trim($_POST['data_inicial'])
        : '';

    $dataFinalPost = isset($_POST['data_final'])
        ? trim($_POST['data_final'])
        : '';

    $dataVencimento = isset($_POST['data_vencimento'])
        ? trim($_POST['data_vencimento'])
        : '';

    $dataFechamento = isset($_POST['data_fechamento'])
        ? trim($_POST['data_fechamento'])
        : date('Y-m-d');

    $idGrupoFinanceiro = isset($_POST['id_grupo_financeiro'])
        ? (int) $_POST['id_grupo_financeiro']
        : 0;

    $itensSelecionados = isset($_POST['itens'])
        && is_array($_POST['itens'])
        ? $_POST['itens']
        : array();

    /*
     * Converte todos os IDs para inteiro e remove valores inválidos.
     */
    $idsLimpos = array();

    foreach ($itensSelecionados as $idItem) {
        $idItem = (int) $idItem;

        if ($idItem > 0) {
            $idsLimpos[] = $idItem;
        }
    }

    $idsLimpos = array_values(
        array_unique($idsLimpos)
    );

    if (
        $idCartaoPost <= 0
        || $idGrupoFinanceiro <= 0
        || !dataValida($dataInicialPost)
        || !dataValida($dataFinalPost)
        || !dataValida($dataVencimento)
        || !dataValida($dataFechamento)
        || $dataInicialPost > $dataFinalPost
    ) {
        header(
            'Location: '
            . BASE_URL
            . 'cadastros/consulta_cartoes.php'
            . '?erro=dados_invalidos'
            . '&id_cartao=' . $idCartaoPost
            . '&data_inicial=' . urlencode($dataInicialPost)
            . '&data_final=' . urlencode($dataFinalPost)
            . '&status=Aberto'
        );
        exit;
    }

    if (count($idsLimpos) === 0) {
        header(
            'Location: '
            . BASE_URL
            . 'cadastros/consulta_cartoes.php'
            . '?erro=sem_selecao'
            . '&id_cartao=' . $idCartaoPost
            . '&data_inicial=' . urlencode($dataInicialPost)
            . '&data_final=' . urlencode($dataFinalPost)
            . '&status=Aberto'
        );
        exit;
    }

    try {
        $pdo->beginTransaction();

        /* =================================================
           BUSCAR O CARTÃO
        ================================================= */
        $stmtCartao = $pdo->prepare("
            SELECT
                id_cartao,
                descricao,
                dia_vencimento,
                dia_fechamento
            FROM cartoes
            WHERE id_cartao = :id_cartao
            FOR UPDATE
        ");

        $stmtCartao->execute(array(
            ':id_cartao' => $idCartaoPost
        ));

        $cartaoSelecionado = $stmtCartao->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$cartaoSelecionado) {
            throw new Exception(
                'Cartão não encontrado.'
            );
        }

        /* =================================================
           BUSCAR SOMENTE OS ITENS AINDA ABERTOS
        ================================================= */
        $placeholders = criarPlaceholders(
            count($idsLimpos)
        );

        $sqlItens = "
            SELECT
                id_lancamento_cartao,
                valor
            FROM lancamentos_cartoes

            WHERE id_lancamento_cartao IN (
                " . $placeholders . "
            )

              AND id_cartao = ?
              AND data_lancamento
                    BETWEEN ? AND ?
              AND status = 'Aberto'
              AND id_lancamento_fatura IS NULL

            FOR UPDATE
        ";

        $parametrosItens = $idsLimpos;
        $parametrosItens[] = $idCartaoPost;
        $parametrosItens[] = $dataInicialPost;
        $parametrosItens[] = $dataFinalPost;

        $stmtItens = $pdo->prepare($sqlItens);
        $stmtItens->execute($parametrosItens);

        $itensValidos = $stmtItens->fetchAll(
            PDO::FETCH_ASSOC
        );

        if (count($itensValidos) === 0) {
            $pdo->rollBack();

            header(
                'Location: '
                . BASE_URL
                . 'cadastros/consulta_cartoes.php'
                . '?erro=sem_lancamentos'
                . '&id_cartao=' . $idCartaoPost
                . '&data_inicial=' . urlencode($dataInicialPost)
                . '&data_final=' . urlencode($dataFinalPost)
                . '&status=Aberto'
            );
            exit;
        }

        /*
         * Todos os itens selecionados precisam continuar válidos.
         */
        if (
            count($itensValidos)
            !== count($idsLimpos)
        ) {
            $pdo->rollBack();

            header(
                'Location: '
                . BASE_URL
                . 'cadastros/consulta_cartoes.php'
                . '?erro=alterado'
                . '&id_cartao=' . $idCartaoPost
                . '&data_inicial=' . urlencode($dataInicialPost)
                . '&data_final=' . urlencode($dataFinalPost)
                . '&status=Aberto'
            );
            exit;
        }

        /* =================================================
           CALCULAR O TOTAL
        ================================================= */
        $valorTotal = 0;

        foreach ($itensValidos as $item) {
            $valorTotal += (float) $item['valor'];
        }

        $valorTotal = round($valorTotal, 2);

        if ($valorTotal <= 0) {
            throw new Exception(
                'O total da fatura é inválido.'
            );
        }

        /* =================================================
           GERAR A DESCRIÇÃO
        ================================================= */
        $descricaoFatura =
            'Fatura '
            . $cartaoSelecionado['descricao']
            . ' - '
            . formatarData($dataInicialPost)
            . ' a '
            . formatarData($dataFinalPost);

        /*
         * A tabela lancamentos exige documento_numero.
         * Primeiro inserimos 0 e, depois de obter o ID,
         * atualizamos documento_numero com o próprio ID.
         */
        $stmtInserir = $pdo->prepare("
            INSERT INTO lancamentos (
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
                id_autor,
                foto_nota
            ) VALUES (
                0,
                :data_lancamento,
                :descricao,
                'Pagar',
                :data_vencimento,
                :valor_nominal,
                NULL,
                NULL,
                'Aberto',
                NULL,
                :id_grupo,
                NULL,
                NULL
            )
        ");

        $stmtInserir->execute(array(
            ':data_lancamento' => $dataFechamento,
            ':descricao' => $descricaoFatura,
            ':data_vencimento' => $dataVencimento,
            ':valor_nominal' => $valorTotal,
            ':id_grupo' => $idGrupoFinanceiro
        ));

        $idLancamentoFatura = (int) $pdo->lastInsertId();

        if ($idLancamentoFatura <= 0) {
            throw new Exception(
                'Não foi possível obter o ID da fatura.'
            );
        }

        /*
         * Utilizamos o próprio ID como número do documento.
         */
        $stmtDocumento = $pdo->prepare("
            UPDATE lancamentos
            SET documento_numero = :documento_numero
            WHERE id_lancamento = :id_lancamento
        ");

        $stmtDocumento->execute(array(
            ':documento_numero' => $idLancamentoFatura,
            ':id_lancamento' => $idLancamentoFatura
        ));

        /* =================================================
           ATUALIZAR AS COMPRAS PARA FATURADO
        ================================================= */
        $idsValidos = array();

        foreach ($itensValidos as $itemValido) {
            $idsValidos[] = (int)
                $itemValido['id_lancamento_cartao'];
        }

        $placeholdersAtualizar = criarPlaceholders(
            count($idsValidos)
        );

        $sqlAtualizar = "
            UPDATE lancamentos_cartoes

            SET
                status = 'Faturado',
                id_lancamento_fatura = ?

            WHERE id_lancamento_cartao IN (
                " . $placeholdersAtualizar . "
            )

              AND status = 'Aberto'
              AND id_lancamento_fatura IS NULL
        ";

        $parametrosAtualizar = array(
            $idLancamentoFatura
        );

        foreach ($idsValidos as $idValido) {
            $parametrosAtualizar[] = $idValido;
        }

        $stmtAtualizar = $pdo->prepare(
            $sqlAtualizar
        );

        $stmtAtualizar->execute(
            $parametrosAtualizar
        );

        if (
            $stmtAtualizar->rowCount()
            !== count($idsValidos)
        ) {
            throw new Exception(
                'Nem todos os itens foram atualizados.'
            );
        }

        $pdo->commit();

        header(
            'Location: '
            . BASE_URL
            . 'cadastros/consulta_cartoes.php'
            . '?sucesso=fatura_gerada'
            . '&fatura=' . $idLancamentoFatura
            . '&id_cartao=' . $idCartaoPost
            . '&data_inicial=' . urlencode($dataInicialPost)
            . '&data_final=' . urlencode($dataFinalPost)
            . '&status=Aberto'
        );
        exit;

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        die(
            '<strong>Erro ao gerar a conta a pagar:</strong>'
            . '<br><br>'
            . h($e->getMessage())
        );
    }
}

/* =========================================================
   IDENTIFICAR O CARTÃO SELECIONADO
========================================================= */
$cartaoAtual = null;

foreach ($cartoes as $cartao) {
    if (
        (int) $cartao['id_cartao']
        === $idCartaoFiltro
    ) {
        $cartaoAtual = $cartao;
        break;
    }
}

/* =========================================================
   CALCULAR VENCIMENTO SUGERIDO
========================================================= */
$vencimentoSugerido = '';

if (
    $cartaoAtual
    && dataValida($dataFinal)
) {
    $vencimentoSugerido = calcularVencimentoFatura(
        $dataFinal,
        $cartaoAtual['dia_fechamento'],
        $cartaoAtual['dia_vencimento']
    );
}

/* =========================================================
   CONSULTAR LANÇAMENTOS
========================================================= */
$lancamentosCartao = array();

if (
    $idCartaoFiltro > 0
    && dataValida($dataInicial)
    && dataValida($dataFinal)
    && $dataInicial <= $dataFinal
) {
    $sqlConsulta = "
        SELECT
            lc.id_lancamento_cartao,
            lc.documento_numero,
            lc.data_lancamento,
            lc.descricao,
            lc.valor,
            lc.status,
            lc.id_lancamento_fatura,

            c.descricao AS cartao_descricao,
            g.descricao AS grupo_descricao

        FROM lancamentos_cartoes lc

        INNER JOIN cartoes c
            ON c.id_cartao = lc.id_cartao

        INNER JOIN grupos g
            ON g.id_grupo = lc.id_grupo

        WHERE lc.id_cartao = :id_cartao
          AND lc.data_lancamento
                BETWEEN :data_inicial
                    AND :data_final
    ";

    $parametrosConsulta = array(
        ':id_cartao' => $idCartaoFiltro,
        ':data_inicial' => $dataInicial,
        ':data_final' => $dataFinal
    );

    if (
        $statusFiltro === 'Aberto'
        || $statusFiltro === 'Faturado'
        || $statusFiltro === 'Pago'
        || $statusFiltro === 'Cancelado'
    ) {
        $sqlConsulta .= "
            AND lc.status = :status
        ";

        $parametrosConsulta[':status'] =
            $statusFiltro;
    }

    $sqlConsulta .= "
        ORDER BY
            lc.data_lancamento,
            lc.id_lancamento_cartao
    ";

    $stmtConsulta = $pdo->prepare($sqlConsulta);
    $stmtConsulta->execute($parametrosConsulta);

    $lancamentosCartao = $stmtConsulta->fetchAll(
        PDO::FETCH_ASSOC
    );
}

/* =========================================================
   TOTAL DA CONSULTA
========================================================= */
$totalConsulta = 0;
$quantidadeAbertos = 0;

foreach ($lancamentosCartao as $lancamento) {
    $totalConsulta += (float) $lancamento['valor'];

    if (
        $lancamento['status'] === 'Aberto'
        && empty($lancamento['id_lancamento_fatura'])
    ) {
        $quantidadeAbertos++;
    }
}

/* =========================================================
   HISTÓRICO DE FATURAS
========================================================= */
$stmtHistorico = $pdo->query("
    SELECT
        l.id_lancamento,
        l.documento_numero,
        l.data_lancamento,
        l.descricao,
        l.data_vencimento,
        l.valor_nominal,
        l.data_pagamento,
        l.valor_pago,
        l.status,

        COUNT(
            lc.id_lancamento_cartao
        ) AS quantidade_compras,

        MIN(
            lc.data_lancamento
        ) AS primeira_compra,

        MAX(
            lc.data_lancamento
        ) AS ultima_compra,

        c.descricao AS cartao_descricao

    FROM lancamentos l

    INNER JOIN lancamentos_cartoes lc
        ON lc.id_lancamento_fatura =
           l.id_lancamento

    INNER JOIN cartoes c
        ON c.id_cartao = lc.id_cartao

    GROUP BY
        l.id_lancamento,
        l.documento_numero,
        l.data_lancamento,
        l.descricao,
        l.data_vencimento,
        l.valor_nominal,
        l.data_pagamento,
        l.valor_pago,
        l.status,
        c.descricao

    ORDER BY
        l.data_vencimento DESC,
        l.id_lancamento DESC
");

$historicoFaturas = $stmtHistorico->fetchAll(
    PDO::FETCH_ASSOC
);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Consulta e fechamento de cartões</title>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background: #f5f5f5;
    color: #222222;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
}

.bloco {
    background: #ffffff;
    border: 1px solid #dddddd;
    border-radius: 7px;
    padding: 20px;
    margin-bottom: 22px;
}

h1,
h2,
h3 {
    margin-top: 0;
}

.subtitulo {
    color: #666666;
    margin-top: -8px;
    margin-bottom: 20px;
}

.filtros {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(180px, 1fr));
    gap: 15px;
    align-items: end;
}

.campo {
    display: flex;
    flex-direction: column;
}

.campo label {
    font-weight: bold;
    margin-bottom: 6px;
}

input,
select {
    box-sizing: border-box;
    width: 100%;
    padding: 9px;
    border: 1px solid #bdbdbd;
    border-radius: 4px;
    font-size: 15px;
}

button,
.botao {
    display: inline-block;
    padding: 10px 17px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    font-size: 15px;
}

.botao-consultar {
    background: #2867a5;
    color: #ffffff;
}

.botao-fechar {
    background: #218838;
    color: #ffffff;
    font-weight: bold;
}

.botao-fechar:disabled {
    background: #9f9f9f;
    cursor: not-allowed;
}

.mensagem {
    padding: 13px;
    margin-bottom: 20px;
    border-radius: 5px;
}

.mensagem-sucesso {
    background: #e8f5e9;
    border: 1px solid #78b681;
    color: #215c29;
}

.mensagem-erro {
    background: #fdeaea;
    border: 1px solid #d58f8f;
    color: #812323;
}

.informacoes-cartao {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(160px, 1fr));
    gap: 15px;
    margin-top: 18px;
}

.informacao {
    background: #f4f7fa;
    border: 1px solid #d7dee5;
    border-radius: 5px;
    padding: 13px;
}

.informacao-titulo {
    display: block;
    color: #666666;
    font-size: 13px;
    margin-bottom: 5px;
}

.informacao-valor {
    font-size: 18px;
    font-weight: bold;
}

.tabela-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
}

th,
td {
    border: 1px solid #cccccc;
    padding: 9px;
    text-align: left;
    vertical-align: middle;
}

th {
    background: #eeeeee;
}

.centralizado {
    text-align: center;
}

.valor {
    text-align: right;
    white-space: nowrap;
}

.total-linha {
    font-weight: bold;
    background: #f5f5f5;
}

.status {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: bold;
}

.status-aberto {
    background: #fff3cd;
    color: #735b00;
}

.status-faturado {
    background: #dbeafe;
    color: #174a7e;
}

.status-pago {
    background: #dff3e3;
    color: #246230;
}

.status-cancelado {
    background: #ececec;
    color: #555555;
}

.resumo-fechamento {
    display: grid;
    grid-template-columns:
        repeat(3, minmax(190px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.total-destaque {
    background: #edf7ee;
    border: 1px solid #9cc4a0;
    border-radius: 6px;
    padding: 17px;
}

.total-destaque small {
    display: block;
    color: #555555;
    margin-bottom: 5px;
}

.total-destaque strong {
    font-size: 25px;
}

.acoes-fechamento {
    margin-top: 18px;
}

.observacao {
    background: #fff8dc;
    border: 1px solid #e2ca78;
    border-radius: 5px;
    padding: 12px;
    margin-top: 15px;
}

.sem-registros {
    text-align: center;
    color: #666666;
    padding: 25px;
}

@media (max-width: 900px) {
    .filtros,
    .informacoes-cartao,
    .resumo-fechamento {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 600px) {
    .filtros,
    .informacoes-cartao,
    .resumo-fechamento {
        grid-template-columns: 1fr;
    }
}
</style>

</head>

<body>

<div class="container">

    <div class="bloco">

        <h1>Consulta e fechamento de cartões</h1>

        <div class="subtitulo">
            Consulte as compras e gere a conta a pagar da fatura.
        </div>

        <?php if ($mensagem !== ''): ?>

            <?php
            $classeMensagem =
                $tipoMensagem === 'sucesso'
                    ? 'mensagem-sucesso'
                    : 'mensagem-erro';
            ?>

            <div
                class="mensagem <?= h($classeMensagem) ?>"
            >
                <?= h($mensagem) ?>
            </div>

        <?php endif; ?>

        <form method="get">

            <div class="filtros">

                <div class="campo">

                    <label for="id_cartao">
                        Cartão
                    </label>

                    <select
                        name="id_cartao"
                        id="id_cartao"
                        required
                    >

                        <option value="">
                            Selecione
                        </option>

                        <?php foreach ($cartoes as $cartao): ?>

                            <option
                                value="<?= (int)
                                    $cartao['id_cartao']
                                ?>"
                                <?= (
                                    (int) $cartao['id_cartao']
                                    === $idCartaoFiltro
                                ) ? 'selected' : '' ?>
                            >
                                <?= h($cartao['descricao']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="campo">

                    <label for="data_inicial">
                        Data inicial
                    </label>

                    <input
                        type="date"
                        name="data_inicial"
                        id="data_inicial"
                        value="<?= h($dataInicial) ?>"
                        required
                    >

                </div>

                <div class="campo">

                    <label for="data_final">
                        Data final
                    </label>

                    <input
                        type="date"
                        name="data_final"
                        id="data_final"
                        value="<?= h($dataFinal) ?>"
                        required
                    >

                </div>

                <div class="campo">

                    <label for="status">
                        Situação
                    </label>

                    <select
                        name="status"
                        id="status"
                    >
                        <option
                            value="Aberto"
                            <?= $statusFiltro === 'Aberto'
                                ? 'selected'
                                : '' ?>
                        >
                            Aberto
                        </option>

                        <option
                            value="Faturado"
                            <?= $statusFiltro === 'Faturado'
                                ? 'selected'
                                : '' ?>
                        >
                            Faturado
                        </option>

                        <option
                            value="Pago"
                            <?= $statusFiltro === 'Pago'
                                ? 'selected'
                                : '' ?>
                        >
                            Pago
                        </option>

                        <option
                            value="Cancelado"
                            <?= $statusFiltro === 'Cancelado'
                                ? 'selected'
                                : '' ?>
                        >
                            Cancelado
                        </option>

                        <option
                            value="Todos"
                            <?= $statusFiltro === 'Todos'
                                ? 'selected'
                                : '' ?>
                        >
                            Todos
                        </option>
                    </select>

                </div>

            </div>

            <div style="margin-top: 16px;">

                <button
                    type="submit"
                    class="botao-consultar"
                >
                    Consultar
                </button>

            </div>

        </form>

        <?php if ($cartaoAtual): ?>

            <div class="informacoes-cartao">

                <div class="informacao">
                    <span class="informacao-titulo">
                        Cartão
                    </span>

                    <span class="informacao-valor">
                        <?= h($cartaoAtual['descricao']) ?>
                    </span>
                </div>

                <div class="informacao">
                    <span class="informacao-titulo">
                        Dia de fechamento
                    </span>

                    <span class="informacao-valor">
                        <?= (int)
                            $cartaoAtual['dia_fechamento']
                        ?>
                    </span>
                </div>

                <div class="informacao">
                    <span class="informacao-titulo">
                        Dia de vencimento
                    </span>

                    <span class="informacao-valor">
                        <?= (int)
                            $cartaoAtual['dia_vencimento']
                        ?>
                    </span>
                </div>

                <div class="informacao">
                    <span class="informacao-titulo">
                        Limite
                    </span>

                    <span class="informacao-valor">
                        R$
                        <?= number_format(
                            (float) $cartaoAtual['limite'],
                            2,
                            ',',
                            '.'
                        ) ?>
                    </span>
                </div>

            </div>

        <?php endif; ?>

    </div>

    <div class="bloco">

        <h2>Compras encontradas</h2>

        <form method="post" id="formFechamento">

            <input
                type="hidden"
                name="acao"
                value="gerar_fatura"
            >

            <input
                type="hidden"
                name="id_cartao"
                value="<?= (int) $idCartaoFiltro ?>"
            >

            <input
                type="hidden"
                name="data_inicial"
                value="<?= h($dataInicial) ?>"
            >

            <input
                type="hidden"
                name="data_final"
                value="<?= h($dataFinal) ?>"
            >

            <div class="tabela-container">

                <table>

                    <thead>

                        <tr>
                            <th class="centralizado">
                                <?php if (
                                    $statusFiltro === 'Aberto'
                                ): ?>
                                    <input
                                        type="checkbox"
                                        id="selecionarTodos"
                                        checked
                                        title="Selecionar todos"
                                    >
                                <?php endif; ?>
                            </th>

                            <th>Documento</th>
                            <th>Data</th>
                            <th>Descrição</th>
                            <th>Grupo</th>
                            <th>Status</th>
                            <th class="valor">Valor</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (
                        count($lancamentosCartao) === 0
                    ): ?>

                        <tr>
                            <td
                                colspan="7"
                                class="sem-registros"
                            >
                                Nenhuma compra encontrada para os filtros informados.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach (
                            $lancamentosCartao as $lancamento
                        ): ?>

                            <?php
                            $status =
                                $lancamento['status'];

                            $classeStatus =
                                'status-aberto';

                            if ($status === 'Faturado') {
                                $classeStatus =
                                    'status-faturado';

                            } elseif ($status === 'Pago') {
                                $classeStatus =
                                    'status-pago';

                            } elseif (
                                $status === 'Cancelado'
                            ) {
                                $classeStatus =
                                    'status-cancelado';
                            }

                            $selecionavel =
                                $status === 'Aberto'
                                && empty(
                                    $lancamento[
                                        'id_lancamento_fatura'
                                    ]
                                );
                            ?>

                            <tr>

                                <td class="centralizado">

                                    <?php if ($selecionavel): ?>

                                        <input
                                            type="checkbox"
                                            class="item-fatura"
                                            name="itens[]"
                                            value="<?= (int)
                                                $lancamento[
                                                    'id_lancamento_cartao'
                                                ]
                                            ?>"
                                            data-valor="<?= h(
                                                $lancamento['valor']
                                            ) ?>"
                                            checked
                                        >

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= h(
                                        $lancamento[
                                            'documento_numero'
                                        ]
                                    ) ?>
                                </td>

                                <td>
                                    <?= h(
                                        formatarData(
                                            $lancamento[
                                                'data_lancamento'
                                            ]
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= h(
                                        $lancamento['descricao']
                                    ) ?>
                                </td>

                                <td>
                                    <?= h(
                                        $lancamento[
                                            'grupo_descricao'
                                        ]
                                    ) ?>
                                </td>

                                <td>
                                    <span
                                        class="status <?= h(
                                            $classeStatus
                                        ) ?>"
                                    >
                                        <?= h($status) ?>
                                    </span>
                                </td>

                                <td class="valor">
                                    R$
                                    <?= number_format(
                                        (float)
                                        $lancamento['valor'],
                                        2,
                                        ',',
                                        '.'
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <tr class="total-linha">
                            <td colspan="6">
                                Total consultado
                            </td>

                            <td class="valor">
                                R$
                                <?= number_format(
                                    $totalConsulta,
                                    2,
                                    ',',
                                    '.'
                                ) ?>
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <?php if (
                $statusFiltro === 'Aberto'
                && $quantidadeAbertos > 0
            ): ?>

                <div class="resumo-fechamento">

                    <div class="total-destaque">

                        <small>
                            Total selecionado
                        </small>

                        <strong id="totalSelecionado">
                            R$
                            <?= number_format(
                                $totalConsulta,
                                2,
                                ',',
                                '.'
                            ) ?>
                        </strong>

                    </div>

                    <div class="campo">

                        <label for="data_fechamento">
                            Data do fechamento
                        </label>

                        <input
                            type="date"
                            name="data_fechamento"
                            id="data_fechamento"
                            value="<?= h(date('Y-m-d')) ?>"
                            required
                        >

                    </div>

                    <div class="campo">

                        <label for="data_vencimento">
                            Data de vencimento
                        </label>

                        <input
                            type="date"
                            name="data_vencimento"
                            id="data_vencimento"
                            value="<?= h(
                                $vencimentoSugerido
                            ) ?>"
                            required
                        >

                    </div>

                    <div class="campo">

                        <label for="id_grupo_financeiro">
                            Grupo da conta a pagar
                        </label>

                        <select
                            name="id_grupo_financeiro"
                            id="id_grupo_financeiro"
                            required
                        >
                            <option value="">
                                Selecione
                            </option>

                            <?php foreach (
                                $grupos as $grupo
                            ): ?>

                                <option
                                    value="<?= (int)
                                        $grupo['id_grupo']
                                    ?>"
                                >
                                    <?= h(
                                        $grupo['descricao']
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>

                <div class="observacao">
                    Recomendo utilizar um grupo específico,
                    como <strong>Cartões de Crédito</strong>.
                    Nos relatórios de despesas por grupo,
                    esse lançamento consolidado deverá ser
                    desconsiderado, porque as compras já estão
                    classificadas individualmente.
                </div>

                <div class="acoes-fechamento">

                    <button
                        type="submit"
                        class="botao-fechar"
                        id="botaoFechar"
                        onclick="return confirmarFechamento();"
                    >
                        Gerar conta a pagar
                    </button>

                </div>

            <?php endif; ?>

        </form>

    </div>

    <div class="bloco">

        <h2>Histórico de faturas geradas</h2>

        <div class="tabela-container">

            <table>

                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Cartão</th>
                        <th>Período das compras</th>
                        <th>Geração</th>
                        <th>Vencimento</th>
                        <th>Compras</th>
                        <th>Status</th>
                        <th class="valor">Valor</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (
                    count($historicoFaturas) === 0
                ): ?>

                    <tr>
                        <td
                            colspan="8"
                            class="sem-registros"
                        >
                            Nenhuma fatura foi gerada até o momento.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $historicoFaturas as $fatura
                    ): ?>

                        <?php
                        $statusFatura =
                            $fatura['status'];

                        $classeFatura =
                            'status-aberto';

                        if ($statusFatura === 'Pago') {
                            $classeFatura =
                                'status-pago';

                        } elseif (
                            $statusFatura === 'Recebido'
                        ) {
                            $classeFatura =
                                'status-pago';
                        }
                        ?>

                        <tr>

                            <td>
                                <?= (int)
                                    $fatura[
                                        'documento_numero'
                                    ]
                                ?>
                            </td>

                            <td>
                                <?= h(
                                    $fatura[
                                        'cartao_descricao'
                                    ]
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    formatarData(
                                        $fatura[
                                            'primeira_compra'
                                        ]
                                    )
                                ) ?>

                                a

                                <?= h(
                                    formatarData(
                                        $fatura[
                                            'ultima_compra'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    formatarData(
                                        $fatura[
                                            'data_lancamento'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    formatarData(
                                        $fatura[
                                            'data_vencimento'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td class="centralizado">
                                <?= (int)
                                    $fatura[
                                        'quantidade_compras'
                                    ]
                                ?>
                            </td>

                            <td>
                                <span
                                    class="status <?= h(
                                        $classeFatura
                                    ) ?>"
                                >
                                    <?= h($statusFatura) ?>
                                </span>
                            </td>

                            <td class="valor">
                                R$
                                <?= number_format(
                                    (float)
                                    $fatura['valor_nominal'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<script>
function atualizarTotalSelecionado()
{
    var caixas = document.querySelectorAll(
        '.item-fatura'
    );

    var total = 0;
    var quantidade = 0;

    for (var i = 0; i < caixas.length; i++) {

        if (caixas[i].checked) {

            var valor = parseFloat(
                caixas[i].getAttribute('data-valor')
            );

            if (!isNaN(valor)) {
                total += valor;
            }

            quantidade++;
        }
    }

    var campoTotal = document.getElementById(
        'totalSelecionado'
    );

    if (campoTotal) {
        campoTotal.textContent =
            total.toLocaleString(
                'pt-BR',
                {
                    style: 'currency',
                    currency: 'BRL'
                }
            );
    }

    var botao = document.getElementById(
        'botaoFechar'
    );

    if (botao) {
        botao.disabled = quantidade === 0;
    }
}

var selecionarTodos = document.getElementById(
    'selecionarTodos'
);

if (selecionarTodos) {

    selecionarTodos.addEventListener(
        'change',
        function () {

            var caixas = document.querySelectorAll(
                '.item-fatura'
            );

            for (
                var i = 0;
                i < caixas.length;
                i++
            ) {
                caixas[i].checked =
                    selecionarTodos.checked;
            }

            atualizarTotalSelecionado();
        }
    );
}

var itensFatura = document.querySelectorAll(
    '.item-fatura'
);

for (
    var indice = 0;
    indice < itensFatura.length;
    indice++
) {
    itensFatura[indice].addEventListener(
        'change',
        atualizarTotalSelecionado
    );
}

function confirmarFechamento()
{
    var caixas = document.querySelectorAll(
        '.item-fatura:checked'
    );

    if (caixas.length === 0) {
        alert(
            'Selecione pelo menos uma compra.'
        );

        return false;
    }

    var grupo = document.getElementById(
        'id_grupo_financeiro'
    );

    if (!grupo || grupo.value === '') {
        alert(
            'Selecione o grupo da conta a pagar.'
        );

        return false;
    }

    var vencimento = document.getElementById(
        'data_vencimento'
    );

    if (
        !vencimento
        || vencimento.value === ''
    ) {
        alert(
            'Informe a data de vencimento.'
        );

        return false;
    }

    return confirm(
        'Deseja gerar a conta a pagar com '
        + caixas.length
        + ' compra(s) selecionada(s)?'
    );
}

atualizarTotalSelecionado();
</script>

</body>

</html>