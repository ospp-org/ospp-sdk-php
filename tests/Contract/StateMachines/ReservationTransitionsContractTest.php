<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\StateMachines;

use Ospp\Protocol\Enums\BayStatus;
use Ospp\Protocol\Enums\OsppErrorCode;
use Ospp\Protocol\Enums\ReservationStatus;
use Ospp\Protocol\StateMachines\ReservationTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReservationTransitionsContractTest extends TestCase
{
    private ReservationTransitions $transitions;

    protected function setUp(): void
    {
        $this->transitions = new ReservationTransitions();
    }

    #[Test]
    public function transition_count_is_exactly_5(): void
    {
        self::assertSame(5, $this->transitions->transitionCount());
    }

    #[Test]
    public function self_transitions_are_never_allowed(): void
    {
        foreach (ReservationStatus::cases() as $status) {
            self::assertFalse(
                $this->transitions->canTransition($status, $status),
                "Self-transition should not be allowed for {$status->value}",
            );
        }
    }

    #[Test]
    public function complete_5x5_transition_matrix(): void
    {
        $valid = 0;
        $invalid = 0;

        foreach (ReservationStatus::cases() as $from) {
            foreach (ReservationStatus::cases() as $to) {
                if ($this->transitions->canTransition($from, $to)) {
                    $valid++;
                } else {
                    $invalid++;
                }
            }
        }

        self::assertSame(5, $valid, 'Expected exactly 5 valid transitions');
        self::assertSame(20, $invalid, 'Expected exactly 20 invalid transitions');
        self::assertSame(25, $valid + $invalid, 'Total pairs should be 25 (5x5)');
    }

    #[Test]
    public function ttl_pinned_min_1_max_15(): void
    {
        $constraints = $this->transitions->getTtlConstraints();

        self::assertSame(['min' => 1, 'max' => 15], $constraints);
    }

    #[Test]
    public function validateBayForReservation_error_codes_are_valid_OsppErrorCodes(): void
    {
        foreach (BayStatus::cases() as $bayStatus) {
            $result = $this->transitions->validateBayForReservation($bayStatus);

            if ($result['allowed'] === true) {
                self::assertNull($result['errorCode'], "Allowed bay status {$bayStatus->value} should have null errorCode");
                continue;
            }

            // This should not throw - if errorCode is valid, OsppErrorCode::from() succeeds
            $errorCode = OsppErrorCode::from($result['errorCode']);
            self::assertInstanceOf(
                OsppErrorCode::class,
                $errorCode,
                "Error code {$result['errorCode']} for bay status {$bayStatus->value} should be a valid OsppErrorCode",
            );
        }
    }

    #[Test]
    public function validateBayForReservation_error_texts_match_OsppErrorCode_names(): void
    {
        foreach (BayStatus::cases() as $bayStatus) {
            $result = $this->transitions->validateBayForReservation($bayStatus);

            if ($result['allowed'] === true) {
                self::assertNull($result['errorText'], "Allowed bay status {$bayStatus->value} should have null errorText");
                continue;
            }

            $errorCode = OsppErrorCode::from($result['errorCode']);
            self::assertSame(
                $errorCode->errorText(),
                $result['errorText'],
                "Error text for bay status {$bayStatus->value} should match OsppErrorCode::errorText()",
            );
        }
    }
}
