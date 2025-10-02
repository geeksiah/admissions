<?php
/**
 * Email Verification Manager
 * Handles email verification for students and applicants
 */
class EmailVerificationManager {
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
    }
    
    /**
     * Send verification email to student
     */
    public function sendVerificationEmail($studentId, $email = null) {
        try {
            // Get student details
            $stmt = $this->database->prepare("
                SELECT id, first_name, last_name, email, email_verified_at
                FROM students
                WHERE id = ?
            ");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$student) {
                throw new Exception('Student not found');
            }
            
            // Use provided email or student's email
            $email = $email ?: $student['email'];
            
            if (empty($email)) {
                throw new Exception('No email address provided');
            }
            
            // Check if already verified
            if ($student['email_verified_at']) {
                throw new Exception('Email already verified');
            }
            
            // Generate verification token
            $verificationToken = $this->generateVerificationToken();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Store verification token
            $stmt = $this->database->prepare("
                INSERT INTO email_verifications (student_id, email, verification_token, expires_at, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                verification_token = VALUES(verification_token),
                expires_at = VALUES(expires_at),
                created_at = NOW()
            ");
            $stmt->execute([$studentId, $email, $verificationToken, $expiresAt]);
            
            // Send verification email
            $verificationUrl = APP_URL . '/verify-email.php?token=' . $verificationToken;
            
            $emailData = [
                'to' => $email,
                'subject' => 'Verify Your Email Address - ' . APP_NAME,
                'template' => 'email_verification',
                'data' => [
                    'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                    'verification_url' => $verificationUrl,
                    'expires_at' => $expiresAt
                ]
            ];
            
            $notificationManager = new NotificationManager($this->database);
            $result = $notificationManager->sendEmail($emailData);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Verification email sent successfully',
                    'verification_token' => $verificationToken
                ];
            } else {
                throw new Exception('Failed to send verification email');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify email with token
     */
    public function verifyEmail($verificationToken) {
        try {
            // Get verification record
            $stmt = $this->database->prepare("
                SELECT ev.*, s.first_name, s.last_name, s.email
                FROM email_verifications ev
                JOIN students s ON ev.student_id = s.id
                WHERE ev.verification_token = ? AND ev.expires_at > NOW() AND ev.verified_at IS NULL
            ");
            $stmt->execute([$verificationToken]);
            $verification = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$verification) {
                throw new Exception('Invalid or expired verification token');
            }
            
            // Update student email verification status
            $stmt = $this->database->prepare("
                UPDATE students 
                SET email_verified_at = NOW(), email = ?
                WHERE id = ?
            ");
            $stmt->execute([$verification['email'], $verification['student_id']]);
            
            // Mark verification as completed
            $stmt = $this->database->prepare("
                UPDATE email_verifications 
                SET verified_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$verification['id']]);
            
            return [
                'success' => true,
                'message' => 'Email verified successfully',
                'student_name' => $verification['first_name'] . ' ' . $verification['last_name']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if email is verified
     */
    public function isEmailVerified($studentId) {
        try {
            $stmt = $this->database->prepare("
                SELECT email_verified_at FROM students WHERE id = ?
            ");
            $stmt->execute([$studentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return !empty($result['email_verified_at']);
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Resend verification email
     */
    public function resendVerificationEmail($studentId) {
        try {
            // Check if already verified
            if ($this->isEmailVerified($studentId)) {
                throw new Exception('Email already verified');
            }
            
            // Check rate limiting (max 3 attempts per hour)
            $stmt = $this->database->prepare("
                SELECT COUNT(*) as attempts
                FROM email_verifications
                WHERE student_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$studentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['attempts'] >= 3) {
                throw new Exception('Too many verification attempts. Please try again later.');
            }
            
            return $this->sendVerificationEmail($studentId);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate verification token
     */
    private function generateVerificationToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Get verification status for student
     */
    public function getVerificationStatus($studentId) {
        try {
            $stmt = $this->database->prepare("
                SELECT 
                    s.email_verified_at,
                    ev.verification_token,
                    ev.expires_at,
                    ev.created_at as last_verification_sent
                FROM students s
                LEFT JOIN email_verifications ev ON s.id = ev.student_id
                WHERE s.id = ?
                ORDER BY ev.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$studentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'is_verified' => !empty($result['email_verified_at']),
                'verified_at' => $result['email_verified_at'],
                'has_pending_verification' => !empty($result['verification_token']) && empty($result['email_verified_at']),
                'verification_expires_at' => $result['expires_at'],
                'last_verification_sent' => $result['last_verification_sent']
            ];
            
        } catch (Exception $e) {
            return [
                'is_verified' => false,
                'verified_at' => null,
                'has_pending_verification' => false,
                'verification_expires_at' => null,
                'last_verification_sent' => null
            ];
        }
    }
    
    /**
     * Clean up expired verification tokens
     */
    public function cleanupExpiredTokens() {
        try {
            $stmt = $this->database->prepare("
                DELETE FROM email_verifications 
                WHERE expires_at < NOW() AND verified_at IS NULL
            ");
            $stmt->execute();
            
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get verification statistics
     */
    public function getVerificationStatistics($days = 30) {
        try {
            $stmt = $this->database->prepare("
                SELECT 
                    COUNT(*) as total_verifications,
                    SUM(CASE WHEN verified_at IS NOT NULL THEN 1 ELSE 0 END) as verified_count,
                    SUM(CASE WHEN verified_at IS NULL AND expires_at > NOW() THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN verified_at IS NULL AND expires_at <= NOW() THEN 1 ELSE 0 END) as expired_count
                FROM email_verifications
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result;
            
        } catch (Exception $e) {
            return [
                'total_verifications' => 0,
                'verified_count' => 0,
                'pending_count' => 0,
                'expired_count' => 0
            ];
        }
    }
    
    /**
     * Bulk send verification emails
     */
    public function bulkSendVerificationEmails($studentIds) {
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($studentIds as $studentId) {
            $result = $this->sendVerificationEmail($studentId);
            $results[] = [
                'student_id' => $studentId,
                'result' => $result
            ];
            
            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }
        
        return [
            'success' => true,
            'total_sent' => count($studentIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results
        ];
    }
}
