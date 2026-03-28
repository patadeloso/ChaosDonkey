<?php
declare(strict_types=1);

namespace Magento\Framework\Component;

class ComponentRegistrar
{
    public const MODULE = 'module';

    public static function register(string $type, string $componentName, string $path): void
    {
        // No-op for standalone unit tests.
    }
}
