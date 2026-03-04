<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit;

use Ospp\Protocol\SchemaPath;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaPathTest extends TestCase
{
    #[Test]
    public function directory_returns_existing_path(): void
    {
        $dir = SchemaPath::directory();

        $this->assertDirectoryExists($dir);
    }

    #[Test]
    public function directory_contains_schema_subdirectories(): void
    {
        $dir = SchemaPath::directory();

        $this->assertDirectoryExists($dir . '/ble');
        $this->assertDirectoryExists($dir . '/common');
        $this->assertDirectoryExists($dir . '/mqtt');
    }

    #[Test]
    public function directory_contains_json_schema_files(): void
    {
        $files = glob(SchemaPath::directory() . '/**/*.schema.json');

        $this->assertNotEmpty($files);
    }

    #[Test]
    public function mqtt_schemas_include_boot_notification_request(): void
    {
        $file = SchemaPath::directory() . '/mqtt/boot-notification-request.schema.json';

        $this->assertFileExists($file);

        $content = json_decode((string) file_get_contents($file), true);
        $this->assertIsArray($content);
    }
}
