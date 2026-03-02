<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\OsppErrorCode;
use Ospp\Protocol\Enums\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OsppErrorCodeTest extends TestCase
{
    #[Test]
    public function it_has_exactly_102_cases(): void
    {
        self::assertCount(102, OsppErrorCode::cases());
    }

    // =========================================================================
    // category()
    // =========================================================================

    #[Test]
    public function transport_errors_have_transport_category(): void
    {
        $transportCodes = [
            OsppErrorCode::TRANSPORT_GENERIC,
            OsppErrorCode::MQTT_CONNECTION_LOST,
            OsppErrorCode::MQTT_PUBLISH_FAILED,
            OsppErrorCode::TLS_HANDSHAKE_FAILED,
            OsppErrorCode::CERTIFICATE_ERROR,
            OsppErrorCode::INVALID_MESSAGE_FORMAT,
            OsppErrorCode::UNKNOWN_ACTION,
            OsppErrorCode::PROTOCOL_VERSION_MISMATCH,
            OsppErrorCode::BLE_RADIO_ERROR,
            OsppErrorCode::DNS_RESOLUTION_FAILED,
            OsppErrorCode::MESSAGE_TIMEOUT,
            OsppErrorCode::URL_UNREACHABLE,
            OsppErrorCode::MAC_VERIFICATION_FAILED,
            OsppErrorCode::MAC_MISSING,
            OsppErrorCode::MESSAGE_TOO_LARGE,
        ];

        foreach ($transportCodes as $code) {
            self::assertSame('transport', $code->category(), "{$code->name} should be transport");
        }
    }

    #[Test]
    public function auth_errors_have_auth_category(): void
    {
        $authCodes = [
            OsppErrorCode::AUTH_GENERIC,
            OsppErrorCode::STATION_NOT_REGISTERED,
            OsppErrorCode::OFFLINE_PASS_INVALID,
            OsppErrorCode::OFFLINE_PASS_EXPIRED,
            OsppErrorCode::OFFLINE_EPOCH_REVOKED,
            OsppErrorCode::OFFLINE_COUNTER_REPLAY,
            OsppErrorCode::OFFLINE_STATION_MISMATCH,
            OsppErrorCode::COMMAND_NOT_SUPPORTED,
            OsppErrorCode::ACTION_NOT_PERMITTED,
            OsppErrorCode::JWT_EXPIRED,
            OsppErrorCode::JWT_INVALID,
            OsppErrorCode::SESSION_TOKEN_EXPIRED,
            OsppErrorCode::SESSION_TOKEN_INVALID,
            OsppErrorCode::BLE_AUTH_FAILED,
        ];

        foreach ($authCodes as $code) {
            self::assertSame('auth', $code->category(), "{$code->name} should be auth");
        }
    }

    #[Test]
    public function session_errors_have_session_category(): void
    {
        $sessionCodes = [
            OsppErrorCode::SESSION_GENERIC,
            OsppErrorCode::BAY_BUSY,
            OsppErrorCode::BAY_NOT_READY,
            OsppErrorCode::SERVICE_UNAVAILABLE,
            OsppErrorCode::INVALID_SERVICE,
            OsppErrorCode::BAY_NOT_FOUND,
            OsppErrorCode::SESSION_NOT_FOUND,
            OsppErrorCode::SESSION_MISMATCH,
            OsppErrorCode::DURATION_INVALID,
            OsppErrorCode::HARDWARE_ACTIVATION_FAILED,
            OsppErrorCode::MAX_DURATION_EXCEEDED,
            OsppErrorCode::BAY_MAINTENANCE,
            OsppErrorCode::RESERVATION_NOT_FOUND,
            OsppErrorCode::RESERVATION_EXPIRED,
            OsppErrorCode::BAY_RESERVED,
            OsppErrorCode::PAYLOAD_INVALID,
            OsppErrorCode::ACTIVE_SESSIONS_PRESENT,
        ];

        foreach ($sessionCodes as $code) {
            self::assertSame('session', $code->category(), "{$code->name} should be session");
        }
    }

    #[Test]
    public function payment_errors_have_payment_category(): void
    {
        $paymentCodes = [
            OsppErrorCode::PAYMENT_GENERIC,
            OsppErrorCode::INSUFFICIENT_BALANCE,
            OsppErrorCode::OFFLINE_LIMIT_EXCEEDED,
            OsppErrorCode::OFFLINE_RATE_LIMITED,
            OsppErrorCode::OFFLINE_PER_TX_EXCEEDED,
            OsppErrorCode::PAYMENT_FAILED,
            OsppErrorCode::PAYMENT_TIMEOUT,
            OsppErrorCode::REFUND_FAILED,
            OsppErrorCode::WEBHOOK_SIGNATURE_INVALID,
            OsppErrorCode::CSR_INVALID,
            OsppErrorCode::CERTIFICATE_CHAIN_INVALID,
            OsppErrorCode::CERTIFICATE_TYPE_MISMATCH,
            OsppErrorCode::RENEWAL_DENIED,
            OsppErrorCode::KEYPAIR_GENERATION_FAILED,
        ];

        foreach ($paymentCodes as $code) {
            self::assertSame('payment', $code->category(), "{$code->name} should be payment");
        }
    }

    #[Test]
    public function station_errors_have_station_category(): void
    {
        $stationCodes = [
            OsppErrorCode::HARDWARE_GENERIC,
            OsppErrorCode::PUMP_SYSTEM,
            OsppErrorCode::FLUID_SYSTEM,
            OsppErrorCode::CONSUMABLE_SYSTEM,
            OsppErrorCode::ELECTRICAL_SYSTEM,
            OsppErrorCode::PAYMENT_HARDWARE,
            OsppErrorCode::HEATING_SYSTEM,
            OsppErrorCode::MECHANICAL_SYSTEM,
            OsppErrorCode::SENSOR_FAILURE,
            OsppErrorCode::EMERGENCY_STOP,
            OsppErrorCode::DOWNLOAD_FAILED,
            OsppErrorCode::CHECKSUM_MISMATCH,
            OsppErrorCode::VERSION_ALREADY_INSTALLED,
            OsppErrorCode::INSUFFICIENT_STORAGE,
            OsppErrorCode::INSTALLATION_FAILED,
            OsppErrorCode::UPLOAD_FAILED,
            OsppErrorCode::INVALID_TIME_WINDOW,
            OsppErrorCode::NO_DIAGNOSTICS_AVAILABLE,
            OsppErrorCode::INVALID_CATALOG,
            OsppErrorCode::UNSUPPORTED_SERVICE,
            OsppErrorCode::CATALOG_TOO_LARGE,
            OsppErrorCode::SOFTWARE_GENERIC,
            OsppErrorCode::FIRMWARE_ERROR,
            OsppErrorCode::CONFIGURATION_ERROR,
            OsppErrorCode::STORAGE_ERROR,
            OsppErrorCode::WATCHDOG_RESET,
            OsppErrorCode::MEMORY_ERROR,
            OsppErrorCode::CLOCK_ERROR,
            OsppErrorCode::OPERATION_IN_PROGRESS,
            OsppErrorCode::CONFIGURATION_KEY_READONLY,
            OsppErrorCode::INVALID_CONFIGURATION_VALUE,
            OsppErrorCode::RESET_FAILED,
            OsppErrorCode::BUFFER_FULL,
            OsppErrorCode::FIRMWARE_SIGNATURE_INVALID,
        ];

        foreach ($stationCodes as $code) {
            self::assertSame('station', $code->category(), "{$code->name} should be station");
        }
    }

    #[Test]
    public function server_errors_have_server_category(): void
    {
        $serverCodes = [
            OsppErrorCode::SERVER_GENERIC,
            OsppErrorCode::SERVER_INTERNAL_ERROR,
            OsppErrorCode::ACK_TIMEOUT,
            OsppErrorCode::STATION_OFFLINE,
            OsppErrorCode::VALIDATION_ERROR,
            OsppErrorCode::SESSION_ALREADY_ACTIVE,
            OsppErrorCode::RATE_LIMIT_EXCEEDED,
            OsppErrorCode::SERVICE_DEGRADED,
        ];

        foreach ($serverCodes as $code) {
            self::assertSame('server', $code->category(), "{$code->name} should be server");
        }
    }

    #[Test]
    public function category_covers_all_cases(): void
    {
        $validCategories = ['transport', 'auth', 'session', 'payment', 'station', 'server'];

        foreach (OsppErrorCode::cases() as $code) {
            self::assertContains(
                $code->category(),
                $validCategories,
                "{$code->name} has unexpected category '{$code->category()}'",
            );
        }
    }

    // =========================================================================
    // severity()
    // =========================================================================

    #[Test]
    public function severity_returns_critical_for_critical_codes(): void
    {
        $criticalCodes = [
            OsppErrorCode::TLS_HANDSHAKE_FAILED,
            OsppErrorCode::CERTIFICATE_ERROR,
            OsppErrorCode::MAC_VERIFICATION_FAILED,
            OsppErrorCode::OFFLINE_COUNTER_REPLAY,
            OsppErrorCode::PUMP_SYSTEM,
            OsppErrorCode::ELECTRICAL_SYSTEM,
            OsppErrorCode::EMERGENCY_STOP,
            OsppErrorCode::FIRMWARE_ERROR,
            OsppErrorCode::WATCHDOG_RESET,
            OsppErrorCode::MEMORY_ERROR,
            OsppErrorCode::WEBHOOK_SIGNATURE_INVALID,
            OsppErrorCode::KEYPAIR_GENERATION_FAILED,
            OsppErrorCode::INSTALLATION_FAILED,
            OsppErrorCode::RESET_FAILED,
            OsppErrorCode::BUFFER_FULL,
            OsppErrorCode::FIRMWARE_SIGNATURE_INVALID,
        ];

        foreach ($criticalCodes as $code) {
            self::assertSame(
                Severity::CRITICAL,
                $code->severity(),
                "{$code->name} should have CRITICAL severity",
            );
        }
    }

    #[Test]
    public function severity_returns_error_for_error_codes(): void
    {
        $errorCodes = [
            OsppErrorCode::TRANSPORT_GENERIC,
            OsppErrorCode::MQTT_CONNECTION_LOST,
            OsppErrorCode::MQTT_PUBLISH_FAILED,
            OsppErrorCode::INVALID_MESSAGE_FORMAT,
            OsppErrorCode::PROTOCOL_VERSION_MISMATCH,
            OsppErrorCode::DNS_RESOLUTION_FAILED,
            OsppErrorCode::MAC_MISSING,
            OsppErrorCode::MESSAGE_TOO_LARGE,
            OsppErrorCode::AUTH_GENERIC,
            OsppErrorCode::STATION_NOT_REGISTERED,
            OsppErrorCode::OFFLINE_PASS_INVALID,
            OsppErrorCode::OFFLINE_EPOCH_REVOKED,
            OsppErrorCode::OFFLINE_STATION_MISMATCH,
            OsppErrorCode::ACTION_NOT_PERMITTED,
            OsppErrorCode::JWT_INVALID,
            OsppErrorCode::BLE_AUTH_FAILED,
            OsppErrorCode::SESSION_GENERIC,
            OsppErrorCode::INVALID_SERVICE,
            OsppErrorCode::BAY_NOT_FOUND,
            OsppErrorCode::SESSION_NOT_FOUND,
            OsppErrorCode::SESSION_MISMATCH,
            OsppErrorCode::DURATION_INVALID,
            OsppErrorCode::HARDWARE_ACTIVATION_FAILED,
            OsppErrorCode::RESERVATION_NOT_FOUND,
            OsppErrorCode::PAYLOAD_INVALID,
            OsppErrorCode::PAYMENT_GENERIC,
            OsppErrorCode::OFFLINE_LIMIT_EXCEEDED,
            OsppErrorCode::OFFLINE_PER_TX_EXCEEDED,
            OsppErrorCode::PAYMENT_FAILED,
            OsppErrorCode::REFUND_FAILED,
            OsppErrorCode::SOFTWARE_GENERIC,
            OsppErrorCode::CONFIGURATION_ERROR,
            OsppErrorCode::STORAGE_ERROR,
            OsppErrorCode::SERVER_GENERIC,
            OsppErrorCode::SERVER_INTERNAL_ERROR,
            OsppErrorCode::VALIDATION_ERROR,
            OsppErrorCode::SESSION_TOKEN_INVALID,
            OsppErrorCode::URL_UNREACHABLE,
            OsppErrorCode::CSR_INVALID,
            OsppErrorCode::CERTIFICATE_CHAIN_INVALID,
            OsppErrorCode::RENEWAL_DENIED,
            OsppErrorCode::DOWNLOAD_FAILED,
            OsppErrorCode::CHECKSUM_MISMATCH,
            OsppErrorCode::INSUFFICIENT_STORAGE,
            OsppErrorCode::UPLOAD_FAILED,
            OsppErrorCode::INVALID_CATALOG,
            OsppErrorCode::CATALOG_TOO_LARGE,
            OsppErrorCode::CONFIGURATION_KEY_READONLY,
            OsppErrorCode::INVALID_CONFIGURATION_VALUE,
        ];

        foreach ($errorCodes as $code) {
            self::assertSame(
                Severity::ERROR,
                $code->severity(),
                "{$code->name} should have ERROR severity",
            );
        }
    }

    #[Test]
    public function severity_returns_info_for_service_degraded(): void
    {
        self::assertSame(Severity::INFO, OsppErrorCode::SERVICE_DEGRADED->severity());
    }

    #[Test]
    public function severity_returns_warning_for_remaining_codes(): void
    {
        // Codes that fall through to the default => Severity::WARNING
        $warningCodes = [
            OsppErrorCode::UNKNOWN_ACTION,
            OsppErrorCode::BLE_RADIO_ERROR,
            OsppErrorCode::MESSAGE_TIMEOUT,
            OsppErrorCode::OFFLINE_PASS_EXPIRED,
            OsppErrorCode::JWT_EXPIRED,
            OsppErrorCode::SESSION_TOKEN_EXPIRED,
            OsppErrorCode::BAY_BUSY,
            OsppErrorCode::BAY_NOT_READY,
            OsppErrorCode::SERVICE_UNAVAILABLE,
            OsppErrorCode::MAX_DURATION_EXCEEDED,
            OsppErrorCode::BAY_MAINTENANCE,
            OsppErrorCode::RESERVATION_EXPIRED,
            OsppErrorCode::BAY_RESERVED,
            OsppErrorCode::INSUFFICIENT_BALANCE,
            OsppErrorCode::OFFLINE_RATE_LIMITED,
            OsppErrorCode::PAYMENT_TIMEOUT,
            OsppErrorCode::HARDWARE_GENERIC,
            OsppErrorCode::FLUID_SYSTEM,
            OsppErrorCode::CONSUMABLE_SYSTEM,
            OsppErrorCode::HEATING_SYSTEM,
            OsppErrorCode::COMMAND_NOT_SUPPORTED,
            OsppErrorCode::SENSOR_FAILURE,
            OsppErrorCode::PAYMENT_HARDWARE,
            OsppErrorCode::MECHANICAL_SYSTEM,
            OsppErrorCode::CERTIFICATE_TYPE_MISMATCH,
            OsppErrorCode::ACTIVE_SESSIONS_PRESENT,
            OsppErrorCode::VERSION_ALREADY_INSTALLED,
            OsppErrorCode::INVALID_TIME_WINDOW,
            OsppErrorCode::NO_DIAGNOSTICS_AVAILABLE,
            OsppErrorCode::UNSUPPORTED_SERVICE,
            OsppErrorCode::CLOCK_ERROR,
            OsppErrorCode::OPERATION_IN_PROGRESS,
            OsppErrorCode::ACK_TIMEOUT,
            OsppErrorCode::STATION_OFFLINE,
            OsppErrorCode::SESSION_ALREADY_ACTIVE,
            OsppErrorCode::RATE_LIMIT_EXCEEDED,
        ];

        foreach ($warningCodes as $code) {
            self::assertSame(
                Severity::WARNING,
                $code->severity(),
                "{$code->name} should have WARNING severity",
            );
        }
    }

    #[Test]
    public function severity_returns_a_severity_enum_for_all_cases(): void
    {
        foreach (OsppErrorCode::cases() as $code) {
            self::assertInstanceOf(Severity::class, $code->severity());
        }
    }

    // =========================================================================
    // isRecoverable()
    // =========================================================================

    #[Test]
    public function non_recoverable_codes_return_false(): void
    {
        $nonRecoverable = [
            OsppErrorCode::TLS_HANDSHAKE_FAILED,
            OsppErrorCode::CERTIFICATE_ERROR,
            OsppErrorCode::INVALID_MESSAGE_FORMAT,
            OsppErrorCode::UNKNOWN_ACTION,
            OsppErrorCode::PROTOCOL_VERSION_MISMATCH,
            OsppErrorCode::MAC_VERIFICATION_FAILED,
            OsppErrorCode::MAC_MISSING,
            OsppErrorCode::MESSAGE_TOO_LARGE,
            OsppErrorCode::AUTH_GENERIC,
            OsppErrorCode::STATION_NOT_REGISTERED,
            OsppErrorCode::OFFLINE_PASS_INVALID,
            OsppErrorCode::OFFLINE_EPOCH_REVOKED,
            OsppErrorCode::OFFLINE_COUNTER_REPLAY,
            OsppErrorCode::OFFLINE_STATION_MISMATCH,
            OsppErrorCode::COMMAND_NOT_SUPPORTED,
            OsppErrorCode::ACTION_NOT_PERMITTED,
            OsppErrorCode::JWT_INVALID,
            OsppErrorCode::SESSION_TOKEN_INVALID,
            OsppErrorCode::BLE_AUTH_FAILED,
            OsppErrorCode::INVALID_SERVICE,
            OsppErrorCode::BAY_NOT_FOUND,
            OsppErrorCode::SESSION_NOT_FOUND,
            OsppErrorCode::SESSION_MISMATCH,
            OsppErrorCode::DURATION_INVALID,
            OsppErrorCode::HARDWARE_ACTIVATION_FAILED,
            OsppErrorCode::MAX_DURATION_EXCEEDED,
            OsppErrorCode::RESERVATION_NOT_FOUND,
            OsppErrorCode::PAYLOAD_INVALID,
            OsppErrorCode::OFFLINE_LIMIT_EXCEEDED,
            OsppErrorCode::OFFLINE_PER_TX_EXCEEDED,
            OsppErrorCode::WEBHOOK_SIGNATURE_INVALID,
            OsppErrorCode::PUMP_SYSTEM,
            OsppErrorCode::PAYMENT_HARDWARE,
            OsppErrorCode::MECHANICAL_SYSTEM,
            OsppErrorCode::EMERGENCY_STOP,
            OsppErrorCode::FIRMWARE_ERROR,
            OsppErrorCode::VALIDATION_ERROR,
            OsppErrorCode::RENEWAL_DENIED,
            OsppErrorCode::KEYPAIR_GENERATION_FAILED,
            OsppErrorCode::CHECKSUM_MISMATCH,
            OsppErrorCode::VERSION_ALREADY_INSTALLED,
            OsppErrorCode::INSUFFICIENT_STORAGE,
            OsppErrorCode::INSTALLATION_FAILED,
            OsppErrorCode::INVALID_TIME_WINDOW,
            OsppErrorCode::NO_DIAGNOSTICS_AVAILABLE,
            OsppErrorCode::INVALID_CATALOG,
            OsppErrorCode::UNSUPPORTED_SERVICE,
            OsppErrorCode::CATALOG_TOO_LARGE,
            OsppErrorCode::CONFIGURATION_KEY_READONLY,
            OsppErrorCode::INVALID_CONFIGURATION_VALUE,
            OsppErrorCode::RESET_FAILED,
            OsppErrorCode::FIRMWARE_SIGNATURE_INVALID,
        ];

        foreach ($nonRecoverable as $code) {
            self::assertFalse(
                $code->isRecoverable(),
                "{$code->name} should NOT be recoverable",
            );
        }
    }

    #[Test]
    public function recoverable_codes_return_true(): void
    {
        $recoverable = [
            OsppErrorCode::TRANSPORT_GENERIC,
            OsppErrorCode::MQTT_CONNECTION_LOST,
            OsppErrorCode::MQTT_PUBLISH_FAILED,
            OsppErrorCode::BLE_RADIO_ERROR,
            OsppErrorCode::DNS_RESOLUTION_FAILED,
            OsppErrorCode::MESSAGE_TIMEOUT,
            OsppErrorCode::URL_UNREACHABLE,
            OsppErrorCode::OFFLINE_PASS_EXPIRED,
            OsppErrorCode::JWT_EXPIRED,
            OsppErrorCode::SESSION_TOKEN_EXPIRED,
            OsppErrorCode::SESSION_GENERIC,
            OsppErrorCode::BAY_BUSY,
            OsppErrorCode::BAY_NOT_READY,
            OsppErrorCode::SERVICE_UNAVAILABLE,
            OsppErrorCode::BAY_MAINTENANCE,
            OsppErrorCode::RESERVATION_EXPIRED,
            OsppErrorCode::BAY_RESERVED,
            OsppErrorCode::PAYMENT_GENERIC,
            OsppErrorCode::INSUFFICIENT_BALANCE,
            OsppErrorCode::OFFLINE_RATE_LIMITED,
            OsppErrorCode::PAYMENT_FAILED,
            OsppErrorCode::PAYMENT_TIMEOUT,
            OsppErrorCode::REFUND_FAILED,
            OsppErrorCode::HARDWARE_GENERIC,
            OsppErrorCode::FLUID_SYSTEM,
            OsppErrorCode::CONSUMABLE_SYSTEM,
            OsppErrorCode::ELECTRICAL_SYSTEM,
            OsppErrorCode::HEATING_SYSTEM,
            OsppErrorCode::SENSOR_FAILURE,
            OsppErrorCode::CSR_INVALID,
            OsppErrorCode::CERTIFICATE_CHAIN_INVALID,
            OsppErrorCode::CERTIFICATE_TYPE_MISMATCH,
            OsppErrorCode::ACTIVE_SESSIONS_PRESENT,
            OsppErrorCode::DOWNLOAD_FAILED,
            OsppErrorCode::UPLOAD_FAILED,
            OsppErrorCode::BUFFER_FULL,
            OsppErrorCode::SOFTWARE_GENERIC,
            OsppErrorCode::CONFIGURATION_ERROR,
            OsppErrorCode::STORAGE_ERROR,
            OsppErrorCode::WATCHDOG_RESET,
            OsppErrorCode::MEMORY_ERROR,
            OsppErrorCode::CLOCK_ERROR,
            OsppErrorCode::OPERATION_IN_PROGRESS,
            OsppErrorCode::SERVER_GENERIC,
            OsppErrorCode::SERVER_INTERNAL_ERROR,
            OsppErrorCode::ACK_TIMEOUT,
            OsppErrorCode::STATION_OFFLINE,
            OsppErrorCode::SESSION_ALREADY_ACTIVE,
            OsppErrorCode::RATE_LIMIT_EXCEEDED,
            OsppErrorCode::SERVICE_DEGRADED,
        ];

        foreach ($recoverable as $code) {
            self::assertTrue(
                $code->isRecoverable(),
                "{$code->name} should be recoverable",
            );
        }
    }

    #[Test]
    public function is_recoverable_returns_a_boolean_for_all_cases(): void
    {
        foreach (OsppErrorCode::cases() as $code) {
            self::assertIsBool($code->isRecoverable());
        }
    }

    // =========================================================================
    // errorText()
    // =========================================================================

    #[Test]
    public function error_text_returns_the_enum_name(): void
    {
        self::assertSame('TRANSPORT_GENERIC', OsppErrorCode::TRANSPORT_GENERIC->errorText());
        self::assertSame('MAC_VERIFICATION_FAILED', OsppErrorCode::MAC_VERIFICATION_FAILED->errorText());
        self::assertSame('INSUFFICIENT_BALANCE', OsppErrorCode::INSUFFICIENT_BALANCE->errorText());
        self::assertSame('EMERGENCY_STOP', OsppErrorCode::EMERGENCY_STOP->errorText());
        self::assertSame('SERVICE_DEGRADED', OsppErrorCode::SERVICE_DEGRADED->errorText());
    }

    #[Test]
    public function error_text_matches_name_property_for_all_cases(): void
    {
        foreach (OsppErrorCode::cases() as $code) {
            self::assertSame($code->name, $code->errorText());
        }
    }

    // =========================================================================
    // httpStatus()
    // =========================================================================

    #[Test]
    public function http_status_400_for_bad_request_codes(): void
    {
        self::assertSame(400, OsppErrorCode::INVALID_MESSAGE_FORMAT->httpStatus());
        self::assertSame(400, OsppErrorCode::PAYLOAD_INVALID->httpStatus());
        self::assertSame(400, OsppErrorCode::VALIDATION_ERROR->httpStatus());
    }

    #[Test]
    public function http_status_401_for_auth_codes(): void
    {
        self::assertSame(401, OsppErrorCode::JWT_EXPIRED->httpStatus());
        self::assertSame(401, OsppErrorCode::JWT_INVALID->httpStatus());
        self::assertSame(401, OsppErrorCode::ACTION_NOT_PERMITTED->httpStatus());
        self::assertSame(401, OsppErrorCode::SESSION_TOKEN_EXPIRED->httpStatus());
        self::assertSame(401, OsppErrorCode::SESSION_TOKEN_INVALID->httpStatus());
    }

    #[Test]
    public function http_status_402_for_insufficient_balance(): void
    {
        self::assertSame(402, OsppErrorCode::INSUFFICIENT_BALANCE->httpStatus());
    }

    #[Test]
    public function http_status_404_for_not_found_codes(): void
    {
        self::assertSame(404, OsppErrorCode::BAY_NOT_FOUND->httpStatus());
        self::assertSame(404, OsppErrorCode::SESSION_NOT_FOUND->httpStatus());
        self::assertSame(404, OsppErrorCode::RESERVATION_NOT_FOUND->httpStatus());
    }

    #[Test]
    public function http_status_409_for_conflict_codes(): void
    {
        self::assertSame(409, OsppErrorCode::BAY_BUSY->httpStatus());
        self::assertSame(409, OsppErrorCode::BAY_RESERVED->httpStatus());
        self::assertSame(409, OsppErrorCode::SESSION_ALREADY_ACTIVE->httpStatus());
    }

    #[Test]
    public function http_status_422_for_validation_codes(): void
    {
        self::assertSame(422, OsppErrorCode::DURATION_INVALID->httpStatus());
        self::assertSame(422, OsppErrorCode::MAX_DURATION_EXCEEDED->httpStatus());
        self::assertSame(422, OsppErrorCode::INVALID_SERVICE->httpStatus());
    }

    #[Test]
    public function http_status_429_for_rate_limit(): void
    {
        self::assertSame(429, OsppErrorCode::RATE_LIMIT_EXCEEDED->httpStatus());
    }

    #[Test]
    public function http_status_502_for_station_offline(): void
    {
        self::assertSame(502, OsppErrorCode::STATION_OFFLINE->httpStatus());
    }

    #[Test]
    public function http_status_504_for_ack_timeout(): void
    {
        self::assertSame(504, OsppErrorCode::ACK_TIMEOUT->httpStatus());
    }

    #[Test]
    public function http_status_500_is_default_for_unmapped_codes(): void
    {
        // Some codes that should fall through to default 500
        self::assertSame(500, OsppErrorCode::TRANSPORT_GENERIC->httpStatus());
        self::assertSame(500, OsppErrorCode::MQTT_CONNECTION_LOST->httpStatus());
        self::assertSame(500, OsppErrorCode::TLS_HANDSHAKE_FAILED->httpStatus());
        self::assertSame(500, OsppErrorCode::MAC_VERIFICATION_FAILED->httpStatus());
        self::assertSame(500, OsppErrorCode::SERVER_INTERNAL_ERROR->httpStatus());
        self::assertSame(500, OsppErrorCode::EMERGENCY_STOP->httpStatus());
        self::assertSame(500, OsppErrorCode::FIRMWARE_ERROR->httpStatus());
    }

    #[Test]
    public function http_status_returns_valid_http_code_for_all_cases(): void
    {
        $validHttpCodes = [400, 401, 402, 404, 409, 422, 429, 500, 502, 504];

        foreach (OsppErrorCode::cases() as $code) {
            self::assertContains(
                $code->httpStatus(),
                $validHttpCodes,
                "{$code->name} has unexpected httpStatus {$code->httpStatus()}",
            );
        }
    }

    // =========================================================================
    // Integer backing values
    // =========================================================================

    #[Test]
    public function transport_codes_are_in_1xxx_range(): void
    {
        self::assertSame(1000, OsppErrorCode::TRANSPORT_GENERIC->value);
        self::assertSame(1014, OsppErrorCode::MESSAGE_TOO_LARGE->value);
    }

    #[Test]
    public function auth_codes_are_in_2xxx_range(): void
    {
        self::assertSame(2000, OsppErrorCode::AUTH_GENERIC->value);
        self::assertSame(2013, OsppErrorCode::BLE_AUTH_FAILED->value);
    }

    #[Test]
    public function session_codes_are_in_3xxx_range(): void
    {
        self::assertSame(3000, OsppErrorCode::SESSION_GENERIC->value);
        self::assertSame(3016, OsppErrorCode::ACTIVE_SESSIONS_PRESENT->value);
    }

    #[Test]
    public function payment_codes_are_in_4xxx_range(): void
    {
        self::assertSame(4000, OsppErrorCode::PAYMENT_GENERIC->value);
        self::assertSame(4014, OsppErrorCode::KEYPAIR_GENERATION_FAILED->value);
    }

    #[Test]
    public function station_hardware_codes_are_in_5xxx_range(): void
    {
        self::assertSame(5000, OsppErrorCode::HARDWARE_GENERIC->value);
        self::assertSame(5009, OsppErrorCode::EMERGENCY_STOP->value);
        self::assertSame(5100, OsppErrorCode::SOFTWARE_GENERIC->value);
        self::assertSame(5112, OsppErrorCode::FIRMWARE_SIGNATURE_INVALID->value);
    }

    #[Test]
    public function server_codes_are_in_6xxx_range(): void
    {
        self::assertSame(6000, OsppErrorCode::SERVER_GENERIC->value);
        self::assertSame(6007, OsppErrorCode::SERVICE_DEGRADED->value);
    }

    // =========================================================================
    // from / tryFrom
    // =========================================================================

    #[Test]
    public function it_can_be_created_from_valid_integer(): void
    {
        self::assertSame(OsppErrorCode::TRANSPORT_GENERIC, OsppErrorCode::from(1000));
        self::assertSame(OsppErrorCode::INSUFFICIENT_BALANCE, OsppErrorCode::from(4001));
        self::assertSame(OsppErrorCode::SERVER_INTERNAL_ERROR, OsppErrorCode::from(6001));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(OsppErrorCode::tryFrom(0));
        self::assertNull(OsppErrorCode::tryFrom(999));
        self::assertNull(OsppErrorCode::tryFrom(7000));
        self::assertNull(OsppErrorCode::tryFrom(-1));
    }

    #[Test]
    public function it_throws_for_invalid_integer_with_from(): void
    {
        $this->expectException(\ValueError::class);
        OsppErrorCode::from(9999);
    }

    // =========================================================================
    // Category counts
    // =========================================================================

    #[Test]
    public function transport_category_has_fifteen_codes(): void
    {
        $count = count(array_filter(
            OsppErrorCode::cases(),
            static fn (OsppErrorCode $c): bool => $c->category() === 'transport',
        ));
        self::assertSame(15, $count);
    }

    #[Test]
    public function auth_category_has_fourteen_codes(): void
    {
        $count = count(array_filter(
            OsppErrorCode::cases(),
            static fn (OsppErrorCode $c): bool => $c->category() === 'auth',
        ));
        self::assertSame(14, $count);
    }

    #[Test]
    public function session_category_has_seventeen_codes(): void
    {
        $count = count(array_filter(
            OsppErrorCode::cases(),
            static fn (OsppErrorCode $c): bool => $c->category() === 'session',
        ));
        self::assertSame(17, $count);
    }

    #[Test]
    public function payment_category_has_fourteen_codes(): void
    {
        $count = count(array_filter(
            OsppErrorCode::cases(),
            static fn (OsppErrorCode $c): bool => $c->category() === 'payment',
        ));
        self::assertSame(14, $count);
    }

    #[Test]
    public function station_category_has_thirty_four_codes(): void
    {
        $count = count(array_filter(
            OsppErrorCode::cases(),
            static fn (OsppErrorCode $c): bool => $c->category() === 'station',
        ));
        self::assertSame(34, $count);
    }

    #[Test]
    public function server_category_has_eight_codes(): void
    {
        $count = count(array_filter(
            OsppErrorCode::cases(),
            static fn (OsppErrorCode $c): bool => $c->category() === 'server',
        ));
        self::assertSame(8, $count);
    }
}
