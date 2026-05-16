<?php
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
verificaAcesso();

require __DIR__ . '/../vendedor/fpdf/fpdf.php';

$data_inicio = $_GET['inicio'] ?? date('Y-m-01');
$data_fim    = $_GET['fim'] ?? date('Y-m-t');
$id_autor    = $_GET['id_autor'] ?? '';

/* =====================
   FILTROS
===================== */
$where = [
    "DATE(l.data_pagamento) BETWEEN :inicio AND :fim",
    "l.valor_pago IS NOT NULL",
    "l.valor_pago > 0"
];

$params = [
    ':inicio' => $data_inicio,
    ':fim' => $data_fim
];

if ($id_autor !== '') {
    $where[] = "l.id_autor = :id_autor";
    $params[':id_autor'] = $id_autor;
}

$whereSQL = "WHERE " . implode(" AND ", $where);

/* =====================
   BUSCAR DADOS
===================== */
$sql = "SELECT 
            l.tipo,
            l.valor_pago,
            l.status,
            l.id_autor,
            g.descricao AS grupo,
            a.nome AS autor_nome
        FROM lancamentos l
        LEFT JOIN grupos g ON g.id_grupo = l.id_grupo
        LEFT JOIN autores a ON a.id_autor = l.id_autor
        $whereSQL";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   AGRUPAR
===================== */
$entradas = [];
$saidas = [];

$total_entrada = 0;
$total_saida = 0;

foreach ($dados as $d) {

    $valor = (float)($d['valor_pago'] ?? 0);
    $grupo = $d['grupo'] ?: 'Sem grupo';

    if ($d['tipo'] == 'Receber') {
        $entradas[$grupo] = ($entradas[$grupo] ?? 0) + $valor;
        $total_entrada += $valor;
    } else {
        $saidas[$grupo] = ($saidas[$grupo] ?? 0) + $valor;
        $total_saida += $valor;
    }
}

$saldo = $total_entrada - $total_saida;

/* =====================
   PDF
===================== */
$pdf = new FPDF();
$pdf->AddPage();

$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10,utf8_decode('Prestação de Contas'),0,1,'C');

$pdf->SetFont('Arial','',10);
$pdf->Cell(0,6,utf8_decode("Período: ".date('d/m/Y', strtotime($data_inicio))." a ".date('d/m/Y', strtotime($data_fim))),0,1,'C');

if ($id_autor !== '') {
    $stmtAutor = $pdo->prepare("SELECT nome FROM autores WHERE id_autor = :id_autor");
    $stmtAutor->execute([':id_autor' => $id_autor]);
    $autor = $stmtAutor->fetch(PDO::FETCH_ASSOC);

    if ($autor) {
        $pdf->Cell(0,6,utf8_decode("Autor / Favorecido: " . $autor['nome']),0,1,'C');
    }
}

$pdf->Ln(5);

/* =====================
   ENTRADAS
===================== */
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,8,utf8_decode('Entradas'),0,1);

$pdf->SetFont('Arial','',10);

foreach ($entradas as $grupo => $valor) {
    $pdf->Cell(130,6,utf8_decode($grupo),1);
    $pdf->Cell(60,6,'R$ '.number_format($valor,2,',','.'),1,1,'R');
}

$pdf->SetFont('Arial','B',10);
$pdf->Cell(130,6,'Total Entradas',1);
$pdf->Cell(60,6,'R$ '.number_format($total_entrada,2,',','.'),1,1,'R');

$pdf->Ln(5);

/* =====================
   SAÍDAS
===================== */
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,8,utf8_decode('Saídas'),0,1);

$pdf->SetFont('Arial','',10);

foreach ($saidas as $grupo => $valor) {
    $pdf->Cell(130,6,utf8_decode($grupo),1);
    $pdf->Cell(60,6,'R$ '.number_format($valor,2,',','.'),1,1,'R');
}

$pdf->SetFont('Arial','B',10);
$pdf->Cell(130,6,utf8_decode('Total Saídas'),1);
$pdf->Cell(60,6,'R$ '.number_format($total_saida,2,',','.'),1,1,'R');

/* =====================
   SALDO FINAL
===================== */
$pdf->SetFont('Arial','B',12);
$pdf->Cell(130,8,'Saldo Final',1);
$pdf->Cell(60,8,'R$ '.number_format($saldo,2,',','.'),1,1,'R');

$pdf->Ln(10);

/* =====================
   ASSINATURA
===================== */
$pdf->Cell(0,6,'_________________________________________',0,1,'C');
$pdf->Cell(0,6,utf8_decode('Tesouraria'),0,1,'C');

$pdf->Output('I','prestacao_contas.pdf');