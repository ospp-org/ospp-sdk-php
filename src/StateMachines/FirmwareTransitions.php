<?php

declare(strict_types=1);

namespace Ospp\Protocol\StateMachines;

use Ospp\Protocol\Enums\FirmwareUpdateStatus;

final class FirmwareTransitions
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'idle' => ['downloading'],
        'downloading' => ['downloaded', 'failed'],
        'downloaded' => ['verifying', 'failed'],
        'verifying' => ['verified', 'failed'],
        'verified' => ['installing'],
        'installing' => ['installed', 'failed'],
        'installed' => ['rebooting', 'failed'],
        'rebooting' => ['activated', 'failed'],
        'activated' => [],
        'failed' => [],
    ];

    public function canTransition(FirmwareUpdateStatus $from, FirmwareUpdateStatus $to): bool
    {
        $allowed = self::TRANSITIONS[$from->value];

        return in_array($to->value, $allowed, true);
    }

    /**
     * @return list<FirmwareUpdateStatus>
     */
    public function allowedTransitions(FirmwareUpdateStatus $from): array
    {
        $allowed = self::TRANSITIONS[$from->value];

        return array_map(
            fn (string $s) => FirmwareUpdateStatus::from($s),
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
