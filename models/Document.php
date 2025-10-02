<?php
/**
 * Document Model
 * Handles document-related operations
 */

class Document {
    private $database;
    
    public function __construct($database = null) {
        if ($database) {
            $this->database = $database;
        } else {
            $this->database = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        }
    }
    
    /**
     * Create a new document record
     */
    public function create($data) {
        $sql = "INSERT INTO application_documents (
            application_id, requirement_id, file_name, file_path, 
            file_size, file_type, uploaded_at, status
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending')";
        
        $stmt = $this->database->prepare($sql);
        return $stmt->execute([
            $data['application_id'],
            $data['requirement_id'],
            $data['file_name'],
            $data['file_path'],
            $data['file_size'],
            $data['file_type']
        ]);
    }
    
    /**
     * Get document by ID
     */
    public function getById($id) {
        $sql = "SELECT ad.*, ar.requirement_name, ar.requirement_type
                FROM application_documents ad
                JOIN application_requirements ar ON ad.requirement_id = ar.id
                WHERE ad.id = ?";
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get documents by application ID
     */
    public function getByApplicationId($applicationId) {
        $sql = "SELECT ad.*, ar.requirement_name, ar.requirement_type, ar.is_mandatory
                FROM application_documents ad
                JOIN application_requirements ar ON ad.requirement_id = ar.id
                WHERE ad.application_id = ?
                ORDER BY ar.is_mandatory DESC, ar.requirement_name";
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$applicationId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Update document status
     */
    public function updateStatus($id, $status, $reviewerId = null, $notes = null) {
        $sql = "UPDATE application_documents 
                SET status = ?, reviewed_by = ?, review_notes = ?, reviewed_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->database->prepare($sql);
        return $stmt->execute([$status, $reviewerId, $notes, $id]);
    }
    
    /**
     * Delete document
     */
    public function delete($id) {
        // Get file path first
        $document = $this->getById($id);
        
        $sql = "DELETE FROM application_documents WHERE id = ?";
        $stmt = $this->database->prepare($sql);
        $result = $stmt->execute([$id]);
        
        // Delete physical file
        if ($result && $document && file_exists($document['file_path'])) {
            unlink($document['file_path']);
        }
        
        return $result;
    }
    
    /**
     * Get documents by status
     */
    public function getByStatus($status, $limit = null, $offset = 0) {
        $sql = "SELECT ad.*, ar.requirement_name, a.application_id, s.first_name, s.last_name
                FROM application_documents ad
                JOIN application_requirements ar ON ad.requirement_id = ar.id
                JOIN applications a ON ad.application_id = a.id
                JOIN students s ON a.student_id = s.id
                WHERE ad.status = ?
                ORDER BY ad.uploaded_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
        }
        
        $stmt = $this->database->prepare($sql);
        if ($limit) {
            $stmt->execute([$status, $limit, $offset]);
        } else {
            $stmt->execute([$status]);
        }
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get document statistics
     */
    public function getStatistics() {
        $sql = "SELECT 
                    status,
                    COUNT(*) as count,
                    COUNT(CASE WHEN file_size > 0 THEN 1 END) as with_files,
                    AVG(file_size) as avg_size
                FROM application_documents 
                GROUP BY status";
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Bulk update document status
     */
    public function bulkUpdateStatus($documentIds, $status, $reviewerId = null, $notes = null) {
        if (empty($documentIds)) {
            return false;
        }
        
        $placeholders = str_repeat('?,', count($documentIds) - 1) . '?';
        $sql = "UPDATE application_documents 
                SET status = ?, reviewed_by = ?, review_notes = ?, reviewed_at = NOW()
                WHERE id IN ($placeholders)";
        
        $params = array_merge([$status, $reviewerId, $notes], $documentIds);
        $stmt = $this->database->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Get pending documents count
     */
    public function getPendingCount() {
        $sql = "SELECT COUNT(*) as count FROM application_documents WHERE status = 'pending'";
        $stmt = $this->database->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'];
    }
    
    /**
     * Get documents by requirement type
     */
    public function getByRequirementType($requirementType, $applicationId = null) {
        $sql = "SELECT ad.*, ar.requirement_name, ar.requirement_type
                FROM application_documents ad
                JOIN application_requirements ar ON ad.requirement_id = ar.id
                WHERE ar.requirement_type = ?";
        
        $params = [$requirementType];
        
        if ($applicationId) {
            $sql .= " AND ad.application_id = ?";
            $params[] = $applicationId;
        }
        
        $sql .= " ORDER BY ad.uploaded_at DESC";
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Check if document exists for application and requirement
     */
    public function existsForApplication($applicationId, $requirementId) {
        $sql = "SELECT COUNT(*) as count 
                FROM application_documents 
                WHERE application_id = ? AND requirement_id = ?";
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$applicationId, $requirementId]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    /**
     * Get document verification status for application
     */
    public function getVerificationStatus($applicationId) {
        $sql = "SELECT 
                    ar.requirement_name,
                    ar.is_mandatory,
                    ad.status,
                    ad.file_name,
                    ad.uploaded_at,
                    ad.review_notes
                FROM application_requirements ar
                LEFT JOIN application_documents ad ON ar.id = ad.requirement_id AND ad.application_id = ?
                WHERE ar.is_mandatory = 1
                ORDER BY ar.requirement_name";
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$applicationId]);
        return $stmt->fetchAll();
    }
}
?>