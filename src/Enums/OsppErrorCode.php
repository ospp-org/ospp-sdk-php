<?php

declare(strict_types=1);

namespace OneStopPay\OsppProtocol\Enums;

/**
 * Complete OSPP Error Code registry.
 * 80 error codes across 6 categories.
 */
enum OsppErrorCode: int
{
    // 1xxx - Transport Errors (15 codes)
    case TRANSPORT_GENERIC = 1000;
    case MQTT_CONNECTION_LOST = 1001;
    case MQTT_PUBLISH_FAILED = 1002;
    case TLS_HANDSHAKE_FAILED = 1003;
    case CERTIFICATE_ERROR = 1004;
    case INVALID_MESSAGE_FORMAT = 1005;
    case UNKNOWN_ACTION = 1006;
    case PROTOCOL_VERSION_MISMATCH = 1007;
    case BLE_RADIO_ERROR = 1008;
    case DNS_RESOLUTION_FAILED = 1009;
    case MESSAGE_TIMEOUT = 1010;
    case URL_UNREACHABLE = 1011;
    case MAC_VERIFICATION_FAILED = 1012;
    case MAC_MISSING = 1013;
    case MESSAGE_TOO_LARGE = 1014;

    // 2xxx - Authentication & Authorization Errors (14 codes)
    case AUTH_GENERIC = 2000;
    case STATION_NOT_REGISTERED = 2001;
    case OFFLINE_PASS_INVALID = 2002;
    case OFFLINE_PASS_EXPIRED = 2003;
    case OFFLINE_EPOCH_REVOKED = 2004;
    case OFFLINE_COUNTER_REPLAY = 2005;
    case OFFLINE_STATION_MISMATCH = 2006;
    case COMMAND_NOT_SUPPORTED = 2007;
    case ACTION_NOT_PERMITTED = 2008;
    case JWT_EXPIRED = 2009;
    case JWT_INVALID = 2010;
    case SESSION_TOKEN_EXPIRED = 2011;
    case SESSION_TOKEN_INVALID = 2012;
    case BLE_AUTH_FAILED = 2013;

    // 3xxx - Session & Bay Errors (16 codes)
    case SESSION_GENERIC = 3000;
    case BAY_BUSY = 3001;
    case BAY_NOT_READY = 3002;
    case SERVICE_UNAVAILABLE = 3003;
    case INVALID_SERVICE = 3004;
    case BAY_NOT_FOUND = 3005;
    case SESSION_NOT_FOUND = 3006;
    case SESSION_MISMATCH = 3007;
    case DURATION_INVALID = 3008;
    case HARDWARE_ACTIVATION_FAILED = 3009;
    case MAX_DURATION_EXCEEDED = 3010;
    case BAY_MAINTENANCE = 3011;
    case RESERVATION_NOT_FOUND = 3012;
    case RESERVATION_EXPIRED = 3013;
    case BAY_RESERVED = 3014;
    case PAYLOAD_INVALID = 3015;

    // 4xxx - Payment & Credit Errors (9 codes)
    case PAYMENT_GENERIC = 4000;
    case INSUFFICIENT_BALANCE = 4001;
    case OFFLINE_LIMIT_EXCEEDED = 4002;
    case OFFLINE_RATE_LIMITED = 4003;
    case OFFLINE_PER_TX_EXCEEDED = 4004;
    case PAYMENT_FAILED = 4005;
    case PAYMENT_TIMEOUT = 4006;
    case REFUND_FAILED = 4007;
    case WEBHOOK_SIGNATURE_INVALID = 4008;

    // 5xxx - Station Hardware & Software Errors (18 codes)
    case HARDWARE_GENERIC = 5000;
    case PUMP_SYSTEM = 5001;
    case WATER_SYSTEM = 5002;
    case CHEMICAL_SYSTEM = 5003;
    case ELECTRICAL_SYSTEM = 5004;
    case PAYMENT_HARDWARE = 5005;
    case HEATING_SYSTEM = 5006;
    case MECHANICAL_SYSTEM = 5007;
    case SENSOR_FAILURE = 5008;
    case EMERGENCY_STOP = 5009;
    case SOFTWARE_GENERIC = 5100;
    case FIRMWARE_ERROR = 5101;
    case CONFIGURATION_ERROR = 5102;
    case STORAGE_ERROR = 5103;
    case WATCHDOG_RESET = 5104;
    case MEMORY_ERROR = 5105;
    case CLOCK_ERROR = 5106;
    case OPERATION_IN_PROGRESS = 5107;

    // 6xxx - Server Errors (8 codes)
    case SERVER_GENERIC = 6000;
    case SERVER_INTERNAL_ERROR = 6001;
    case ACK_TIMEOUT = 6002;
    case STATION_OFFLINE = 6003;
    case VALIDATION_ERROR = 6004;
    case SESSION_ALREADY_ACTIVE = 6005;
    case RATE_LIMIT_EXCEEDED = 6006;
    case SERVICE_DEGRADED = 6007;

    public function category(): string
    {
        return match (intdiv($this->value, 1000)) {
            1 => 'transport',
            2 => 'auth',
            3 => 'session',
            4 => 'payment',
            5 => 'station',
            6 => 'server',
            default => 'unknown',
        };
    }

