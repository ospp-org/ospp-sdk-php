<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\StateMachines;

use Ospp\Protocol\Enums\BayStatus;
use Ospp\Protocol\Enums\EffectedBy;
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
        $table = $this->transitions->getTransitionTable(EffectedBy::STATION);
        $tableKeys = array_keys($table);
        sort($tableKeys);

        $bayStatusValues = array_map(fn (BayStatus $s) => $s->value, BayStatus::cases());
        sort($bayStatusValues);

        self::assertCount(7, $table);
        self::assertSame($bayStatusValues, $tableKeys);
    }

    #[Test]
    public function transition_count_is_exactly_20_for_a_station(): void
    {
        // spec/05-state-machines.md §2.3: "Twenty `Station` rows by distinct
        // `(from, to)` pair, and six `Server` rows -- twenty-six in all."
        self::assertSame(20, $this->transitions->transitionCount(EffectedBy::STATION));
        self::assertSame(26, $this->transitions->transitionCount(EffectedBy::SERVER));
    }

    #[Test]
    public function self_transitions_are_never_allowed(): void
    {
        foreach (BayStatus::cases() as $status) {
            self::assertFalse(
                $this->transitions->canTransition($status, $status, EffectedBy::STATION),
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
                if ($this->transitions->canTransition($from, $to, EffectedBy::STATION)) {
                    $valid++;
                } else {
                    $invalid++;
                }
            }
        }

        self::assertSame(20, $valid, 'Expected exactly 20 valid Station transitions');
        self::assertSame(29, $invalid, 'Expected exactly 29 invalid Station transitions');
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

            if ($this->transitions->canTransition($status, BayStatus::FAULTED, EffectedBy::STATION)) {
                $statesThatCanReachFaulted[] = $status->value;
            }
        }

        // unknown, available, reserved, occupied, finishing, unavailable can all reach faulted
        self::assertCount(6, $statesThatCanReachFaulted);
        self::assertFalse(
            $this->transitions->canTransition(BayStatus::FAULTED, BayStatus::FAULTED, EffectedBy::STATION),
            'FAULTED should not transition to itself',
        );
    }

    #[Test]
    public function faulted_recovers_to_exactly_available_or_unavailable(): void
    {
        $allowed = $this->transitions->allowedTransitions(BayStatus::FAULTED, EffectedBy::STATION);

        self::assertCount(2, $allowed);

        $values = array_map(fn (BayStatus $s) => $s->value, $allowed);
        sort($values);

        self::assertSame(['available', 'unavailable'], $values);
    }

    #[Test]
    public function all_target_values_are_valid_BayStatus_strings(): void
    {
        $validValues = array_map(fn (BayStatus $s) => $s->value, BayStatus::cases());
        $table = $this->transitions->getTransitionTable(EffectedBy::STATION);

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
