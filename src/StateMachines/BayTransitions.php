<?php

declare(strict_types=1);

namespace Ospp\Protocol\StateMachines;

use Ospp\Protocol\Enums\BayStatus;
use Ospp\Protocol\Enums\EffectedBy;

/**
 * The canonical bay transition table — spec/05-state-machines.md §2.3.
 *
 * "This is the canonical table. Nothing else in this specification restates it."
 *
 * Twenty `Station` rows by distinct (from, to) pair and six `Server` rows,
 * twenty-six in all. The split is the point: a station effects and reports the
 * physical transitions, a server infers the move to `Unknown` when it can no
 * longer hear the station. Which party a caller is asking about is therefore a
 * required argument, not a default — see {@see EffectedBy}.
 */
final class BayTransitions
{
    /**
     * The twenty `Station` rows. These are the complete set a station may effect
     * and therefore the complete set a StatusNotification [MSG-009] may report.
     *
     * `Unknown` has SIX exits. §2.3: a station that reboots mid-session MUST
     * resume that session, and on the boot that follows the bay is physically
     * `Occupied` (or `Finishing`, mid wind-down) and owes a post-boot report.
     * With only the three determinate-idle exits that station had no truthful
     * report to send — `Available` would free a bay running a paid session.
     *
     * `Unknown -> Reserved` is the sixth, and it was MISSING here until `0.31.0`
     * of this SDK. Spec `0.30.0` added the row together with a station-side MUST
     * to persist a `Confirmed` reservation durably, *for exactly this reason*: a
     * station that reboots holding one reports `Reserved` and resumes the expiry
     * timer. This class refused it, `sdk-ts` refused it, and the reference server
     * delegates to this class — so the one truthful report was rejected in all
     * three, and a station following the guides reported `Available` instead,
     * which resells a reserved bay. Nothing caught it because the canonical table
     * was TRANSCRIBED into a contract test rather than derived from the spec;
     * `scripts/check-bay-transitions.php` now derives it.
     *
     * `Unavailable -> Faulted` is here because a bay taken out of service can
     * still develop a fault, and a technician working on it is the most likely
     * person to find one. Forbidding it does not prevent the fault, only the
     * report of it.
     *
     * @var array<string, list<string>>
     */
    private const STATION_TRANSITIONS = [
        'unknown' => ['available', 'faulted', 'unavailable', 'occupied', 'finishing', 'reserved'],
        'available' => ['reserved', 'occupied', 'faulted', 'unavailable'],
        'reserved' => ['occupied', 'available', 'faulted'],
        'occupied' => ['finishing', 'faulted'],
        'finishing' => ['available', 'faulted'],
        'faulted' => ['available', 'unavailable'],
        'unavailable' => ['available', 'faulted'],
    ];

    /**
     * The six `Server` rows — every state to `Unknown`, on connection loss.
     *
     * Inferred, never reported: no message carries these and a station MUST NOT
     * implement them. The server leaves `Unknown` on the next accepted
     * StatusNotification, not on being told about it.
     *
     * `Unknown -> Unknown` is absent: the table contains no self-transition, and
     * a server already holding a bay at `Unknown` has nothing to infer.
     *
     * @var array<string, list<string>>
     */
    private const SERVER_TRANSITIONS = [
        'available' => ['unknown'],
        'reserved' => ['unknown'],
        'occupied' => ['unknown'],
        'finishing' => ['unknown'],
        'faulted' => ['unknown'],
        'unavailable' => ['unknown'],
    ];

    /**
     * May $effectedBy move a bay from $from to $to?
     *
     * A station is held to the twenty `Station` rows. A server implements all
     * twenty-six — the station's rows included, because it must accept what a
     * station reports.
     */
    public function canTransition(BayStatus $from, BayStatus $to, EffectedBy $effectedBy): bool
    {
        return in_array($to->value, $this->targets($from, $effectedBy), true);
    }

    /**
     * @return list<BayStatus>
     */
    public function allowedTransitions(BayStatus $from, EffectedBy $effectedBy): array
    {
        return array_map(
            fn (string $s) => BayStatus::from($s),
            $this->targets($from, $effectedBy),
        );
    }

    /**
     * The table as this party sees it.
     *
     * @return array<string, list<string>>
     */
    public function getTransitionTable(EffectedBy $effectedBy): array
    {
        $table = self::STATION_TRANSITIONS;

        if ($effectedBy === EffectedBy::SERVER) {
            foreach (self::SERVER_TRANSITIONS as $from => $targets) {
                $table[$from] = [...$table[$from], ...$targets];
            }
        }

        return $table;
    }

    /**
     * Twenty for a station, twenty-six for a server.
     */
    public function transitionCount(EffectedBy $effectedBy): int
    {
        $count = 0;
        foreach ($this->getTransitionTable($effectedBy) as $targets) {
            $count += count($targets);
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    private function targets(BayStatus $from, EffectedBy $effectedBy): array
    {
        $table = $this->getTransitionTable($effectedBy);

        return $table[$from->value] ?? [];
    }
}
