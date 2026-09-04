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
     * spec v0.11.1 03-messages.md §5.4: the ONLY member that bills a non-zero
     * amount for a session the station did not run to completion. Every other
     * non-completion reason here mandates zero, and `Deauthorized` reads as the
     * nearest alternative while carrying "Session MUST be billed at zero" — so
     * reusing it delivers a wash and charges nothing for it.
     *
     * Settled under the operator-disable policy (04-flows.md): metered from the
     * time ACTUALLY DELIVERED, reported, and only then does the station act.
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
     * Billed **pro-rata on delivered duration** — the customer received service
     * and then stopped engaging with it, the same shape as `Local`, and settled
     * the same way (04-flows.md §6). It is therefore NOT one of the zero-billing
     * reasons.
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
