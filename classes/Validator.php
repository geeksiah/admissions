<?php
/**
 * Validator Class
 * Handles data validation for forms and API inputs
 */

class Validator {
    private $errors = [];
    private $data = [];
    
    public function __construct($data = []) {
        $this->data = $data;
    }
    
    /**
     * Add error message
     */
    public function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Get all errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get errors for specific field
     */
    public function getFieldErrors($field) {
        return $this->errors[$field] ?? [];
    }
    
    /**
     * Check if validation failed
     */
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    /**
     * Check if specific field has errors
     */
    public function hasFieldError($field) {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }
    
    /**
     * Validate required fields
     */
    public function required($fields) {
        $fields = is_array($fields) ? $fields : [$fields];
        
        foreach ($fields as $field) {
            if (!isset($this->data[$field]) || empty(trim($this->data[$field]))) {
                $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' is required');
            }
        }
        
        return $this;
    }
    
    /**
     * Validate email format
     */
    public function email($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
                $this->addError($field, 'Please enter a valid email address');
            }
        }
        return $this;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength($field, $length) {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must be at least $length characters long");
        }
        return $this;
    }
    
    /**
     * Validate maximum length
     */
    public function maxLength($field, $length) {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $length) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must not exceed $length characters");
        }
        return $this;
    }
    
    /**
     * Validate numeric value
     */
    public function numeric($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!is_numeric($this->data[$field])) {
                $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be a valid number');
            }
        }
        return $this;
    }
    
    /**
     * Validate integer value
     */
    public function integer($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_INT)) {
                $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be a valid integer');
            }
        }
        return $this;
    }
    
    /**
     * Validate date format
     */
    public function date($field, $format = 'Y-m-d') {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $d = DateTime::createFromFormat($format, $this->data[$field]);
            if (!$d || $d->format($format) !== $this->data[$field]) {
                $this->addError($field, 'Please enter a valid date');
            }
        }
        return $this;
    }
    
    /**
     * Validate phone number
     */
    public function phone($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $phone = preg_replace('/[^0-9]/', '', $this->data[$field]);
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                $this->addError($field, 'Please enter a valid phone number');
            }
        }
        return $this;
    }
    
    /**
     * Validate password strength
     */
    public function password($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $password = $this->data[$field];
            
            if (strlen($password) < PASSWORD_MIN_LENGTH) {
                $this->addError($field, 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long');
            }
            
            if (!preg_match('/[A-Z]/', $password)) {
                $this->addError($field, 'Password must contain at least one uppercase letter');
            }
            
            if (!preg_match('/[a-z]/', $password)) {
                $this->addError($field, 'Password must contain at least one lowercase letter');
            }
            
            if (!preg_match('/[0-9]/', $password)) {
                $this->addError($field, 'Password must contain at least one number');
            }
            
            if (!preg_match('/[^A-Za-z0-9]/', $password)) {
                $this->addError($field, 'Password must contain at least one special character');
            }
        }
        return $this;
    }
    
    /**
     * Validate password confirmation
     */
    public function confirmPassword($passwordField, $confirmField) {
        if (isset($this->data[$passwordField]) && isset($this->data[$confirmField])) {
            if ($this->data[$passwordField] !== $this->data[$confirmField]) {
                $this->addError($confirmField, 'Password confirmation does not match');
            }
        }
        return $this;
    }
    
    /**
     * Validate file upload
     */
    public function file($field, $allowedTypes = [], $maxSize = MAX_FILE_SIZE) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES[$field];
            
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->addError($field, 'File upload failed');
                return $this;
            }
            
            // Check file size
            if ($file['size'] > $maxSize) {
                $this->addError($field, 'File size exceeds maximum allowed size');
            }
            
            // Check file type
            if (!empty($allowedTypes)) {
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedTypes)) {
                    $this->addError($field, 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes));
                }
            }
        }
        return $this;
    }
    
    /**
     * Validate unique value in database
     */
    public function unique($field, $table, $column, $excludeId = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            global $database;
            
            try {
                $sql = "SELECT COUNT(*) as count FROM $table WHERE $column = ?";
                $params = [$this->data[$field]];
                
                if ($excludeId) {
                    $sql .= " AND id != ?";
                    $params[] = $excludeId;
                }
                
                $stmt = $database->getConnection()->prepare($sql);
                $stmt->execute($params);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' already exists');
                }
            } catch (Exception $e) {
                error_log("Unique validation error: " . $e->getMessage());
            }
        }
        return $this;
    }
    
    /**
     * Validate against array of allowed values
     */
    public function in($field, $allowedValues) {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowedValues)) {
            $this->addError($field, 'Invalid value selected');
        }
        return $this;
    }
    
    /**
     * Custom validation rule
     */
    public function custom($field, $callback, $message = 'Invalid value') {
        if (isset($this->data[$field]) && !$callback($this->data[$field])) {
            $this->addError($field, $message);
        }
        return $this;
    }
    
    /**
     * Validate URL format
     */
    public function url($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
                $this->addError($field, 'Please enter a valid URL');
            }
        }
        return $this;
    }
    
    /**
     * Validate postal code (basic US format)
     */
    public function postalCode($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!preg_match('/^\d{5}(-\d{4})?$/', $this->data[$field])) {
                $this->addError($field, 'Please enter a valid postal code');
            }
        }
        return $this;
    }
    
    /**
     * Validate age (minimum age)
     */
    public function minAge($field, $minAge) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $birthDate = new DateTime($this->data[$field]);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            
            if ($age < $minAge) {
                $this->addError($field, "You must be at least $minAge years old");
            }
        }
        return $this;
    }
    
    /**
     * Sanitize and return validated data
     */
    public function getValidatedData() {
        $validated = [];
        foreach ($this->data as $key => $value) {
            if (!isset($this->errors[$key])) {
                $validated[$key] = is_string($value) ? trim($value) : $value;
            }
        }
        return $validated;
    }
}
