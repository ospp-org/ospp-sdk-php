<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\OsppErrorCode;
use Ospp\Protocol\Enums\SessionEndReason;
use Ospp\Protocol\Enums\Severity;
use PHPUnit\Framework\TestCase;

/**
 * spec v0.11.1 — the three members added, and their metadata.
 *
 * Metadata is asserted rather than left to the `default` arms, because the
 * defaults are wrong for both new codes: severity defaults to WARNING (3019 is
 * an Error) and httpStatus defaults to 500 (both are 409). A code that carries
 * the wrong status is worse than one that carries none — a client retries a 500
 * and does not retry a 409.
 */
final class V0111CodesTest extends TestCase
{
    public function test_operator_stopped_exists_and_is_the_wire_value(): void
    {
        self::assertSame('OperatorStopped', SessionEndReason::OPERATOR_STOPPED->value);
    }

    public function test_operator_stopped_is_distinct_from_deauthorized(): void
    {
        // The whole reason it was added: `Deauthorized` carries "Session MUST be
        // billed at zero", while a forced stop bills the delivered quantity.
        self::assertNotSame(
            SessionEndReason::DEAUTHORIZED,
            SessionEndReason::OPERATOR_STOPPED,
        );
    }

    public function test_service_not_bound_metadata(): void
    {
        $c = OsppErrorCode::SERVICE_NOT_BOUND;

        self::assertSame(3019, $c->value);
        self::assertSame('SERVICE_NOT_BOUND', $c->errorText());
        self::assertSame(Severity::ERROR, $c->severity());
        self::assertTrue($c->isRecoverable());
        // 409 Conflict, not 422: the request is well-formed and every value in it
        // is valid. What is incomplete is the server's own configuration.
        self::assertSame(409, $c->httpStatus());
    }

    public function test_command_pre_empted_metadata(): void
    {
        $c = OsppErrorCode::COMMAND_PRE_EMPTED;

        self::assertSame(6008, $c->value);
        self::assertSame('COMMAND_PRE_EMPTED', $c->errorText());
        self::assertSame(Severity::WARNING, $c->severity());
        self::assertTrue($c->isRecoverable());
        self::assertSame(409, $c->httpStatus());
    }

    public function test_4020_no_longer_names_a_deleted_field(): void
    {
        // The twelfth defect. The spec said `bays` from v0.11.0; this text said
        // `bayCount`, a field the request no longer has, and told the reader to
        // carry two counts that are equal whenever a bay is swapped.
        $action = OsppErrorCode::BAY_COUNT_MISMATCH->recommendedAction();

        self::assertIsString($action);
        self::assertStringNotContainsString('bayCount', $action);
        self::assertStringNotContainsString('carry both counts', $action);
        self::assertStringContainsString('declaredBayNumbers', $action);
        self::assertStringContainsString('registeredBayNumbers', $action);
    }
}
