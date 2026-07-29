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
========================================================= */

/**
 * Protege textos apresentados no HTML.
 */
function fluxoH($valor)
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * Formata a data para o padrão brasileiro.
 */
function fluxoDataBR($data)
{
    if (
        empty($data)
        || $data === '0000-00-00'
    ) {
        return '';
    }

    $objeto = DateTime::createFromFormat(
        'Y-m-d',
        $data
    );

    if (!$objeto) {
        return $data;
    }

    return $objeto->format('d/m/Y');
}

/**
 * Formata valores monetários.
 */
function fluxoMoedaBR($valor)
{
    return number_format(
        (float) $valor,
        2,
        ',',
        '.'
    );
}

/**
 * Valida uma data no formato Y-m-d.
 */
function fluxoDataValida($data)
{
    if (empty($data)) {
        return false;
    }

    $objeto = DateTime::createFromFormat(
        'Y-m-d',
        $data
    );

    return $objeto
        && $objeto->format('Y-m-d') === $data;
}

/**
 * Retorna o último dia existente em determinado mês.
 */
function fluxoUltimoDiaMes($ano, $mes)
{
    return (int) date(
        't',
        strtotime(
            sprintf(
                '%04d-%02d-01',
                $ano,
                $mes
            )
        )
    );
}

/**
 * Cria uma data, limitando o dia ao último dia
 * existente no mês.
 *
 * Exemplo:
 * vencimento dia 31 em fevereiro será ajustado
 * para o último dia de fevereiro.
 */
function fluxoCriarDataComDia(
    $ano,
    $mes,
    $dia
) {
    $ultimoDia = fluxoUltimoDiaMes(
        $ano,
        $mes
    );

    $diaValido = min(
        max((int) $dia, 1),
        $ultimoDia
    );

    return sprintf(
        '%04d-%02d-%02d',
        $ano,
        $mes,
        $diaValido
    );
}

/**
 * Avança ou retrocede uma quantidade de meses.
 */
function fluxoDeslocarMes(
    $ano,
    $mes,
    $quantidade
) {
    $dataBase = DateTime::createFromFormat(
        'Y-m-d',
        sprintf(
            '%04d-%02d-01',
            $ano,
            $mes
        )
    );

    if (!$dataBase) {
        return array(
            'ano' => (int) $ano,
            'mes' => (int) $mes
        );
    }

    if ($quantidade > 0) {
        $dataBase->modify(
            '+' . $quantidade . ' month'
        );

    } elseif ($quantidade < 0) {
        $dataBase->modify(
            $quantidade . ' month'
        );
    }

    return array(
        'ano' => (int) $dataBase->format('Y'),
        'mes' => (int) $dataBase->format('m')
    );
}

/**
 * Calcula o vencimento previsto da fatura de uma compra.
 *
 * Regra:
 *
 * 1. Compra realizada até o fechamento:
 *    entra na fatura que fecha naquele mês.
 *
 * 2. Compra realizada após o fechamento:
 *    entra na fatura que fecha no mês seguinte.
 *
 * 3. Quando o vencimento é posterior ao fechamento:
 *    vence no mesmo mês do fechamento.
 *
 * 4. Quando o vencimento é igual ou anterior ao fechamento:
 *    vence no mês seguinte ao fechamento.
 */
