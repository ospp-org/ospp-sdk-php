<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\StateMachines;

use Ospp\Protocol\Actions\OsppAction;
use Ospp\Protocol\Enums\FirmwareUpdateStatus;
use Ospp\Protocol\StateMachines\FirmwareTransitions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The canonical firmware update transition table — spec/05-state-machines.md §6.3.
 *
 * The table has fourteen ROWS and thirteen EDGES. §6.3: "`Verifying -> Failed`
 * appears twice — once for a checksum mismatch and once for an invalid signature
 * — because the two have different actions and different error codes, not
 * because they are two transitions. An implementation of this machine has **13**
 * `(from, to)` pairs, and a conformance check that asserts a transition *count*
 * must assert 13; one that counts the rows of this table gets 14 and then has to
 * invent an edge to reach it."
 *
 * That is the defect this file exists to prevent, and it is why nothing here
 * asserts a cardinal. A count is the one assertion that cannot say WHICH edge
 * moved: `14` and `13` are the same diff whether the machine gained a phantom
 * or lost a real one. The list below fixes the SET, so removing an edge shows
 * up in review as a named line, and the exhaustive sweep in
 * {@see testEveryPairOutsideTheTableIsRefused} closes the other direction.
 *
 * The pair list is transcribed from §6.3 and is the SAME vector list the sdk-ts
 * mirror of this file asserts. Both SDKs are reference implementations and a
 * disagreement between them becomes a defect in every consumer — it has once
 * already, which is why this table now has one home.
 */
final class FirmwareCanonicalTableContractTest extends TestCase
{
    private FirmwareTransitions $transitions;

    protected function setUp(): void
    {
        $this->transitions = new FirmwareTransitions();
    }

    /**
     * The thirteen distinct `(from, to)` pairs of §6.3, in table order.
     *
     * @return list<array{FirmwareUpdateStatus, FirmwareUpdateStatus}>
     */
    public static function edges(): array
    {
        return [
            // UpdateFirmware [MSG-016] accepted
            [FirmwareUpdateStatus::IDLE, FirmwareUpdateStatus::DOWNLOADING],

            // Download complete / Download error
            [FirmwareUpdateStatus::DOWNLOADING, FirmwareUpdateStatus::DOWNLOADED],
            [FirmwareUpdateStatus::DOWNLOADING, FirmwareUpdateStatus::FAILED],

            // Integrity and authenticity checks start. `Downloaded` has ONE exit:
            // §6.3 lists no `Downloaded -> Failed` row. A staging area that is not
            // intact fails in `Verifying`, which is where the checks run.
            [FirmwareUpdateStatus::DOWNLOADED, FirmwareUpdateStatus::VERIFYING],

            // Checksum matches AND signature verifies.
            [FirmwareUpdateStatus::VERIFYING, FirmwareUpdateStatus::VERIFIED],
            // The doubled row: "Checksum mismatch" and "Signature invalid (5112)"
            // are two rows and ONE edge.
            [FirmwareUpdateStatus::VERIFYING, FirmwareUpdateStatus::FAILED],

            // Write to inactive partition begins.
            [FirmwareUpdateStatus::VERIFIED, FirmwareUpdateStatus::INSTALLING],

            // Write complete / Write error
            [FirmwareUpdateStatus::INSTALLING, FirmwareUpdateStatus::INSTALLED],
            [FirmwareUpdateStatus::INSTALLING, FirmwareUpdateStatus::FAILED],

            // Station reboots. `Installed` has ONE exit: §6.3 lists no
            // `Installed -> Failed` row. The write already succeeded and the boot
            // target is already set; the next thing that can go wrong is the boot,
            // and that is `Rebooting -> Failed` under the watchdog.
            [FirmwareUpdateStatus::INSTALLED, FirmwareUpdateStatus::REBOOTING],

            // Boot on new partition / Boot failure, watchdog
            [FirmwareUpdateStatus::REBOOTING, FirmwareUpdateStatus::ACTIVATED],
            [FirmwareUpdateStatus::REBOOTING, FirmwareUpdateStatus::FAILED],

            // Rollback complete. §6.3: "`Failed` has exactly one outgoing edge,
            // `Failed -> Idle`; it is **not** terminal, and a machine that treats
            // it as terminal can run one firmware update and never a second."
            [FirmwareUpdateStatus::FAILED, FirmwareUpdateStatus::IDLE],
        ];
    }

