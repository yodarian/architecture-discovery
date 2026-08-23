<?php
namespace ArchitectureDiscovery\Infrastructure\Analyzer;

/**
 * A framework whose ORM/dynamic-resolution patterns a detector can recognize.
 */
enum Framework: string
{
    case CakePhp = 'cakephp';
    case Laravel = 'laravel';

    public function composerPackage(): string
    {
        return match ($this) {
            self::CakePhp => 'cakephp/cakephp',
            self::Laravel => 'laravel/framework',
        };
    }
}
