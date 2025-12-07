<?php

namespace EmbroideryConverter\InputSource;

/**
 * File upload input source (MVP implementation)
 */
class FileUploadSource implements InputSourceInterface
{
    private array $file;
    private string $storagePath;
    private ?string $savedPath = null;

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    /**
     * @param array $file $_FILES array element
     * @param string $storagePath Base storage directory path
     */
    public function __construct(array $file, string $storagePath)
    {
        $this->file = $file;
        $this->storagePath = rtrim($storagePath, '/');
    }

    /**
     * {@inheritdoc}
     */
    public function validate(): array
    {
        $errors = [];

        // Check for upload errors
        if (!isset($this->file['error']) || $this->file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed: ' . $this->getUploadErrorMessage($this->file['error'] ?? -1);
            return ['valid' => false, 'errors' => $errors];
        }

        // Check file size
        if ($this->file['size'] > self::MAX_FILE_SIZE) {
            $errors[] = 'File too large. Maximum size is ' . (self::MAX_FILE_SIZE / 1024 / 1024) . 'MB';
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $this->file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            $errors[] = 'Invalid file type. Allowed types: JPG, PNG, GIF, WEBP';
        }

        // Additional check: verify it's actually an image
        if (!getimagesize($this->file['tmp_name'])) {
            $errors[] = 'File is not a valid image';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function process(): string
    {
        $validation = $this->validate();
        if (!$validation['valid']) {
            throw new \RuntimeException('Validation failed: ' . implode(', ', $validation['errors']));
        }

        // Generate unique filename
        $extension = $this->getFileExtension();
        $uuid = $this->generateUuid();
        $filename = $uuid . '.' . $extension;

        // Ensure uploads directory exists
        $uploadsDir = $this->storagePath . '/uploads';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $targetPath = $uploadsDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($this->file['tmp_name'], $targetPath)) {
            throw new \RuntimeException('Failed to save uploaded file');
        }

        $this->savedPath = $targetPath;
        return $targetPath;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(): array
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $this->file['tmp_name']);
        finfo_close($finfo);

        return [
            'original_name' => $this->file['name'] ?? 'unknown',
            'size' => $this->file['size'] ?? 0,
            'mime_type' => $mimeType,
            'saved_path' => $this->savedPath
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'file';
    }

    /**
     * Get file extension from uploaded file
     */
    private function getFileExtension(): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $this->file['tmp_name']);
        finfo_close($finfo);

        $mimeToExtension = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];

        return $mimeToExtension[$mimeType] ?? 'jpg';
    }

    /**
     * Generate UUID v4
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Get human-readable upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
        ];

        return $errors[$errorCode] ?? 'Unknown upload error';
    }
}
