<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

/**
 * Which party effects a state transition.
 *
 * This is the `Effected by` column of the canonical bay transition table
 * (spec/05-state-machines.md §2.3), and it exists because the table merges two
 * different objects:
 *
 * > "A bay a station operates and a bay a server believes in are different
 * > objects. The station effects and reports the physical transitions; the
 * > server infers exactly one, the move to `Unknown` when it can no longer hear
 * > the station. Merging them without saying so is what produced the divergence:
 * > a station implementer read the `→ Unknown` rows as theirs to implement, and
 * > a server implementer read the station's rows as the whole model."
 *
 * The divergence was real and it was between these two SDKs: ospp-sdk-php
 * carried the profile's station rows and sdk-ts carried the chapter's merged
 * set, so each rejected traffic the other produced. Naming the party is the
 * root-cause fix, not a convenience.
 *
 * A station implements the `Station` rows and MUST NOT implement the `Server`
 * ones. A server implements all of them.
 */
enum EffectedBy: string
{
    /** The station, from its own hardware. Reportable in a StatusNotification. */
    case STATION = 'Station';

    /**
     * The server, by inference. No message carries such a transition — the
     * server reaches it on the LWT or a heartbeat timeout and leaves it on the
     * next accepted report, never on being told about it.
     */
    case SERVER = 'Server';
}
