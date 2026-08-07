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
}
