<?php
/**
 * Bulk Operations Manager
 * Handles bulk operations for admin portal including applications, students, and other entities
 */
class BulkOperationsManager {
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
    }
    
    /**
     * Bulk update application status
     */
    public function bulkUpdateApplicationStatus($applicationIds, $newStatus, $updatedBy) {
        try {
            if (empty($applicationIds) || !is_array($applicationIds)) {
                throw new Exception('No applications selected');
            }
            
            // Validate status
            $validStatuses = ['submitted', 'under_review', 'approved', 'rejected', 'waitlisted'];
            if (!in_array($newStatus, $validStatuses)) {
                throw new Exception('Invalid status');
            }
            
            // Prepare placeholders for IN clause
            $placeholders = str_repeat('?,', count($applicationIds) - 1) . '?';
            
            // Update applications
            $stmt = $this->database->prepare("
                UPDATE applications 
                SET status = ?, updated_by = ?, updated_at = NOW()
                WHERE id IN ($placeholders)
            ");
            
            $params = array_merge([$newStatus, $updatedBy], $applicationIds);
            $stmt->execute($params);
            
            $updatedCount = $stmt->rowCount();
            
            // Log the bulk operation
            $this->logBulkOperation('bulk_update_application_status', $updatedCount, [
                'application_ids' => $applicationIds,
                'new_status' => $newStatus,
                'updated_by' => $updatedBy
            ]);
            
            return [
                'success' => true,
                'updated_count' => $updatedCount,
                'message' => "Successfully updated $updatedCount applications to $newStatus"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Bulk assign reviewers to applications
     */
    public function bulkAssignReviewers($applicationIds, $reviewerIds, $assignedBy) {
        try {
            if (empty($applicationIds) || !is_array($applicationIds)) {
                throw new Exception('No applications selected');
            }
            
            if (empty($reviewerIds) || !is_array($reviewerIds)) {
                throw new Exception('No reviewers selected');
            }
            
            $assignedCount = 0;
            
            foreach ($applicationIds as $applicationId) {
                foreach ($reviewerIds as $reviewerId) {
                    // Check if assignment already exists
                    $stmt = $this->database->prepare("
                        SELECT COUNT(*) FROM application_reviewers 
                        WHERE application_id = ? AND reviewer_id = ?
                    ");
                    $stmt->execute([$applicationId, $reviewerId]);
                    
                    if ($stmt->fetchColumn() == 0) {
                        // Create new assignment
                        $stmt = $this->database->prepare("
                            INSERT INTO application_reviewers (application_id, reviewer_id, assigned_by, assigned_at)
                            VALUES (?, ?, ?, NOW())
                        ");
                        $stmt->execute([$applicationId, $reviewerId, $assignedBy]);
                        $assignedCount++;
                    }
                }
            }
            
            // Log the bulk operation
            $this->logBulkOperation('bulk_assign_reviewers', $assignedCount, [
                'application_ids' => $applicationIds,
                'reviewer_ids' => $reviewerIds,
                'assigned_by' => $assignedBy
            ]);
            
            return [
                'success' => true,
                'assigned_count' => $assignedCount,
                'message' => "Successfully assigned $assignedCount reviewer assignments"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Bulk send notifications
     */
    public function bulkSendNotifications($applicationIds, $notificationType, $message, $sentBy) {
        try {
            if (empty($applicationIds) || !is_array($applicationIds)) {
                throw new Exception('No applications selected');
            }
            
            if (empty($message)) {
                throw new Exception('Message is required');
            }
            
            $sentCount = 0;
            $notificationManager = new NotificationManager($this->database);
            
            foreach ($applicationIds as $applicationId) {
                // Get application details
                $stmt = $this->database->prepare("
                    SELECT a.*, s.email, s.phone, s.first_name, s.last_name, p.program_name
                    FROM applications a
                    JOIN students s ON a.student_id = s.id
                    JOIN programs p ON a.program_id = p.id
                    WHERE a.id = ?
                ");
                $stmt->execute([$applicationId]);
                $application = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($application) {
                    // Send email notification
                    if (!empty($application['email'])) {
                        $emailData = [
                            'to' => $application['email'],
                            'subject' => 'Notification - ' . $application['program_name'],
                            'template' => 'bulk_notification',
                            'data' => [
                                'student_name' => $application['first_name'] . ' ' . $application['last_name'],
                                'program_name' => $application['program_name'],
                                'application_id' => $application['application_id'],
                                'message' => $message
                            ]
                        ];
                        
                        if ($notificationManager->sendEmail($emailData)) {
                            $sentCount++;
                        }
                    }
                    
                    // Send SMS notification if phone is available
                    if (!empty($application['phone'])) {
                        $smsData = [
                            'to' => $application['phone'],
                            'message' => $message,
                            'template' => 'bulk_notification_sms'
                        ];
                        
                        $notificationManager->sendSms($smsData);
                    }
                }
            }
            
            // Log the bulk operation
            $this->logBulkOperation('bulk_send_notifications', $sentCount, [
                'application_ids' => $applicationIds,
                'notification_type' => $notificationType,
                'message' => $message,
                'sent_by' => $sentBy
            ]);
            
            return [
                'success' => true,
                'sent_count' => $sentCount,
                'message' => "Successfully sent $sentCount notifications"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Bulk export applications
     */
    public function bulkExportApplications($applicationIds, $format = 'csv', $includeFiles = false) {
        try {
            if (empty($applicationIds) || !is_array($applicationIds)) {
                throw new Exception('No applications selected');
            }
            
            // Prepare placeholders for IN clause
            $placeholders = str_repeat('?,', count($applicationIds) - 1) . '?';
            
            // Get application data
            $stmt = $this->database->prepare("
                SELECT 
                    a.id,
                    a.application_id,
                    a.status,
                    a.submitted_at,
                    a.deadline,
                    s.first_name,
                    s.last_name,
                    s.email,
                    s.phone,
                    p.program_name,
                    p.level_name
                FROM applications a
                JOIN students s ON a.student_id = s.id
                JOIN programs p ON a.program_id = p.id
                WHERE a.id IN ($placeholders)
                ORDER BY a.submitted_at DESC
            ");
            $stmt->execute($applicationIds);
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($applications)) {
                throw new Exception('No applications found');
            }
            
            if ($includeFiles && $format === 'zip') {
                return $this->exportApplicationsWithFiles($applications, $applicationIds);
            }
            
            // Generate export file
            $filename = 'applications_export_' . date('Y-m-d_H-i-s') . '.' . $format;
            $filepath = sys_get_temp_dir() . '/' . $filename;
            
            if ($format === 'csv') {
                $this->exportToCSV($applications, $filepath);
            } elseif ($format === 'excel') {
                $this->exportToExcel($applications, $filepath);
            } else {
                throw new Exception('Unsupported export format');
            }
            
            // Log the bulk operation
            $this->logBulkOperation('bulk_export_applications', count($applications), [
                'application_ids' => $applicationIds,
                'format' => $format,
                'filename' => $filename
            ]);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'count' => count($applications),
                'message' => "Successfully exported " . count($applications) . " applications"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Export applications with uploaded files as ZIP
     */
    private function exportApplicationsWithFiles($applications, $applicationIds) {
        try {
            $zipFilename = 'applications_with_files_' . date('Y-m-d_H-i-s') . '.zip';
            $zipPath = sys_get_temp_dir() . '/' . $zipFilename;
            
            // Create ZIP file
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                throw new Exception('Cannot create ZIP file');
            }
            
            // Add CSV data file
            $csvData = [];
            foreach ($applications as $app) {
                $csvData[] = [
                    'Application ID' => $app['application_id'],
                    'Student Name' => $app['first_name'] . ' ' . $app['last_name'],
                    'Email' => $app['email'],
                    'Phone' => $app['phone'],
                    'Program' => $app['program_name'],
                    'Status' => $app['status'],
                    'Submitted At' => $app['submitted_at'],
                    'Deadline' => $app['deadline']
                ];
            }
            
            $csvContent = $this->arrayToCSV($csvData);
            $zip->addFromString('applications_data.csv', $csvContent);
            
            // Add uploaded files for each application
            foreach ($applicationIds as $appId) {
                $stmt = $this->database->prepare("
                    SELECT ad.*, ar.requirement_name
                    FROM application_documents ad
                    JOIN application_requirements ar ON ad.requirement_id = ar.id
                    WHERE ad.application_id = ?
                ");
                $stmt->execute([$appId]);
                $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($documents)) {
                    $appFolder = 'Application_' . $appId . '_Files/';
                    
                    foreach ($documents as $doc) {
                        if (file_exists($doc['file_path'])) {
                            $fileInfo = pathinfo($doc['file_path']);
                            $filename = $doc['requirement_name'] . '_' . $doc['id'] . '.' . $fileInfo['extension'];
                            $zip->addFile($doc['file_path'], $appFolder . $filename);
                        }
                    }
                }
            }
            
            $zip->close();
            
            // Log the bulk operation
            $this->logBulkOperation('bulk_export_applications_with_files', count($applications), [
                'application_ids' => $applicationIds,
                'filename' => $zipFilename
            ]);
            
            return [
                'success' => true,
                'filename' => $zipFilename,
                'filepath' => $zipPath,
                'count' => count($applications),
                'message' => "Successfully exported " . count($applications) . " applications with files"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Convert array to CSV string
     */
    private function arrayToCSV($data) {
        if (empty($data)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Bulk delete applications
     */
    public function bulkDeleteApplications($applicationIds, $deletedBy) {
        try {
            if (empty($applicationIds) || !is_array($applicationIds)) {
                throw new Exception('No applications selected');
            }
            
            // Prepare placeholders for IN clause
            $placeholders = str_repeat('?,', count($applicationIds) - 1) . '?';
            
            // Soft delete applications
            $stmt = $this->database->prepare("
                UPDATE applications 
                SET deleted_at = NOW(), deleted_by = ?
                WHERE id IN ($placeholders) AND deleted_at IS NULL
            ");
            
            $params = array_merge([$deletedBy], $applicationIds);
            $stmt->execute($params);
            
            $deletedCount = $stmt->rowCount();
            
            // Log the bulk operation
            $this->logBulkOperation('bulk_delete_applications', $deletedCount, [
                'application_ids' => $applicationIds,
                'deleted_by' => $deletedBy
            ]);
            
            return [
                'success' => true,
                'deleted_count' => $deletedCount,
                'message' => "Successfully deleted $deletedCount applications"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Bulk update student information
     */
    public function bulkUpdateStudents($studentIds, $updateData, $updatedBy) {
        try {
            if (empty($studentIds) || !is_array($studentIds)) {
                throw new Exception('No students selected');
            }
            
            if (empty($updateData)) {
                throw new Exception('No update data provided');
            }
            
            // Validate update fields
            $allowedFields = ['status', 'notes', 'is_active'];
            $updateFields = [];
            $updateValues = [];
            
            foreach ($updateData as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $updateFields[] = "$field = ?";
                    $updateValues[] = $value;
                }
            }
            
            if (empty($updateFields)) {
                throw new Exception('No valid update fields provided');
            }
            
            // Prepare placeholders for IN clause
            $placeholders = str_repeat('?,', count($studentIds) - 1) . '?';
            
            // Update students
            $sql = "UPDATE students SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id IN ($placeholders)";
            $params = array_merge($updateValues, $studentIds);
            
            $stmt = $this->database->prepare($sql);
            $stmt->execute($params);
            
            $updatedCount = $stmt->rowCount();
            
            // Log the bulk operation
            $this->logBulkOperation('bulk_update_students', $updatedCount, [
                'student_ids' => $studentIds,
                'update_data' => $updateData,
                'updated_by' => $updatedBy
            ]);
            
            return [
                'success' => true,
                'updated_count' => $updatedCount,
                'message' => "Successfully updated $updatedCount students"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Export to CSV
     */
    private function exportToCSV($data, $filepath) {
        $file = fopen($filepath, 'w');
        
        if (!$file) {
            throw new Exception('Failed to create export file');
        }
        
        // Write headers
        if (!empty($data)) {
            fputcsv($file, array_keys($data[0]));
            
            // Write data
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
        }
        
        fclose($file);
    }
    
    /**
     * Export to Excel
     */
    private function exportToExcel($data, $filepath) {
        // This would require a library like PhpSpreadsheet
        // For now, we'll use CSV format
        $this->exportToCSV($data, $filepath);
    }
    
    /**
     * Log bulk operation
     */
    private function logBulkOperation($operation, $count, $data) {
        try {
            $stmt = $this->database->prepare("
                INSERT INTO bulk_operations_log (operation_type, record_count, operation_data, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $operation,
                $count,
                json_encode($data)
            ]);
            
        } catch (Exception $e) {
            error_log('Failed to log bulk operation: ' . $e->getMessage());
        }
    }
    
    /**
     * Get bulk operation history
     */
    public function getBulkOperationHistory($limit = 50) {
        try {
            $stmt = $this->database->prepare("
                SELECT * FROM bulk_operations_log
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Failed to get bulk operation history: ' . $e->getMessage());
            return [];
        }
    }
}