    #[DataProvider('edges')]
    public function testEveryEdgeInTheTableIsPermitted(
        FirmwareUpdateStatus $from,
        FirmwareUpdateStatus $to,
    ): void {
        self::assertTrue(
            $this->transitions->canTransition($from, $to),
            "§6.3 lists {$from->value} -> {$to->value}",
        );
    }

    /**
     * Everything outside the list is invalid.
     *
     * §1 (chapter preamble): "A transition not listed for a machine is invalid,
     * and implementations MUST NOT perform one."
     *
     * This is the half a positive-only vector list cannot do. A machine that
     * answered `true` to everything would satisfy the provider above and fail
     * here on the first non-edge.
     */
    public function testEveryPairOutsideTheTableIsRefused(): void
    {
        $table = [];
        foreach (self::edges() as [$from, $to]) {
            $table[$from->value.'>'.$to->value] = true;
        }

        foreach (FirmwareUpdateStatus::cases() as $from) {
            foreach (FirmwareUpdateStatus::cases() as $to) {
                $key = $from->value.'>'.$to->value;

                self::assertSame(
                    isset($table[$key]),
                    $this->transitions->canTransition($from, $to),
                    $key,
                );
            }
        }
    }

    /**
     * The public accessors must describe the same machine as `canTransition()`.
     *
     * Two readers of one constant is a weak check today, but it is the seam a
     * future refactor would break silently, and it costs nothing to pin.
     */
    public function testTransitionTableAgreesWithCanTransition(): void
    {
        $fromTable = [];
        foreach ($this->transitions->getTransitionTable() as $from => $targets) {
            foreach ($targets as $to) {
                $fromTable[] = $from.'>'.$to;
            }
        }

        $fromPredicate = [];
        foreach (FirmwareUpdateStatus::cases() as $from) {
            foreach ($this->transitions->allowedTransitions($from) as $to) {
                $fromPredicate[] = $from->value.'>'.$to->value;
            }
        }

        $expected = [];
        foreach (self::edges() as [$from, $to]) {
            $expected[] = $from->value.'>'.$to->value;
        }

        sort($fromTable);
        sort($fromPredicate);
        sort($expected);

        self::assertSame($expected, $fromTable, 'getTransitionTable()');
        self::assertSame($expected, $fromPredicate, 'allowedTransitions()');

        // Pins the third accessor without naming a cardinal: the expected value
        // is the pinned set's own size, so no literal can be "corrected" to
        // match a machine that grew an edge.
        self::assertSame(count($expected), $this->transitions->transitionCount());
    }

    /**
     * `Activated` is terminal and `Failed` is not.
     *
     * This is the consequence the missing edge had, stated as behaviour rather
     * than as a property: without `Failed -> Idle` the machine is single-use.
     */
    public function testTheMachineSurvivesAFailedUpdateAndCanRunAnother(): void
    {
        self::assertSame(
            [FirmwareUpdateStatus::IDLE],
            $this->transitions->allowedTransitions(FirmwareUpdateStatus::FAILED),
            '§6.3: Failed has exactly one outgoing edge',
        );

        self::assertSame(
            [],
            $this->transitions->allowedTransitions(FirmwareUpdateStatus::ACTIVATED),
            '§6.3: Activated is terminal',
        );

        // A second update after a rollback: the walk that the old table could
        // not complete, because `failed` had no exits at all.
        $walk = [
            [FirmwareUpdateStatus::IDLE, FirmwareUpdateStatus::DOWNLOADING],
            [FirmwareUpdateStatus::DOWNLOADING, FirmwareUpdateStatus::FAILED],
            [FirmwareUpdateStatus::FAILED, FirmwareUpdateStatus::IDLE],
            [FirmwareUpdateStatus::IDLE, FirmwareUpdateStatus::DOWNLOADING],
        ];

        foreach ($walk as $step => [$from, $to]) {
            self::assertTrue(
                $this->transitions->canTransition($from, $to),
                "step {$step}: {$from->value} -> {$to->value}",
            );
        }
    }

