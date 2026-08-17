<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Schemas;

use Opis\JsonSchema\Validator;
use Ospp\Protocol\SchemaPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The spec's conformance vectors, validated against the vendored schemas.
 *
 * Mirrors sdk-ts tests/validation/SchemaValidator.test.ts, which has run these
 * for some time. THIS SDK RAN NONE OF THEM until this arc: it vendored the
 * complete schema set and asserted only that the files existed and parsed. The
 * byte-identity gate caught a schema that drifted from the spec; nothing caught
 * a schema whose CONTENT the SDK misunderstood, and nothing exercised a schema
 * against a payload at all.
 *
 * That asymmetry is why the two SDKs could disagree about the wire while both
 * suites were green.
 *
 * Every vector is mapped, and an unmapped filename FAILS rather than being
 * skipped — a silent skip is how coverage reads as complete when it is not.
 */
final class ConformanceVectorTest extends TestCase
{
    private const VECTORS = __DIR__.'/../../Fixtures/test-vectors';

    /**
     * "<transport>/<schema-name>" -> schema file path.
     *
     * @return array<string, string>
     */
    private static function schemaMap(): array
    {
        $map = [];

        foreach (['mqtt', 'ble'] as $dir) {
            foreach (glob(SchemaPath::directory()."/{$dir}/*.schema.json") ?: [] as $path) {
                $map[$dir.'/'.basename($path, '.schema.json')] = $path;
            }
        }

        return $map;
    }

    /**
     * BLE and MQTT both define start-service-*; the `offline` vector directory
     * is BLE, every other directory is MQTT. Longest filename prefix wins.
     */
    private static function resolveSchema(string $category, string $fileName): ?string
    {
        $transport = $category === 'offline' ? 'ble' : 'mqtt';
        $name = (string) preg_replace('/\.json$/', '', $fileName);
        $parts = explode('-', $name);
        $map = self::schemaMap();

        // Down to ONE part: ble/hello, ble/receipt and ble/challenge are
        // single-word schema names, and a loop that stops at two never reaches
        // them. sdk-ts's deriveSchemaKey has the same floor and silently drops
        // the same fifteen vectors.
        for ($len = count($parts); $len >= 1; $len--) {
            $candidate = $transport.'/'.implode('-', array_slice($parts, 0, $len));
            if (isset($map[$candidate])) {
                return $map[$candidate];
            }
        }

        return null;
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    private static function discover(string $bucket): iterable
    {
        foreach (glob(self::VECTORS."/{$bucket}/*", GLOB_ONLYDIR) ?: [] as $categoryDir) {
            $category = basename($categoryDir);

            foreach (glob($categoryDir.'/*.json') ?: [] as $path) {
                $fileName = basename($path);
                yield "{$bucket}/{$category}/{$fileName}" => [$path, $category, $fileName];
            }
        }
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function validVectors(): iterable
    {
        yield from self::discover('valid');
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidVectors(): iterable
    {
        yield from self::discover('invalid');
    }

    private function validate(string $vectorPath, string $schemaPath): bool
    {
        $validator = new Validator();
        $resolver = $validator->resolver();

        // Register every vendored schema under its own $id so the relative
        // $refs the spec's schemas use resolve against something.
        foreach (['common', 'mqtt', 'ble'] as $dir) {
            foreach (glob(SchemaPath::directory()."/{$dir}/*.schema.json") ?: [] as $path) {
                /** @var object{'$id'?: string} $decoded */
                $decoded = json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);

                if (isset($decoded->{'$id'})) {
                    $resolver->registerFile($decoded->{'$id'}, $path);
                }
            }
        }

        $schema = json_decode((string) file_get_contents($schemaPath), false, 512, JSON_THROW_ON_ERROR);
        $payload = json_decode((string) file_get_contents($vectorPath), false, 512, JSON_THROW_ON_ERROR);

        return $validator->validate($payload, $schema)->isValid();
    }

    #[DataProvider('validVectors')]
    public function testValidVectorPasses(string $path, string $category, string $fileName): void
    {
        $schemaPath = self::resolveSchema($category, $fileName);
        self::assertNotNull($schemaPath, "no schema maps to {$category}/{$fileName} — map it rather than skipping it");

        self::assertTrue($this->validate($path, $schemaPath), "{$category}/{$fileName} must be VALID");
    }

    #[DataProvider('invalidVectors')]
    public function testInvalidVectorIsRejected(string $path, string $category, string $fileName): void
    {
        $schemaPath = self::resolveSchema($category, $fileName);
        self::assertNotNull($schemaPath, "no schema maps to {$category}/{$fileName} — map it rather than skipping it");

        self::assertFalse($this->validate($path, $schemaPath), "{$category}/{$fileName} must be REJECTED");
    }

    /**
     * The corpus must be the whole corpus. A truncated vendored copy would make
     * every test above pass vacuously.
     *
     * These two literals are a SECOND COPY of a fact about the corpus, not a check
     * on it: nothing derives them from the vendored tree, so a human must bump them
     * by hand on every new spec vector — and when they are forgotten the failure
     * lands on the maintainer who did the re-vendor CORRECTLY. See KNOWN-ISSUES:
     * the gate that should replace them counts the tree at run time and asserts only
     * `> 0`, with byte-identity against the spec clone pinning WHICH vectors are here.
     */
    #[Test]
    public function theVendoredCorpusIsComplete(): void
    {
        self::assertSame(160, iterator_count(self::validVectors()));
        // 158 at spec v0.20.2, which added invalid/device-management/
        // update-firmware-request-http-url.json.
        self::assertSame(158, iterator_count(self::invalidVectors()));
    }
}
