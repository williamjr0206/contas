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
 * Protege textos exibidos no HTML.
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
 * Formata data para o padrão brasileiro.
 */
function dataBR($data)
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
 * Formata valores em reais.
 */
function moedaBR($valor)
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
 * Verifica se a data está no formato Y-m-d.
 */
function dataValida($data)
{
    if ($data === '') {
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
 * Retorna o último dia válido do mês.
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
 * Cria uma data limitando o dia ao último dia
 * existente naquele mês.
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
 * Avança ou retrocede meses.
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
 * Calcula a data prevista de vencimento da fatura
 * correspondente a uma compra.
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

    $anoCompra =
        (int) $compra->format('Y');

    $mesCompra =
        (int) $compra->format('m');

    /*
     * Data de fechamento existente naquele mês.
     */
    $dataFechamentoMes =
        criarDataComDia(
            $anoCompra,
            $mesCompra,
            $diaFechamento
        );

    /*
     * Compra realizada até o fechamento:
     * pertence à fatura que fecha no mesmo mês.
     *
     * Compra após o fechamento:
     * pertence à fatura que fecha no mês seguinte.
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
     * Quando o vencimento é depois do fechamento,
     * ocorre no mesmo mês do fechamento.
     *
     * Quando o vencimento é antes ou no mesmo dia
     * do fechamento, ocorre no mês seguinte.
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
$data_inicio = isset($_GET['inicio'])
    ? trim($_GET['inicio'])
    : date('Y-m-01');

$data_fim = isset($_GET['fim'])
    ? trim($_GET['fim'])
    : date('Y-m-t');

$status = isset($_GET['status'])
    ? trim($_GET['status'])
    : 'Aberto';

/* =========================================================
   VALIDAR FILTROS
========================================================= */
$statusPermitidos = array(
    'Todos',
    'Aberto',
    'Pago'
);

if (
    !in_array(
        $status,
        $statusPermitidos,
        true
    )
) {
    $status = 'Aberto';
}

if (
    !dataValida($data_inicio)
    || !dataValida($data_fim)
    || $data_inicio > $data_fim
) {
    die(
        'O período informado é inválido.'
    );
}

/* =========================================================
   BUSCAR COMPROMISSOS NORMAIS
========================================================= */
$where = array(
    "l.tipo = 'Pagar'",
    "l.data_vencimento BETWEEN :inicio AND :fim"
);

$params = array(
    ':inicio' => $data_inicio,
    ':fim' => $data_fim
);

if ($status !== 'Todos') {

    $where[] =
        "l.status = :status";

    $params[':status'] =
        $status;
}

$whereSQL =
    'WHERE '
    . implode(
        ' AND ',
        $where
    );

$sql = "
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
        l.forma_de_pagamento_recebimento,
        l.id_grupo,

        g.descricao AS grupo

    FROM lancamentos l

    LEFT JOIN grupos g
        ON g.id_grupo = l.id_grupo

    $whereSQL

    ORDER BY
        l.data_vencimento ASC,
        l.descricao ASC
";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$lancamentosNormais =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

/* =========================================================
   LISTA UNIFICADA
========================================================= */
$lancamentos = array();

/*
 * Acrescenta os compromissos normais.
 */
foreach (
    $lancamentosNormais as $lancamento
) {
    $lancamento['origem'] =
        'lancamento';

    $lancamento['quantidade_compras'] =
        null;

    $lancamentos[] =
        $lancamento;
}

/* =========================================================
   BUSCAR COMPRAS ABERTAS DOS CARTÕES
========================================================= */

/*
 * As previsões aparecem somente quando o filtro for:
 *
 * - Todos;
 * - Aberto.
 *
 * Não aparecem quando o filtro for Pago.
 */
$faturasPrevistas = array();

if (
    $status === 'Todos'
    || $status === 'Aberto'
) {
    $sqlComprasCartoes = "
        SELECT
            lc.id_lancamento_cartao,
            lc.data_lancamento,
            lc.valor,
            lc.id_cartao,

            c.descricao AS cartao_descricao,
            c.dia_fechamento,
            c.dia_vencimento

        FROM lancamentos_cartoes lc

        INNER JOIN cartoes c
            ON c.id_cartao = lc.id_cartao

        WHERE lc.status = 'Aberto'
          AND lc.id_lancamento_fatura IS NULL

        ORDER BY
            lc.data_lancamento ASC,
            lc.id_lancamento_cartao ASC
    ";

    $stmtCartoes = $pdo->query(
        $sqlComprasCartoes
    );

    $comprasCartoes =
        $stmtCartoes->fetchAll(
            PDO::FETCH_ASSOC
        );

    /* =====================================================
       AGRUPAR POR CARTÃO E VENCIMENTO PREVISTO
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
         * O filtro do relatório é aplicado ao
         * vencimento previsto.
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

        $chave =
            (int) $compra['id_cartao']
            . '|'
            . $vencimentoPrevisto;

        if (
            !isset(
                $faturasPrevistas[$chave]
            )
        ) {
            $faturasPrevistas[
                $chave
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
                'data_vencimento' =>
                    $vencimentoPrevisto,
                'valor_nominal' => 0,
                'data_pagamento' => null,
                'valor_pago' => 0,
                'status' => 'Aberto',
                'forma_de_pagamento_recebimento'
                    => '',
                'id_grupo' => null,
                'grupo' =>
                    'Cartão de crédito',
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
            $chave
        ]['valor_nominal'] +=
            (float) $compra['valor'];

        $faturasPrevistas[
            $chave
        ]['quantidade_compras']++;

        /*
         * Mantém como data de lançamento a data
         * da compra mais antiga daquela previsão.
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

    /*
     * Inclui as previsões na lista principal.
     */
    foreach (
        $faturasPrevistas as $fatura
    ) {
        $fatura['valor_nominal'] =
            round(
                $fatura['valor_nominal'],
                2
            );

        $lancamentos[] =
            $fatura;
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
$total_aberto = 0;
$total_pago = 0;
$total_geral = 0;
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

    $total_geral +=
        $valorNominal;

    if (
        isset($lancamento['status'])
        && $lancamento['status']
            === 'Pago'
    ) {
        $total_pago +=
            $valorPago > 0
                ? $valorPago
                : $valorNominal;

    } else {
        $total_aberto +=
            $valorNominal;
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
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

<meta charset="UTF-8">

<title>Compromissos de Pagamentos</title>

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background: #f4f6f8;
    color: #222222;
}

h2 {
    margin-bottom: 10px;
}

.filtros,
.resumo {
    background: #ffffff;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #dddddd;
}

.form-filtros {
    display: flex;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 10px;
}

.campo {
    display: flex;
    flex-direction: column;
}

label {
    font-weight: bold;
    margin-bottom: 4px;
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
}

button,
.botao {
    cursor: pointer;
    border: none;
    background: #2c3e50;
    color: #ffffff;
    text-decoration: none;
    display: inline-block;
}

.botao-limpar {
    background: #777777;
}

.cards {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.card {
    background: #ffffff;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #dddddd;
    min-width: 190px;
}

.card strong {
    display: block;
    font-size: 14px;
    color: #555555;
    margin-bottom: 7px;
}

.card span {
    font-size: 22px;
    font-weight: bold;
}

.card-previstas {
    background: #fff8e1;
    border-color: #e5bd5a;
}

.aviso-previsao {
    background: #fff8e1;
    border: 1px solid #e5bd5a;
    color: #6d5300;
    border-radius: 7px;
    padding: 12px;
    margin: 20px 0;
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

th,
td {
    border: 1px solid #cccccc;
    padding: 8px;
    font-size: 14px;
}

th {
    background: #2c3e50;
    color: #ffffff;
    white-space: nowrap;
}

tr:nth-child(even) {
    background: #f9f9f9;
}

.linha-prevista {
    background: #fff8dc !important;
}

.aberto {
    color: #c0392b;
    font-weight: bold;
}

.pago {
    color: #207245;
    font-weight: bold;
}

.valor {
    text-align: right;
    white-space: nowrap;
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
    background: #e9ecef;
    color: #343a40;
}

.selo-previsto {
    background: #ffe8a1;
    color: #674d00;
}

.acoes {
    margin-top: 15px;
}

.sem-registro {
    text-align: center;
    padding: 20px;
    color: #777777;
}

@media print {

    .filtros,
    .acoes,
    .menu,
    nav,
    header {
        display: none !important;
    }

    body {
        background: #ffffff;
        margin: 10px;
    }

    .aviso-previsao {
        border: 1px solid #999999;
    }
}
</style>

</head>

<body>

<h2>Compromissos de Pagamentos</h2>

<div class="filtros">

    <form
        method="get"
        class="form-filtros"
    >

        <div class="campo">

            <label for="inicio">
                Data inicial
            </label>

            <input
                type="date"
                id="inicio"
                name="inicio"
                value="<?= h($data_inicio) ?>"
                required
            >

        </div>

        <div class="campo">

            <label for="fim">
                Data final
            </label>

            <input
                type="date"
                id="fim"
                name="fim"
                value="<?= h($data_fim) ?>"
                required
            >

        </div>

        <div class="campo">

            <label for="status">
                Status
            </label>

            <select
                id="status"
                name="status"
            >

                <?php foreach (
                    array(
                        'Todos',
                        'Aberto',
                        'Pago'
                    ) as $statusOpcao
                ): ?>

                    <option
                        value="<?= h(
                            $statusOpcao
                        ) ?>"
                        <?= $status
                            === $statusOpcao
                            ? 'selected="selected"'
                            : ''
                        ?>
                    >
                        <?= h($statusOpcao) ?>
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
                href="<?= BASE_URL ?>relatorios/compromissos_pagamentos.php"
            >
                Limpar
            </a>

        </div>

    </form>

</div>

<div class="cards">

    <div class="card">

        <strong>
            Total de compromissos
        </strong>

        <span>
            <?= moedaBR($total_geral) ?>
        </span>

    </div>

    <div class="card">

        <strong>
            Total em aberto
        </strong>

        <span>
            <?= moedaBR($total_aberto) ?>
        </span>

    </div>

    <div class="card">

        <strong>
            Total pago
        </strong>

        <span>
            <?= moedaBR($total_pago) ?>
        </span>

    </div>

    <div class="card card-previstas">

        <strong>
            Faturas previstas em aberto
        </strong>

        <span>
            <?= moedaBR(
                $total_faturas_previstas
            ) ?>
        </span>

    </div>

</div>

<?php if (
    $total_faturas_previstas > 0
): ?>

    <div class="aviso-previsao">

        <strong>
            Faturas previstas:
        </strong>

        as compras abertas dos cartões foram
        agrupadas pelo cartão e pela data prevista
        de vencimento. Quando uma fatura real for
        gerada, sua previsão desaparecerá
        automaticamente e será substituída pelo
        compromisso registrado em lançamentos.

    </div>

<?php endif; ?>

<br>

<div class="tabela-container">

    <table>

        <thead>

            <tr>
                <th>Origem</th>
                <th>Vencimento</th>
                <th>Documento</th>
                <th>Descrição</th>
                <th>Grupo</th>
                <th>Compras</th>
                <th>Valor</th>
                <th>Status</th>
                <th>Data Pagamento</th>
                <th>Valor Pago</th>
                <th>Forma</th>
            </tr>

        </thead>

        <tbody>

            <?php if (
                count($lancamentos) === 0
            ): ?>

                <tr>

                    <td
                        colspan="11"
                        class="sem-registro"
                    >
                        Nenhum compromisso encontrado
                        para o período selecionado.
                    </td>

                </tr>

            <?php else: ?>

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

                    $classeStatus =
                        isset(
                            $lancamento['status']
                        )
                        && $lancamento['status']
                            === 'Pago'
                            ? 'pago'
                            : 'aberto';
                    ?>

                    <tr class="<?= h(
                        $classeLinha
                    ) ?>">

                        <td class="centro">

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

                        <td class="centro">
                            <?= h(
                                dataBR(
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
                                        'grupo'
                                    ]
                                )
                                    ? $lancamento[
                                        'grupo'
                                    ]
                                    : 'Sem grupo'
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

                        <td class="valor">
                            <?= moedaBR(
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

                        <td
                            class="<?= h(
                                $classeStatus
                            ) ?>"
                        >
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

                        <td class="centro">
                            <?= h(
                                dataBR(
                                    isset(
                                        $lancamento[
                                            'data_pagamento'
                                        ]
                                    )
                                        ? $lancamento[
                                            'data_pagamento'
                                        ]
                                        : ''
                                )
                            ) ?>
                        </td>

                        <td class="valor">

                            <?php if (
                                !empty(
                                    $lancamento[
                                        'valor_pago'
                                    ]
                                )
                            ): ?>

                                <?= moedaBR(
                                    $lancamento[
                                        'valor_pago'
                                    ]
                                ) ?>

                            <?php endif; ?>

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

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

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