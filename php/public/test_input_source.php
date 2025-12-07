<?php
/**
 * Simple test script for InputSource classes
 * This file will be removed after testing
 */

require_once __DIR__ . '/../vendor/autoload.php';

use EmbroideryConverter\InputSource\InputSourceFactory;

header('Content-Type: application/json');

// Test factory registration
$factory = new InputSourceFactory('/var/www/storage');
$types = InputSourceFactory::getRegisteredTypes();

echo json_encode([
    'status' => 'OK',
    'message' => 'InputSource classes loaded successfully',
    'registered_types' => $types,
    'storage_path' => '/var/www/storage'
], JSON_PRETTY_PRINT);
