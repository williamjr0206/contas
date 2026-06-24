<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
verificaAcesso();

require __DIR__ . '/../vendedor/fpdf/fpdf.php';

function texto($txt) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $txt ?? '');
}

function moeda($valor) {
    return number_format((float)$valor, 2, ',', '.');
}

$id_receita = $_GET['id_receita'] ?? null;

if (!$id_receita) {
    die('Receita não informada.');
}

$stmt = $pdo->prepare("
    SELECT r.*, c.nome_categoria
    FROM receitas r
    INNER JOIN receitas_categorias c ON c.id_categoria = r.id_categoria
    WHERE r.id_receita = :id
");
$stmt->execute([':id' => $id_receita]);
$receita = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$receita) {
    die('Receita não encontrada.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM receitas_ingredientes
    WHERE id_receita = :id
    ORDER BY id_ingrediente
");
$stmt->execute([':id' => $id_receita]);
$ingredientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT *
    FROM receitas_processos
    WHERE id_receita = :id
    ORDER BY sequencia, id_processo
");
$stmt->execute([':id' => $id_receita]);
$etapas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_receita = 0;
foreach ($ingredientes as $ing) {
    $total_receita += (float)$ing['custo_total'];
}

class PDF extends FPDF
{
    function Header()
    {
        $this->SetFillColor(44, 62, 80);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 12, texto('Caderno de Receitas da Zenilda'), 0, 1, 'C', true);
        $this->Ln(5);
        $this->SetTextColor(0, 0, 0);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 10, texto('Página ') . $this->PageNo(), 0, 0, 'C');
    }

    function TituloSecao($titulo)
    {
        $this->Ln(4);
        $this->SetFillColor(236, 240, 241);
        $this->SetTextColor(44, 62, 80);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, texto($titulo), 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 18);
$pdf->MultiCell(0, 9, texto($receita['titulo']));
$pdf->Ln(3);

if (!empty($receita['foto_receita'])) {
    $fotoPath = __DIR__ . '/../' . $receita['foto_receita'];

    if (file_exists($fotoPath)) {
        $ext = strtolower(pathinfo($fotoPath, PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $pdf->Image($fotoPath, 15, $pdf->GetY(), 70);
            $pdf->Ln(55);
        }
    }
}

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(45, 7, texto('Categoria:'), 0, 0);
$pdf->Cell(0, 7, texto($receita['nome_categoria']), 0, 1);

$pdf->Cell(45, 7, texto('Tempo de preparo:'), 0, 0);
$pdf->Cell(0, 7, texto(($receita['tempo_preparo_minutos'] ?? '') . ' min'), 0, 1);

$pdf->Cell(45, 7, texto('Rendimento:'), 0, 0);
$pdf->Cell(0, 7, texto($receita['rendimento'] ?? ''), 0, 1);

$pdf->Cell(45, 7, texto('Dificuldade:'), 0, 0);
$pdf->Cell(0, 7, texto($receita['dificuldade'] ?? ''), 0, 1);

$pdf->Cell(45, 7, texto('Custo estimado:'), 0, 0);
$pdf->Cell(0, 7, texto('R$ ' . moeda($total_receita)), 0, 1);

if (!empty($receita['fonte_site'])) {
    $pdf->Cell(45, 7, texto('Fonte:'), 0, 0);
    $pdf->MultiCell(0, 7, texto($receita['fonte_site']));
}

if (!empty($receita['descricao'])) {
    $pdf->TituloSecao('Descrição');
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 6, texto($receita['descricao']));
}

$pdf->TituloSecao('Ingredientes');

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(44, 62, 80);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(85, 8, texto('Ingrediente'), 1, 0, 'L', true);
$pdf->Cell(30, 8, texto('Quantidade'), 1, 0, 'C', true);
$pdf->Cell(25, 8, texto('Unidade'), 1, 0, 'C', true);
$pdf->Cell(35, 8, texto('Custo R$'), 1, 1, 'R', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 9);

foreach ($ingredientes as $ing) {
    $pdf->Cell(85, 7, texto(substr($ing['descricao_ingrediente'], 0, 45)), 1);
    $pdf->Cell(30, 7, number_format((float)$ing['quantidade'], 3, ',', '.'), 1, 0, 'C');
    $pdf->Cell(25, 7, texto($ing['unidade']), 1, 0, 'C');
    $pdf->Cell(35, 7, moeda($ing['custo_total']), 1, 1, 'R');
}

$pdf->TituloSecao('Modo de Preparo');

$pdf->SetFont('Arial', '', 10);

if (!empty($etapas)) {
    foreach ($etapas as $etapa) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->MultiCell(0, 6, texto($etapa['sequencia'] . '. Etapa'));
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 6, texto($etapa['descricao_etapa']));
        $pdf->Ln(2);
    }
} else {
    $pdf->MultiCell(0, 6, texto($receita['modo_preparo'] ?? ''));
}

if (!empty($receita['observacoes'])) {
    $pdf->TituloSecao('Observações');
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 6, texto($receita['observacoes']));
}

ob_end_clean();
$pdf->Output('I', 'receita_' . $id_receita . '.pdf');
exit;