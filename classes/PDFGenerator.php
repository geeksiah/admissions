<?php
/**
 * PDF Generator for Admissions Management System
 * Generates fillable PDF forms, receipts, and reports
 */

class PDFGenerator {
    private $db;
    private $fpdf;
    
    public function __construct($database) {
        $this->db = $database;
        // Note: In production, you would include FPDF library
        // require_once 'vendor/fpdf/fpdf.php';
    }
    
    /**
     * Generate fillable application form PDF
     */
    public function generateApplicationForm($programId, $applicationNumber = null) {
        try {
            $programModel = new Program($this->db);
            $program = $programModel->getById($programId);
            
            if (!$program) {
                throw new Exception('Program not found');
            }
            
            // Generate application number if not provided
            if (!$applicationNumber) {
                $applicationNumber = $this->generateApplicationNumber();
            }
            
            // Create PDF
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            
            // Set font
            $pdf->SetFont('Arial', 'B', 16);
            
            // Header
            $this->addHeader($pdf, $program);
            
            // Application number and QR code
            $this->addApplicationInfo($pdf, $applicationNumber);
            
            // Form fields
            $this->addPersonalInfoSection($pdf);
            $this->addAcademicInfoSection($pdf);
            $this->addContactInfoSection($pdf);
            $this->addDocumentChecklist($pdf);
            $this->addSignatureSection($pdf);
            
            // Footer
            $this->addFooter($pdf);
            
            return $pdf;
            
        } catch (Exception $e) {
            error_log("PDF Generation Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate receipt PDF
     */
    public function generateReceipt($paymentData) {
        try {
            $pdf = new FPDF('P', 'mm', 'A5');
            $pdf->AddPage();
            
            // Set font
            $pdf->SetFont('Arial', 'B', 14);
            
            // Header with logo
            $this->addReceiptHeader($pdf);
            
            // Receipt details
            $this->addReceiptDetails($pdf, $paymentData);
            
            // Payment information
            $this->addPaymentInfo($pdf, $paymentData);
            
            // Footer
            $this->addReceiptFooter($pdf);
            
            return $pdf;
            
        } catch (Exception $e) {
            error_log("Receipt Generation Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate admission letter PDF
     */
    public function generateAdmissionLetter($applicationId) {
        try {
            $applicationModel = new Application($this->db);
            $application = $applicationModel->getById($applicationId);
            
            if (!$application) {
                throw new Exception('Application not found');
            }
            
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            
            // Set font
            $pdf->SetFont('Arial', 'B', 14);
            
            // Header
            $this->addLetterHeader($pdf);
            
            // Letter content
            $this->addLetterContent($pdf, $application);
            
            // Signature section
            $this->addLetterSignature($pdf);
            
            return $pdf;
            
        } catch (Exception $e) {
            error_log("Admission Letter Generation Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add header to application form
     */
    private function addHeader($pdf, $program) {
        // Institution name
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->Cell(0, 10, 'UNIVERSITY ADMISSIONS FORM', 0, 1, 'C');
        
        // Program name
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, $program['program_name'], 0, 1, 'C');
        $pdf->Cell(0, 8, $program['department'], 0, 1, 'C');
        
        $pdf->Ln(5);
        
        // Instructions
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Please fill in all required fields. Use black ink and write clearly.', 0, 1, 'L');
        $pdf->Cell(0, 5, 'This form can be filled digitally or printed and filled manually.', 0, 1, 'L');
        $pdf->Ln(3);
    }
    
    /**
     * Add application information section
     */
    private function addApplicationInfo($pdf, $applicationNumber) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'APPLICATION INFORMATION', 0, 1, 'L');
        $pdf->SetLineWidth(0.5);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(3);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(40, 6, 'Application Number:', 0, 0, 'L');
        $pdf->Cell(60, 6, $applicationNumber, 0, 0, 'L');
        
        // Add QR code placeholder
        $pdf->Cell(40, 6, 'QR Code:', 0, 0, 'L');
        $pdf->Cell(0, 6, '[QR CODE PLACEHOLDER]', 0, 1, 'L');
        
        $pdf->Cell(40, 6, 'Date:', 0, 0, 'L');
        $pdf->Cell(60, 6, date('Y-m-d'), 0, 1, 'L');
        
        $pdf->Ln(5);
    }
    
    /**
     * Add personal information section
     */
    private function addPersonalInfoSection($pdf) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'PERSONAL INFORMATION', 0, 1, 'L');
        $pdf->SetLineWidth(0.5);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(3);
        
        $pdf->SetFont('Arial', '', 10);
        
        // Name fields
        $pdf->Cell(30, 6, 'First Name:', 0, 0, 'L');
        $pdf->Cell(70, 6, '________________________', 0, 0, 'L');
        $pdf->Cell(20, 6, 'Last Name:', 0, 0, 'L');
        $pdf->Cell(0, 6, '________________________', 0, 1, 'L');
        
        // Other personal info
        $pdf->Cell(30, 6, 'Date of Birth:', 0, 0, 'L');
        $pdf->Cell(30, 6, '____/____/____', 0, 0, 'L');
        $pdf->Cell(20, 6, 'Gender:', 0, 0, 'L');
        $pdf->Cell(20, 6, '□ Male □ Female', 0, 1, 'L');
        
        $pdf->Cell(30, 6, 'Nationality:', 0, 0, 'L');
        $pdf->Cell(70, 6, '________________________', 0, 0, 'L');
        $pdf->Cell(20, 6, 'Marital Status:', 0, 0, 'L');
        $pdf->Cell(0, 6, '□ Single □ Married', 0, 1, 'L');
        
        $pdf->Ln(5);
    }
    
    /**
     * Add academic information section
     */
    private function addAcademicInfoSection($pdf) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'ACADEMIC INFORMATION', 0, 1, 'L');
        $pdf->SetLineWidth(0.5);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(3);
        
        $pdf->SetFont('Arial', '', 10);
        
        // Previous education
        $pdf->Cell(0, 6, 'Previous School/Institution:', 0, 1, 'L');
        $pdf->Cell(0, 6, '_________________________________________________', 0, 1, 'L');
        
        $pdf->Cell(40, 6, 'Qualification:', 0, 0, 'L');
        $pdf->Cell(60, 6, '________________________', 0, 0, 'L');
        $pdf->Cell(20, 6, 'Year:', 0, 0, 'L');
        $pdf->Cell(0, 6, '________', 0, 1, 'L');
        
        $pdf->Cell(40, 6, 'Grade/GPA:', 0, 0, 'L');
        $pdf->Cell(60, 6, '________________________', 0, 1, 'L');
        
        $pdf->Ln(5);
    }
    
    /**
     * Add contact information section
     */
    private function addContactInfoSection($pdf) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'CONTACT INFORMATION', 0, 1, 'L');
        $pdf->SetLineWidth(0.5);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(3);
        
        $pdf->SetFont('Arial', '', 10);
        
        $pdf->Cell(0, 6, 'Email Address:', 0, 1, 'L');
        $pdf->Cell(0, 6, '_________________________________________________', 0, 1, 'L');
        
        $pdf->Cell(0, 6, 'Phone Number:', 0, 1, 'L');
        $pdf->Cell(0, 6, '_________________________________________________', 0, 1, 'L');
        
        $pdf->Cell(0, 6, 'Address:', 0, 1, 'L');
        $pdf->Cell(0, 6, '_________________________________________________', 0, 1, 'L');
        $pdf->Cell(0, 6, '_________________________________________________', 0, 1, 'L');
        
        $pdf->Ln(5);
    }
    
