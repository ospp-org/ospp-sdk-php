<?php

declare(strict_types=1);

namespace Ospp\Protocol\StateMachines;

use Ospp\Protocol\Enums\BayStatus;

final class BayTransitions
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'unknown' => ['available', 'faulted', 'unavailable'],
        'available' => ['reserved', 'occupied', 'faulted', 'unavailable'],
        'reserved' => ['available', 'occupied', 'faulted'],
        'occupied' => ['finishing', 'faulted'],
        'finishing' => ['available', 'faulted'],
        'faulted' => ['available', 'unavailable'],
        'unavailable' => ['available', 'faulted'],
    ];

    public function canTransition(BayStatus $from, BayStatus $to): bool
    {
        $allowed = self::TRANSITIONS[$from->value];

        return in_array($to->value, $allowed, true);
    }

    /**
     * @return list<BayStatus>
     */
    public function allowedTransitions(BayStatus $from): array
    {
        $allowed = self::TRANSITIONS[$from->value];

        return array_map(
            fn (string $s) => BayStatus::from($s),
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
