<?php

declare(strict_types=1);

namespace Ospp\Protocol\StateMachines;

use Ospp\Protocol\Enums\DiagnosticsStatus;

final class DiagnosticsTransitions
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending' => ['collecting', 'failed'],
        'collecting' => ['uploading', 'failed'],
        'uploading' => ['uploaded', 'failed'],
        'uploaded' => [],
        'failed' => [],
    ];

    public function canTransition(DiagnosticsStatus $from, DiagnosticsStatus $to): bool
    {
        $allowed = self::TRANSITIONS[$from->value];

        return in_array($to->value, $allowed, true);
    }

    /**
     * @return list<DiagnosticsStatus>
     */
    public function allowedTransitions(DiagnosticsStatus $from): array
    {
        $allowed = self::TRANSITIONS[$from->value];

        return array_map(
            fn (string $s) => DiagnosticsStatus::from($s),
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
