<?php

declare(strict_types=1);

namespace Ospp\Protocol;

final class SchemaPath
{
    /**
     * Returns the absolute path to the schemas/ directory shipped with this package.
     */
    public static function directory(): string
    {
        return dirname(__DIR__) . '/schemas';
    }
}