    /**
     * `isTerminal()` and the transition table must not disagree about `Failed`.
     *
     * They did: the enum called `Failed` terminal while §6.3 gives it an exit.
     * The two encode the same fact in different files, so the check belongs here
     * rather than in either file's own unit test.
     */
    public function testIsTerminalAgreesWithTheAbsenceOfOutgoingEdges(): void
    {
        foreach (FirmwareUpdateStatus::cases() as $state) {
            self::assertSame(
                $this->transitions->allowedTransitions($state) === [],
                $state->isTerminal(),
                "{$state->value}: isTerminal() must mean 'has no outgoing edge'",
            );
        }
    }

    public function testNoSelfTransition(): void
    {
        foreach (FirmwareUpdateStatus::cases() as $state) {
            self::assertFalse($this->transitions->canTransition($state, $state));
        }
    }

    // ---------------------------------------------------------------------
    // The wire <-> machine bridge — spec/05-state-machines.md §6.6.
    //
    // §6.6 exists because this machine's gap to the wire is the widest in the
    // chapter: a FirmwareStatusNotification carries FIVE of the ten states, and
    // the other five are not one kind of thing. Four are unobservable — "a server
    // that models the station's ten states will hold four of them that nothing on
    // the wire can ever set" — and the fifth, `Activated`, is reported by
    // BootNotification [MSG-001] instead. The diagnostics bridge (§8.4) had one
    // non-reportable state and no such split, so a single predicate answered both
    // questions there and cannot here.
    //
    // The consequence that costs a consumer money is the SKIP. Three of the
    // thirteen edges run through unobservable states, so the conforming
    // notification sequence is not a walk of §6.3: the station goes
    // `Downloaded -> Verifying -> Verified -> Installing` and the server is told
    // `Downloaded` then `Installing`. Feed those two into canTransition() and the
    // update is refused at the moment it starts installing.
    //
    // Nothing below asserts a cardinal, for the reason §6.3's closing paragraph
    // gives about its own 14 rows and 13 edges.
    // ---------------------------------------------------------------------

    /** The five states with a FirmwareStatusNotification value (§6.6). */
    private const REPORTABLE = ['downloading', 'downloaded', 'installing', 'installed', 'failed'];

    /** §6.6: "Four states have no notification value at all and are ... unobservable." */
    private const UNOBSERVABLE = ['idle', 'verifying', 'verified', 'rebooting'];

    /**
     * Every `(held state, arriving status)` pair a server can legitimately see.
     *
     * Transcribed from §6.3's edges read through §6.6's mapping — the reportable
     * states reachable by a path whose intermediate states are all unobservable.
     * It is a SET, not a count: that it happens to have as many members as the
     * machine has edges is a coincidence of this table and nothing may lean on it.
     *
     * @return list<array{FirmwareUpdateStatus, string}>
     */
    public static function observablePairs(): array
    {
        return [
            // The entry. A server holding `Idle` is told `Downloading` (§6.3 row 1).
            [FirmwareUpdateStatus::IDLE, 'Downloading'],

            [FirmwareUpdateStatus::DOWNLOADING, 'Downloaded'],
            [FirmwareUpdateStatus::DOWNLOADING, 'Failed'],

            // The SKIP: `Downloaded -> Verifying -> Verified -> Installing`, two
            // silent states, one observed step. And `Downloaded -> Verifying ->
            // Failed` for a checksum mismatch or an invalid signature (5112).
            [FirmwareUpdateStatus::DOWNLOADED, 'Installing'],
            [FirmwareUpdateStatus::DOWNLOADED, 'Failed'],

            // A consumer that did model the silent states still advances correctly.
            [FirmwareUpdateStatus::VERIFYING, 'Installing'],
            [FirmwareUpdateStatus::VERIFYING, 'Failed'],
            [FirmwareUpdateStatus::VERIFIED, 'Installing'],

            [FirmwareUpdateStatus::INSTALLING, 'Installed'],
            [FirmwareUpdateStatus::INSTALLING, 'Failed'],

            // `Installed -> Rebooting -> Failed` under the watchdog. The SUCCESS
            // branch of `Rebooting` is `Activated`, which this message never
            // carries, so `Installed` has exactly one observable successor on this
            // wire and silence is the other outcome.
            [FirmwareUpdateStatus::INSTALLED, 'Failed'],
            [FirmwareUpdateStatus::REBOOTING, 'Failed'],

            // `Failed -> Idle -> Downloading`. §8.4 names this class of edge for
            // diagnostics — "no wire trigger, and a server must not wait for one" —
            // and it is the same here: nothing announces the rollback is done, the
            // next `Downloading` does.
            [FirmwareUpdateStatus::FAILED, 'Downloading'],
        ];
    }

