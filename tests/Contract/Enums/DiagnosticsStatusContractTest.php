<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Tests\Contract\Enums;

use OneStopPay\OsppProtocol\Enums\DiagnosticsStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for DiagnosticsStatus enum.
 *
 * Pins the fromNotificationStatus explicit PascalCase match,
 * Pending rejection, transition consistency, and terminal counts.
 */
final class DiagnosticsStatusContractTest extends TestCase
{
    #[Test]
    public function fromNotificationStatus_accepts_exactly_4_PascalCase_values(): void
    {
        $expected = [
            'Collecting' => DiagnosticsStatus::COLLECTING,
            'Uploading' => DiagnosticsStatus::UPLOADING,
            'Uploaded' => DiagnosticsStatus::UPLOADED,
            'Failed' => DiagnosticsStatus::FAILED,
        ];

        foreach ($expected as $input => $expectedStatus) {
            self::assertSame(
                $expectedStatus,
                DiagnosticsStatus::fromNotificationStatus($input),
                "fromNotificationStatus('{$input}') should return DiagnosticsStatus::{$expectedStatus->name}",
            );
        }
    }

    #[Test]
    public function fromNotificationStatus_rejects_Pending(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DiagnosticsStatus::fromNotificationStatus('Pending');
    }

    #[Test]
    public function fromNotificationStatus_rejects_lowercase_variants(): void
    {
        $lowercaseVariants = ['collecting', 'uploading', 'uploaded', 'failed'];
        $rejected = 0;

        foreach ($lowercaseVariants as $variant) {
            try {
                DiagnosticsStatus::fromNotificationStatus($variant);
            } catch (\InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(4, $rejected, 'All 4 lowercase variants must be rejected');
    }

    #[Test]
    public function fromNotificationStatus_rejects_UPPERCASE_variants(): void
    {
        $uppercaseVariants = ['COLLECTING', 'UPLOADING', 'UPLOADED', 'FAILED'];
        $rejected = 0;

        foreach ($uppercaseVariants as $variant) {
            try {
                DiagnosticsStatus::fromNotificationStatus($variant);
            } catch (\InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(4, $rejected, 'All 4 UPPERCASE variants must be rejected');
    }

    #[Test]
    public function allowedTransitions_consistent_with_canTransitionTo(): void
    {
        $allCases = DiagnosticsStatus::cases();

        foreach ($allCases as $from) {
            foreach ($allCases as $to) {
                $viaAllowed = in_array($to, $from->allowedTransitions(), true);
                $viaCan = $from->canTransitionTo($to);

                self::assertSame(
                    $viaAllowed,
                    $viaCan,
                    "DiagnosticsStatus::{$from->name}->canTransitionTo({$to->name}) "
                    . "should match in_array check on allowedTransitions()",
                );
            }
        }
    }

    #[Test]
    public function isTerminal_count_is_exactly_2(): void
    {
        $terminal = array_filter(
            DiagnosticsStatus::cases(),
            fn (DiagnosticsStatus $s) => $s->isTerminal(),
        );
        self::assertCount(2, $terminal);

        $terminalSet = array_values($terminal);
        self::assertContains(DiagnosticsStatus::UPLOADED, $terminalSet);
        self::assertContains(DiagnosticsStatus::FAILED, $terminalSet);
    }
}
