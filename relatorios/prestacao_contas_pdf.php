<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

verificaAcesso();

require __DIR__ . '/../vendedor/fpdf/fpdf.php';

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
    ? (int) $_GET['id_autor']
    : 0;

/* =========================================================
   FUNÇÕES AUXILIARES
========================================================= */

/**
 * Verifica se uma data está no formato Y-m-d.
 */
function dataValida($data)
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
 * Converte uma data Y-m-d para d/m/Y.
 */
function dataBR($data)
{
    if (empty($data)) {
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
 * Formata valor monetário.
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
 * Converte texto UTF-8 para uso no FPDF.
 */
function textoPDF($texto)
{
    return utf8_decode(
        (string) $texto
    );
}

/* =========================================================
   VALIDAR PERÍODO
========================================================= */
if (
    !dataValida($data_inicio)
    || !dataValida($data_fim)
    || $data_inicio > $data_fim
) {
    die(
        'Período informado inválido.'
    );
}

/* =========================================================
   NOME DO AUTOR SELECIONADO
========================================================= */
$nomeAutorSelecionado = '';

if ($id_autor > 0) {

    $stmtAutor = $pdo->prepare("
        SELECT nome
        FROM autores
        WHERE id_autor = :id_autor
    ");

    $stmtAutor->execute(array(
        ':id_autor' => $id_autor
    ));

    $autorSelecionado = $stmtAutor->fetch(
        PDO::FETCH_ASSOC
    );

    if ($autorSelecionado) {
        $nomeAutorSelecionado =
            $autorSelecionado['nome'];
    }
}

/* =========================================================
   LANÇAMENTOS NORMAIS PAGOS / RECEBIDOS
========================================================= */

/*
 * A consulta considera somente movimentações realizadas:
 *
 * - data_pagamento dentro do período;
 * - valor_pago preenchido e maior que zero.
 *
 * Os lançamentos consolidados de faturas de cartão são
 * retirados desta consulta, pois as compras individuais
 * serão buscadas separadamente em lancamentos_cartoes.
 */
$whereLancamentos = array(
    "DATE(l.data_pagamento) BETWEEN :inicio AND :fim",
    "l.valor_pago IS NOT NULL",
    "l.valor_pago > 0",
    "
    NOT EXISTS (
        SELECT 1
        FROM lancamentos_cartoes lc_fatura
        WHERE lc_fatura.id_lancamento_fatura =
              l.id_lancamento
    )
    "
);

$paramsLancamentos = array(
    ':inicio' => $data_inicio,
    ':fim' => $data_fim
);

if ($id_autor > 0) {

    $whereLancamentos[] =
        "l.id_autor = :id_autor";

    $paramsLancamentos[':id_autor'] =
        $id_autor;
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
        l.tipo,
        l.valor_pago,
        l.valor_nominal,
        l.status,
        l.id_autor,

        g.descricao AS grupo,
        a.nome AS autor_nome

    FROM lancamentos l

    LEFT JOIN grupos g
        ON g.id_grupo = l.id_grupo

    LEFT JOIN autores a
        ON a.id_autor = l.id_autor

    $whereLancamentosSQL
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
   COMPRAS DE CARTÃO CUJA FATURA FOI PAGA
========================================================= */

/*
 * As compras são selecionadas pela data de pagamento
 * do lançamento consolidado da fatura.
 *
 * Exemplo:
 *
 * compra realizada em julho;
 * fatura paga em agosto;
 *
 * a compra entra na Prestação de Contas de agosto,
 * classificada pelo grupo original da compra.
 */
$whereCartoes = array(
    "DATE(lf.data_pagamento) BETWEEN :inicio_cartao AND :fim_cartao",
    "lf.valor_pago IS NOT NULL",
    "lf.valor_pago > 0",
    "lf.tipo = 'Pagar'",
    "lc.id_lancamento_fatura IS NOT NULL",
    "lc.status <> 'Cancelado'"
);

$paramsCartoes = array(
    ':inicio_cartao' => $data_inicio,
    ':fim_cartao' => $data_fim
);

if ($id_autor > 0) {

    $whereCartoes[] =
        "lc.id_autor = :id_autor_cartao";

    $paramsCartoes[':id_autor_cartao'] =
        $id_autor;
}

$whereCartoesSQL =
    'WHERE '
    . implode(
        ' AND ',
        $whereCartoes
    );

$sqlCartoes = "
    SELECT
        lc.id_lancamento_cartao,
        lc.valor,
        lc.id_grupo,
        lc.id_autor,
        lc.status,

        g.descricao AS grupo,
        a.nome AS autor_nome,
        c.descricao AS cartao_nome,

        lf.id_lancamento AS id_fatura,
        lf.data_pagamento AS data_pagamento_fatura,
        lf.valor_pago AS valor_pago_fatura,
        lf.status AS status_fatura

    FROM lancamentos_cartoes lc

    INNER JOIN lancamentos lf
        ON lf.id_lancamento =
           lc.id_lancamento_fatura

    INNER JOIN cartoes c
        ON c.id_cartao = lc.id_cartao

    LEFT JOIN grupos g
        ON g.id_grupo = lc.id_grupo

    LEFT JOIN autores a
        ON a.id_autor = lc.id_autor

    $whereCartoesSQL
";

$stmtCartoes = $pdo->prepare(
    $sqlCartoes
);

$stmtCartoes->execute(
    $paramsCartoes
);

$comprasCartoes =
    $stmtCartoes->fetchAll(
        PDO::FETCH_ASSOC
    );

/* =========================================================
   AGRUPAR ENTRADAS E SAÍDAS
========================================================= */
$entradas = array();
$saidas = array();

$total_entrada = 0;
$total_saida = 0;

$total_saidas_normais = 0;
$total_saidas_cartoes = 0;

/* =========================================================
   AGRUPAR LANÇAMENTOS NORMAIS
========================================================= */
foreach (
    $lancamentosNormais as $lancamento
) {
    $valorPago = isset(
        $lancamento['valor_pago']
    )
        ? (float) $lancamento['valor_pago']
        : 0;

    /*
     * Caso valor_pago esteja zerado por alguma inconsistência,
     * usa valor_nominal como segurança.
     */
    if (
        $valorPago <= 0
        && isset($lancamento['valor_nominal'])
    ) {
        $valorPago =
            (float)
            $lancamento['valor_nominal'];
    }

    if ($valorPago <= 0) {
        continue;
    }

    $grupo = !empty(
        $lancamento['grupo']
    )
        ? $lancamento['grupo']
        : 'Sem grupo';

    if (
        $lancamento['tipo'] === 'Receber'
    ) {
        if (!isset($entradas[$grupo])) {
            $entradas[$grupo] = 0;
        }

        $entradas[$grupo] += $valorPago;
        $total_entrada += $valorPago;

    } elseif (
        $lancamento['tipo'] === 'Pagar'
    ) {
        if (!isset($saidas[$grupo])) {
            $saidas[$grupo] = 0;
        }

        $saidas[$grupo] += $valorPago;
        $total_saida += $valorPago;
        $total_saidas_normais += $valorPago;
    }
}

/* =========================================================
   AGRUPAR COMPRAS DOS CARTÕES
========================================================= */
foreach (
    $comprasCartoes as $compra
) {
    $valorCompra = isset($compra['valor'])
        ? (float) $compra['valor']
        : 0;

    if ($valorCompra <= 0) {
        continue;
    }

    $grupo = !empty($compra['grupo'])
        ? $compra['grupo']
        : 'Sem grupo';

    if (!isset($saidas[$grupo])) {
        $saidas[$grupo] = 0;
    }

    $saidas[$grupo] += $valorCompra;
    $total_saida += $valorCompra;
    $total_saidas_cartoes += $valorCompra;
}

/* =========================================================
   ORDENAR GRUPOS
========================================================= */
ksort(
    $entradas,
    SORT_NATURAL | SORT_FLAG_CASE
);

ksort(
    $saidas,
    SORT_NATURAL | SORT_FLAG_CASE
);

/* =========================================================
   SALDO FINAL
========================================================= */
$saldo =
    $total_entrada
    - $total_saida;

/* =========================================================
   GERAR PDF
========================================================= */
$pdf = new FPDF();

$pdf->SetMargins(
    10,
    10,
    10
);

$pdf->AddPage();

/* =========================================================
   CABEÇALHO
========================================================= */
$pdf->SetFont(
    'Arial',
    'B',
    14
);

$pdf->Cell(
    0,
    10,
    textoPDF('Prestação de Contas'),
    0,
    1,
    'C'
);

$pdf->SetFont(
    'Arial',
    '',
    10
);

$pdf->Cell(
    0,
    6,
    textoPDF(
        'Período: '
        . dataBR($data_inicio)
        . ' a '
        . dataBR($data_fim)
    ),
    0,
    1,
    'C'
);

if ($nomeAutorSelecionado !== '') {

    $pdf->Cell(
        0,
        6,
        textoPDF(
            'Autor / Favorecido: '
            . $nomeAutorSelecionado
        ),
        0,
        1,
        'C'
    );
}

$pdf->Ln(5);

/* =========================================================
   ENTRADAS
========================================================= */
$pdf->SetFont(
    'Arial',
    'B',
    12
);

$pdf->Cell(
    0,
    8,
    textoPDF('Entradas'),
    0,
    1
);

$pdf->SetFont(
    'Arial',
    'B',
    10
);

$pdf->Cell(
    130,
    7,
    textoPDF('Grupo'),
    1,
    0,
    'L'
);

$pdf->Cell(
    60,
    7,
    textoPDF('Valor'),
    1,
    1,
    'R'
);

$pdf->SetFont(
    'Arial',
    '',
    10
);

if (count($entradas) === 0) {

    $pdf->Cell(
        190,
        7,
        textoPDF(
            'Nenhuma entrada encontrada no período.'
        ),
        1,
        1,
        'C'
    );

} else {

    foreach (
        $entradas as $grupo => $valor
    ) {
        $pdf->Cell(
            130,
            7,
            textoPDF($grupo),
            1,
            0,
            'L'
        );

        $pdf->Cell(
            60,
            7,
            moedaBR($valor),
            1,
            1,
            'R'
        );
    }
}

$pdf->SetFont(
    'Arial',
    'B',
    10
);

$pdf->Cell(
    130,
    7,
    textoPDF('Total Entradas'),
    1,
    0,
    'L'
);

$pdf->Cell(
    60,
    7,
    moedaBR($total_entrada),
    1,
    1,
    'R'
);

$pdf->Ln(5);

/* =========================================================
   SAÍDAS
========================================================= */
$pdf->SetFont(
    'Arial',
    'B',
    12
);

$pdf->Cell(
    0,
    8,
    textoPDF('Saídas'),
    0,
    1
);

$pdf->SetFont(
    'Arial',
    'B',
    10
);

$pdf->Cell(
    130,
    7,
    textoPDF('Grupo'),
    1,
    0,
    'L'
);

$pdf->Cell(
    60,
    7,
    textoPDF('Valor'),
    1,
    1,
    'R'
);

$pdf->SetFont(
    'Arial',
    '',
    10
);

if (count($saidas) === 0) {

    $pdf->Cell(
        190,
        7,
        textoPDF(
            'Nenhuma saída encontrada no período.'
        ),
        1,
        1,
        'C'
    );

} else {

    foreach (
        $saidas as $grupo => $valor
    ) {
        $pdf->Cell(
            130,
            7,
            textoPDF($grupo),
            1,
            0,
            'L'
        );

        $pdf->Cell(
            60,
            7,
            moedaBR($valor),
            1,
            1,
            'R'
        );
    }
}

$pdf->SetFont(
    'Arial',
    'B',
    10
);

$pdf->Cell(
    130,
    7,
    textoPDF('Total Saídas'),
    1,
    0,
    'L'
);

$pdf->Cell(
    60,
    7,
    moedaBR($total_saida),
    1,
    1,
    'R'
);

/* =========================================================
   SALDO FINAL
========================================================= */
$pdf->Ln(5);

$pdf->SetFont(
    'Arial',
    'B',
    12
);

$pdf->Cell(
    130,
    9,
    textoPDF('Saldo Final'),
    1,
    0,
    'L'
);

$pdf->Cell(
    60,
    9,
    moedaBR($saldo),
    1,
    1,
    'R'
);

/* =========================================================
   INFORMAÇÃO COMPLEMENTAR
========================================================= */
$pdf->Ln(6);

$pdf->SetFont(
    'Arial',
    '',
    9
);

$pdf->Cell(
    0,
    5,
    textoPDF(
        'Composição das saídas: '
        . moedaBR($total_saidas_normais)
        . ' em lançamentos normais e '
        . moedaBR($total_saidas_cartoes)
        . ' em compras de cartões.'
    ),
    0,
    1,
    'L'
);

$pdf->Cell(
    0,
    5,
    textoPDF(
        'As compras dos cartões foram classificadas pelos grupos originais e consideradas na data do pagamento da fatura.'
    ),
    0,
    1,
    'L'
);

/* =========================================================
   ASSINATURA
========================================================= */
$pdf->Ln(14);

$pdf->SetFont(
    'Arial',
    '',
    10
);

$pdf->Cell(
    0,
    6,
    '_________________________________________',
    0,
    1,
    'C'
);

$pdf->Cell(
    0,
    6,
    textoPDF('Tesouraria'),
    0,
    1,
    'C'
);

/* =========================================================
   SAÍDA DO PDF
========================================================= */
$pdf->Output(
    'I',
    'prestacao_contas.pdf'
);