function fluxoCalcularVencimentoCartao(
    $dataCompra,
    $diaFechamento,
    $diaVencimento
) {
    $compra = DateTime::createFromFormat(
        'Y-m-d',
        $dataCompra
    );

    if (!$compra) {
        return null;
    }

    $diaFechamento = (int) $diaFechamento;
    $diaVencimento = (int) $diaVencimento;

    if (
        $diaFechamento <= 0
        || $diaVencimento <= 0
    ) {
        return null;
    }

    $anoCompra =
        (int) $compra->format('Y');

    $mesCompra =
        (int) $compra->format('m');

    $dataFechamentoMes =
        fluxoCriarDataComDia(
            $anoCompra,
            $mesCompra,
            $diaFechamento
        );

    /*
     * Determina o mês de fechamento da fatura.
     */
    if ($dataCompra <= $dataFechamentoMes) {

        $anoFechamento = $anoCompra;
        $mesFechamento = $mesCompra;

    } else {

        $proximoMes = fluxoDeslocarMes(
            $anoCompra,
            $mesCompra,
            1
        );

        $anoFechamento =
            $proximoMes['ano'];

        $mesFechamento =
            $proximoMes['mes'];
    }

    /*
     * Determina o mês de vencimento.
     */
    if (
        $diaVencimento
        > $diaFechamento
    ) {
        $anoVencimento =
            $anoFechamento;

        $mesVencimento =
            $mesFechamento;

    } else {

        $mesSeguinte = fluxoDeslocarMes(
            $anoFechamento,
            $mesFechamento,
            1
        );

        $anoVencimento =
            $mesSeguinte['ano'];

        $mesVencimento =
            $mesSeguinte['mes'];
    }

    return fluxoCriarDataComDia(
        $anoVencimento,
        $mesVencimento,
        $diaVencimento
    );
}

/* =========================================================
   FILTROS
========================================================= */
$data_inicio = isset($_GET['inicio'])
    ? trim($_GET['inicio'])
    : date('Y-m-01');

$data_fim = isset($_GET['fim'])
    ? trim($_GET['fim'])
    : date('Y-m-t');

$id_autor = isset($_GET['id_autor'])
    ? trim($_GET['id_autor'])
    : '';

/* =========================================================
   VALIDAR PERÍODO
========================================================= */
if (
    !fluxoDataValida($data_inicio)
    || !fluxoDataValida($data_fim)
    || $data_inicio > $data_fim
) {
    die(
        'O período informado é inválido.'
    );
}