    public function severity(): Severity
    {
        return match ($this) {
            self::TLS_HANDSHAKE_FAILED,
            self::CERTIFICATE_ERROR,
            self::MAC_VERIFICATION_FAILED,
            self::OFFLINE_COUNTER_REPLAY,
            self::PUMP_SYSTEM,
            self::ELECTRICAL_SYSTEM,
            self::EMERGENCY_STOP,
            self::FIRMWARE_ERROR,
            self::WATCHDOG_RESET,
            self::MEMORY_ERROR,
            self::WEBHOOK_SIGNATURE_INVALID => Severity::CRITICAL,

            self::TRANSPORT_GENERIC,
            self::MQTT_CONNECTION_LOST,
            self::MQTT_PUBLISH_FAILED,
            self::INVALID_MESSAGE_FORMAT,
            self::PROTOCOL_VERSION_MISMATCH,
            self::DNS_RESOLUTION_FAILED,
            self::MAC_MISSING,
            self::MESSAGE_TOO_LARGE,
            self::AUTH_GENERIC,
            self::STATION_NOT_REGISTERED,
            self::OFFLINE_PASS_INVALID,
            self::OFFLINE_EPOCH_REVOKED,
            self::OFFLINE_STATION_MISMATCH,
            self::ACTION_NOT_PERMITTED,
            self::JWT_INVALID,
            self::BLE_AUTH_FAILED,
            self::SESSION_GENERIC,
            self::INVALID_SERVICE,
            self::BAY_NOT_FOUND,
            self::SESSION_NOT_FOUND,
            self::SESSION_MISMATCH,
            self::DURATION_INVALID,
            self::HARDWARE_ACTIVATION_FAILED,
            self::RESERVATION_NOT_FOUND,
            self::PAYLOAD_INVALID,
            self::PAYMENT_GENERIC,
            self::OFFLINE_LIMIT_EXCEEDED,
            self::OFFLINE_PER_TX_EXCEEDED,
            self::PAYMENT_FAILED,
            self::REFUND_FAILED,
            self::SOFTWARE_GENERIC,
            self::CONFIGURATION_ERROR,
            self::STORAGE_ERROR,
            self::SERVER_GENERIC,
            self::SERVER_INTERNAL_ERROR,
            self::VALIDATION_ERROR,
            self::SESSION_TOKEN_INVALID,
            self::COMMAND_NOT_SUPPORTED,
            self::SENSOR_FAILURE,
            self::PAYMENT_HARDWARE,
            self::MECHANICAL_SYSTEM,
            self::URL_UNREACHABLE => Severity::ERROR,

            self::SERVICE_DEGRADED => Severity::INFO,

            default => Severity::WARNING,
        };
    }

    public function isRecoverable(): bool
    {
        return match ($this) {
            self::TLS_HANDSHAKE_FAILED,
            self::CERTIFICATE_ERROR,
            self::INVALID_MESSAGE_FORMAT,
            self::UNKNOWN_ACTION,
            self::PROTOCOL_VERSION_MISMATCH,
            self::MAC_VERIFICATION_FAILED,
            self::MAC_MISSING,
            self::MESSAGE_TOO_LARGE,
            self::AUTH_GENERIC,
            self::STATION_NOT_REGISTERED,
            self::OFFLINE_PASS_INVALID,
            self::OFFLINE_EPOCH_REVOKED,
            self::OFFLINE_COUNTER_REPLAY,
            self::OFFLINE_STATION_MISMATCH,
            self::COMMAND_NOT_SUPPORTED,
            self::ACTION_NOT_PERMITTED,
            self::JWT_INVALID,
            self::SESSION_TOKEN_INVALID,
            self::BLE_AUTH_FAILED,
            self::INVALID_SERVICE,
            self::BAY_NOT_FOUND,
            self::SESSION_NOT_FOUND,
            self::SESSION_MISMATCH,
            self::DURATION_INVALID,
            self::HARDWARE_ACTIVATION_FAILED,
            self::MAX_DURATION_EXCEEDED,
            self::RESERVATION_NOT_FOUND,
            self::PAYLOAD_INVALID,
            self::OFFLINE_LIMIT_EXCEEDED,
            self::OFFLINE_PER_TX_EXCEEDED,
            self::WEBHOOK_SIGNATURE_INVALID,
            self::PUMP_SYSTEM,
            self::PAYMENT_HARDWARE,
            self::MECHANICAL_SYSTEM,
            self::EMERGENCY_STOP,
            self::FIRMWARE_ERROR,
            self::VALIDATION_ERROR => false,

            default => true,
        };
    }

    public function errorText(): string
    {
        return $this->name;
    }

    public function httpStatus(): int
    {
        return match ($this) {
            self::INVALID_MESSAGE_FORMAT, self::PAYLOAD_INVALID, self::VALIDATION_ERROR => 400,
            self::JWT_EXPIRED, self::JWT_INVALID, self::ACTION_NOT_PERMITTED,
            self::SESSION_TOKEN_EXPIRED, self::SESSION_TOKEN_INVALID => 401,
            self::INSUFFICIENT_BALANCE => 402,
            self::BAY_NOT_FOUND, self::SESSION_NOT_FOUND, self::RESERVATION_NOT_FOUND => 404,
            self::BAY_BUSY, self::BAY_RESERVED, self::SESSION_ALREADY_ACTIVE => 409,
            self::DURATION_INVALID, self::MAX_DURATION_EXCEEDED, self::INVALID_SERVICE => 422,
            self::RATE_LIMIT_EXCEEDED => 429,
            self::STATION_OFFLINE => 502,
            self::ACK_TIMEOUT => 504,
            default => 500,
        };
    }
}
