<?php

namespace EmbroideryConverter\InputSource;

/**
 * Interface for all input sources (file uploads, URLs, text+LLM, etc.)
 */
interface InputSourceInterface
{
    /**
     * Validate input data
     *
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public function validate(): array;

    /**
     * Process and save input data
     *
     * @return string Path to the processed file
     */
    public function process(): string;

    /**
     * Get metadata about input data
     *
     * @return array Metadata (original_name, size, mime_type, etc.)
     */
    public function getMetadata(): array;

    /**
     * Get input source type
     *
     * @return string Type identifier ('file', 'url', 'text', etc.)
     */
    public function getType(): string;
}
