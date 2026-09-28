<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum SessionEndReason: string
{
    case TIMER_EXPIRED = 'TimerExpired';
    case FAULT = 'Fault';
    case LOCAL = 'Local';
    case LOCAL_OUT_OF_CREDIT = 'LocalOutOfCredit';
    case DEAUTHORIZED = 'Deauthorized';
    /**
     * An operator ended the session deliberately — a Reset carrying `force: true`,
     * or a station disable.
     *
     * The station's side does not depend on the service kind. Under the
     * operator-disable policy (04-flows.md) it stops the service, meters it from
     * the time ACTUALLY DELIVERED, reports the `actualDurationSeconds` delivered and
     * the `creditsCharged` those seconds earned, and only then acts.
     *
     * What the customer pays is the SERVER's decision, by service kind. spec 0.44.0
     * 03-messages.md §5.4: "pro-rata on delivered time for `UserDuration`, a full
     * refund for `FixedDuration` and `MultiUnit`, because the operator, not the
     * customer, cut the preset short" (04-flows.md §6, *Settlement by Service
     * Kind*). A stop the server itself issues for an operator settles the same way:
     * StopService carries no reason and produces no SessionEnded, so only the
     * server knows an operator asked for it, and it must not settle that stop as
     * the customer's own.
     *
     * Until 0.44.0 the two preset kinds took a full charge on this reason, and this
     * docblock called it the ONLY member that bills a non-zero amount for a session
     * the station did not run to completion. spec 0.44.0 drops that sentence along
     * with the full charge; the amount is not the reason's to state.
     *
     * spec v0.11.1 added the member because there was nothing to report a forced
     * stop with. `Deauthorized` reads as the nearest alternative while carrying
     * "Session MUST be billed at zero" (03-messages.md §5.4), so reusing it would
     * erase the delivered time a `UserDuration` session is billed on.
     */
    case OPERATOR_STOPPED = 'OperatorStopped';

    /**
     * The `SessionTimeout` idle timer elapsed — no user interaction within the
     * window, so the station stopped the service on its own.
     *
     * spec 0.31.0 08-configuration.md `SessionTimeout`: **MeterValues do NOT
     * reset the timer.** They are the station's own telemetry, emitted on a
     * timer whether or not a customer is present, so counting them would make
     * the timer measure the station rather than the user. The registry's
     * *no user interaction* is the trigger; 05-state-machines.md §3.4's
     * *no MeterValues or user interaction* was an unswept restatement and the
     * registry always governed.
     *
     * Off unless an operator turns it on. Since spec 0.44.0 `SessionTimeout`
     * defaults to `0`, and at `0` the station must not stop a session on
     * inactivity (08-configuration.md §3), so a station left on its defaults never
     * reports this value. It defaulted to 120 before 0.44.0.
     *
     * Billed **pro-rata on delivered duration** for a `UserDuration` session — the
     * customer received service and then stopped engaging with it, the same shape
     * as `Local`, and settled the same way (04-flows.md §6), which for a
     * `FixedDuration` or `MultiUnit` session is a full charge. It is therefore NOT
     * one of the zero-billing reasons, whatever the kind.
     *
     * The seventh member. The enum was closed at six and none of them was true
     * of an idle stop, so the one EVENT required to report it (session-ended.md
     * §6) had no value to report it with, and a station had to choose between an
     * inaccurate `reason` and a silent termination. 0.30.0's note declined the
     * widening; 0.31.0 makes it, on the argument that an obligation with no legal
     * value to satisfy it is not an unimplemented rule but an unimplementable one.
     */
    case INACTIVITY = 'Inactivity';
}
