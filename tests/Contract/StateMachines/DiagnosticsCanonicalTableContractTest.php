<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\StateMachines;

use Ospp\Protocol\Enums\DiagnosticsState;
use Ospp\Protocol\StateMachines\DiagnosticsTransitions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The canonical diagnostics upload transition table — spec/05-state-machines.md §8.3.
 *
 * Seven rows and seven edges. §8.3 states why that coincidence must not be leaned
 * on: "no `(from, to)` pair appears twice here, so the row count and the edge count
 * coincide. That they coincide is a property of this table and not a general one: a
 * conformance check MUST assert the pairs, not the cardinal, because a count cannot
 * say which edge moved."
 *
 * This file exists because the count-based form is exactly what let this SDK carry a
 * wrong table for four releases. Until 0.23.0 there was no §8 to check against, and
 * this SDK's machine and sdk-ts's disagreed on THREE edges — initial state, whether
 * the initial state can fail, and whether the outcomes are terminal — with both
 * suites green, each pinning its own answer, and neither able to cite a source. The
 * pair list below is the same vector list the sdk-ts mirror of this file asserts.
 *
 * The subject is the STATION. A server's record of a request is a different object
 * with a different table (§8.5, {@see \Ospp\Protocol\Enums\DiagnosticsStatus}), and
 * §8.5 forbids asserting one against the other.
 */
final class DiagnosticsCanonicalTableContractTest extends TestCase
{
    private DiagnosticsTransitions $transitions;

    protected function setUp(): void
    {
        $this->transitions = new DiagnosticsTransitions();
    }

    /**
     * The seven distinct `(from, to)` pairs of §8.3, in table order.
     *
     * @return list<array{DiagnosticsState, DiagnosticsState}>
     */
    public static function edges(): array
    {
        return [
            // GetDiagnostics [MSG-018] answered `Accepted`. The ONLY entry edge:
            // §8.3, "there is no `Idle -> Failed` edge, and its absence is
            // load-bearing" — a station that cannot start answers `Rejected` and
            // never enters the machine.
            [DiagnosticsState::IDLE, DiagnosticsState::COLLECTING],

            // Archive complete / collection error
            [DiagnosticsState::COLLECTING, DiagnosticsState::UPLOADING],
            [DiagnosticsState::COLLECTING, DiagnosticsState::FAILED],

            // PUT completes / PUT fails after the station's own retries
            [DiagnosticsState::UPLOADING, DiagnosticsState::UPLOADED],
            [DiagnosticsState::UPLOADING, DiagnosticsState::FAILED],

            // Both outcomes return to Idle. Neither is terminal, and §8.4 notes
            // these two edges have NO wire trigger: nothing the station sends
            // announces that it is ready again.
            [DiagnosticsState::UPLOADED, DiagnosticsState::IDLE],
            [DiagnosticsState::FAILED, DiagnosticsState::IDLE],
        ];
    }

    /**
     * @return list<array{DiagnosticsState, DiagnosticsState}>
     */
    public static function edgeProvider(): array
    {
        return self::edges();
    }

    #[DataProvider('edgeProvider')]
    public function testEachEdgeOfTheCanonicalTableIsPermitted(
        DiagnosticsState $from,
        DiagnosticsState $to,
    ): void {
        self::assertTrue(
            $this->transitions->canTransition($from, $to),
            "§8.3 lists {$from->value} -> {$to->value}",
        );
    }

    /**
     * The other direction: every pair the table does NOT list must be refused.
     *
     * 5 states = 25 ordered pairs, 7 permitted, 18 refused. The sweep is
     * exhaustive rather than a chosen list, so a pair that is neither asserted
     * allowed nor asserted rejected cannot exist — which it did in the sdk-ts
     * mirror, for two pairs, before this file's twin was written.
     */
    public function testEveryPairOutsideTheTableIsRefused(): void
    {
        $permitted = [];
        foreach (self::edges() as [$from, $to]) {
            $permitted["{$from->value}->{$to->value}"] = true;
        }

        foreach (DiagnosticsState::cases() as $from) {
            foreach (DiagnosticsState::cases() as $to) {
                $key = "{$from->value}->{$to->value}";
                self::assertSame(
                    isset($permitted[$key]),
                    $this->transitions->canTransition($from, $to),
                    "{$key}: table and canonical edge set disagree",
                );
            }
        }
    }

    /**
     * The map and the function must not drift apart.
     */
    public function testTheTransitionTableAgreesWithCanTransition(): void
    {
        $table = $this->transitions->getTransitionTable();

        foreach (DiagnosticsState::cases() as $from) {
            self::assertArrayHasKey($from->value, $table);
            foreach ($table[$from->value] as $toValue) {
                $to = DiagnosticsState::from($toValue);
                self::assertTrue($this->transitions->canTransition($from, $to));
            }
        }

        // The only cardinal asserted anywhere, and it is compared against the
        // pinned set's own size, so no literal can be "corrected" to match a
        // machine that grew an edge.
        self::assertSame(count(self::edges()), $this->transitions->transitionCount());
    }

