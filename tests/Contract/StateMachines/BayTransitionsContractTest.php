<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\StateMachines;

use Ospp\Protocol\Enums\BayStatus;
use Ospp\Protocol\StateMachines\BayTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BayTransitionsContractTest extends TestCase
{
    private BayTransitions $transitions;

    protected function setUp(): void
    {
        $this->transitions = new BayTransitions();
    }

    #[Test]
    public function transition_table_has_exactly_7_states(): void
    {
        $table = $this->transitions->getTransitionTable();
        $tableKeys = array_keys($table);
        sort($tableKeys);

        $bayStatusValues = array_map(fn (BayStatus $s) => $s->value, BayStatus::cases());
        sort($bayStatusValues);

        self::assertCount(7, $table);
        self::assertSame($bayStatusValues, $tableKeys);
    }

    #[Test]
    public function transition_count_is_exactly_18(): void
    {
        self::assertSame(18, $this->transitions->transitionCount());
    }

    #[Test]
    public function self_transitions_are_never_allowed(): void
    {
        foreach (BayStatus::cases() as $status) {
            self::assertFalse(
                $this->transitions->canTransition($status, $status),
                "Self-transition should not be allowed for {$status->value}",
            );
        }
    }

    #[Test]
    public function complete_7x7_transition_matrix(): void
    {
        $valid = 0;
        $invalid = 0;

        foreach (BayStatus::cases() as $from) {
            foreach (BayStatus::cases() as $to) {
                if ($this->transitions->canTransition($from, $to)) {
                    $valid++;
                } else {
                    $invalid++;
                }
            }
        }

        self::assertSame(18, $valid, 'Expected exactly 18 valid transitions');
        self::assertSame(31, $invalid, 'Expected exactly 31 invalid transitions');
        self::assertSame(49, $valid + $invalid, 'Total pairs should be 49 (7x7)');
    }

    #[Test]
    public function every_state_can_reach_faulted_except_from_faulted(): void
    {
        $statesThatCanReachFaulted = [];

        foreach (BayStatus::cases() as $status) {
            if ($status === BayStatus::FAULTED) {
                continue;
            }

            if ($this->transitions->canTransition($status, BayStatus::FAULTED)) {
                $statesThatCanReachFaulted[] = $status->value;
            }
        }

        // unknown, available, reserved, occupied, finishing, unavailable can all reach faulted
        self::assertCount(6, $statesThatCanReachFaulted);
        self::assertFalse(
            $this->transitions->canTransition(BayStatus::FAULTED, BayStatus::FAULTED),
            'FAULTED should not transition to itself',
        );
    }

    #[Test]
    public function faulted_recovers_to_exactly_available_or_unavailable(): void
    {
        $allowed = $this->transitions->allowedTransitions(BayStatus::FAULTED);

        self::assertCount(2, $allowed);

        $values = array_map(fn (BayStatus $s) => $s->value, $allowed);
        sort($values);

        self::assertSame(['available', 'unavailable'], $values);
    }

    #[Test]
    public function all_target_values_are_valid_BayStatus_strings(): void
    {
        $validValues = array_map(fn (BayStatus $s) => $s->value, BayStatus::cases());
        $table = $this->transitions->getTransitionTable();

        foreach ($table as $source => $targets) {
            self::assertContains($source, $validValues, "Source '{$source}' is not a valid BayStatus");

            foreach ($targets as $target) {
                self::assertContains(
                    $target,
                    $validValues,
                    "Target '{$target}' from '{$source}' is not a valid BayStatus",
                );
            }
        }
    }
}
