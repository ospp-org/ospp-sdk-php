<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\StateMachines;

use Ospp\Protocol\Enums\StationState;
use Ospp\Protocol\StateMachines\StationTransitions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The station state machine — spec/05-state-machines.md §1.
 *
 * Mirrored by sdk-ts tests/state-machines/StationStateMachine.test.ts.
 *
 * NEITHER SDK had this machine before this arc. The chapter defined machines
 * for bays, sessions, reservations, BLE and firmware and none for the station,
 * so `Pending`, `Rejected`, `Accepted` and not-provisioned had no formal home —
 * and `3018 TOPOLOGY_MISMATCH` depended on a state that existed nowhere
 * structurally.
 */
final class StationTransitionsContractTest extends TestCase
{
    private StationTransitions $transitions;

    protected function setUp(): void
    {
        $this->transitions = new StationTransitions();
    }

    /**
     * §1: "A station MUST be in exactly one of the six defined states at all
     * times."
     */
    #[Test]
    public function thereAreExactlySixStates(): void
    {
        self::assertSame(
            ['NotProvisioned', 'Booting', 'Pending', 'Rejected', 'Operational', 'Disconnected'],
            array_map(fn (StationState $s) => $s->value, StationState::cases()),
        );
    }

    /**
     * The §1.3 transition table.
     *
     * @return list<array{StationState, StationState}>
     */
    public static function validTransitions(): array
    {
        return [
            [StationState::NOT_PROVISIONED, StationState::BOOTING],   // Credential obtained
            [StationState::BOOTING, StationState::OPERATIONAL],       // RESPONSE Accepted
            [StationState::BOOTING, StationState::PENDING],           // RESPONSE Pending
            [StationState::BOOTING, StationState::REJECTED],          // RESPONSE Rejected
            [StationState::BOOTING, StationState::BOOTING],           // Response timeout / retry
            [StationState::PENDING, StationState::BOOTING],           // retryInterval elapsed
            [StationState::REJECTED, StationState::BOOTING],          // retryInterval elapsed
            [StationState::BOOTING, StationState::DISCONNECTED],      // MQTT connection lost
            [StationState::PENDING, StationState::DISCONNECTED],
            [StationState::REJECTED, StationState::DISCONNECTED],
            [StationState::OPERATIONAL, StationState::DISCONNECTED],
            [StationState::DISCONNECTED, StationState::BOOTING],      // MQTT reconnected
            [StationState::OPERATIONAL, StationState::BOOTING],       // Reboot
        ];
    }

    #[DataProvider('validTransitions')]
    public function testTableAllows(StationState $from, StationState $to): void
    {
        self::assertTrue(
            $this->transitions->canTransition($from, $to),
            "{$from->value} -> {$to->value} must be allowed",
        );
    }

    /**
     * §1.3: "In particular there is **no** edge from `Pending` or `Rejected`
     * directly to `Operational`: a station leaves a restricted state only by
     * re-sending BootNotification and receiving `Accepted`. The server cannot
     * promote a station in place."
     */
    #[Test]
    public function thereIsNoDirectPromotionOutOfARestrictedState(): void
    {
        self::assertFalse($this->transitions->canTransition(StationState::PENDING, StationState::OPERATIONAL));
        self::assertFalse($this->transitions->canTransition(StationState::REJECTED, StationState::OPERATIONAL));
    }

    /**
     * §1.2: "A station MUST NOT enter this state autonomously — there is no
     * remote credential wipe."
     */
    #[Test]
    public function nothingReEntersNotProvisioned(): void
    {
        foreach (StationState::cases() as $from) {
            self::assertFalse(
                $this->transitions->canTransition($from, StationState::NOT_PROVISIONED),
                "{$from->value} -> NotProvisioned",
            );
        }
    }

    #[Test]
    public function everythingOutsideTheTableIsRefused(): void
    {
        $allowed = [];
        foreach (self::validTransitions() as [$from, $to]) {
            $allowed[$from->value.'>'.$to->value] = true;
        }

        foreach (StationState::cases() as $from) {
            foreach (StationState::cases() as $to) {
                $key = $from->value.'>'.$to->value;
                self::assertSame(
                    isset($allowed[$key]),
                    $this->transitions->canTransition($from, $to),
                    $key,
                );
            }
        }
    }

