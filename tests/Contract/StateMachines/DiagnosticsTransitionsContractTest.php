<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\StateMachines;

use Ospp\Protocol\Enums\DiagnosticsState;
use Ospp\Protocol\StateMachines\DiagnosticsTransitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DiagnosticsTransitionsContractTest extends TestCase
{
    private DiagnosticsTransitions $transitions;

    protected function setUp(): void
    {
        $this->transitions = new DiagnosticsTransitions();
    }

    // The edge set — and its size — is pinned by DiagnosticsCanonicalTableContractTest
    // against spec §8.3, and is deliberately not restated as a cardinal here. This
    // file asserted `6`, and the 5x5 sweep asserted a 6/19 tally. A tally cannot say
    // WHICH pair moved: the machine was missing `uploaded -> idle` and
    // `failed -> idle`, and carried `pending -> failed` that §8.3 does not list, and
    // every one of those numbers was internally consistent throughout.

    #[Test]
    public function self_transitions_are_never_allowed(): void
    {
        foreach (DiagnosticsState::cases() as $state) {
            self::assertFalse(
                $this->transitions->canTransition($state, $state),
                "Self-transition should not be allowed for {$state->value}",
            );
        }
    }

    /**
     * The progress stream is not a self-transition, and this is the assertion that
     * says which of the two readings applies.
     *
     * §8.4: "The repeated `Uploading` notifications are one state, not many. §8.3
     * has no `Uploading -> Uploading` edge and MUST NOT gain one: the progress
     * stream is the station re-reporting a state it has not left. A server that
     * drives this machine by feeding it arriving notifications MUST advance on a
     * CHANGE of `status`."
     *
     * So a consumer must not call the machine per message. The refusal above is
     * correct; feeding it every notification is the error, and it is an error a
     * count-based test cannot express.
     */
    #[Test]
    public function repeated_uploading_notifications_are_one_state(): void
    {
        $stream = ['Uploading', 'Uploading', 'Uploading', 'Uploaded'];

        $state = DiagnosticsState::UPLOADING;
        foreach ($stream as $status) {
            $reported = DiagnosticsState::fromNotificationStatus($status);
            if ($reported === $state) {
                continue; // a progress report, not a transition
            }
            self::assertTrue(
                $this->transitions->canTransition($state, $reported),
                "{$state->value} -> {$reported->value}",
            );
            $state = $reported;
        }

        self::assertSame(DiagnosticsState::UPLOADED, $state);
    }

    #[Test]
    public function happy_path_idle_collecting_uploading_uploaded_idle(): void
    {
        $sequence = [
            [DiagnosticsState::IDLE, DiagnosticsState::COLLECTING],
            [DiagnosticsState::COLLECTING, DiagnosticsState::UPLOADING],
            [DiagnosticsState::UPLOADING, DiagnosticsState::UPLOADED],
            [DiagnosticsState::UPLOADED, DiagnosticsState::IDLE],
        ];

        foreach ($sequence as [$from, $to]) {
            self::assertTrue(
                $this->transitions->canTransition($from, $to),
                "{$from->value} -> {$to->value} should be valid in the happy path",
            );
        }
    }

    #[Test]
    public function only_the_two_in_progress_states_can_fail(): void
    {
        // §8.3: `collecting` and `uploading` are the states where something can go
        // wrong. `idle` cannot fail — a station that cannot start answers `Rejected`
        // — and the two outcomes cannot fail again.
        $canFail = [DiagnosticsState::COLLECTING, DiagnosticsState::UPLOADING];
        $cannotFail = [DiagnosticsState::IDLE, DiagnosticsState::UPLOADED, DiagnosticsState::FAILED];

        foreach ($canFail as $state) {
            self::assertTrue(
                $this->transitions->canTransition($state, DiagnosticsState::FAILED),
                "{$state->value} should be able to reach FAILED",
            );
        }

        foreach ($cannotFail as $state) {
            self::assertFalse(
                $this->transitions->canTransition($state, DiagnosticsState::FAILED),
                "{$state->value} must NOT reach FAILED",
            );
        }
    }
}