    /**
     * Nothing is terminal, and that is the consequence stated as behaviour.
     *
     * Before 0.23.0 `uploaded` and `failed` both had empty rows, so the machine
     * could run one diagnostics upload and never a second — the identical defect
     * FirmwareTransitions closed at 0.20.0 and that was never carried across.
     */
    public function testTheMachineSurvivesAnUploadAndCanRunAnother(): void
    {
        self::assertSame(
            [DiagnosticsState::IDLE],
            $this->transitions->allowedTransitions(DiagnosticsState::UPLOADED),
            '§8.3: Uploaded returns to Idle',
        );
        self::assertSame(
            [DiagnosticsState::IDLE],
            $this->transitions->allowedTransitions(DiagnosticsState::FAILED),
            '§8.3: Failed returns to Idle',
        );

        $walk = [
            [DiagnosticsState::IDLE, DiagnosticsState::COLLECTING],
            [DiagnosticsState::COLLECTING, DiagnosticsState::UPLOADING],
            [DiagnosticsState::UPLOADING, DiagnosticsState::UPLOADED],
            [DiagnosticsState::UPLOADED, DiagnosticsState::IDLE],
            // second upload, which the old table could not begin
            [DiagnosticsState::IDLE, DiagnosticsState::COLLECTING],
            [DiagnosticsState::COLLECTING, DiagnosticsState::FAILED],
            [DiagnosticsState::FAILED, DiagnosticsState::IDLE],
            // and a third after a failure
            [DiagnosticsState::IDLE, DiagnosticsState::COLLECTING],
        ];

        foreach ($walk as $step => [$from, $to]) {
            self::assertTrue(
                $this->transitions->canTransition($from, $to),
                "step {$step}: {$from->value} -> {$to->value}",
            );
        }
    }

    public function testIsTerminalAgreesWithTheAbsenceOfOutgoingEdges(): void
    {
        foreach (DiagnosticsState::cases() as $state) {
            self::assertSame(
                $this->transitions->allowedTransitions($state) === [],
                $state->isTerminal(),
                "{$state->value}: isTerminal() must mean 'has no outgoing edge'",
            );
        }
    }

    public function testNoSelfTransition(): void
    {
        foreach (DiagnosticsState::cases() as $state) {
            self::assertFalse($this->transitions->canTransition($state, $state));
        }
    }

    /**
     * The wire -> machine bridge, and the state it cannot produce.
     *
     * §8.4 maps four of the five states to a notification value; `idle` has none
     * "in either direction". A bridge that could produce `idle` from a wire value
     * would be inventing the one edge the protocol never announces.
     */
    public function testTheBridgeCoversTheFourWireValuesAndCannotProduceIdle(): void
    {
        $wire = ['Collecting', 'Uploading', 'Uploaded', 'Failed'];

        $produced = [];
        foreach ($wire as $status) {
            $state = DiagnosticsState::fromNotificationStatus($status);
            $produced[] = $state;
            self::assertSame(
                $status,
                $state->toNotificationStatus(),
                'the bridge must round-trip',
            );
            self::assertTrue($state->isReportable());
        }

        self::assertNotContains(DiagnosticsState::IDLE, $produced);
        self::assertNull(DiagnosticsState::IDLE->toNotificationStatus());
        self::assertFalse(DiagnosticsState::IDLE->isReportable());

        // Every state except `idle` is reachable from the wire, so the mapping is
        // total in the direction a server consumes it.
        self::assertCount(count(DiagnosticsState::cases()) - 1, $produced);
    }

    /**
     * The wire enum is the schema's, not a hand-kept list.
     *
     * Read from the vendored schema so that a spec change to the notification enum
     * fails here rather than being absorbed by a literal in this file.
     */
    public function testTheBridgeRejectsAnythingOutsideTheSchemaEnum(): void
    {
        $schemaPath = \dirname(__DIR__, 3).'/schemas/mqtt/diagnostics-notification.schema.json';
        $raw = file_get_contents($schemaPath);
        self::assertIsString($raw);
        /** @var array{properties: array{status: array{enum: list<string>}}} $schema */
        $schema = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $enum = $schema['properties']['status']['enum'];

        self::assertSame(['Collecting', 'Uploading', 'Uploaded', 'Failed'], $enum);

        foreach ($enum as $status) {
            DiagnosticsState::fromNotificationStatus($status);
        }

        // `Pending` is a record state and is not on the wire (§8.5).
        $this->expectException(\InvalidArgumentException::class);
        DiagnosticsState::fromNotificationStatus('Pending');
    }
}
