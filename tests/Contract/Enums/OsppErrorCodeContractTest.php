<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Enums;

use Ospp\Protocol\Enums\OsppErrorCode;
use Ospp\Protocol\Enums\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for OsppErrorCode enum.
 *
 * Pins the total count (80), category distribution, uniqueness,
 * and validates that every code returns valid metadata.
 */
final class OsppErrorCodeContractTest extends TestCase
{
    #[Test]
    public function total_code_count_is_exactly_80(): void
    {
        self::assertCount(80, OsppErrorCode::cases());
    }

    #[Test]
    public function transport_category_has_15_codes(): void
    {
        $count = $this->countByCategory('transport');
        self::assertSame(15, $count);
    }

    #[Test]
    public function auth_category_has_14_codes(): void
    {
        $count = $this->countByCategory('auth');
        self::assertSame(14, $count);
    }

    #[Test]
    public function session_category_has_16_codes(): void
    {
        $count = $this->countByCategory('session');
        self::assertSame(16, $count);
    }

    #[Test]
    public function payment_category_has_9_codes(): void
    {
        $count = $this->countByCategory('payment');
        self::assertSame(9, $count);
    }

    #[Test]
    public function station_category_has_18_codes(): void
    {
        $count = $this->countByCategory('station');
        self::assertSame(18, $count);
    }

    #[Test]
    public function server_category_has_8_codes(): void
    {
        $count = $this->countByCategory('server');
        self::assertSame(8, $count);
    }

    #[Test]
    public function category_counts_sum_to_80(): void
    {
        $sum = $this->countByCategory('transport')
            + $this->countByCategory('auth')
            + $this->countByCategory('session')
            + $this->countByCategory('payment')
            + $this->countByCategory('station')
            + $this->countByCategory('server');

        self::assertSame(80, $sum);
    }

    #[Test]
    public function all_80_codes_have_unique_integer_values(): void
    {
        $values = array_map(fn (OsppErrorCode $code) => $code->value, OsppErrorCode::cases());
        self::assertCount(80, array_unique($values));
    }

    #[Test]
    public function every_code_returns_valid_severity_httpStatus_isRecoverable_errorText(): void
    {
        foreach (OsppErrorCode::cases() as $code) {
            $severity = $code->severity();
            self::assertInstanceOf(Severity::class, $severity, "{$code->name}: severity() must return Severity");

            $httpStatus = $code->httpStatus();
            self::assertIsInt($httpStatus, "{$code->name}: httpStatus() must return int");

            $isRecoverable = $code->isRecoverable();
            self::assertIsBool($isRecoverable, "{$code->name}: isRecoverable() must return bool");

            $errorText = $code->errorText();
            self::assertIsString($errorText, "{$code->name}: errorText() must return string");
            self::assertNotEmpty($errorText, "{$code->name}: errorText() must not be empty");
        }
    }

    #[Test]
    public function severity_counts_sum_to_80(): void
    {
        $counts = [];
        foreach (Severity::cases() as $sev) {
            $counts[$sev->value] = 0;
        }

        foreach (OsppErrorCode::cases() as $code) {
            $counts[$code->severity()->value]++;
        }

        self::assertSame(80, array_sum($counts));
    }

    #[Test]
    public function recoverable_counts_sum_to_80(): void
    {
        $recoverable = 0;
        $nonRecoverable = 0;

        foreach (OsppErrorCode::cases() as $code) {
            if ($code->isRecoverable()) {
                $recoverable++;
            } else {
                $nonRecoverable++;
            }
        }

        self::assertSame(80, $recoverable + $nonRecoverable);
    }

    #[Test]
    public function reservation_error_codes_exist_in_OsppErrorCode(): void
    {
        // BAY_RESERVED = 3014, BAY_BUSY = 3001, BAY_NOT_READY = 3002, BAY_MAINTENANCE = 3011
        self::assertSame(OsppErrorCode::BAY_RESERVED, OsppErrorCode::from(3014));
        self::assertSame(OsppErrorCode::BAY_BUSY, OsppErrorCode::from(3001));
        self::assertSame(OsppErrorCode::BAY_NOT_READY, OsppErrorCode::from(3002));
        self::assertSame(OsppErrorCode::BAY_MAINTENANCE, OsppErrorCode::from(3011));
    }

    #[Test]
    public function httpStatus_returns_valid_http_codes_for_all(): void
    {
        foreach (OsppErrorCode::cases() as $code) {
            $status = $code->httpStatus();
            self::assertGreaterThanOrEqual(100, $status, "{$code->name}: HTTP status {$status} is below 100");
            self::assertLessThanOrEqual(599, $status, "{$code->name}: HTTP status {$status} is above 599");
        }
    }

    private function countByCategory(string $category): int
    {
        return count(array_filter(
            OsppErrorCode::cases(),
            fn (OsppErrorCode $code) => $code->category() === $category,
        ));
    }
}