/* =========================================================
   BUSCAR AUTORES
========================================================= */
$stmtAutores = $pdo->query("
    SELECT
        id_autor,
        nome
    FROM autores
    ORDER BY nome
");

$autores = $stmtAutores->fetchAll(
    PDO::FETCH_ASSOC
);

/* =========================================================
   BUSCAR LANÇAMENTOS NORMAIS
========================================================= */
$whereLancamentos = array(
    "l.data_vencimento BETWEEN :inicio AND :fim"
);

$paramsLancamentos = array(
    ':inicio' => $data_inicio,
    ':fim' => $data_fim
);

if ($id_autor !== '') {

    $whereLancamentos[] =
        "l.id_autor = :id_autor";

    $paramsLancamentos[':id_autor'] =
        (int) $id_autor;
}

$whereLancamentosSQL =
    'WHERE '
    . implode(
        ' AND ',
        $whereLancamentos
    );

$sqlLancamentos = "
    SELECT
        l.id_lancamento,
        l.documento_numero,
        l.descricao,
        l.tipo,
        l.valor_nominal,
        l.valor_pago,
        l.data_lancamento,
        l.data_vencimento,
        l.data_pagamento,
        l.status,
        l.id_autor,

        a.nome AS autor_nome

    FROM lancamentos l

    LEFT JOIN autores a
        ON a.id_autor = l.id_autor

    $whereLancamentosSQL

    ORDER BY
        l.data_vencimento ASC,
        l.descricao ASC,
        l.id_lancamento ASC
";

$stmtLancamentos = $pdo->prepare(
    $sqlLancamentos
);

$stmtLancamentos->execute(
    $paramsLancamentos
);

$lancamentosNormais =
    $stmtLancamentos->fetchAll(
        PDO::FETCH_ASSOC
    );

/* =========================================================
   PREPARAR LISTA UNIFICADA
========================================================= */
$lancamentos = array();

/*
 * Inclui os lançamentos normais.
 *
 * Aqui também aparecerão as faturas reais já geradas,
 * pois elas são compromissos normais da tabela lancamentos.
 */
foreach (
    $lancamentosNormais as $lancamento
) {
    $lancamento['origem'] =
        'lancamento';

    $lancamento['quantidade_compras'] =
        null;

    $lancamento['cartao_descricao'] =
        '';

    $lancamentos[] =
        $lancamento;
}

/* =========================================================
   BUSCAR COMPRAS ABERTAS DOS CARTÕES
========================================================= */

/*
 * Somente compras:
 *
 * - com status Aberto;
 * - ainda não vinculadas a uma fatura real.
 *
 * Quando a fatura for gerada, o campo
 * id_lancamento_fatura será preenchido e a previsão
 * desaparecerá automaticamente.
 */
$whereCartoes = array(
    "lc.status = 'Aberto'",
    "lc.id_lancamento_fatura IS NULL"
);

$paramsCartoes = array();

if ($id_autor !== '') {

    $whereCartoes[] =
        "lc.id_autor = :id_autor_cartao";

    $paramsCartoes[
        ':id_autor_cartao'
    ] = (int) $id_autor;
}

$whereCartoesSQL =
    'WHERE '
    . implode(
        ' AND ',
        $whereCartoes
    );

$sqlComprasCartoes = "
    SELECT
        lc.id_lancamento_cartao,
        lc.documento_numero,
        lc.data_lancamento,
        lc.descricao,
        lc.valor,
        lc.id_cartao,
        lc.id_autor,
        lc.status,

        c.descricao AS cartao_descricao,
        c.dia_fechamento,
        c.dia_vencimento,

        a.nome AS autor_nome

    FROM lancamentos_cartoes lc

    INNER JOIN cartoes c
        ON c.id_cartao = lc.id_cartao

    LEFT JOIN autores a
        ON a.id_autor = lc.id_autor

    $whereCartoesSQL

    ORDER BY
        lc.data_lancamento ASC,
        lc.id_lancamento_cartao ASC
";

$stmtCartoes = $pdo->prepare(
    $sqlComprasCartoes
);

$stmtCartoes->execute(
    $paramsCartoes
);

$comprasCartoes =
    $stmtCartoes->fetchAll(
        PDO::FETCH_ASSOC
    );

/* =========================================================
   AGRUPAR FATURAS PREVISTAS
========================================================= */
$faturasPrevistas = array();

foreach (
    $comprasCartoes as $compra
) {
    $vencimentoPrevisto =
        fluxoCalcularVencimentoCartao(
            $compra['data_lancamento'],
            $compra['dia_fechamento'],
            $compra['dia_vencimento']
        );

    if ($vencimentoPrevisto === null) {
        continue;
    }

    /*
     * O filtro do fluxo é aplicado sobre o vencimento
     * previsto da fatura, e não sobre a data da compra.
     */
    if (
        $vencimentoPrevisto
        < $data_inicio
    ) {
        continue;
    }

    if (
        $vencimentoPrevisto
        > $data_fim
    ) {
        continue;
    }

    /*
     * Agrupa pelo cartão e pelo vencimento previsto.
     */
    $chave =
        (int) $compra['id_cartao']
        . '|'
        . $vencimentoPrevisto;

    if (
        !isset(
            $faturasPrevistas[$chave]
        )
    ) {
        $faturasPrevistas[$chave] = array(
            'id_lancamento' => null,
            'documento_numero' => '',
            'descricao' =>
                'Fatura prevista - '
                . $compra[
                    'cartao_descricao'
                ],
            'tipo' => 'Pagar',
            'valor_nominal' => 0,
            'valor_pago' => 0,
            'data_lancamento' =>
                $compra['data_lancamento'],
            'data_vencimento' =>
                $vencimentoPrevisto,
            'data_pagamento' => null,
            'status' => 'Aberto',
            'id_autor' =>
                $id_autor !== ''
                    ? (int) $id_autor
                    : null,
            'autor_nome' =>
                $id_autor !== ''
                    ? (
                        !empty(
                            $compra['autor_nome']
                        )
                            ? $compra['autor_nome']
                            : 'Sem autor'
                    )
                    : 'Diversos autores',
            'origem' =>
                'fatura_prevista',
            'quantidade_compras' => 0,
            'cartao_descricao' =>
                $compra[
                    'cartao_descricao'
                ]
        );
    }

    $faturasPrevistas[
        $chave
    ]['valor_nominal'] +=
        (float) $compra['valor'];

    $faturasPrevistas[
        $chave
    ]['quantidade_compras']++;

    /*
     * Mantém como data de lançamento a compra mais
     * antiga que compõe a previsão.
     */
    if (
        $compra['data_lancamento']
        <
        $faturasPrevistas[
            $chave
        ]['data_lancamento']
    ) {
        $faturasPrevistas[
            $chave
        ]['data_lancamento'] =
            $compra['data_lancamento'];
    }
}

/* =========================================================
   ACRESCENTAR PREVISÕES À LISTA
========================================================= */
foreach (
    $faturasPrevistas as $faturaPrevista
) {
    $faturaPrevista['valor_nominal'] =
        round(
            $faturaPrevista[
                'valor_nominal'
            ],
            2
        );

    $lancamentos[] =
        $faturaPrevista;
}

/* =========================================================
   ORDENAR LISTA UNIFICADA
========================================================= */
usort(
    $lancamentos,
    function ($a, $b) {

        $dataA = isset(
            $a['data_vencimento']
        )
            ? $a['data_vencimento']
            : '';

        $dataB = isset(
            $b['data_vencimento']
        )
            ? $b['data_vencimento']
            : '';

        if ($dataA === $dataB) {

            /*
             * Quando existem dois registros no mesmo dia,
             * ordena pela descrição.
             */
            $descricaoA = isset(
                $a['descricao']
            )
                ? $a['descricao']
                : '';

            $descricaoB = isset(
                $b['descricao']
            )
                ? $b['descricao']
                : '';

            return strcasecmp(
                $descricaoA,
                $descricaoB
            );
        }

        return strcmp(
            $dataA,
            $dataB
        );
    }
);

/* =========================================================
   CALCULAR TOTAIS
========================================================= */
$saldo_previsto = 0;
$total_entradas_previstas = 0;
$total_saidas_previstas = 0;
$total_faturas_previstas = 0;

foreach (
    $lancamentos as $lancamento
) {
    $valorNominal = isset(
        $lancamento['valor_nominal']
    )
        ? (float)
            $lancamento['valor_nominal']
        : 0;

    $valorPago = isset(
        $lancamento['valor_pago']
    )
        ? (float)
            $lancamento['valor_pago']
        : 0;

    /*
     * Para lançamentos realizados, utiliza o valor pago.
     * Para lançamentos abertos, utiliza o valor nominal.
     */
    $valorConsiderado =
        $valorPago > 0
            ? $valorPago
            : $valorNominal;

    if (
        isset($lancamento['tipo'])
        && $lancamento['tipo']
            === 'Receber'
    ) {
        $total_entradas_previstas +=
            $valorConsiderado;
    }

    if (
        isset($lancamento['tipo'])
        && $lancamento['tipo']
            === 'Pagar'
    ) {
        $total_saidas_previstas +=
            $valorConsiderado;
    }

    if (
        isset($lancamento['origem'])
        && $lancamento['origem']
            === 'fatura_prevista'
    ) {
        $total_faturas_previstas +=
            $valorConsiderado;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Previsão de Fluxo de Caixa</title>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background: #f4f6f8;
    color: #222222;
}

h2 {
    margin-bottom: 18px;
}

form {
    background: #ffffff;
    padding: 15px;
    border: 1px solid #dddddd;
    border-radius: 8px;
    margin-bottom: 20px;
}

.filtros {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 10px;
}

.campo {
    display: flex;
    flex-direction: column;
}

label {
    font-weight: bold;
    margin-bottom: 4px;
    font-size: 14px;
}

input,
select,
button,
.botao {
    padding: 8px;
    border-radius: 5px;
    font-size: 14px;
}

input,
select {
    border: 1px solid #bbbbbb;
    min-width: 170px;
}

button,
.botao {
    border: none;
    background: #2c3e50;
    color: #ffffff;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.botao-limpar {
    background: #777777;
}

.cards {
    display: grid;
    grid-template-columns:
        repeat(
            auto-fit,
            minmax(200px, 1fr)
        );
    gap: 12px;
    margin-bottom: 20px;
}

.card {
    padding: 16px;
    border-radius: 8px;
    color: #ffffff;
}

.card small {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
}

.card strong {
    font-size: 22px;
}

.card-entrada {
    background: #207245;
}

.card-saida {
    background: #b3261e;
}

.card-fatura {
    background: #b9770e;
}

.card-saldo {
    background: #2867a5;
}

.aviso-previsao {
    background: #fff8e1;
    border: 1px solid #e5bd5a;
    color: #6d5300;
    border-radius: 7px;
    padding: 12px;
    margin-bottom: 20px;
    line-height: 1.5;
}

.tabela-container {
    overflow-x: auto;
}

table {
    border-collapse: collapse;
    width: 100%;
    background: #ffffff;
}

th,
td {
    padding: 8px;
    border: 1px solid #cccccc;
    font-size: 14px;
}

th {
    background: #2c3e50;
    color: #ffffff;
    white-space: nowrap;
}

tr:nth-child(even) {
    background: #f8f8f8;
}

.linha-prevista {
    background: #fff8dc !important;
}

.entrada {
    color: #207245;
    font-weight: bold;
    text-align: right;
    white-space: nowrap;
}

.saida {
    color: #b3261e;
    font-weight: bold;
    text-align: right;
    white-space: nowrap;
}

.saldo {
    font-weight: bold;
    text-align: right;
    white-space: nowrap;
}

.aberto {
    color: #c0392b;
    font-weight: bold;
}

.pago,
.recebido {
    color: #207245;
    font-weight: bold;
}

.centro {
    text-align: center;
    white-space: nowrap;
}

.selo {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    white-space: nowrap;
}

.selo-normal {
    background: #e7edf2;
    color: #34495e;
}

.selo-previsto {
    background: #ffe49a;
    color: #674d00;
}

.sem-registro {
    text-align: center;
    padding: 20px;
    color: #777777;
}

.total {
    background: #e9ecef;
}

.acoes {
    margin-top: 15px;
}

@media print {

    form,
    .acoes,
    nav,
    header,
    .menu {
        display: none !important;
    }

    body {
        margin: 5px;
        background: #ffffff;
    }

    .aviso-previsao {
        border: 1px solid #999999;
    }
}
</style>

</head>

<body>

<h2>Previsão de Fluxo de Caixa</h2>

<form method="get">

    <div class="filtros">

        <div class="campo">

            <label for="inicio">
                Data Inicial
            </label>

            <input
                type="date"
                id="inicio"
                name="inicio"
                value="<?= fluxoH(
                    $data_inicio
                ) ?>"
                required
            >

        </div>

        <div class="campo">

            <label for="fim">
                Data Final
            </label>

            <input
                type="date"
                id="fim"
                name="fim"
                value="<?= fluxoH(
                    $data_fim
                ) ?>"
                required
            >

        </div>

        <div class="campo">

            <label for="id_autor">
                Autor / Favorecido
            </label>

            <select
                id="id_autor"
                name="id_autor"
            >

                <option value="">
                    Todos
                </option>

                <?php foreach (
                    $autores as $autor
                ): ?>

                    <option
                        value="<?= (int)
                            $autor['id_autor']
                        ?>"
                        <?= (
                            $id_autor !== ''
                            && (int) $id_autor
                                === (int)
                                $autor['id_autor']
                        )
                            ? 'selected="selected"'
                            : ''
                        ?>
                    >
                        <?= fluxoH(
                            $autor['nome']
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="campo">

            <button type="submit">
                Filtrar
            </button>

        </div>

        <div class="campo">

            <a
                class="botao botao-limpar"
                href="<?= BASE_URL ?>relatorios/fluxo_caixa.php"
            >
                Limpar
            </a>

        </div>

    </div>

</form>

<div class="cards">

    <div class="card card-entrada">

        <small>
            Entradas previstas
        </small>

        <strong>
            R$ <?= fluxoMoedaBR(
                $total_entradas_previstas
            ) ?>
        </strong>

    </div>

    <div class="card card-saida">

        <small>
            Saídas previstas
        </small>

        <strong>
            R$ <?= fluxoMoedaBR(
                $total_saidas_previstas
            ) ?>
        </strong>

    </div>

    <div class="card card-fatura">

        <small>
            Faturas previstas dos cartões
        </small>

        <strong>
            R$ <?= fluxoMoedaBR(
                $total_faturas_previstas
            ) ?>
        </strong>

    </div>

    <div class="card card-saldo">

        <small>
            Saldo previsto do período
        </small>

        <strong>
            R$ <?= fluxoMoedaBR(
                $total_entradas_previstas
                - $total_saidas_previstas
            ) ?>
        </strong>

    </div>

</div>

<?php if (
    $total_faturas_previstas > 0
): ?>

    <div class="aviso-previsao">

        <strong>
            Faturas previstas:
        </strong>

        as compras abertas dos cartões foram agrupadas
        pelo cartão e pelo vencimento estimado. Quando
        uma fatura real for gerada, sua previsão
        desaparecerá automaticamente e será substituída
        pelo lançamento real.

    </div>

<?php endif; ?>

<div class="tabela-container">

    <table>

        <thead>

            <tr>
                <th>Origem</th>
                <th>Vencimento</th>
                <th>Documento</th>
                <th>Descrição</th>
                <th>Autor / Favorecido</th>
                <th>Status</th>
                <th>Compras</th>
                <th>Entrada Prevista</th>
                <th>Saída Prevista</th>
                <th>Saldo Previsto</th>
            </tr>

        </thead>

        <tbody>

            <?php if (
                empty($lancamentos)
            ): ?>

                <tr>

                    <td
                        colspan="10"
                        class="sem-registro"
                    >
                        Nenhum lançamento encontrado
                        no período.
                    </td>

                </tr>

            <?php endif; ?>

            <?php
            /*
             * Reinicia o saldo para calcular a evolução
             * linha por linha na ordem dos vencimentos.
             */
            $saldo_previsto = 0;
            ?>

            <?php foreach (
                $lancamentos as $lancamento
            ): ?>

                <?php
                $entrada = 0;
                $saida = 0;

                $valorNominal = isset(
                    $lancamento['valor_nominal']
                )
                    ? (float)
                        $lancamento['valor_nominal']
                    : 0;

                $valorPago = isset(
                    $lancamento['valor_pago']
                )
                    ? (float)
                        $lancamento['valor_pago']
                    : 0;

                $valorConsiderado =
                    $valorPago > 0
                        ? $valorPago
                        : $valorNominal;

                if (
                    isset($lancamento['tipo'])
                    && $lancamento['tipo']
                        === 'Receber'
                ) {
                    $entrada =
                        $valorConsiderado;

                    $saldo_previsto +=
                        $entrada;
                }

                if (
                    isset($lancamento['tipo'])
                    && $lancamento['tipo']
                        === 'Pagar'
                ) {
                    $saida =
                        $valorConsiderado;

                    $saldo_previsto -=
                        $saida;
                }

                $ehPrevisao =
                    isset(
                        $lancamento['origem']
                    )
                    && $lancamento['origem']
                        === 'fatura_prevista';

                $classeLinha =
                    $ehPrevisao
                        ? 'linha-prevista'
                        : '';

                $classeSelo =
                    $ehPrevisao
                        ? 'selo-previsto'
                        : 'selo-normal';

                $textoOrigem =
                    $ehPrevisao
                        ? 'Fatura prevista'
                        : 'Lançamento';

                $classeStatus =
                    strtolower(
                        isset(
                            $lancamento['status']
                        )
                            ? $lancamento['status']
                            : ''
                    );

                $autorExibido =
                    !empty(
                        $lancamento['autor_nome']
                    )
                        ? $lancamento[
                            'autor_nome'
                        ]
                        : 'Sem autor';
                ?>

                <tr class="<?= fluxoH(
                    $classeLinha
                ) ?>">

                    <td class="centro">

                        <span
                            class="selo <?= fluxoH(
                                $classeSelo
                            ) ?>"
                        >
                            <?= fluxoH(
                                $textoOrigem
                            ) ?>
                        </span>

                    </td>

                    <td class="centro">
                        <?= fluxoH(
                            fluxoDataBR(
                                isset(
                                    $lancamento[
                                        'data_vencimento'
                                    ]
                                )
                                    ? $lancamento[
                                        'data_vencimento'
                                    ]
                                    : ''
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= fluxoH(
                            isset(
                                $lancamento[
                                    'documento_numero'
                                ]
                            )
                                ? $lancamento[
                                    'documento_numero'
                                ]
                                : ''
                        ) ?>
                    </td>

                    <td>
                        <?= fluxoH(
                            isset(
                                $lancamento[
                                    'descricao'
                                ]
                            )
                                ? $lancamento[
                                    'descricao'
                                ]
                                : ''
                        ) ?>
                    </td>

                    <td>
                        <?= fluxoH(
                            $autorExibido
                        ) ?>
                    </td>

                    <td
                        class="<?= fluxoH(
                            $classeStatus
                        ) ?>"
                    >
                        <?= fluxoH(
                            isset(
                                $lancamento['status']
                            )
                                ? $lancamento['status']
                                : ''
                        ) ?>
                    </td>

                    <td class="centro">

                        <?php if ($ehPrevisao): ?>

                            <?= (int)
                                $lancamento[
                                    'quantidade_compras'
                                ]
                            ?>

                        <?php else: ?>

                            -

                        <?php endif; ?>

                    </td>

                    <td class="entrada">

                        <?php if ($entrada > 0): ?>

                            R$ <?= fluxoMoedaBR(
                                $entrada
                            ) ?>

                        <?php endif; ?>

                    </td>

                    <td class="saida">

                        <?php if ($saida > 0): ?>

                            R$ <?= fluxoMoedaBR(
                                $saida
                            ) ?>

                        <?php endif; ?>

                    </td>

                    <td class="saldo">
                        R$ <?= fluxoMoedaBR(
                            $saldo_previsto
                        ) ?>
                    </td>

                </tr>

            <?php endforeach; ?>

            <tr class="total">

                <th colspan="7">
                    Totais do Período
                </th>

                <th class="entrada">
                    R$ <?= fluxoMoedaBR(
                        $total_entradas_previstas
                    ) ?>
                </th>

                <th class="saida">
                    R$ <?= fluxoMoedaBR(
                        $total_saidas_previstas
                    ) ?>
                </th>

                <th>
                    R$ <?= fluxoMoedaBR(
                        $total_entradas_previstas
                        - $total_saidas_previstas
                    ) ?>
                </th>

            </tr>

        </tbody>

    </table>

</div>

<div class="acoes">

    <button
        type="button"
        onclick="window.print()"
    >
        Imprimir / Salvar em PDF
    </button>

</div>

</body>

</html>