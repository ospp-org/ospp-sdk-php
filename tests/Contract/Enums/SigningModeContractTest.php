<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Enums;

use Ospp\Protocol\Actions\OsppAction;
use Ospp\Protocol\Crypto\CriticalMessageRegistry;
use Ospp\Protocol\Enums\SigningMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for SigningMode enum.
 *
 * Pins the three mode behaviors (ALL, CRITICAL, NONE) and verifies
 * that shouldVerify is identical to shouldSign for all modes and actions.
 */
final class SigningModeContractTest extends TestCase
{
    #[Test]
    public function CRITICAL_mode_delegates_to_CriticalMessageRegistry(): void
    {
        $criticalActions = CriticalMessageRegistry::allCriticalActions();
        self::assertCount(14, $criticalActions, 'Expected exactly 14 critical actions');

        // All 14 critical actions must return true
        foreach ($criticalActions as $action) {
            self::assertTrue(
                SigningMode::CRITICAL->shouldSign($action),
                "CRITICAL->shouldSign('{$action}') should be true",
            );
        }

        // Find a non-critical MQTT action and verify it returns false
        $nonCriticalMqttActions = array_filter(
            OsppAction::mqttActions(),
            fn (string $action) => ! CriticalMessageRegistry::isCritical($action),
        );

        foreach ($nonCriticalMqttActions as $action) {
            self::assertFalse(
                SigningMode::CRITICAL->shouldSign($action),
                "CRITICAL->shouldSign('{$action}') should be false (non-critical MQTT action)",
            );
        }
    }

    #[Test]
    public function ALL_mode_signs_everything(): void
    {
        // All known actions
        foreach (OsppAction::all() as $action) {
            self::assertTrue(
                SigningMode::ALL->shouldSign($action),
                "ALL->shouldSign('{$action}') should be true",
            );
        }

        // Even completely unknown actions
        self::assertTrue(SigningMode::ALL->shouldSign('FooBarUnknown'));
    }

    #[Test]
    public function NONE_mode_signs_nothing(): void
    {
        // All known actions
        foreach (OsppAction::all() as $action) {
            self::assertFalse(
                SigningMode::NONE->shouldSign($action),
                "NONE->shouldSign('{$action}') should be false",
            );
        }

        // Even critical actions
        self::assertFalse(SigningMode::NONE->shouldSign('StartService'));
    }

    #[Test]
    public function shouldVerify_identical_to_shouldSign_for_all_modes_and_all_24_actions(): void
    {
        $allActions = OsppAction::all();
        self::assertCount(24, $allActions, 'Expected exactly 24 OSPP actions');

        foreach (SigningMode::cases() as $mode) {
            foreach ($allActions as $action) {
                self::assertSame(
                    $mode->shouldSign($action),
                    $mode->shouldVerify($action),
                    "SigningMode::{$mode->name}->shouldVerify('{$action}') "
                    . "should be identical to shouldSign('{$action}')",
                );
            }
        }
    }
}
