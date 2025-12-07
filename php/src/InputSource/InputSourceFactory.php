<?php

namespace EmbroideryConverter\InputSource;

/**
 * Factory for creating input source instances
 * Implements Strategy pattern for extensibility
 */
class InputSourceFactory
{
    private static array $registry = [];
    private string $storagePath;

    /**
     * @param string $storagePath Base storage directory path
     */
    public function __construct(string $storagePath)
    {
        $this->storagePath = $storagePath;
        $this->registerDefaultSources();
    }

    /**
     * Register default input sources
     */
    private function registerDefaultSources(): void
    {
        self::register('file', FileUploadSource::class);
        // Future sources will be registered here:
        // self::register('url', UrlSource::class);
        // self::register('text', TextLLMSource::class);
    }

    /**
     * Register a custom input source
     *
     * @param string $type Type identifier
     * @param string $className Fully qualified class name
     */
    public static function register(string $type, string $className): void
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Class {$className} does not exist");
        }

        if (!in_array(InputSourceInterface::class, class_implements($className))) {
            throw new \InvalidArgumentException(
                "Class {$className} must implement InputSourceInterface"
            );
        }

        self::$registry[$type] = $className;
    }

    /**
     * Create input source instance
     *
     * @param string $type Input type ('file', 'url', etc.)
     * @param mixed $data Input data (depends on type)
     * @return InputSourceInterface
     * @throws \InvalidArgumentException
     */
    public function create(string $type, $data): InputSourceInterface
    {
        if (!isset(self::$registry[$type])) {
            throw new \InvalidArgumentException("Unknown input source type: {$type}");
        }

        $className = self::$registry[$type];

        // Create instance based on type
        switch ($type) {
            case 'file':
                if (!is_array($data)) {
                    throw new \InvalidArgumentException('File input requires array data');
                }
                return new $className($data, $this->storagePath);

            // Future types will be handled here:
            // case 'url':
            //     return new $className($data, $this->storagePath);

            default:
                // Generic instantiation for custom sources
                return new $className($data, $this->storagePath);
        }
    }

    /**
     * Get all registered input source types
     *
     * @return array List of registered type identifiers
     */
    public static function getRegisteredTypes(): array
    {
        return array_keys(self::$registry);
    }
}
