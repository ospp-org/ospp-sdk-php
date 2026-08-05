<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Enums;

use Ospp\Protocol\Enums\OsppErrorCode;
use Ospp\Protocol\Enums\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The two codes the topology/programs arc adds — spec/07-errors.md §3.3.
 *
 * Mirrored by sdk-ts tests/enums/ProgramAndTopologyErrorCode.test.ts, which
 * asserts the same facts on the same inputs.
 */
final class ProgramAndTopologyErrorCodeContractTest extends TestCase
{
    /**
     * §3.3: "3017 | `PROGRAM_NOT_DECLARED` | Error | false | The `programNumber`
     * in the request was never declared for the target bay."
     */
    #[Test]
    public function programNotDeclaredIs3017(): void
    {
        $code = OsppErrorCode::PROGRAM_NOT_DECLARED;

        self::assertSame(3017, $code->value);
        self::assertSame('PROGRAM_NOT_DECLARED', $code->errorText());
        self::assertSame(Severity::ERROR, $code->severity());
        // "A reference failure, not a value failure" — and not recoverable by
        // retrying the same message: the ordinal names nothing on that bay.
        self::assertFalse($code->isRecoverable());
    }

    /**
     * §3.3: "3018 | `TOPOLOGY_MISMATCH` | Error | true | The topology the station
     * declared in BootNotification does not match the topology recorded for it at
     * provisioning [...] `recoverable: true` records exactly that — the station is
     * out of service but reachable."
     */
    #[Test]
    public function topologyMismatchIs3018(): void
    {
        $code = OsppErrorCode::TOPOLOGY_MISMATCH;

        self::assertSame(3018, $code->value);
        self::assertSame('TOPOLOGY_MISMATCH', $code->errorText());
        self::assertSame(Severity::ERROR, $code->severity());
        self::assertTrue($code->isRecoverable());
    }

    /**
     * 3018 is in the 3xxx range, not the transport range: "it is a disagreement
     * about hardware, not a transport failure."
     */
    #[Test]
    public function topologyMismatchIsASessionAndBayCodeNotATransportOne(): void
    {
        self::assertSame('session', OsppErrorCode::TOPOLOGY_MISMATCH->category());
        self::assertSame('session', OsppErrorCode::PROGRAM_NOT_DECLARED->category());
    }

    /**
     * "3xxx is dense with no gaps, so allocation is dense and gaps are never
     * back-filled. Registry totals move 114 -> 116."
     */
    #[Test]
    public function registryTotalIs116AndThe3xxxRangeIsDense(): void
    {
        self::assertCount(116, OsppErrorCode::cases());

        $threeK = array_values(array_filter(
            array_map(fn (OsppErrorCode $c) => $c->value, OsppErrorCode::cases()),
            fn (int $v) => $v >= 3000 && $v < 4000,
        ));
        sort($threeK);

        self::assertSame(range(3000, 3018), $threeK, '3xxx must be dense from 3000 to 3018');
        self::assertCount(19, $threeK);
    }

    /**
     * The narrowing of 3015. §3.3: "this code covers a value that could never be
     * valid. It does NOT cover a well-formed identifier that simply refers to
     * nothing — those are reference failures and each identifier kind has its own
     * code (`3004` `serviceId`, `3005` `bayId`, `3006` `sessionId`, `3012`
     * `reservationId`, `3017` `programNumber`)."
     *
     * Pinned as data so the two SDKs answer the same code for the same kind.
     */
    #[Test]
    public function eachIdentifierKindHasItsOwnReferenceFailureCode(): void
    {
        $byKind = [
            'serviceId' => OsppErrorCode::INVALID_SERVICE,
            'bayId' => OsppErrorCode::BAY_NOT_FOUND,
            'sessionId' => OsppErrorCode::SESSION_NOT_FOUND,
            'reservationId' => OsppErrorCode::RESERVATION_NOT_FOUND,
            'programNumber' => OsppErrorCode::PROGRAM_NOT_DECLARED,
        ];

        self::assertSame(
            ['serviceId' => 3004, 'bayId' => 3005, 'sessionId' => 3006, 'reservationId' => 3012, 'programNumber' => 3017],
            array_map(fn (OsppErrorCode $c) => $c->value, $byKind),
        );

        // None of them is 3015: a dangling reference is not a bad value.
        foreach ($byKind as $kind => $code) {
            self::assertNotSame(OsppErrorCode::PAYLOAD_INVALID, $code, "{$kind} must not resolve to 3015");
        }
    }
}
