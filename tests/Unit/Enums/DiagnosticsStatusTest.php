<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\DiagnosticsStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DiagnosticsStatusTest extends TestCase
{
    #[Test]
    public function it_has_exactly_five_cases(): void
    {
        self::assertCount(5, DiagnosticsStatus::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('pending', DiagnosticsStatus::PENDING->value);
        self::assertSame('collecting', DiagnosticsStatus::COLLECTING->value);
        self::assertSame('uploading', DiagnosticsStatus::UPLOADING->value);
        self::assertSame('uploaded', DiagnosticsStatus::UPLOADED->value);
        self::assertSame('failed', DiagnosticsStatus::FAILED->value);
    }

    // --- isTerminal ---

    #[Test]
    public function is_terminal_returns_true_for_uploaded_and_failed(): void
    {
        self::assertTrue(DiagnosticsStatus::UPLOADED->isTerminal());
        self::assertTrue(DiagnosticsStatus::FAILED->isTerminal());
    }

    #[Test]
    public function is_terminal_returns_false_for_non_terminal_states(): void
    {
        self::assertFalse(DiagnosticsStatus::PENDING->isTerminal());
        self::assertFalse(DiagnosticsStatus::COLLECTING->isTerminal());
        self::assertFalse(DiagnosticsStatus::UPLOADING->isTerminal());
    }

    // --- isActive ---

    #[Test]
    public function is_active_returns_true_for_non_terminal_states(): void
    {
        self::assertTrue(DiagnosticsStatus::PENDING->isActive());
        self::assertTrue(DiagnosticsStatus::COLLECTING->isActive());
        self::assertTrue(DiagnosticsStatus::UPLOADING->isActive());
    }

    #[Test]
    public function is_active_returns_false_for_terminal_states(): void
    {
        self::assertFalse(DiagnosticsStatus::UPLOADED->isActive());
        self::assertFalse(DiagnosticsStatus::FAILED->isActive());
    }

    // --- allowedTransitions ---

    #[Test]
    public function pending_can_transition_to_collecting_or_failed(): void
    {
        $allowed = DiagnosticsStatus::PENDING->allowedTransitions();

        self::assertCount(2, $allowed);
        self::assertSame(DiagnosticsStatus::COLLECTING, $allowed[0]);
        self::assertSame(DiagnosticsStatus::FAILED, $allowed[1]);
    }

    #[Test]
    public function collecting_can_transition_to_uploading_or_failed(): void
    {
        $allowed = DiagnosticsStatus::COLLECTING->allowedTransitions();

        self::assertCount(2, $allowed);
        self::assertSame(DiagnosticsStatus::UPLOADING, $allowed[0]);
        self::assertSame(DiagnosticsStatus::FAILED, $allowed[1]);
    }

    #[Test]
    public function uploading_can_transition_to_uploaded_or_failed(): void
    {
        $allowed = DiagnosticsStatus::UPLOADING->allowedTransitions();

        self::assertCount(2, $allowed);
        self::assertSame(DiagnosticsStatus::UPLOADED, $allowed[0]);
        self::assertSame(DiagnosticsStatus::FAILED, $allowed[1]);
    }

    #[Test]
    public function uploaded_has_no_allowed_transitions(): void
    {
        self::assertSame([], DiagnosticsStatus::UPLOADED->allowedTransitions());
    }

    #[Test]
    public function failed_has_no_allowed_transitions(): void
    {
        self::assertSame([], DiagnosticsStatus::FAILED->allowedTransitions());
    }

    // --- canTransitionTo ---

    #[Test]
    public function can_transition_to_returns_true_for_allowed_transitions(): void
    {
        self::assertTrue(DiagnosticsStatus::PENDING->canTransitionTo(DiagnosticsStatus::COLLECTING));
        self::assertTrue(DiagnosticsStatus::PENDING->canTransitionTo(DiagnosticsStatus::FAILED));
        self::assertTrue(DiagnosticsStatus::COLLECTING->canTransitionTo(DiagnosticsStatus::UPLOADING));
        self::assertTrue(DiagnosticsStatus::COLLECTING->canTransitionTo(DiagnosticsStatus::FAILED));
        self::assertTrue(DiagnosticsStatus::UPLOADING->canTransitionTo(DiagnosticsStatus::UPLOADED));
        self::assertTrue(DiagnosticsStatus::UPLOADING->canTransitionTo(DiagnosticsStatus::FAILED));
    }

    #[Test]
    public function can_transition_to_returns_false_for_disallowed_transitions(): void
    {
        // PENDING cannot go to UPLOADING, UPLOADED
        self::assertFalse(DiagnosticsStatus::PENDING->canTransitionTo(DiagnosticsStatus::UPLOADING));
        self::assertFalse(DiagnosticsStatus::PENDING->canTransitionTo(DiagnosticsStatus::UPLOADED));
        self::assertFalse(DiagnosticsStatus::PENDING->canTransitionTo(DiagnosticsStatus::PENDING));

        // COLLECTING cannot go to PENDING, UPLOADED
        self::assertFalse(DiagnosticsStatus::COLLECTING->canTransitionTo(DiagnosticsStatus::PENDING));
        self::assertFalse(DiagnosticsStatus::COLLECTING->canTransitionTo(DiagnosticsStatus::UPLOADED));
        self::assertFalse(DiagnosticsStatus::COLLECTING->canTransitionTo(DiagnosticsStatus::COLLECTING));

        // UPLOADING cannot go to PENDING, COLLECTING
        self::assertFalse(DiagnosticsStatus::UPLOADING->canTransitionTo(DiagnosticsStatus::PENDING));
        self::assertFalse(DiagnosticsStatus::UPLOADING->canTransitionTo(DiagnosticsStatus::COLLECTING));
        self::assertFalse(DiagnosticsStatus::UPLOADING->canTransitionTo(DiagnosticsStatus::UPLOADING));

        // Terminal states cannot go anywhere
        self::assertFalse(DiagnosticsStatus::UPLOADED->canTransitionTo(DiagnosticsStatus::PENDING));
        self::assertFalse(DiagnosticsStatus::UPLOADED->canTransitionTo(DiagnosticsStatus::FAILED));
        self::assertFalse(DiagnosticsStatus::FAILED->canTransitionTo(DiagnosticsStatus::PENDING));
        self::assertFalse(DiagnosticsStatus::FAILED->canTransitionTo(DiagnosticsStatus::UPLOADED));
    }

    // --- fromNotificationStatus (explicit match — only accepts PascalCase notification values) ---

    #[Test]
    public function from_notification_status_maps_valid_notification_values(): void
    {
        self::assertSame(DiagnosticsStatus::COLLECTING, DiagnosticsStatus::fromNotificationStatus('Collecting'));
        self::assertSame(DiagnosticsStatus::UPLOADING, DiagnosticsStatus::fromNotificationStatus('Uploading'));
        self::assertSame(DiagnosticsStatus::UPLOADED, DiagnosticsStatus::fromNotificationStatus('Uploaded'));
        self::assertSame(DiagnosticsStatus::FAILED, DiagnosticsStatus::fromNotificationStatus('Failed'));
    }

    #[Test]
    public function from_notification_status_throws_for_unknown_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown diagnostics notification status: NONEXISTENT');
        DiagnosticsStatus::fromNotificationStatus('NONEXISTENT');
    }

    #[Test]
    public function from_notification_status_throws_for_lowercase_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DiagnosticsStatus::fromNotificationStatus('collecting');
    }

    #[Test]
    public function from_notification_status_throws_for_pending_which_is_not_a_notification(): void
    {
        // PENDING is the initial state — stations never send a "Pending" notification
        $this->expectException(\InvalidArgumentException::class);
        DiagnosticsStatus::fromNotificationStatus('Pending');
    }
}
