<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

use Ospp\Protocol\Actions\OsppAction;

/**
 * The STATION's firmware update state — spec/05-state-machines.md §6.2.
 *
 * Ten states, thirteen edges ({@see \Ospp\Protocol\StateMachines\FirmwareTransitions}).
 * `Activated` is the only terminal one.
 *
 * The gap between this machine and the wire is the widest in the chapter, and §6.6
 * exists to state it rather than leave it to be inferred from absent rows: a
 * FirmwareStatusNotification carries **five** of the ten states. The other five split
 * two ways, and the split is the whole reason this bridge is not a copy of the
 * diagnostics one:
 *
 *  * **Four are unobservable.** `Idle`, `Verifying`, `Verified` and `Rebooting` have
 *    no notification value at all. §6.6: "a server that models the station's ten
 *    states will hold four of them that nothing on the wire can ever set." A server
 *    that waits to be told about one waits for a message the protocol never sends.
 *  * **`Activated` is observable, by a different message.** It has no
 *    FirmwareStatusNotification value either, but §6.6 maps it to BootNotification
 *    [MSG-001] carrying the new `firmwareVersion` and `reason: "FirmwareUpdate"`.
 *
 * So `! isReportable()` has FIVE members and `! isObservable()` has FOUR, and the
 * difference between them is `Activated` — the state a server learns about, but not
 * from this notification. The diagnostics bridge had one non-reportable state and no
 * such difference to carry ({@see DiagnosticsState}, §8.4), which is why it could
 * answer both questions with a single predicate and this cannot.
 */
enum FirmwareUpdateStatus: string
{
    case IDLE = 'idle';
    case DOWNLOADING = 'downloading';
    case DOWNLOADED = 'downloaded';
    case VERIFYING = 'verifying';
    case VERIFIED = 'verified';
    case INSTALLING = 'installing';
    case INSTALLED = 'installed';
    case REBOOTING = 'rebooting';
    case ACTIVATED = 'activated';
    case FAILED = 'failed';

    /**
     * `Activated` is the only terminal state.
     *
     * spec/05-state-machines.md §6.3: "`Failed` has exactly one outgoing edge,
     * `Failed -> Idle`; it is **not** terminal, and a machine that treats it as
     * terminal can run one firmware update and never a second."
     */
    public function isTerminal(): bool
    {
        return $this === self::ACTIVATED;
    }

    /**
     * An update in flight. Enumerated rather than derived from isTerminal():
     * `Failed` is not terminal but it is not in flight either — it is rolling
     * back — so the two predicates do not partition the same way.
     */
    public function isActive(): bool
    {
        return $this !== self::IDLE
            && $this !== self::ACTIVATED
            && $this !== self::FAILED;
    }

    /**
     * Whether a FirmwareStatusNotification can carry this state — §6.6.
     *
     * True for the five values of `schemas/mqtt/firmware-status-notification.schema.json`.
     * False for the other five, INCLUDING `Activated`: it is reported, but not by
     * this message. Gate on {@see self::isObservable()} to ask the other question.
     */
    public function isReportable(): bool
    {
        return $this->toNotificationStatus() !== null;
    }

    /**
     * Whether the server is ever told this state, by any message — §6.6.
     *
     * False for exactly the four §6.6 calls unobservable. This is the predicate the
     * diagnostics bridge never needed: there, "not reportable" and "never observed"
     * were the same one state. Here they differ by `Activated`, and a server that
     * conflated them would either wait forever for a `Verifying` that has no
     * message, or discard the BootNotification that is the only report of a
     * completed update.
     *
     * The four are not a gap to be closed. `Rebooting` is unobservable because the
     * station is offline for the whole of it; `Verifying` and `Verified` because
     * §6.6's silent interval is where the SHA-256 and the ECDSA P-256 verification
     * run; `Idle` because the absence of an update is not an event.
     */
    public function isObservable(): bool
    {
        return $this->observedBy() !== null;
    }

    /**
     * The message that reports this state, or null if nothing does — §6.6.
     *
     * Returns an {@see OsppAction} constant rather than a literal so a rename of the
     * action cannot leave this mapping pointing at a message that no longer exists.
     */
    public function observedBy(): ?string
    {
        return match ($this) {
            self::DOWNLOADING,
            self::DOWNLOADED,
            self::INSTALLING,
            self::INSTALLED,
            self::FAILED => OsppAction::FIRMWARE_STATUS_NOTIFICATION,

            // §6.6: "Reported via BootNotification [MSG-001], not
            // FirmwareStatusNotification." The station that comes out of this state
            // is running different software, and the message that says so is the one
            // it sends on the way back up.
            self::ACTIVATED => OsppAction::BOOT_NOTIFICATION,

            // The four. §6.6: "Four states have no notification value at all and
            // are, from the server's side, unobservable."
            self::IDLE,
            self::VERIFYING,
            self::VERIFIED,
            self::REBOOTING => null,
        };
    }

    /**
     * The wire -> machine bridge. §6.6's mapping, read in the direction a server
     * consumes it.
     *
     * The five values are exactly the enum of
     * `schemas/mqtt/firmware-status-notification.schema.json`. There is deliberately
     * no value that yields any of the four unobservable states, and none that yields
     * `Activated` either — that one arrives on BootNotification, and a bridge that
     * produced it from a FirmwareStatusNotification would be inventing a message.
     *
     * @throws \InvalidArgumentException on any value outside the notification enum
     */
    public static function fromNotificationStatus(string $notificationStatus): self
    {
        return match ($notificationStatus) {
            'Downloading' => self::DOWNLOADING,
            'Downloaded' => self::DOWNLOADED,
            'Installing' => self::INSTALLING,
            'Installed' => self::INSTALLED,
            'Failed' => self::FAILED,
            default => throw new \InvalidArgumentException("Unknown firmware notification status: {$notificationStatus}"),
        };
    }

    /**
     * The machine -> wire direction. Null for the five states no
     * FirmwareStatusNotification carries (§6.6).
     *
     * Null for `Activated` too. That is not a hole: {@see self::observedBy()} names
     * the message that does carry it. Gate on this before putting a held state into
     * a payload; a null here means the state must not be transmitted as a firmware
     * status, whatever else may be true of it.
     */
    public function toNotificationStatus(): ?string
    {
        return match ($this) {
            self::DOWNLOADING => 'Downloading',
            self::DOWNLOADED => 'Downloaded',
            self::INSTALLING => 'Installing',
            self::INSTALLED => 'Installed',
            self::FAILED => 'Failed',
            self::IDLE, self::VERIFYING, self::VERIFIED, self::REBOOTING, self::ACTIVATED => null,
        };
    }
}
