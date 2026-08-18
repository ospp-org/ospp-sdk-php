<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\StateMachines;

use Ospp\Protocol\Enums\DiagnosticsState;
use Ospp\Protocol\StateMachines\DiagnosticsTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DiagnosticsTransitionsTest extends TestCase
{
    private DiagnosticsTransitions $machine;

    protected function setUp(): void
    {
        $this->machine = new DiagnosticsTransitions();
    }

    // The valid-edge list and the `transitionCount() === 6` assertion that used to
    // live here are gone. Both were positive-only — six named pairs asserted
    // permitted, nothing asserted refused — so a machine answering `true` to every
    // pair would have passed. Worse, all six were pinned against a table that had
    // no source: spec/05-state-machines.md had no diagnostics section until 0.23.0,
    // and this file's six edges disagreed with sdk-ts's seven on three of them while
    // both suites stayed green. DiagnosticsCanonicalTableContractTest now owns the
    // edge set, sweeps the full 5x5 matrix, and cites §8.3 for every pair.
    //
    // The states also changed subject. This machine is the STATION's and starts at
    // `idle`; `pending` was never a station state and belongs to a server's record
    // of a request (§8.5, DiagnosticsStatus).

    #[Test]
    public function tableCoversAllFiveStatesAndNothingElse(): void
    {
        $table = $this->machine->getTransitionTable();

        self::assertCount(5, $table);

        $expectedKeys = ['idle', 'collecting', 'uploading', 'uploaded', 'failed'];
        sort($expectedKeys);
        $actualKeys = array_keys($table);
        sort($actualKeys);
        self::assertSame($expectedKeys, $actualKeys);

        self::assertSame(['collecting'], $table['idle']);
        self::assertSame(['uploading', 'failed'], $table['collecting']);
        self::assertSame(['uploaded', 'failed'], $table['uploading']);
        self::assertSame(['idle'], $table['uploaded']);
        self::assertSame(['idle'], $table['failed']);
    }

    #[Test]
    public function noStateIsADeadEnd(): void
    {
        // The property the old table violated, asserted directly: every state has
        // somewhere to go, so no upload can leave the machine stuck.
        foreach (DiagnosticsState::cases() as $state) {
            self::assertNotSame(
                [],
                $this->machine->allowedTransitions($state),
                "{$state->value} has no outgoing edge — the machine would be single-use",
            );
        }
    }

    #[Test]
    public function theOnlyWayInIsThroughCollecting(): void
    {
        // §8.3: no `idle -> failed`, no `idle -> uploading`, no `idle -> uploaded`.
        // A station that cannot start answers the command `Rejected`; it does not
        // report a failure it never began.
        self::assertSame(
            [DiagnosticsState::COLLECTING],
            $this->machine->allowedTransitions(DiagnosticsState::IDLE),
        );
    }
}