    // ── §1.4, the restricted states ─────────────────────────────────────────

    #[Test]
    public function pendingAndRejectedAreTheRestrictedStates(): void
    {
        self::assertTrue(StationState::PENDING->isRestricted());
        self::assertTrue(StationState::REJECTED->isRestricted());
        self::assertFalse(StationState::OPERATIONAL->isRestricted());
        self::assertFalse(StationState::BOOTING->isRestricted());
    }

    #[Test]
    public function receivesAndProcessesServerCommands(): void
    {
        self::assertFalse(StationState::BOOTING->mayReceiveCommands());
        self::assertTrue(StationState::PENDING->mayReceiveCommands());
        self::assertFalse(StationState::REJECTED->mayReceiveCommands());
        self::assertTrue(StationState::OPERATIONAL->mayReceiveCommands());
    }

    #[Test]
    public function answersAServerCommandWithAResponse(): void
    {
        self::assertFalse(StationState::BOOTING->mayAnswerCommands());
        self::assertTrue(StationState::PENDING->mayAnswerCommands());
        self::assertFalse(StationState::REJECTED->mayAnswerCommands());
        self::assertTrue(StationState::OPERATIONAL->mayAnswerCommands());
    }

    #[Test]
    public function originatesOnlyStandingRepairMessagesWhileRestricted(): void
    {
        // §1.4: BootNotification retries are a MUST in both restricted states.
        self::assertTrue(StationState::BOOTING->mayOriginate('BootNotification'));
        self::assertTrue(StationState::PENDING->mayOriginate('BootNotification'));
        self::assertTrue(StationState::REJECTED->mayOriginate('BootNotification'));

        // The second standing-repair message, and the row this release added.
        // Only `Pending` — `Booting` and `Rejected` hold no session key, and
        // SignCertificate is in the signed 44.
        self::assertTrue(StationState::PENDING->mayOriginate('SignCertificate'));
        self::assertFalse(StationState::BOOTING->mayOriginate('SignCertificate'));
        self::assertFalse(StationState::REJECTED->mayOriginate('SignCertificate'));

        // Everything else reports on the station's work, and stays forbidden.
        foreach (['Heartbeat', 'StatusNotification', 'MeterValues', 'TransactionEvent', 'SecurityEvent'] as $action) {
            self::assertFalse(StationState::BOOTING->mayOriginate($action), $action);
            self::assertFalse(StationState::PENDING->mayOriginate($action), $action);
            self::assertFalse(StationState::REJECTED->mayOriginate($action), $action);
            self::assertTrue(StationState::OPERATIONAL->mayOriginate($action), $action);
        }

        // Neither is a §1.4 state; both answer false, as before.
        self::assertFalse(StationState::NOT_PROVISIONED->mayOriginate('BootNotification'));
        self::assertFalse(StationState::DISCONNECTED->mayOriginate('BootNotification'));
    }

    #[Test]
    public function startsNewCustomerService(): void
    {
        self::assertFalse(StationState::BOOTING->mayStartNewService());
        self::assertFalse(StationState::PENDING->mayStartNewService());
        self::assertFalse(StationState::REJECTED->mayStartNewService());
        self::assertTrue(StationState::OPERATIONAL->mayStartNewService());
    }

    /**
     * §1.4: "The key row is what makes the rest of the table possible, and it is
     * easy to get wrong."
     */
    #[Test]
    public function holdsASessionKeyPendingDoesRejectedDoesNot(): void
    {
        self::assertFalse(StationState::BOOTING->holdsSessionKey());
        self::assertTrue(StationState::PENDING->holdsSessionKey());
        self::assertFalse(StationState::REJECTED->holdsSessionKey());
        self::assertTrue(StationState::OPERATIONAL->holdsSessionKey());
    }

    /**
     * The key row and the answering row must agree: a state that answers a
     * signed command must hold the key to sign the answer with.
     */
    #[Test]
    public function neverAnswersACommandInAStateWhereItHoldsNoKey(): void
    {
        foreach (StationState::cases() as $state) {
            if ($state->mayAnswerCommands()) {
                self::assertTrue(
                    $state->holdsSessionKey(),
                    "{$state->value} answers commands but holds no key",
                );
            }
        }
    }
}
