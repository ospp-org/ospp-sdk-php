<?php

declare(strict_types=1);

namespace Ospp\Protocol\StateMachines;

use Ospp\Protocol\Enums\StationState;

/**
 * Station State Machine — 6 states, the OUTERMOST machine.
 *
 * Source: spec/05-state-machines.md §1.
 *
 * The chapter previously defined machines for bays, sessions, reservations, BLE
 * and firmware and none for the station, so `Pending`, `Rejected`, `Accepted`
 * and not-provisioned had no formal home, and `3018 TOPOLOGY_MISMATCH` depended
 * on a state that existed nowhere structurally.
 *
 * Every other machine on a station is scoped inside this one: a bay transition
 * is only reportable, and a session only startable, while the station is
 * `Operational`.
 */
final class StationTransitions
{
    /**
     * §1.3. Any transition not listed here is invalid.
     *
     * Note `Booting -> Booting`: a response timeout is a real self-transition
     * here, the station re-publishing its BootNotification after 30 s. That is
     * unlike the bay machine, which contains no self-transition at all.
     *
     * There is no edge from `Pending` or `Rejected` directly to `Operational`:
     * a station leaves a restricted state only by re-sending BootNotification
     * and receiving `Accepted`. The server cannot promote a station in place.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        // Credential obtained (provisioning, out of band, or HTTP).
        'NotProvisioned' => ['Booting'],
        // RESPONSE Accepted / Pending / Rejected, response timeout, connection lost.
        'Booting' => ['Operational', 'Pending', 'Rejected', 'Booting', 'Disconnected'],
        // retryInterval elapsed -> re-publish BootNotification.
        'Pending' => ['Booting', 'Disconnected'],
        'Rejected' => ['Booting', 'Disconnected'],
        // Reboot (Reset, firmware update, watchdog, power cycle), or connection lost.
        'Operational' => ['Booting', 'Disconnected'],
        // MQTT reconnected -> BootNotification with bootReason "Reconnect" if it
        // did not reboot.
        'Disconnected' => ['Booting'],
    ];

    public function canTransition(StationState $from, StationState $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value], true);
    }

    /**
     * @return list<StationState>
     */
    public function allowedTransitions(StationState $from): array
    {
        return array_map(
            fn (string $s) => StationState::from($s),
            self::TRANSITIONS[$from->value],
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
