<?php
/**
 * File Upload Class
 * Handles secure file uploads with validation
 */

class FileUpload {
    private $uploadPath;
    private $allowedTypes;
    private $maxSize;
    
    public function __construct($uploadPath = UPLOAD_PATH, $allowedTypes = ALLOWED_FILE_TYPES, $maxSize = MAX_FILE_SIZE) {
        $this->uploadPath = rtrim($uploadPath, '/') . '/';
        $this->allowedTypes = $allowedTypes;
        $this->maxSize = $maxSize;
        
        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }
    
    /**
     * Upload single file
     */
    public function upload($file, $subdirectory = '', $customName = null) {
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'error' => 'No file uploaded'];
        }
        
        // Validate file
        $validation = $this->validateFile($file);
        if (!$validation['success']) {
            return $validation;
        }
        
        // Generate unique filename
        $filename = $customName ?: $this->generateUniqueFilename($file['name']);
        $uploadDir = $this->uploadPath . $subdirectory;
        
        // Create subdirectory if it doesn't exist
        if (!empty($subdirectory) && !is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filePath = $uploadDir . '/' . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $filePath,
                'size' => $file['size'],
                'type' => $file['type']
            ];
        } else {
            return ['success' => false, 'error' => 'Failed to move uploaded file'];
        }
    }
    
    /**
     * Upload multiple files
     */
    public function uploadMultiple($files, $subdirectory = '') {
        $results = [];
        
        if (!isset($files) || empty($files['name'][0])) {
            return ['success' => false, 'error' => 'No files uploaded'];
        }
        
        $fileCount = count($files['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
            
            $results[] = $this->upload($file, $subdirectory);
        }
        
        return $results;
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => $this->getUploadErrorMessage($file['error'])];
        }
        
        // Check file size
        if ($file['size'] > $this->maxSize) {
            return ['success' => false, 'error' => 'File size exceeds maximum allowed size of ' . $this->formatBytes($this->maxSize)];
        }
        
        // Check file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes)) {
            return ['success' => false, 'error' => 'File type not allowed. Allowed types: ' . implode(', ', $this->allowedTypes)];
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!$this->isValidMimeType($mimeType, $extension)) {
            return ['success' => false, 'error' => 'Invalid file type'];
        }
        
        // Check for malicious content in images
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            if (!$this->isValidImage($file['tmp_name'])) {
                return ['success' => false, 'error' => 'Invalid image file'];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Generate unique filename
     */
    private function generateUniqueFilename($originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Sanitize filename
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
        $basename = substr($basename, 0, 50); // Limit length
        
        // Generate unique name with timestamp and random string
        $uniqueName = $basename . '_' . time() . '_' . bin2hex(random_bytes(8));
        
        return $uniqueName . '.' . $extension;
    }
    
    /**
     * Check if MIME type is valid for extension
     */
    private function isValidMimeType($mimeType, $extension) {
        $validMimeTypes = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif']
        ];
        
        return isset($validMimeTypes[$extension]) && in_array($mimeType, $validMimeTypes[$extension]);
    }
    
    /**
     * Validate image file
     */
    private function isValidImage($filePath) {
        $imageInfo = getimagesize($filePath);
        return $imageInfo !== false;
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($errorCode) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        return $messages[$errorCode] ?? 'Unknown upload error';
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Delete file
     */
    public function deleteFile($filePath) {
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
    
    /**
     * Get file info
     */
    public function getFileInfo($filePath) {
        if (!file_exists($filePath)) {
            return null;
        }
        
        return [
            'name' => basename($filePath),
            'size' => filesize($filePath),
            'type' => mime_content_type($filePath),
            'modified' => filemtime($filePath),
            'extension' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION))
        ];
    }
    
    /**
     * Create thumbnail for image
     */
    public function createThumbnail($sourcePath, $thumbPath, $maxWidth = 150, $maxHeight = 150) {
        if (!file_exists($sourcePath)) {
            return false;
        }
        
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        // Calculate thumbnail dimensions
        $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $thumbWidth = round($sourceWidth * $ratio);
        $thumbHeight = round($sourceHeight * $ratio);
        
        // Create source image resource
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }
        
        // Create thumbnail
        $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);
        
        // Preserve transparency for PNG and GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbImage, false);
            imagesavealpha($thumbImage, true);
            $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
            imagefill($thumbImage, 0, 0, $transparent);
        }
        
        imagecopyresampled($thumbImage, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $sourceWidth, $sourceHeight);
        
        // Save thumbnail
        $result = false;
        switch ($mimeType) {
            case 'image/jpeg':
                $result = imagejpeg($thumbImage, $thumbPath, 90);
                break;
            case 'image/png':
                $result = imagepng($thumbImage, $thumbPath, 8);
                break;
            case 'image/gif':
                $result = imagegif($thumbImage, $thumbPath);
                break;
        }
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($thumbImage);
        
        return $result;
    }
}
