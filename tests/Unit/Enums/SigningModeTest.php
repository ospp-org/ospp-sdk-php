<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\MessageType;
use Ospp\Protocol\Enums\SigningMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SigningModeTest extends TestCase
{
    #[Test]
    public function it_has_exactly_two_cases(): void
    {
        // spec/06-security.md §5.1: "`MessageSigningMode` -- Chapter 08 registry
        // key #18, with values `All` and `None` -- is **withdrawn**", and "the
        // middle mode `Critical` had already been removed on the same reasoning:
        // with everything signed it selected nothing."
        //
        // "Two modes are defined" is NOT in the spec -- zero hits at `.spec-ref`.
        // §5.1 reads "There is no mode, and no configuration key selects one."
        self::assertCount(2, SigningMode::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('All', SigningMode::ALL->value);
        self::assertSame('None', SigningMode::NONE->value);
    }

    #[Test]
    public function the_default_is_all(): void
    {
        self::assertSame(SigningMode::ALL, SigningMode::default());
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(SigningMode::ALL, SigningMode::from('All'));
        self::assertSame(SigningMode::NONE, SigningMode::from('None'));
    }

    #[Test]
    public function try_from_refuses_the_removed_and_the_lowercase_forms(): void
    {
        // The lowercase spellings were removed as drift; a backed enum refuses
        // them through `tryFrom()`. The sentence this comment used to attribute
        // to §5.1 is not in the spec -- the record is the spec CHANGELOG at
        // 0.34.0, which names five sites across four files.
        self::assertNull(SigningMode::tryFrom('Critical'));
        self::assertNull(SigningMode::tryFrom('all'));
        self::assertNull(SigningMode::tryFrom('none'));
        self::assertNull(SigningMode::tryFrom('critical'));
        self::assertNull(SigningMode::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        SigningMode::from('Critical');
    }

    #[Test]
    public function requires_mac_answers_per_action_and_message_type(): void
    {
        self::assertTrue(SigningMode::ALL->requiresMac('StartService', MessageType::REQUEST));
        self::assertFalse(SigningMode::ALL->requiresMac('BootNotification', MessageType::REQUEST));
        self::assertFalse(SigningMode::NONE->requiresMac('StartService', MessageType::REQUEST));
    }
}
