<?php
// Minimal PDF generator wrapper using FPDF (bundled or fallback to stub)

if (!class_exists('FPDF')) {
  // Very small fallback stub to avoid fatal if FPDF not present
  class FPDF {
    public function AddPage() {}
    public function SetFont($fam,$style='',$size=12) {}
    public function Cell($w,$h=0,$txt='',$border=0,$ln=0,$align='',$fill=false,$link='') {}
    public function Ln($h=null) {}
    public function Output($dest='', $name='doc.pdf') { header('Content-Type: application/pdf'); echo "%PDF-1.3\n% Stub PDF\n"; }
  }
}

class PDFGenerator {
  public static function generateAdmissionLetter(array $data, string $targetPath): void {
    $dir = dirname($targetPath);
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10, $data['institution_name'] ?? 'Institution', 0, 1, 'C');
    $pdf->SetFont('Arial','',12);
    $pdf->Ln(6);
    $pdf->Cell(0,8, 'Admission Offer Letter', 0, 1, 'C');
    $pdf->Ln(10);
    $pdf->Cell(0,8, 'Student: '.(($data['student_name'] ?? '')), 0, 1);
    $pdf->Cell(0,8, 'Program: '.(($data['program_name'] ?? '')), 0, 1);
    $pdf->Cell(0,8, 'Application #: '.(($data['application_id'] ?? '')), 0, 1);
    $pdf->Ln(6);
    $body = $data['body'] ?? 'Congratulations! You have been admitted.';
    $pdf->MultiCell(0,7, $body);
    $pdf->Ln(10);
    $pdf->Cell(0,8, 'Date: '.date('Y-m-d'), 0, 1);
    $pdf->Ln(10);
    $pdf->Cell(0,8, 'Signed,', 0, 1);
    $pdf->Cell(0,8, 'Admissions Office', 0, 1);

    // Save
    $tmp = tempnam(sys_get_temp_dir(), 'pdf_');
    ob_start();
    $pdf->Output('I', 'letter.pdf'); // output to buffer
    $content = ob_get_clean();
    file_put_contents($targetPath, $content);
  }
}


