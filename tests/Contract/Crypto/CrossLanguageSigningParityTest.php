<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Crypto;

use Ospp\Protocol\Crypto\MessageSigningRegistry;
use Ospp\Protocol\Enums\MessageType;
use Ospp\Protocol\Enums\SigningMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cross-language signing-classification parity (ospp-sdk-php side).
 *
 * The shared fixture signing-classification.json is BYTE-IDENTICAL with sdk-ts
 * (tests/crypto/fixtures/signing-classification.json) and encodes spec §5.6 as
 * data.
 *
 * It is now EXHAUSTIVE — all 47 message types, each with the answer
 * `requiresMac` must give in `All` mode — because §5.6 removed per-message
 * judgement entirely. Both SDKs assert every row, so a signing rule implemented
 * in one language and not the other turns one repo's suite RED.
 *
 * This SDK now keys on (action, messageType), the SAME axis as the spec and as
 * sdk-ts. It used to key on action alone, which needed a collapse projection
 * and a no-split guard to stay sound; the projection is gone because the axis
 * no longer differs.
 */
final class CrossLanguageSigningParityTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $classification;

    protected function setUp(): void
    {
        $raw = file_get_contents(__DIR__.'/fixtures/signing-classification.json');
        self::assertIsString($raw, 'shared classification fixture must be readable');

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->classification = $decoded;
    }

    /**
     * @return list<array{action: string, messageType: string, signedInAll: bool}>
     */
    private function messages(): array
    {
        /** @var list<array{action: string, messageType: string, signedInAll: bool}> $rows */
        $rows = $this->classification['messages'];

        return $rows;
    }

    #[Test]
    public function itAgreesOnTheModeVocabularyAndTheDefault(): void
    {
        self::assertSame(['All', 'None'], $this->classification['modes']);
        self::assertSame($this->classification['defaultMode'], SigningMode::default()->value);
    }

    #[Test]
    public function structuralExemptionsEqualTheSharedSet(): void
    {
        /** @var list<array{action: string, messageType: string}> $rows */
        $rows = $this->classification['structuralExemptions'];

        $expected = array_map(fn (array $r) => $r['action'].':'.$r['messageType'], $rows);
        sort($expected);

        $actual = MessageSigningRegistry::allStructuralExemptions();
        sort($actual);

        self::assertSame($expected, $actual);
        self::assertCount(3, $actual);
    }

    #[Test]
    public function itCoversAll47MessageTypes44SignedAnd3Exempt(): void
    {
        $messages = $this->messages();

        self::assertCount(47, $messages);
        self::assertCount(44, array_filter($messages, fn (array $r) => $r['signedInAll']));
        self::assertCount(3, array_filter($messages, fn (array $r) => ! $r['signedInAll']));
    }

    #[Test]
    public function requiresMacMatchesTheFixtureOnEveryRowInAllMode(): void
    {
        foreach ($this->messages() as $row) {
            self::assertSame(
                $row['signedInAll'],
                SigningMode::ALL->requiresMac($row['action'], MessageType::from($row['messageType'])),
                "{$row['action']}:{$row['messageType']}",
            );
        }
    }

    #[Test]
    public function requiresMacIsFalseOnEveryRowInNoneMode(): void
    {
        foreach ($this->messages() as $row) {
            self::assertFalse(
                SigningMode::NONE->requiresMac($row['action'], MessageType::from($row['messageType'])),
                "{$row['action']}:{$row['messageType']}",
            );
        }
    }

    /**
     * §5.7 makes the sending and receiving paths the same condition read from
     * two ends. A receiver that expected a MAC the sender did not owe would
     * reject conforming traffic, so the two answers must never differ.
     */
    #[Test]
    public function theSendAndVerifySidesAgreeOnEveryRowInEveryMode(): void
    {
        foreach (SigningMode::cases() as $mode) {
            foreach ($this->messages() as $row) {
                $type = MessageType::from($row['messageType']);

                self::assertSame(
                    $mode->requiresMac($row['action'], $type),
                    $mode->requiresMacVerification($row['action'], $type),
                    "{$row['action']}:{$row['messageType']} in {$mode->value}",
                );
            }
        }
    }

    /**
     * The three REST-only actions this SDK once carried in its critical set are
     * not wire messages and are absent from the 47.
     */
    #[Test]
    public function thePhpRestOnlySupersetIsNotAWireMessage(): void
    {
        /** @var list<string> $superset */
        $superset = $this->classification['phpApiOnlySuperset'];
        $actions = array_column($this->messages(), 'action');

        foreach ($superset as $action) {
            self::assertNotContains($action, $actions, "{$action} is REST-only, not a wire message");
        }
    }
}
