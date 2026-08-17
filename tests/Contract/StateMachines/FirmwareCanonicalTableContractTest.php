<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\StateMachines;

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
}