    /**
     * Add document checklist section
     */
    private function addDocumentChecklist($pdf) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'REQUIRED DOCUMENTS CHECKLIST', 0, 1, 'L');
        $pdf->SetLineWidth(0.5);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(3);
        
        $pdf->SetFont('Arial', '', 10);
        
        $documents = [
            'Birth Certificate',
            'Academic Transcripts/Results',
            'Passport Photograph (2 copies)',
            'Recommendation Letters (2)',
            'Medical Certificate',
            'Previous School Transfer Certificate',
            'National ID/Passport Copy'
        ];
        
        foreach ($documents as $doc) {
            $pdf->Cell(5, 6, '□', 0, 0, 'L');
            $pdf->Cell(0, 6, $doc, 0, 1, 'L');
        }
        
        $pdf->Ln(5);
    }
    
    /**
     * Add signature section
     */
    private function addSignatureSection($pdf) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'DECLARATION AND SIGNATURE', 0, 1, 'L');
        $pdf->SetLineWidth(0.5);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(3);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 5, 'I declare that the information provided in this application is true and accurate. I understand that any false information may result in the rejection of my application or termination of my admission.', 0, 'L');
        
        $pdf->Ln(10);
        
        $pdf->Cell(60, 6, 'Applicant Signature:', 0, 0, 'L');
        $pdf->Cell(0, 6, '________________________', 0, 1, 'L');
        
        $pdf->Cell(60, 6, 'Date:', 0, 0, 'L');
        $pdf->Cell(0, 6, '________________________', 0, 1, 'L');
        
        $pdf->Ln(10);
        
        $pdf->Cell(60, 6, 'Parent/Guardian Signature:', 0, 0, 'L');
        $pdf->Cell(0, 6, '________________________', 0, 1, 'L');
        
        $pdf->Cell(60, 6, 'Date:', 0, 0, 'L');
        $pdf->Cell(0, 6, '________________________', 0, 1, 'L');
    }
    
    /**
     * Add footer to application form
     */
    private function addFooter($pdf) {
        $pdf->SetY(-30);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(0, 5, 'For office use only:', 0, 1, 'L');
        $pdf->Cell(0, 5, 'Application Fee Paid: □ Yes □ No    Amount: $_______    Date: _______', 0, 1, 'L');
        $pdf->Cell(0, 5, 'Documents Verified: □ Yes □ No    Verified By: _________________', 0, 1, 'L');
        $pdf->Cell(0, 5, 'Application Status: □ Pending □ Approved □ Rejected', 0, 1, 'L');
    }
    
    /**
     * Add receipt header
     */
    private function addReceiptHeader($pdf) {
        // Institution logo placeholder
        $pdf->Cell(0, 10, '[LOGO]', 0, 1, 'C');
        
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 8, 'PAYMENT RECEIPT', 0, 1, 'C');
        
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 6, 'University Name', 0, 1, 'C');
        $pdf->Cell(0, 6, 'Address Line 1', 0, 1, 'C');
        $pdf->Cell(0, 6, 'Address Line 2', 0, 1, 'C');
        
        $pdf->Ln(5);
    }
    
    /**
     * Add receipt details
     */
    private function addReceiptDetails($pdf, $paymentData) {
        $pdf->SetFont('Arial', '', 10);
        
        $pdf->Cell(40, 6, 'Receipt No:', 0, 0, 'L');
        $pdf->Cell(0, 6, $paymentData['receipt_number'], 0, 1, 'L');
        
        $pdf->Cell(40, 6, 'Date:', 0, 0, 'L');
        $pdf->Cell(0, 6, $paymentData['payment_date'], 0, 1, 'L');
        
        $pdf->Cell(40, 6, 'Application No:', 0, 0, 'L');
        $pdf->Cell(0, 6, $paymentData['application_number'], 0, 1, 'L');
        
        $pdf->Cell(40, 6, 'Student Name:', 0, 0, 'L');
        $pdf->Cell(0, 6, $paymentData['student_name'], 0, 1, 'L');
        
        $pdf->Ln(5);
    }
    
    /**
     * Add payment information
     */
    private function addPaymentInfo($pdf, $paymentData) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'PAYMENT DETAILS', 0, 1, 'L');
        $pdf->SetLineWidth(0.5);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(3);
        
        $pdf->SetFont('Arial', '', 10);
        
        $pdf->Cell(60, 6, 'Payment Type:', 0, 0, 'L');
        $pdf->Cell(0, 6, $paymentData['payment_type'], 0, 1, 'L');
        
        $pdf->Cell(60, 6, 'Amount Paid:', 0, 0, 'L');
        $pdf->Cell(0, 6, '$' . number_format($paymentData['amount'], 2), 0, 1, 'L');
        
        $pdf->Cell(60, 6, 'Payment Method:', 0, 0, 'L');
        $pdf->Cell(0, 6, $paymentData['payment_method'], 0, 1, 'L');
        
        if (isset($paymentData['transaction_id'])) {
            $pdf->Cell(60, 6, 'Transaction ID:', 0, 0, 'L');
            $pdf->Cell(0, 6, $paymentData['transaction_id'], 0, 1, 'L');
        }
        
        $pdf->Ln(10);
        
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 6, 'Thank you for your payment!', 0, 1, 'C');
    }
    
    /**
     * Add receipt footer
     */
    private function addReceiptFooter($pdf) {
        $pdf->SetY(-20);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(0, 5, 'This is a computer-generated receipt. No signature required.', 0, 1, 'C');
        $pdf->Cell(0, 5, 'For inquiries, contact: admissions@university.edu', 0, 1, 'C');
    }
    
    /**
     * Add letter header
     */
    private function addLetterHeader($pdf) {
        $pdf->Cell(0, 10, '[LOGO]', 0, 1, 'C');
        
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 8, 'ADMISSION LETTER', 0, 1, 'C');
        
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 6, 'University Name', 0, 1, 'C');
        
        $pdf->Ln(10);
    }
    
    /**
     * Add letter content
     */
    private function addLetterContent($pdf, $application) {
        $pdf->SetFont('Arial', '', 12);
        
        $pdf->Cell(0, 6, 'Date: ' . date('F d, Y'), 0, 1, 'L');
        $pdf->Ln(5);
        
        $pdf->Cell(0, 6, 'Dear ' . $application['student_first_name'] . ' ' . $application['student_last_name'] . ',', 0, 1, 'L');
        $pdf->Ln(5);
        
        $pdf->MultiCell(0, 6, 'We are pleased to inform you that your application for admission to our university has been approved. Congratulations on your acceptance!', 0, 'L');
        $pdf->Ln(5);
        
        $pdf->MultiCell(0, 6, 'Your application number is ' . $application['application_number'] . ' and you have been admitted to the ' . $application['program_name'] . ' program.', 0, 'L');
        $pdf->Ln(5);
        
        $pdf->MultiCell(0, 6, 'Please complete the following steps to secure your admission:', 0, 'L');
        $pdf->Ln(3);
        
        $pdf->Cell(5, 6, '1.', 0, 0, 'L');
        $pdf->MultiCell(0, 6, 'Pay the acceptance fee within 14 days of receiving this letter.', 0, 'L');
        
        $pdf->Cell(5, 6, '2.', 0, 0, 'L');
        $pdf->MultiCell(0, 6, 'Submit all required documents if not already submitted.', 0, 'L');
        
        $pdf->Cell(5, 6, '3.', 0, 0, 'L');
        $pdf->MultiCell(0, 6, 'Complete the online registration process.', 0, 'L');
        
        $pdf->Ln(10);
        
        $pdf->MultiCell(0, 6, 'We look forward to welcoming you to our university community.', 0, 'L');
        $pdf->Ln(5);
        
        $pdf->MultiCell(0, 6, 'Sincerely,', 0, 'L');
        $pdf->MultiCell(0, 6, 'Admissions Office', 0, 'L');
    }
    
    /**
     * Add letter signature
     */
    private function addLetterSignature($pdf) {
        $pdf->Ln(20);
        $pdf->Cell(0, 6, '________________________', 0, 1, 'L');
        $pdf->Cell(0, 6, 'Admissions Officer', 0, 1, 'L');
    }
    
    /**
     * Generate application number
     */
    private function generateApplicationNumber() {
        $year = date('Y');
        $prefix = 'APP';
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return $prefix . $year . $random;
    }
    
    /**
     * Save PDF to file
     */
    public function savePDF($pdf, $filename, $path = null) {
        if (!$path) {
            $path = UPLOAD_PATH . '/generated/';
        }
        
        // Create directory if it doesn't exist
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        
        $fullPath = $path . $filename;
        $pdf->Output('F', $fullPath);
        
        return $fullPath;
    }
    
    /**
     * Output PDF to browser
     */
    public function outputPDF($pdf, $filename) {
        $pdf->Output('D', $filename);
    }
}
