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
 * Formata valor monetário.
 */
function dinheiro($valor)
{
    return 'R$ '
        . number_format(
            (float) $valor,
            2,
            ',',
            '.'
        );
}

/**
 * Formata data para o padrão brasileiro.
 */
function dataBr($data)
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
 * Verifica se a data está no formato Y-m-d.
 */
function dataValida($data)
{
    if ($data === '') {
        return true;
    }

    $objeto = DateTime::createFromFormat(
        'Y-m-d',
        $data
    );

    return $objeto
        && $objeto->format('Y-m-d') === $data;
}

/**
 * Retorna o último dia válido de determinado mês.
 */
function ultimoDiaMes($ano, $mes)
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
 * Cria uma data usando um dia desejado, limitando-o ao
 * último dia existente no mês.
 */
function criarDataComDia(
    $ano,
    $mes,
    $dia
) {
    $ultimoDia = ultimoDiaMes(
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
 * Avança ou retrocede determinado número de meses.
 *
 * Retorna um array contendo ano e mês.
 */
function deslocarMes(
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
 * Calcula o vencimento previsto da compra de cartão.
 *
 * Regra:
 *
 * 1. Compra até o fechamento:
 *    entra na fatura que fecha naquele mês.
 *
 * 2. Compra depois do fechamento:
 *    entra na fatura que fecha no mês seguinte.
 *
 * 3. Se o dia de vencimento for posterior ao fechamento,
 *    o vencimento ocorre no mesmo mês do fechamento.
 *
 * 4. Se o dia de vencimento for igual ou anterior ao
 *    fechamento, o vencimento ocorre no mês seguinte.
 */
function calcularVencimentoCartao(
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

    $anoCompra = (int) $compra->format('Y');
    $mesCompra = (int) $compra->format('m');

    $dataFechamentoMes = criarDataComDia(
        $anoCompra,
        $mesCompra,
        $diaFechamento
    );

    /*
     * Define o mês do fechamento da fatura.
     */
    if ($dataCompra <= $dataFechamentoMes) {

        $anoFechamento = $anoCompra;
        $mesFechamento = $mesCompra;

    } else {

        $proximoMes = deslocarMes(
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
     * Define o mês do vencimento.
     */
    if (
        (int) $diaVencimento
        > (int) $diaFechamento
    ) {
        $anoVencimento =
            $anoFechamento;

        $mesVencimento =
            $mesFechamento;

    } else {

        $mesSeguinte = deslocarMes(
            $anoFechamento,
            $mesFechamento,
            1
        );

        $anoVencimento =
            $mesSeguinte['ano'];

        $mesVencimento =
            $mesSeguinte['mes'];
    }

    return criarDataComDia(
        $anoVencimento,
        $mesVencimento,
        $diaVencimento
    );
}

/* =========================================================
   FILTROS
========================================================= */
$data_inicio = isset($_GET['data_inicio'])
    ? trim($_GET['data_inicio'])
    : '';

$data_fim = isset($_GET['data_fim'])
    ? trim($_GET['data_fim'])
    : '';

$tipo = isset($_GET['tipo'])
    ? trim($_GET['tipo'])
    : '';

$status = isset($_GET['status'])
    ? trim($_GET['status'])
    : '';

$id_grupo = isset($_GET['id_grupo'])
    ? trim($_GET['id_grupo'])
    : '';

$id_autor = isset($_GET['id_autor'])
    ? trim($_GET['id_autor'])
    : '';

$forma = isset(
    $_GET['forma_de_pagamento_recebimento']
)
    ? trim(
        $_GET[
            'forma_de_pagamento_recebimento'
        ]
    )
    : '';

/* =========================================================
   VALIDAR DATAS
========================================================= */
if (
    !dataValida($data_inicio)
    || !dataValida($data_fim)
    || (
        $data_inicio !== ''
        && $data_fim !== ''
        && $data_inicio > $data_fim
    )
) {
    die(
        'O período informado é inválido.'
    );
}

/* =========================================================
   FILTROS DOS LANÇAMENTOS NORMAIS
========================================================= */
$where = array();
$params = array();

if ($data_inicio !== '') {
    $where[] =
        "l.data_vencimento >= :data_inicio";

    $params[':data_inicio'] =
        $data_inicio;
}

if ($data_fim !== '') {
    $where[] =
        "l.data_vencimento <= :data_fim";

    $params[':data_fim'] =
        $data_fim;
}

if ($tipo !== '') {
    $where[] =
        "l.tipo = :tipo";

    $params[':tipo'] =
        $tipo;
}

if ($status !== '') {
    $where[] =
        "l.status = :status";

    $params[':status'] =
        $status;
}

if ($id_grupo !== '') {
    $where[] =
        "l.id_grupo = :id_grupo";

    $params[':id_grupo'] =
        (int) $id_grupo;
}

if ($id_autor !== '') {
    $where[] =
        "l.id_autor = :id_autor";

    $params[':id_autor'] =
        (int) $id_autor;
}

if ($forma !== '') {
    $where[] =
        "l.forma_de_pagamento_recebimento = :forma";

    $params[':forma'] =
        $forma;
}

$sqlWhere = count($where) > 0
    ? 'WHERE ' . implode(' AND ', $where)
    : '';

/* =========================================================
   BUSCAR GRUPOS
========================================================= */
$stmtGrupos = $pdo->query("
    SELECT
        id_grupo,
        descricao
    FROM grupos
    ORDER BY descricao
");

$grupos = $stmtGrupos->fetchAll(
    PDO::FETCH_ASSOC
);

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
$sqlLancamentos = "
    SELECT
        l.id_lancamento,
        l.documento_numero,
        l.data_lancamento,
        l.descricao,
        l.tipo,
        l.data_vencimento,
        l.valor_nominal,
        l.data_pagamento,
        l.valor_pago,
        l.status,
        l.forma_de_pagamento_recebimento,
        l.id_grupo,
        l.id_autor,

        g.descricao AS grupo_descricao,
        a.nome AS autor_nome

    FROM lancamentos l

    LEFT JOIN grupos g
        ON g.id_grupo = l.id_grupo

    LEFT JOIN autores a
        ON a.id_autor = l.id_autor

    $sqlWhere

    ORDER BY
        l.data_vencimento ASC,
        l.id_lancamento ASC
";

$stmtLancamentos = $pdo->prepare(
    $sqlLancamentos
);

$stmtLancamentos->execute(
    $params
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
 * Acrescenta uma identificação interna de origem.
 */
foreach (
    $lancamentosNormais as $lancamentoNormal
) {
    $lancamentoNormal['origem'] =
        'lancamento';

    $lancamentoNormal[
        'quantidade_compras'
    ] = null;

    $lancamentos[] =
        $lancamentoNormal;
}

/* =========================================================
   BUSCAR COMPRAS ABERTAS DOS CARTÕES
========================================================= */

/*
 * Uma previsão de fatura somente deve aparecer quando:
 *
 * - o tipo estiver vazio ou for Pagar;
 * - o status estiver vazio ou for Aberto;
 * - não houver filtro de forma de pagamento.
 *
 * Uma previsão ainda não possui uma forma de pagamento
 * definida.
 */
$incluirPrevisoes = true;

if (
    $tipo !== ''
    && $tipo !== 'Pagar'
) {
    $incluirPrevisoes = false;
}

if (
    $status !== ''
    && $status !== 'Aberto'
) {
    $incluirPrevisoes = false;
}

if ($forma !== '') {
    $incluirPrevisoes = false;
}

$faturasPrevistas = array();

if ($incluirPrevisoes) {

    $whereCartoes = array(
        "lc.status = 'Aberto'",
        "lc.id_lancamento_fatura IS NULL"
    );

    $paramsCartoes = array();

    /*
     * Os filtros de grupo e autor são aplicados às compras
     * que formam a previsão.
     */
    if ($id_grupo !== '') {

        $whereCartoes[] =
            "lc.id_grupo = :id_grupo_cartao";

        $paramsCartoes[
            ':id_grupo_cartao'
        ] = (int) $id_grupo;
    }

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
            lc.id_cartao,
            lc.id_grupo,
            lc.id_autor,
            lc.valor,
            lc.status,

            c.descricao AS cartao_descricao,
            c.dia_fechamento,
            c.dia_vencimento,

            g.descricao AS grupo_descricao,
            a.nome AS autor_nome

        FROM lancamentos_cartoes lc

        INNER JOIN cartoes c
            ON c.id_cartao = lc.id_cartao

        LEFT JOIN grupos g
            ON g.id_grupo = lc.id_grupo

        LEFT JOIN autores a
            ON a.id_autor = lc.id_autor

        $whereCartoesSQL

        ORDER BY
            lc.data_lancamento ASC,
            lc.id_lancamento_cartao ASC
    ";

    $stmtComprasCartoes = $pdo->prepare(
        $sqlComprasCartoes
    );

    $stmtComprasCartoes->execute(
        $paramsCartoes
    );

    $comprasCartoes =
        $stmtComprasCartoes->fetchAll(
            PDO::FETCH_ASSOC
        );

    /* =====================================================
       AGRUPAR COMPRAS POR CARTÃO E VENCIMENTO
    ===================================================== */
    foreach (
        $comprasCartoes as $compra
    ) {
        $vencimentoPrevisto =
            calcularVencimentoCartao(
                $compra['data_lancamento'],
                $compra['dia_fechamento'],
                $compra['dia_vencimento']
            );

        if ($vencimentoPrevisto === null) {
            continue;
        }

        /*
         * O filtro de vencimento é aplicado após o cálculo.
         */
        if (
            $data_inicio !== ''
            && $vencimentoPrevisto < $data_inicio
        ) {
            continue;
        }

        if (
            $data_fim !== ''
            && $vencimentoPrevisto > $data_fim
        ) {
            continue;
        }

        $chaveFatura =
            (int) $compra['id_cartao']
            . '|'
            . $vencimentoPrevisto;

        if (
            !isset(
                $faturasPrevistas[$chaveFatura]
            )
        ) {
            $faturasPrevistas[
                $chaveFatura
            ] = array(
                'id_lancamento' => null,
                'documento_numero' => '',
                'data_lancamento' =>
                    $compra['data_lancamento'],
                'descricao' =>
                    'Fatura prevista - '
                    . $compra[
                        'cartao_descricao'
                    ],
                'tipo' => 'Pagar',
                'data_vencimento' =>
                    $vencimentoPrevisto,
                'valor_nominal' => 0,
                'data_pagamento' => null,
                'valor_pago' => 0,
                'status' => 'Aberto',
                'forma_de_pagamento_recebimento'
                    => '',
                'id_grupo' => null,
                'id_autor' => null,
                'grupo_descricao' =>
                    $id_grupo !== ''
                        ? (
                            !empty(
                                $compra[
                                    'grupo_descricao'
                                ]
                            )
                                ? $compra[
                                    'grupo_descricao'
                                ]
                                : 'Sem grupo'
                        )
                        : 'Diversos grupos',
                'autor_nome' =>
                    $id_autor !== ''
                        ? (
                            !empty(
                                $compra[
                                    'autor_nome'
                                ]
                            )
                                ? $compra[
                                    'autor_nome'
                                ]
                                : 'Sem autor'
                        )
                        : 'Diversos autores',
                'origem' =>
                    'fatura_prevista',
                'quantidade_compras' => 0,
                'id_cartao' =>
                    (int)
                    $compra['id_cartao'],
                'cartao_descricao' =>
                    $compra[
                        'cartao_descricao'
                    ]
            );
        }

        $faturasPrevistas[
            $chaveFatura
        ]['valor_nominal'] +=
            (float) $compra['valor'];

        $faturasPrevistas[
            $chaveFatura
        ]['quantidade_compras']++;

        /*
         * Mantém como data do lançamento a compra mais
         * antiga daquela previsão.
         */
        if (
            $compra['data_lancamento']
            <
            $faturasPrevistas[
                $chaveFatura
            ]['data_lancamento']
        ) {
            $faturasPrevistas[
                $chaveFatura
            ]['data_lancamento'] =
                $compra['data_lancamento'];
        }
    }

    /*
     * Acrescenta as previsões à lista principal.
     */
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
$total_receber_aberto = 0;
$total_recebido = 0;
$total_pagar_aberto = 0;
$total_pago = 0;
$total_faturas_previstas = 0;

foreach ($lancamentos as $lancamento) {

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

    if (
        $lancamento['tipo'] === 'Receber'
    ) {
        if (
            $lancamento['status']
            === 'Recebido'
        ) {
            $totalRecebidoItem =
                $valorPago > 0
                    ? $valorPago
                    : $valorNominal;

            $total_recebido +=
                $totalRecebidoItem;

        } else {
            $total_receber_aberto +=
                $valorNominal;
        }
    }

    if (
        $lancamento['tipo'] === 'Pagar'
    ) {
        if (
            $lancamento['status']
            === 'Pago'
        ) {
            $totalPagoItem =
                $valorPago > 0
                    ? $valorPago
                    : $valorNominal;

            $total_pago +=
                $totalPagoItem;

        } else {
            $total_pagar_aberto +=
                $valorNominal;
        }
    }

    if (
        isset($lancamento['origem'])
        && $lancamento['origem']
            === 'fatura_prevista'
    ) {
        $total_faturas_previstas +=
            $valorNominal;
    }
}

/* =========================================================
   SALDOS
========================================================= */
$saldo_previsto =
    (
        $total_receber_aberto
        + $total_recebido
    )
    -
    (
        $total_pagar_aberto
        + $total_pago
    );

$saldo_realizado =
    $total_recebido
    - $total_pago;
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Relatório Financeiro</title>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background: #f4f6f8;
    color: #222;
}

h2,
h3 {
    margin-top: 0;
}

.container {
    background: #ffffff;
    padding: 20px;
    border-radius: 10px;
    box-shadow:
        0 2px 8px rgba(0, 0, 0, 0.08);
}

.filtros {
    display: grid;
    grid-template-columns:
        repeat(
            auto-fit,
            minmax(220px, 1fr)
        );
    gap: 12px;
    margin-bottom: 20px;
}

label {
    font-weight: bold;
    font-size: 14px;
}

input,
select {
    width: 100%;
    padding: 8px;
    margin-top: 4px;
    border: 1px solid #cccccc;
    border-radius: 6px;
    box-sizing: border-box;
}

.botoes {
    margin: 15px 0 25px 0;
}

button,
.btn {
    background: #2c3e50;
    color: #ffffff;
    padding: 9px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    margin-right: 8px;
}

.btn-limpar {
    background: #777777;
}

.cards {
    display: grid;
    grid-template-columns:
        repeat(
            auto-fit,
            minmax(180px, 1fr)
        );
    gap: 12px;
    margin-bottom: 25px;
}

.card {
    padding: 15px;
    border-radius: 10px;
    color: #ffffff;
    font-weight: bold;
}

.card span {
    display: block;
    font-size: 13px;
    margin-bottom: 8px;
    font-weight: normal;
}

.receber {
    background: #2980b9;
}

.recebido {
    background: #27ae60;
}

.pagar {
    background: #c0392b;
}

.pago {
    background: #8e44ad;
}

.saldo-previsto {
    background: #d35400;
}

.saldo-realizado {
    background: #16a085;
}

.previsoes {
    background: #b9770e;
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
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
}

th {
    background: #2c3e50;
    color: #ffffff;
    padding: 9px;
    font-size: 14px;
    white-space: nowrap;
}

td {
    border: 1px solid #dddddd;
    padding: 8px;
    font-size: 14px;
}

tr:nth-child(even) {
    background: #f8f8f8;
}

.linha-prevista {
    background: #fff8dc !important;
}

.direita {
    text-align: right;
    white-space: nowrap;
}

.centro {
    text-align: center;
    white-space: nowrap;
}

.origem {
    white-space: nowrap;
}

.selo {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.selo-normal {
    background: #e9ecef;
    color: #343a40;
}

.selo-previsto {
    background: #ffe8a1;
    color: #674d00;
}

.sem-registro {
    padding: 20px;
    text-align: center;
    color: #777777;
}

@media print {

    .botoes,
    form,
    nav,
    header,
    .menu {
        display: none !important;
    }

    body {
        background: #ffffff;
        margin: 0;
    }

    .container {
        box-shadow: none;
        border-radius: 0;
    }

    .aviso-previsao {
        border: 1px solid #999999;
    }
}
</style>

</head>

<body>

<div class="container">

    <h2>Relatório Financeiro</h2>

    <form method="get">

        <div class="filtros">

            <div>
                <label>
                    Data Inicial do Vencimento
                </label>

                <input
                    type="date"
                    name="data_inicio"
                    value="<?= h($data_inicio) ?>"
                >
            </div>

            <div>
                <label>
                    Data Final do Vencimento
                </label>

                <input
                    type="date"
                    name="data_fim"
                    value="<?= h($data_fim) ?>"
                >
            </div>

            <div>
                <label>Tipo</label>

                <select name="tipo">

                    <option value="">
                        Todos
                    </option>

                    <?php foreach (
                        array(
                            'Pagar',
                            'Receber'
                        ) as $tipoOpcao
                    ): ?>

                        <option
                            value="<?= h(
                                $tipoOpcao
                            ) ?>"
                            <?= $tipo === $tipoOpcao
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= h($tipoOpcao) ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </div>

            <div>
                <label>Status</label>

                <select name="status">

                    <option value="">
                        Todos
                    </option>

                    <?php foreach (
                        array(
                            'Aberto',
                            'Recebido',
                            'Pago'
                        ) as $statusOpcao
                    ): ?>

                        <option
                            value="<?= h(
                                $statusOpcao
                            ) ?>"
                            <?= $status
                                === $statusOpcao
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= h($statusOpcao) ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </div>

            <div>
                <label>Classificação</label>

                <select name="id_grupo">

                    <option value="">
                        Todas
                    </option>

                    <?php foreach (
                        $grupos as $grupo
                    ): ?>

                        <option
                            value="<?= (int)
                                $grupo['id_grupo']
                            ?>"
                            <?= (
                                $id_grupo !== ''
                                && (int) $id_grupo
                                    === (int)
                                    $grupo['id_grupo']
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= h(
                                $grupo['descricao']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </div>

            <div>
                <label>
                    Autor / Favorecido
                </label>

                <select name="id_autor">

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
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= h($autor['nome']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </div>

            <div>
                <label>
                    Forma de Pagamento/Recebimento
                </label>

                <select
                    name="forma_de_pagamento_recebimento"
                >

                    <?php
                    $formasPagamento = array(
                        '' => 'Todas',
                        'Pix Recebido' =>
                            'Pix Recebido',
                        'Pix QR Code' =>
                            'Pix QR Code',
                        'Aplicação' =>
                            'Aplicação',
                        'Cartão Débito' =>
                            'Cartão Débito',
                        'Débito Automático' =>
                            'Débito Automático',
                        'Crédito em Conta' =>
                            'Crédito em Conta',
                        'Débito em Conta' =>
                            'Débito em Conta',
                        'Pagamento Boleto' =>
                            'Pagamento Boleto',
                        'Pix Pagamento' =>
                            'Pix Pagamento',
                        'Transação Bancária' =>
                            'Transação Bancária'
                    );
                    ?>

                    <?php foreach (
                        $formasPagamento
                        as $valorForma => $textoForma
                    ): ?>

                        <option
                            value="<?= h(
                                $valorForma
                            ) ?>"
                            <?= $forma === $valorForma
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= h($textoForma) ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </div>

        </div>

        <div class="botoes">

            <button type="submit">
                Filtrar
            </button>

            <a
                class="btn btn-limpar"
                href="<?= BASE_URL ?>relatorios/relatorio_financeiro.php"
            >
                Limpar
            </a>

            <button
                type="button"
                onclick="window.print()"
            >
                Imprimir / Salvar PDF
            </button>

        </div>

    </form>

    <div class="cards">

        <div class="card receber">
            <span>A Receber em Aberto</span>
            <?= dinheiro(
                $total_receber_aberto
            ) ?>
        </div>

        <div class="card recebido">
            <span>Total Recebido</span>
            <?= dinheiro(
                $total_recebido
            ) ?>
        </div>

        <div class="card pagar">
            <span>A Pagar em Aberto</span>
            <?= dinheiro(
                $total_pagar_aberto
            ) ?>
        </div>

        <div class="card pago">
            <span>Total Pago</span>
            <?= dinheiro(
                $total_pago
            ) ?>
        </div>

        <div class="card previsoes">
            <span>
                Faturas Previstas em Aberto
            </span>
            <?= dinheiro(
                $total_faturas_previstas
            ) ?>
        </div>

        <div class="card saldo-previsto">
            <span>Saldo Previsto</span>
            <?= dinheiro(
                $saldo_previsto
            ) ?>
        </div>

        <div class="card saldo-realizado">
            <span>Saldo Realizado</span>
            <?= dinheiro(
                $saldo_realizado
            ) ?>
        </div>

    </div>

    <?php if (
        $total_faturas_previstas > 0
    ): ?>

        <div class="aviso-previsao">

            <strong>
                Faturas previstas:
            </strong>

            as compras abertas dos cartões já foram
            incluídas no total a pagar e no saldo
            previsto. Quando uma fatura real for
            gerada, sua previsão será substituída
            automaticamente pelo lançamento da conta
            a pagar.

        </div>

    <?php endif; ?>

    <h3>Lançamentos Encontrados</h3>

    <?php if (
        count($lancamentos) === 0
    ): ?>

        <div class="sem-registro">

            Nenhum lançamento encontrado para os
            filtros informados.

        </div>

    <?php else: ?>

        <div class="tabela-container">

            <table>

                <thead>

                    <tr>
                        <th>Origem</th>
                        <th>Documento</th>
                        <th>Data Lançamento</th>
                        <th>Descrição</th>
                        <th>Grupo</th>
                        <th>Autor / Favorecido</th>
                        <th>Tipo</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                        <th>Forma</th>
                        <th>Compras</th>
                        <th>Valor Nominal</th>
                        <th>Valor Pago</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach (
                        $lancamentos as $lancamento
                    ): ?>

                        <?php
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
                        ?>

                        <tr class="<?= h(
                            $classeLinha
                        ) ?>">

                            <td class="origem">

                                <span
                                    class="selo <?= h(
                                        $classeSelo
                                    ) ?>"
                                >
                                    <?= h(
                                        $textoOrigem
                                    ) ?>
                                </span>

                            </td>

                            <td>
                                <?= h(
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

                            <td class="centro">
                                <?= dataBr(
                                    isset(
                                        $lancamento[
                                            'data_lancamento'
                                        ]
                                    )
                                        ? $lancamento[
                                            'data_lancamento'
                                        ]
                                        : ''
                                ) ?>
                            </td>

                            <td>
                                <?= h(
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
                                <?= h(
                                    !empty(
                                        $lancamento[
                                            'grupo_descricao'
                                        ]
                                    )
                                        ? $lancamento[
                                            'grupo_descricao'
                                        ]
                                        : 'Sem grupo'
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    !empty(
                                        $lancamento[
                                            'autor_nome'
                                        ]
                                    )
                                        ? $lancamento[
                                            'autor_nome'
                                        ]
                                        : 'Sem autor'
                                ) ?>
                            </td>

                            <td class="centro">
                                <?= h(
                                    isset(
                                        $lancamento['tipo']
                                    )
                                        ? $lancamento[
                                            'tipo'
                                        ]
                                        : ''
                                ) ?>
                            </td>

                            <td class="centro">
                                <?= dataBr(
                                    isset(
                                        $lancamento[
                                            'data_vencimento'
                                        ]
                                    )
                                        ? $lancamento[
                                            'data_vencimento'
                                        ]
                                        : ''
                                ) ?>
                            </td>

                            <td class="centro">
                                <?= h(
                                    isset(
                                        $lancamento[
                                            'status'
                                        ]
                                    )
                                        ? $lancamento[
                                            'status'
                                        ]
                                        : ''
                                ) ?>
                            </td>

                            <td>
                                <?php if ($ehPrevisao): ?>

                                    A definir

                                <?php else: ?>

                                    <?= h(
                                        isset(
                                            $lancamento[
                                                'forma_de_pagamento_recebimento'
                                            ]
                                        )
                                            ? $lancamento[
                                                'forma_de_pagamento_recebimento'
                                            ]
                                            : ''
                                    ) ?>

                                <?php endif; ?>
                            </td>

                            <td class="centro">

                                <?php if (
                                    $ehPrevisao
                                ): ?>

                                    <?= (int)
                                        $lancamento[
                                            'quantidade_compras'
                                        ]
                                    ?>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>

                            <td class="direita">
                                <?= dinheiro(
                                    isset(
                                        $lancamento[
                                            'valor_nominal'
                                        ]
                                    )
                                        ? $lancamento[
                                            'valor_nominal'
                                        ]
                                        : 0
                                ) ?>
                            </td>

                            <td class="direita">
                                <?= dinheiro(
                                    isset(
                                        $lancamento[
                                            'valor_pago'
                                        ]
                                    )
                                        ? $lancamento[
                                            'valor_pago'
                                        ]
                                        : 0
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>

</body>

</html>