<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\OsppErrorCode;
use Ospp\Protocol\Enums\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 3020 BINDING_UNCOVERED — the binding EXISTS and the ordinal is GONE.
 *
 * The refusal this code names had no honest member before it, and the three
 * candidates each failed for a different reason, which is why the metadata below
 * is asserted rather than left to a `default` arm:
 *
 *  - `3019 SERVICE_NOT_BOUND` is false here. Its own action sends an operator to
 *    create a binding that is already in the table.
 *  - `3017 PROGRAM_NOT_DECLARED` is the STATION's code for an ordinal it was sent
 *    and does not have; this fault is the server's and the station has no part in
 *    it.
 *  - `3003 SERVICE_UNAVAILABLE` is the adjacent case, discriminated by
 *    `details.cause: station-reported` — the opposite of this fault.
 *
 * Severity `Warning` and `recoverable: true` both coincide with the enum's
 * defaults. They are pinned anyway: a default that happens to be right is not a
 * statement, and the next code to land in either arm would move them silently.
 */
final class BindingUncoveredCodeTest extends TestCase
{
    #[Test]
    public function itResolvesByNumber(): void
    {
        self::assertSame(OsppErrorCode::BINDING_UNCOVERED, OsppErrorCode::from(3020));
        self::assertSame(OsppErrorCode::BINDING_UNCOVERED, OsppErrorCode::tryFrom(3020));
    }

    #[Test]
    public function itResolvesByName(): void
    {
        self::assertSame(3020, OsppErrorCode::BINDING_UNCOVERED->value);
        self::assertSame('BINDING_UNCOVERED', OsppErrorCode::BINDING_UNCOVERED->name);
        self::assertSame('BINDING_UNCOVERED', OsppErrorCode::BINDING_UNCOVERED->errorText());
    }

    #[Test]
    public function itCarriesTheStatusSeverityAndRecoverabilityTheRegistryGivesIt(): void
    {
        $c = OsppErrorCode::BINDING_UNCOVERED;

        // 409, not 422 and not 500: a fact about the addressed resource, not about
        // the server answering. The same row 3003 was moved to at spec 0.31.0, and
        // where 3001, 3014 and 3019 already sit.
        self::assertSame(409, $c->httpStatus());
        self::assertSame(Severity::WARNING, $c->severity());
        // An operator re-binds the service to a declared ordinal and the next start
        // succeeds. No firmware, no visit.
        self::assertTrue($c->isRecoverable());
        self::assertSame('session', $c->category());
    }

    #[Test]
    public function itsRecommendedActionRenders(): void
    {
        $action = OsppErrorCode::BINDING_UNCOVERED->recommendedAction();

        self::assertNotSame('', trim($action));
        // Appendix C bounds `recommendedAction` at 1..500 code points.
        self::assertGreaterThanOrEqual(1, mb_strlen($action));
        self::assertLessThanOrEqual(500, mb_strlen($action));
        // Not the one string 07-errors.md §1.4 names as non-conforming.
        self::assertStringNotContainsStringIgnoringCase(
            'review the error details and take corrective action',
            $action,
        );
    }

    #[Test]
    public function itsActionNamesTheOrdinalAndTheSetToRebindOnto(): void
    {
        $action = OsppErrorCode::BINDING_UNCOVERED->recommendedAction();

        // `details.declaredPrograms` is the whole of the repair: without it an
        // operator knows the binding is wrong and not what to point it at.
        self::assertStringContainsString('details.declaredPrograms', $action);
        self::assertStringContainsString('details.programNumber', $action);
    }

    #[Test]
    public function itsActionAddressesTheOperatorAndTheServerAndNotTheStation(): void
    {
        $action = OsppErrorCode::BINDING_UNCOVERED->recommendedAction();

        self::assertStringContainsString('Operator:', $action);
        self::assertStringContainsString('Server:', $action);
        // The direction rule, in the only form this package can carry it: the code
        // is server-originated toward the requesting client and MUST NOT be
        // transmitted to a station, so the action has nothing to tell a station.
        // 3019 is addressed the same way; 3017, which IS the station's code, opens
        // with `Station:`.
        self::assertStringNotContainsString('Station:', $action);
        self::assertStringNotContainsString('Station:', OsppErrorCode::SERVICE_NOT_BOUND->recommendedAction());
        self::assertStringContainsString('Station:', OsppErrorCode::PROGRAM_NOT_DECLARED->recommendedAction());
    }

    #[Test]
    public function itDoesNotRepeatTheLieTheOtherThreeCodesTold(): void
    {
        $action = OsppErrorCode::BINDING_UNCOVERED->recommendedAction();

        // 3019's action says "create the binding for this (bay, service) pair".
        // Here the row is already there, so that sentence would send an operator to
        // create a row that exists.
        self::assertStringNotContainsString('create the binding', $action);

        // And the three it replaces must stay distinct from it, which is also what
        // scripts/check-recommended-action.php rule 4 asserts over the whole set.
        self::assertNotSame(OsppErrorCode::SERVICE_NOT_BOUND->recommendedAction(), $action);
        self::assertNotSame(OsppErrorCode::PROGRAM_NOT_DECLARED->recommendedAction(), $action);
        self::assertNotSame(OsppErrorCode::SERVICE_UNAVAILABLE->recommendedAction(), $action);
    }

    /**
     * The negative control for the whole change: the registry did not become
     * permissive. 3021 is the next ordinal and is refused exactly as 3020 was
     * before this code landed.
     */
    #[Test]
    public function aCodeOutsideTheRegistryIsStillRefused(): void
    {
        self::assertNull(OsppErrorCode::tryFrom(3021));
        self::assertNull(OsppErrorCode::tryFrom(0));
        self::assertNull(OsppErrorCode::tryFrom(999));
        self::assertNull(OsppErrorCode::tryFrom(7000));
        self::assertNull(OsppErrorCode::tryFrom(-1));

        $this->expectException(\ValueError::class);
        OsppErrorCode::from(3021);
    }
}
