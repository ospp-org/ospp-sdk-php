<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Enums;

use Ospp\Protocol\Actions\OsppAction;
use Ospp\Protocol\Crypto\MessageSigningRegistry;
use Ospp\Protocol\Enums\MessageType;
use Ospp\Protocol\Enums\SigningMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for SigningMode — spec/06-security.md §5.1, §5.6.
 *
 * Two modes now, not three. `Critical` is removed: with everything signed it
 * selected nothing, and the 47-row per-message classification table went with
 * it. The exhaustive per-message proof lives in
 * {@see \Ospp\Protocol\Tests\Contract\Crypto\CrossLanguageSigningParityTest},
 * which runs the shared fixture sdk-ts runs; this file pins the enum itself.
 */
final class SigningModeContractTest extends TestCase
{
    #[Test]
    public function ALL_mode_signs_everything_except_the_three_structural_exemptions(): void
    {
        foreach (OsppAction::all() as $action) {
            foreach (MessageType::cases() as $type) {
                $exempt = MessageSigningRegistry::isStructurallyExempt($action, $type);

                self::assertSame(
                    ! $exempt,
                    SigningMode::ALL->requiresMac($action, $type),
                    "ALL->requiresMac('{$action}', '{$type->value}')",
                );
            }
        }

        // There is no per-message judgement left, so an action this SDK has
        // never heard of is signed rather than exempted.
        self::assertTrue(SigningMode::ALL->requiresMac('FooBarUnknown', MessageType::REQUEST));
    }

    #[Test]
    public function ALL_mode_exempts_the_three_and_only_the_three(): void
    {
        // ConnectionLost is exempt only as the broker's LWT EVENT: the station
        // cannot pre-sign the broker's Last Will. As a REQUEST it is signed
        // like anything else.
        self::assertFalse(SigningMode::ALL->requiresMac('ConnectionLost', MessageType::EVENT));
        self::assertTrue(SigningMode::ALL->requiresMac('ConnectionLost', MessageType::REQUEST));

        // BootNotification is exempt in BOTH directions, for two different
        // structural reasons — one precedes the key, one carries it.
        self::assertFalse(SigningMode::ALL->requiresMac('BootNotification', MessageType::REQUEST));
        self::assertFalse(SigningMode::ALL->requiresMac('BootNotification', MessageType::RESPONSE));
        self::assertTrue(SigningMode::ALL->requiresMac('BootNotification', MessageType::EVENT));
    }

    #[Test]
    public function NONE_mode_signs_nothing(): void
    {
        foreach (OsppAction::all() as $action) {
            foreach (MessageType::cases() as $type) {
                self::assertFalse(
                    SigningMode::NONE->requiresMac($action, $type),
                    "NONE->requiresMac('{$action}', '{$type->value}')",
                );
            }
        }
    }

    #[Test]
    public function verification_is_identical_to_signing_for_every_mode_action_and_type(): void
    {
        $allActions = OsppAction::all();
        self::assertCount(30, $allActions, 'Expected exactly 30 OSPP actions');

        foreach (SigningMode::cases() as $mode) {
            foreach ($allActions as $action) {
                foreach (MessageType::cases() as $type) {
                    self::assertSame(
                        $mode->requiresMac($action, $type),
                        $mode->requiresMacVerification($action, $type),
                        "SigningMode::{$mode->name} disagrees with itself on {$action}:{$type->value}",
                    );
                }
            }
        }
    }
}
