<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

/**
 * The six states of the station state machine — spec/05-state-machines.md §1.
 *
 * This is the OUTERMOST machine: every other machine on a station is scoped
 * inside it. A bay transition is only reportable, and a session only startable,
 * while the station is `Operational`. A station MUST be in exactly one of these
 * six at all times.
 *
 * Two other machines in the chapter also have a state named `Pending` — the
 * session machine and the reservation machine. They are unrelated. This
 * `Pending` is the only one a BootNotification RESPONSE can carry.
 *
 * Which party holds which: the station holds all six about itself. The server
 * holds five — it never observes `Booting`, because the REQUEST that opens it
 * and the RESPONSE that closes it are one exchange the server completes
 * synchronously.
 */
enum StationState: string
{
    /**
     * The station holds no operator-issued client certificate and cannot open
     * an mTLS connection to the broker.
     *
     * OSPP does not begin here, and a station MUST NOT enter this state
     * autonomously — there is no remote credential wipe (reset.md §5.1).
     */
    case NOT_PROVISIONED = 'NotProvisioned';

    /**
     * Connected, subscribed, BootNotification published, waiting for the
     * RESPONSE.
     *
     * Restricted more tightly than `Pending`: it holds no session key yet, so
     * it can neither sign nor verify, and therefore cannot process a command
     * even if one arrives.
     */
    case BOOTING = 'Booting';

    /**
     * The server accepted the connection but has not cleared the station for
     * service — an operator approval is outstanding, or a `3018
     * TOPOLOGY_MISMATCH` needs repair.
     *
     * A RESTRICTED state: the station answers commands and sends nothing
     * unsolicited. It DOES hold a session key — the response that put it here
     * carries one — because every command it answers is signed.
     */
    case PENDING = 'Pending';

    /**
     * The server refused the boot and said why.
     *
     * A RESTRICTED state, and stricter than `Pending`: the station also refuses
     * commands, because the server that would send them does not consider it
     * registered — and it holds no session key, so it could not verify one.
     */
    case REJECTED = 'Rejected';

    /**
     * The boot was `Accepted`. The station has a session key, has reported every
     * bay, is heartbeating, accepts and executes commands, and serves
     * customers. The only state in which a session may start.
     */
    case OPERATIONAL = 'Operational';

    /**
     * No MQTT connection. Hardware keeps running: active sessions continue on
     * the station's local timer, BLE stays available, and events are buffered.
     *
     * The station is not idle here — it is operating without a server. The
     * server infers this state from the LWT or a heartbeat timeout and holds
     * every bay at `Unknown`; it is the station-scope twin of a bay's `Unknown`.
     */
    case DISCONNECTED = 'Disconnected';

    /**
     * §1.4: "`Pending` and `Rejected` are both restricted, and they differ in
     * exactly one respect: whether the station answers commands."
     */
    public function isRestricted(): bool
    {
        return $this === self::PENDING || $this === self::REJECTED;
    }

    /**
     * §1.4 row: *Receives and processes server commands.*
     *
     * `Pending` MUST; `Rejected` MUST NOT. `Pending` exists so a human can
     * repair something — approve a registration, correct a topology record —
     * and the repair may need the command channel. `Rejected` carries no such
     * expectation: the server has said the station is not registered, and it
     * has nothing to configure.
     */
    public function mayReceiveCommands(): bool
    {
        return $this === self::PENDING || $this === self::OPERATIONAL;
    }

    /**
     * §1.4 row: *Answers a server command with a RESPONSE.*
     *
     * "The distinction is carried by the envelope, not by the action." A
     * restricted station is forbidden `Event` and forbidden any `Request` other
     * than BootNotification; `Response` is permitted in `Pending`, because a
     * RESPONSE is not something the station initiates.
     */
    public function mayAnswerCommands(): bool
    {
        return $this->mayReceiveCommands();
    }

    /**
     * §1.4 row: *Sends anything else unsolicited (EVENT, or a REQUEST it
     * originates).*
     *
     * Only `Operational` MAY. The BootNotification retry is not "anything else"
     * — it is the one message a restricted station MUST send.
     */
    public function maySendUnsolicited(): bool
    {
        return $this === self::OPERATIONAL;
    }

    /**
     * §1.4 row: *Starts new customer service.*
     *
     * While restricted the station MUST reject StartService and ReserveBay with
     * `3002 BAY_NOT_READY`, on every transport, and MUST NOT authorize a BLE
     * offline session.
     *
     * Serving no customers is not the same as stopping: a session already
     * running MUST continue, be metered, and be settled. A customer who has paid
     * is served.
     */
    public function mayStartNewService(): bool
    {
        return $this === self::OPERATIONAL;
    }

    /**
     * §1.4 row: *Holds a session key.*
     *
     * "The key row is what makes the rest of the table possible, and it is easy
     * to get wrong." Every command is signed and both directions fail closed, so
     * a `Pending` station that held no key could not be sent a command, could
     * not accept one, and could not answer — the repair channel the `Pending`
     * window exists for would exist only on paper. `Rejected` needs none: it
     * answers nothing.
     */
    public function holdsSessionKey(): bool
    {
        return $this === self::PENDING || $this === self::OPERATIONAL;
    }
}
