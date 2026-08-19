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

    /**
     * The reportable states that can legitimately arrive next, from `$from`.
     *
     * This is the method the diagnostics machine did not need. There, every edge
     * of §8.3 runs between two states the wire carries, so the sequence a server
     * observes IS a walk of the table and `canTransition()` answers directly.
     *
     * Here it is not. Four states are unobservable (§6.6) and three of the
     * thirteen edges pass through them, so the conforming notification sequence
     * SKIPS states:
     *
     *   `Downloaded -> Verifying -> Verified -> Installing`
     *
     * is what the station does, and `Downloaded` then `Installing` is what the
     * server is told. A consumer that fed those two into `canTransition()` would
     * refuse the update at the point it starts installing — §6.3 has no
     * `Downloaded -> Installing` row and MUST NOT gain one. The edge is not
     * missing; the two states between it are silent.
     *
     * So the answer is the set of reportable states reachable by a path whose
     * INTERMEDIATE states are all unobservable. `Activated` is neither returned
     * nor traversed: it is observable (§6.6 maps it to BootNotification), so a
     * server is told about it and must not have it inferred, and it is terminal
     * besides.
     *
     * @return list<FirmwareUpdateStatus>
     */
    public function observableTargets(FirmwareUpdateStatus $from): array
    {
        $reachable = [];
        $queue = [$from];
        $seen = [$from->value => true];

        while ($queue !== []) {
            $state = array_shift($queue);

            foreach ($this->allowedTransitions($state) as $target) {
                if ($target->isReportable()) {
                    $reachable[$target->value] = $target;

                    continue;
                }

                // Observable but not reportable — `Activated`. The server hears
                // about it from another message; it is not something to walk past.
                if ($target->isObservable()) {
                    continue;
                }

                if (! isset($seen[$target->value])) {
                    $seen[$target->value] = true;
                    $queue[] = $target;
                }
            }
        }

        return array_values($reachable);
    }

    /**
     * Advance a server's mirror of the machine from one arriving
     * FirmwareStatusNotification.
     *
     * Two rules, and neither is `canTransition()`:
     *
     *  * A repeat of the state already held is a PROGRESS report, not a
     *    transition. `firmware-status.md` §5 rules 1 and 2 ask for a notification
     *    every 10% of `Downloading` and at four milestones of `Installing`, and
     *    §6.3 has no self-edge for either. This is the same rule §8.4 states for
     *    the repeated `Uploading` stream.
     *  * Anything else is judged against {@see self::observableTargets()}, which
     *    walks the unobservable states the wire cannot report.
     *
     * @throws \InvalidArgumentException on a status outside the notification enum,
     *                                   or on a sequence §6.3 cannot produce
     */
    public function applyNotification(FirmwareUpdateStatus $current, string $status): FirmwareUpdateStatus
    {
        $reported = FirmwareUpdateStatus::fromNotificationStatus($status);

        if ($reported === $current) {
            return $current; // a progress report, not a transition
        }

        if (! in_array($reported, $this->observableTargets($current), true)) {
            throw new \InvalidArgumentException(
                "Invalid firmware transition: {$current->value} -> {$reported->value}",
            );
        }

        return $reported;
    }
}
