<?php

declare(strict_types=1);

namespace Ospp\Protocol\StateMachines;

use Ospp\Protocol\Enums\DiagnosticsState;

final class DiagnosticsTransitions
{
    /**
     * The seven edges of spec/05-state-machines.md §8.3.
     *
     * Seven rows and seven edges: unlike §6.3, no `(from, to)` pair appears twice
     * here. §8.3 states that the coincidence "is a property of this table and not
     * a general one: a conformance check MUST assert the pairs, not the cardinal,
     * because a count cannot say which edge moved."
     *
     * Three things this table did NOT have before 0.23.0, each with its own
     * consequence:
     *
     *  * It started at `pending`, a state the STATION does not have. `pending`
     *    belongs to a server's record of a request (§8.5) and is not a member of
     *    this machine at all.
     *  * It carried `pending -> failed`. There is no `idle -> failed` edge and its
     *    absence is load-bearing: a station that cannot start answers the command
     *    `Rejected` and never enters the machine, so a `Failed` that is the FIRST
     *    notification of an accepted operation is non-conforming.
     *  * `uploaded` and `failed` were terminal, which made the machine single-use
     *    — the identical defect FirmwareTransitions closed at 0.20.0 and that was
     *    never carried across.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'idle' => ['collecting'],
        'collecting' => ['uploading', 'failed'],
        'uploading' => ['uploaded', 'failed'],
        'uploaded' => ['idle'],
        'failed' => ['idle'],
    ];

    public function canTransition(DiagnosticsState $from, DiagnosticsState $to): bool
    {
        $allowed = self::TRANSITIONS[$from->value];

        return in_array($to->value, $allowed, true);
    }

    /**
     * @return list<DiagnosticsState>
     */
    public function allowedTransitions(DiagnosticsState $from): array
    {
        $allowed = self::TRANSITIONS[$from->value];

        return array_map(
            fn (string $s) => DiagnosticsState::from($s),
            $allowed,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    public function getTransitionTable(): array
    {
        return self::TRANSITIONS;
    }

    public function transitionCount(): int
    {
        $count = 0;
        foreach (self::TRANSITIONS as $targets) {
            $count += count($targets);
        }

        return $count;
    }
}
