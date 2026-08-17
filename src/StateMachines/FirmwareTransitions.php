<?php

declare(strict_types=1);

namespace Ospp\Protocol\StateMachines;

use Ospp\Protocol\Enums\FirmwareUpdateStatus;

final class FirmwareTransitions
{
    /**
     * The thirteen edges of spec/05-state-machines.md §6.3.
     *
     * The table there has fourteen ROWS: `Verifying -> Failed` is listed twice,
     * once for a checksum mismatch and once for an invalid signature, "because
     * the two have different actions and different error codes, not because they
     * are two transitions."
     *
     * `Failed` is NOT terminal. §6.3: "`Failed` has exactly one outgoing edge,
     * `Failed -> Idle`; it is **not** terminal, and a machine that treats it as
     * terminal can run one firmware update and never a second."
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'idle' => ['downloading'],
        'downloading' => ['downloaded', 'failed'],
        'downloaded' => ['verifying'],
        'verifying' => ['verified', 'failed'],
        'verified' => ['installing'],
        'installing' => ['installed', 'failed'],
        'installed' => ['rebooting'],
        'rebooting' => ['activated', 'failed'],
        'activated' => [],
        'failed' => ['idle'],
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
