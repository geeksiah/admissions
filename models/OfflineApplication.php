<?php
/**
 * Offline Application Model
 * Handles offline application processing and data entry
 */

class OfflineApplication {
    private $db;
    
    public function __construct($database) {
        // Handle both Database object and PDO connection
        if ($database instanceof PDO) {
            $this->db = $database;
        } else {
            $this->db = $database->getConnection();
        }
    }
    
    /**
     * Create new offline application
     */
    public function create($data) {
        try {
            $this->db->beginTransaction();
            
            // Generate application number
            $applicationNumber = $this->generateApplicationNumber();
            
            $stmt = $this->db->prepare("
                INSERT INTO offline_applications (
                    application_number, student_first_name, student_last_name, student_email, 
                    student_phone, program_id, application_date, entry_method, received_by, 
                    status, conversion_notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $applicationNumber,
                $data['student_first_name'],
                $data['student_last_name'],
                $data['student_email'] ?? null,
                $data['student_phone'] ?? null,
                $data['program_id'],
                $data['application_date'],
                $data['entry_method'],
                $data['received_by'],
                $data['status'] ?? 'received',
                $data['conversion_notes'] ?? null
            ]);
            
            if ($result) {
                $applicationId = $this->db->lastInsertId();
                $this->db->commit();
                return $applicationId;
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Offline application creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get offline application by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT oa.*, 
                       p.program_name, p.program_code, p.department,
                       u.first_name as received_by_first_name, u.last_name as received_by_last_name,
                       a.application_number as converted_application_number
                FROM offline_applications oa
                JOIN programs p ON oa.program_id = p.id
                LEFT JOIN users u ON oa.received_by = u.id
                LEFT JOIN applications a ON oa.converted_to_online = a.id
                WHERE oa.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Offline application fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get offline application by application number
     */
    public function getByApplicationNumber($applicationNumber) {
        try {
            $stmt = $this->db->prepare("
                SELECT oa.*, 
                       p.program_name, p.program_code, p.department,
                       u.first_name as received_by_first_name, u.last_name as received_by_last_name
                FROM offline_applications oa
                JOIN programs p ON oa.program_id = p.id
                LEFT JOIN users u ON oa.received_by = u.id
                WHERE oa.application_number = ?
            ");
            $stmt->execute([$applicationNumber]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Offline application fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all offline applications with pagination
     */
    public function getAll($page = 1, $limit = RECORDS_PER_PAGE, $filters = []) {
        try {
            $offset = ($page - 1) * $limit;
            $where = ['1=1'];
            $params = [];
            
            // Apply filters
            if (!empty($filters['status'])) {
                $where[] = 'oa.status = ?';
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['entry_method'])) {
                $where[] = 'oa.entry_method = ?';
                $params[] = $filters['entry_method'];
            }
            
            if (!empty($filters['program_id'])) {
                $where[] = 'oa.program_id = ?';
                $params[] = $filters['program_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = 'oa.application_date >= ?';
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = 'oa.application_date <= ?';
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['search'])) {
                $where[] = '(oa.application_number LIKE ? OR oa.student_first_name LIKE ? OR oa.student_last_name LIKE ? OR oa.student_email LIKE ?)';
                $searchTerm = '%' . $filters['search'] . '%';
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            $whereClause = implode(' AND ', $where);
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare("
                SELECT oa.*, 
                       p.program_name, p.program_code, p.department,
                       u.first_name as received_by_first_name, u.last_name as received_by_last_name
                FROM offline_applications oa
                JOIN programs p ON oa.program_id = p.id
                LEFT JOIN users u ON oa.received_by = u.id
                WHERE $whereClause 
                ORDER BY oa.application_date DESC 
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute($params);
            $applications = $stmt->fetchAll();
            
            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total FROM offline_applications oa WHERE $whereClause
            ");
            $countStmt->execute(array_slice($params, 0, -2));
            $total = $countStmt->fetch()['total'];
            
            return [
                'applications' => $applications,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];
        } catch (Exception $e) {
            error_log("Offline applications fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update offline application
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $values = [];
            
            foreach ($data as $key => $value) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
            
            $values[] = $id;
            
            $stmt = $this->db->prepare("
                UPDATE offline_applications SET " . implode(', ', $fields) . " WHERE id = ?
            ");
            
            return $stmt->execute($values);
        } catch (Exception $e) {
            error_log("Offline application update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Convert offline application to online application
     */
    public function convertToOnline($offlineApplicationId, $studentData, $applicationData, $convertedBy) {
        try {
            $this->db->beginTransaction();
            
            // Get offline application details
            $offlineApp = $this->getById($offlineApplicationId);
            if (!$offlineApp) {
                throw new Exception('Offline application not found');
            }
            
            // Create student record
            $studentModel = new Student($this->db);
            $studentId = $studentModel->create($studentData);
            if (!$studentId) {
                throw new Exception('Failed to create student record');
            }
            
            // Create online application
            $applicationModel = new Application($this->db);
            $applicationData['student_id'] = $studentId;
            $applicationData['program_id'] = $offlineApp['program_id'];
            $applicationData['notes'] = "Converted from offline application: " . $offlineApp['application_number'];
            
            $onlineApplicationId = $applicationModel->create($applicationData);
            if (!$onlineApplicationId) {
                throw new Exception('Failed to create online application');
            }
            
            // Update offline application status
            $this->update($offlineApplicationId, [
                'status' => 'converted',
                'converted_to_online' => $onlineApplicationId,
                'conversion_notes' => 'Converted to online application by user ID: ' . $convertedBy
            ]);
            
            // Convert documents if any
            $this->convertDocuments($offlineApplicationId, $onlineApplicationId);
            
            $this->db->commit();
            return $onlineApplicationId;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Offline application conversion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add document to offline application
     */
    public function addDocument($offlineApplicationId, $data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO offline_application_documents (
                    offline_application_id, document_type, document_name, file_path, 
                    physical_location, received_date, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $offlineApplicationId,
                $data['document_type'],
                $data['document_name'],
                $data['file_path'] ?? null,
                $data['physical_location'] ?? null,
                $data['received_date'] ?? date('Y-m-d'),
                $data['notes'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Offline application document addition error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get documents for offline application
     */
    public function getDocuments($offlineApplicationId) {
        try {
            $stmt = $this->db->prepare("
                SELECT oad.*, u.first_name as verified_by_first_name, u.last_name as verified_by_last_name
                FROM offline_application_documents oad
                LEFT JOIN users u ON oad.verified_by = u.id
                WHERE oad.offline_application_id = ?
                ORDER BY oad.received_date DESC
            ");
            $stmt->execute([$offlineApplicationId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Offline application documents fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify document
     */
    public function verifyDocument($documentId, $verified, $verifiedBy, $notes = null) {
        try {
            $stmt = $this->db->prepare("
                UPDATE offline_application_documents 
                SET verified = ?, verified_by = ?, verified_at = NOW(), notes = ?
                WHERE id = ?
            ");
            
            return $stmt->execute([$verified, $verifiedBy, $notes, $documentId]);
        } catch (Exception $e) {
            error_log("Document verification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get offline application statistics
     */
    public function getStatistics() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_offline_applications,
                    SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received_applications,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_applications,
                    SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted_applications,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications,
                    SUM(CASE WHEN entry_method = 'walk_in' THEN 1 ELSE 0 END) as walk_in_applications,
                    SUM(CASE WHEN entry_method = 'mail' THEN 1 ELSE 0 END) as mail_applications,
                    SUM(CASE WHEN entry_method = 'fax' THEN 1 ELSE 0 END) as fax_applications,
                    SUM(CASE WHEN entry_method = 'email' THEN 1 ELSE 0 END) as email_applications
                FROM offline_applications
            ");
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Offline application statistics error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete offline application
     */
    public function delete($id) {
        try {
            $this->db->beginTransaction();
            
            // Delete documents first
            $docStmt = $this->db->prepare("
                DELETE FROM offline_application_documents WHERE offline_application_id = ?
            ");
            $docStmt->execute([$id]);
            
            // Delete application
            $appStmt = $this->db->prepare("
                DELETE FROM offline_applications WHERE id = ?
            ");
            $result = $appStmt->execute([$id]);
            
            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Offline application deletion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Convert documents from offline to online application
     */
    private function convertDocuments($offlineApplicationId, $onlineApplicationId) {
        try {
            $documents = $this->getDocuments($offlineApplicationId);
            
            foreach ($documents as $doc) {
                $stmt = $this->db->prepare("
                    INSERT INTO application_documents (
                        application_id, document_type, document_name, file_path, 
                        file_size, mime_type, uploaded_at, verified, verified_by, verified_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)
                ");
                
                $stmt->execute([
                    $onlineApplicationId,
                    $doc['document_type'],
                    $doc['document_name'],
                    $doc['file_path'],
                    null, // file_size
                    null, // mime_type
                    $doc['verified'],
                    $doc['verified_by'],
                    $doc['verified_at']
                ]);
            }
        } catch (Exception $e) {
            error_log("Document conversion error: " . $e->getMessage());
        }
    }
    
    /**
     * Generate unique application number
     */
    private function generateApplicationNumber() {
        $prefix = 'OFF' . date('Y');
        
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM offline_applications WHERE application_number LIKE ?
            ");
            $stmt->execute([$prefix . '%']);
            $result = $stmt->fetch();
            
            $sequence = str_pad($result['count'] + 1, 6, '0', STR_PAD_LEFT);
            return $prefix . $sequence;
        } catch (Exception $e) {
            error_log("Application number generation error: " . $e->getMessage());
            return $prefix . '000001';
        }
    }
    
    /**
     * Get entry methods
     */
    public function getEntryMethods() {
        return [
            'walk_in' => 'Walk-in',
            'mail' => 'Mail',
            'fax' => 'Fax',
            'email' => 'Email',
            'phone' => 'Phone',
            'other' => 'Other'
        ];
    }
    
    /**
     * Get status options
     */
    public function getStatusOptions() {
        return [
            'received' => 'Received',
            'processing' => 'Processing',
            'converted' => 'Converted',
            'rejected' => 'Rejected'
        ];
    }
    
    /**
     * Bulk import from CSV file
     */
    public function bulkImportFromCSV($csvFile, $importedBy) {
        try {
            $this->db->beginTransaction();
            
            $importedCount = 0;
            $errorCount = 0;
            $errors = [];
            
            // Open CSV file
            $handle = fopen($csvFile['tmp_name'], 'r');
            if (!$handle) {
                throw new Exception('Could not open CSV file');
            }
            
            // Read header row
            $headers = fgetcsv($handle);
            if (!$headers) {
                throw new Exception('CSV file is empty or invalid');
            }
            
            // Validate required headers
            $requiredHeaders = ['student_first_name', 'student_last_name', 'program_id', 'application_date', 'entry_method'];
            $missingHeaders = array_diff($requiredHeaders, $headers);
            if (!empty($missingHeaders)) {
                throw new Exception('Missing required columns: ' . implode(', ', $missingHeaders));
            }
            
            $rowNumber = 1;
            while (($data = fgetcsv($handle)) !== false) {
                $rowNumber++;
                
                // Skip empty rows
                if (empty(array_filter($data))) {
                    continue;
                }
                
                // Create associative array from headers and data
                $rowData = array_combine($headers, $data);
                
                // Validate required fields
                $validationErrors = [];
                if (empty($rowData['student_first_name'])) {
                    $validationErrors[] = 'First name is required';
                }
                if (empty($rowData['student_last_name'])) {
                    $validationErrors[] = 'Last name is required';
                }
                if (empty($rowData['program_id']) || !is_numeric($rowData['program_id'])) {
                    $validationErrors[] = 'Valid program ID is required';
                }
                if (empty($rowData['application_date']) || !strtotime($rowData['application_date'])) {
                    $validationErrors[] = 'Valid application date is required';
                }
                if (empty($rowData['entry_method'])) {
                    $validationErrors[] = 'Entry method is required';
                }
                
                if (!empty($validationErrors)) {
                    $errors[] = "Row {$rowNumber}: " . implode(', ', $validationErrors);
                    $errorCount++;
                    continue;
                }
                
                // Validate program exists
                $programModel = new Program($this->db);
                if (!$programModel->getById($rowData['program_id'])) {
                    $errors[] = "Row {$rowNumber}: Program ID {$rowData['program_id']} does not exist";
                    $errorCount++;
                    continue;
                }
                
                // Prepare data for insertion
                $applicationData = [
                    'program_id' => (int)$rowData['program_id'],
                    'student_first_name' => trim($rowData['student_first_name']),
                    'student_last_name' => trim($rowData['student_last_name']),
                    'student_email' => !empty($rowData['student_email']) ? trim($rowData['student_email']) : null,
                    'student_phone' => !empty($rowData['student_phone']) ? trim($rowData['student_phone']) : null,
                    'application_date' => date('Y-m-d', strtotime($rowData['application_date'])),
                    'entry_method' => trim($rowData['entry_method']),
                    'status' => 'received',
                    'received_by' => $importedBy,
                    'conversion_notes' => !empty($rowData['conversion_notes']) ? trim($rowData['conversion_notes']) : 'Bulk imported from CSV'
                ];
                
                // Generate application number
                $applicationData['application_number'] = $this->generateApplicationNumber();
                
                // Insert application
                if ($this->create($applicationData)) {
                    $importedCount++;
                } else {
                    $errors[] = "Row {$rowNumber}: Failed to create application record";
                    $errorCount++;
                }
            }
            
            fclose($handle);
            
            if ($errorCount === 0) {
                $this->db->commit();
            } else {
                $this->db->rollback();
            }
            
            return [
                'success' => $errorCount === 0,
                'imported_count' => $importedCount,
                'error_count' => $errorCount,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Bulk import error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'imported_count' => 0,
                'error_count' => 0,
                'errors' => [$e->getMessage()]
            ];
        }
    }
    
    /**
     * Download CSV template
     */
    public function downloadCSVTemplate() {
        $filename = 'offline_application_template.csv';
        
        // Set headers for download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Write headers
        $headers = [
            'student_first_name',
            'student_last_name',
            'student_email',
            'student_phone',
            'program_id',
            'application_date',
            'entry_method',
            'conversion_notes'
        ];
        fputcsv($output, $headers);
        
        // Write sample data
        $sampleData = [
            'John',
            'Doe',
            'john.doe@email.com',
            '+1234567890',
            '1',
            date('Y-m-d'),
            'manual',
            'Sample offline application'
        ];
        fputcsv($output, $sampleData);
        
        fclose($output);
        exit;
    }
}