    /**
     * The bridge admits exactly the vendored schema enum, and nothing else.
     *
     * The wire list is read from the schema rather than restated here, so a spec
     * change to the notification enum fails in this file instead of being absorbed
     * by a literal in it.
     */
    public function testTheBridgeAdmitsExactlyTheSchemaEnum(): void
    {
        $schemaPath = \dirname(__DIR__, 3).'/schemas/mqtt/firmware-status-notification.schema.json';
        $raw = file_get_contents($schemaPath);
        self::assertIsString($raw);
        /** @var array{properties: array{status: array{enum: list<string>}}} $schema */
        $schema = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $enum = $schema['properties']['status']['enum'];

        self::assertSame(['Downloading', 'Downloaded', 'Installing', 'Installed', 'Failed'], $enum);

        $produced = [];
        foreach ($enum as $status) {
            $state = FirmwareUpdateStatus::fromNotificationStatus($status);
            $produced[] = $state->value;
            self::assertSame($status, $state->toNotificationStatus(), 'the bridge must round-trip');
            self::assertTrue($state->isReportable(), $state->value);
        }
        self::assertSame(self::REPORTABLE, $produced);

        // The four unobservable states are not wire values, and neither is
        // `Activated` — it is reported, but not by this message.
        foreach ([...self::UNOBSERVABLE, 'activated'] as $value) {
            $state = FirmwareUpdateStatus::from($value);
            self::assertNull($state->toNotificationStatus(), $value);
            self::assertFalse($state->isReportable(), $value);

            try {
                FirmwareUpdateStatus::fromNotificationStatus(ucfirst($value));
                self::fail("fromNotificationStatus() must refuse '{$value}'");
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Unknown firmware notification status', $e->getMessage());
            }
        }
    }

    /**
     * The distinction the diagnostics bridge never had to draw.
     *
     * `! isReportable()` has FIVE members and `! isObservable()` has FOUR. A server
     * that used one for the other either waits forever for a `Verifying` that has
     * no message, or discards the BootNotification that is the only report a
     * completed update ever gets.
     */
    public function testNoNotificationValueAndNeverObservedAreDifferentSets(): void
    {
        $notReportable = [];
        $notObservable = [];
        foreach (FirmwareUpdateStatus::cases() as $state) {
            if (! $state->isReportable()) {
                $notReportable[] = $state->value;
            }
            if (! $state->isObservable()) {
                $notObservable[] = $state->value;
            }
        }

        self::assertSame(self::UNOBSERVABLE, $notObservable, '§6.6 names four');
        self::assertSame(
            ['activated'],
            array_values(array_diff($notReportable, $notObservable)),
            'the two sets differ, and they differ by exactly `activated`',
        );

        foreach (self::REPORTABLE as $value) {
            self::assertSame(
                OsppAction::FIRMWARE_STATUS_NOTIFICATION,
                FirmwareUpdateStatus::from($value)->observedBy(),
                $value,
            );
        }
        self::assertSame(
            OsppAction::BOOT_NOTIFICATION,
            FirmwareUpdateStatus::ACTIVATED->observedBy(),
            '§6.6: reported via BootNotification, not FirmwareStatusNotification',
        );
        foreach (self::UNOBSERVABLE as $value) {
            self::assertNull(FirmwareUpdateStatus::from($value)->observedBy(), $value);
        }
    }

    /**
     * The pair SET, swept exhaustively — 10 held states x 5 arriving statuses.
     *
     * Every one of the 50 combinations is asserted one way or the other, so a pair
     * that is neither permitted nor refused cannot exist. A repeat of the held
     * state is a progress report and is accepted without being a transition, which
     * is why it is excluded from the pair list rather than added to it.
     */
    public function testPermitsExactlyTheObservablePairsAndRefusesEveryOther(): void
    {
        $permitted = [];
        foreach (self::observablePairs() as [$from, $status]) {
            $permitted[$from->value.'>'.$status] = true;
        }

        $wire = ['Downloading', 'Downloaded', 'Installing', 'Installed', 'Failed'];
        $accepted = 0;
        $refused = 0;

        foreach (FirmwareUpdateStatus::cases() as $from) {
            foreach ($wire as $status) {
                $key = $from->value.'>'.$status;
                $isRepeat = $from->toNotificationStatus() === $status;
                $shouldPass = $isRepeat || isset($permitted[$key]);

                try {
                    $result = $this->transitions->applyNotification($from, $status);
                    self::assertTrue($shouldPass, "{$key} was accepted and must not be");
                    self::assertSame(
                        FirmwareUpdateStatus::fromNotificationStatus($status),
                        $result,
                        $key,
                    );
                    $accepted++;
                } catch (\InvalidArgumentException $e) {
                    self::assertFalse($shouldPass, "{$key} was refused and must not be");
                    self::assertStringContainsString('Invalid firmware transition', $e->getMessage());
                    $refused++;
                }
            }
        }

        // The denominator. Without it a sweep that iterated nothing would pass.
        self::assertSame(
            count(FirmwareUpdateStatus::cases()) * count($wire),
            $accepted + $refused,
        );
        self::assertGreaterThan(0, $accepted);
        self::assertGreaterThan(0, $refused);
    }

    public function testObservableTargetsExposesTheSamePairs(): void
    {
        $fromMethod = [];
        foreach (FirmwareUpdateStatus::cases() as $from) {
            foreach ($this->transitions->observableTargets($from) as $to) {
                $fromMethod[] = $from->value.'>'.$to->toNotificationStatus();
            }
        }

        $expected = [];
        foreach (self::observablePairs() as [$from, $status]) {
            $expected[] = $from->value.'>'.$status;
        }

        sort($fromMethod);
        sort($expected);
        self::assertSame($expected, $fromMethod);

        // `Activated` is terminal, so nothing follows it on any wire.
        self::assertSame([], $this->transitions->observableTargets(FirmwareUpdateStatus::ACTIVATED));
    }

    /**
     * The defect the bridge exists to prevent, driven end to end.
     *
     * This is the conforming success stream of `firmware-status.md` §5.1. A
     * consumer feeding it into canTransition() fails at the third step, because
     * §6.3 has no `Downloaded -> Installing` row — and MUST NOT gain one.
     */
    public function testAcceptsTheConformingStreamAcrossTheSilentVerificationInterval(): void
    {
        $stream = ['Downloading', 'Downloaded', 'Installing', 'Installed'];

        $state = FirmwareUpdateStatus::IDLE;
        foreach ($stream as $status) {
            $state = $this->transitions->applyNotification($state, $status);
        }
        self::assertSame(FirmwareUpdateStatus::INSTALLED, $state);

        // And the raw table still refuses the step the wire skips, which is the
        // correct answer to a different question.
        self::assertFalse($this->transitions->canTransition(
            FirmwareUpdateStatus::DOWNLOADED,
            FirmwareUpdateStatus::INSTALLING,
        ));
        self::assertTrue($this->transitions->canTransition(
            FirmwareUpdateStatus::DOWNLOADED,
            FirmwareUpdateStatus::VERIFYING,
        ));
        self::assertTrue($this->transitions->canTransition(
            FirmwareUpdateStatus::VERIFIED,
            FirmwareUpdateStatus::INSTALLING,
        ));
    }

    /**
     * The repeat streams. `firmware-status.md` §5 rule 1 asks for a notification at
     * every 10% of `Downloading` and rule 2 for four milestones of `Installing`,
     * and §6.3 has a self-edge for neither.
     */
    public function testAcceptsTheRepeatedProgressStreamsAsOneStateEach(): void
    {
        $stream = [
            'Downloading', 'Downloading', 'Downloading', 'Downloading',
            'Downloaded',
            'Installing', 'Installing', 'Installing', 'Installing',
            'Installed',
        ];

        $state = FirmwareUpdateStatus::IDLE;
        foreach ($stream as $status) {
            $state = $this->transitions->applyNotification($state, $status);
        }
        self::assertSame(FirmwareUpdateStatus::INSTALLED, $state);

        self::assertFalse($this->transitions->canTransition(
            FirmwareUpdateStatus::DOWNLOADING,
            FirmwareUpdateStatus::DOWNLOADING,
        ));
        self::assertFalse($this->transitions->canTransition(
            FirmwareUpdateStatus::INSTALLING,
            FirmwareUpdateStatus::INSTALLING,
        ));
    }

    /**
     * The end of a successful update is SILENCE on this message, and the news
     * arrives on another one.
     *
     * §6.6: `Installed -> Rebooting -> Activated` crosses one unobservable state
     * and ends in a state this notification does not carry. The only
     * FirmwareStatusNotification that can follow `Installed` is the watchdog's
     * `Failed`.
     */
    public function testASuccessfulActivationHasNoNotification(): void
    {
        self::assertSame(
            [FirmwareUpdateStatus::FAILED],
            $this->transitions->observableTargets(FirmwareUpdateStatus::INSTALLED),
        );
        self::assertNull(FirmwareUpdateStatus::ACTIVATED->toNotificationStatus());
        self::assertSame(
            OsppAction::BOOT_NOTIFICATION,
            FirmwareUpdateStatus::ACTIVATED->observedBy(),
        );

        // A server holding `Installed` that waits for a firmware status to tell it
        // the update completed waits forever. §6.3 still has the edges; they are
        // silent.
        self::assertTrue($this->transitions->canTransition(
            FirmwareUpdateStatus::INSTALLED,
            FirmwareUpdateStatus::REBOOTING,
        ));
        self::assertTrue($this->transitions->canTransition(
            FirmwareUpdateStatus::REBOOTING,
            FirmwareUpdateStatus::ACTIVATED,
        ));
    }

    /** A second update after a rollback, through the bridge alone. */
    public function testNeedsNoMessageToReturnToIdleBetweenTwoUpdates(): void
    {
        $state = FirmwareUpdateStatus::IDLE;
        foreach (['Downloading', 'Failed'] as $status) {
            $state = $this->transitions->applyNotification($state, $status);
        }
        self::assertSame(FirmwareUpdateStatus::FAILED, $state);

        // `Failed -> Idle` is the rollback and nothing announces it. The next thing
        // the server hears is the next `Downloading`.
        $state = $this->transitions->applyNotification($state, 'Downloading');
        self::assertSame(FirmwareUpdateStatus::DOWNLOADING, $state);
    }

    public function testRefusesAStreamTheTableCannotProduce(): void
    {
        $cannot = [
            // Skipping `Downloaded`: the station MUST report each status transition
            // (`firmware-status.md` §6 rule 1), so a jump straight to `Installing`
            // is a report from a station that skipped a MUST.
            [FirmwareUpdateStatus::DOWNLOADING, 'Installing'],
            // Nothing follows `Activated`.
            [FirmwareUpdateStatus::ACTIVATED, 'Downloading'],
            // `Installed` cannot go back to `Installing`.
            [FirmwareUpdateStatus::INSTALLED, 'Installing'],
        ];

        foreach ($cannot as [$from, $status]) {
            try {
                $this->transitions->applyNotification($from, $status);
                self::fail("{$from->value} -> {$status} must be refused");
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Invalid firmware transition', $e->getMessage());
            }
        }

        // A value outside the enum fails as an unknown status, not as a transition.
        try {
            $this->transitions->applyNotification(FirmwareUpdateStatus::IDLE, 'Verifying');
            self::fail('an unobservable state is not a wire value');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Unknown firmware notification status', $e->getMessage());
        }
    }
}
