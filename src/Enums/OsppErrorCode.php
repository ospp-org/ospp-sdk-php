<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

/**
 * Complete OSPP Error Code registry.
 *
 * 118 standard error codes across 6 categories (spec 07-errors.md §1.1). The
 * count moved 114 → 116 with 3017 PROGRAM_NOT_DECLARED and 3018
 * TOPOLOGY_MISMATCH, then → 118 with 3019 SERVICE_NOT_BOUND and 6008; the
 * total is now asserted against the spec by scripts/check-error-registry.sh
 * rather than restated here. The registry states its own total in five places and they
 * move together. Fully spec-aligned with sdk-ts.
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

    // 2xxx - Authentication & Authorization Errors (20 codes — v0.5.2 added 2014-2017, v0.6.2 added 2018, v0.8.0 added 2019)
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
    // spec v0.4.2 07-errors.md §3.2 additions — reconciliation gate hard-rejects
    case OFFLINE_PASS_REVOKED = 2014;
    case OFFLINE_ORG_MISMATCH = 2015;
    case OFFLINE_USER_MISMATCH = 2016;
    case OFFLINE_RECEIPT_MISMATCH = 2017;
    // spec v0.6.2 07-errors.md §3.2 — BLE Partial-A ServerSignedAuth anti-replay nonce check
    case SERVER_AUTH_NONCE_MISMATCH = 2018;
    // spec v0.8.0 07-errors.md §3.2 — provisioning token unusable (expired / superseded / revoked)
    case PROVISIONING_TOKEN_INVALID = 2019;

    // 3xxx - Session & Bay Errors (20 codes — v0.11.0 added 3017 PROGRAM_NOT_DECLARED
    // and 3018 TOPOLOGY_MISMATCH; v0.11.1 added 3019 SERVICE_NOT_BOUND; the range is
    // dense and gaps are never back-filled)
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
    case ACTIVE_SESSIONS_PRESENT = 3016;
    case PROGRAM_NOT_DECLARED = 3017;
    case TOPOLOGY_MISMATCH = 3018;
    /**
     * The server holds no service→program binding and cannot form a conforming
     * StartService. The mirror of 3017, which is the STATION refusing an ordinal
     * it was sent. spec v0.11.1 07-errors.md §3.3.
     *
     * Server-originated toward the requesting client and MUST NOT be transmitted
     * to a station.
     */
    case SERVICE_NOT_BOUND = 3019;

    // 4xxx - Payment & Credit Errors (20 codes — v0.8.0 added 4015-4017, v0.8.3 added 4018-4019, v0.8.4 added 4020)
    case PAYMENT_GENERIC = 4000;
    case INSUFFICIENT_BALANCE = 4001;
    case OFFLINE_LIMIT_EXCEEDED = 4002;
    case OFFLINE_RATE_LIMITED = 4003;
    case OFFLINE_PER_TX_EXCEEDED = 4004;
    case PAYMENT_FAILED = 4005;
    case PAYMENT_TIMEOUT = 4006;
    case REFUND_FAILED = 4007;
    case WEBHOOK_SIGNATURE_INVALID = 4008;
    case CSR_INVALID = 4010;
    case CERTIFICATE_CHAIN_INVALID = 4011;
    case CERTIFICATE_TYPE_MISMATCH = 4012;
    case RENEWAL_DENIED = 4013;
    case KEYPAIR_GENERATION_FAILED = 4014;
    // spec v0.8.0 07-errors.md §3.4 — provisioning identity binding (§2 bound-set rule)
    case PROVISIONING_KEY_MISMATCH = 4015;
    case PROVISIONING_KEY_REUSE = 4016;
    case PROVISIONING_REQUEST_INVALID = 4017;
    case PROVISIONING_TOKEN_CONSUMED = 4018;
    case PUBLIC_KEY_INVALID = 4019;
    case BAY_COUNT_MISMATCH = 4020;

    // 5xxx - Station Hardware & Software Errors (34 codes)
    case HARDWARE_GENERIC = 5000;
    case PUMP_SYSTEM = 5001;
    case FLUID_SYSTEM = 5002;
    case CONSUMABLE_SYSTEM = 5003;
    case ELECTRICAL_SYSTEM = 5004;
    case PAYMENT_HARDWARE = 5005;
    case HEATING_SYSTEM = 5006;
    case MECHANICAL_SYSTEM = 5007;
    case SENSOR_FAILURE = 5008;
    case EMERGENCY_STOP = 5009;
    case DOWNLOAD_FAILED = 5014;
    case CHECKSUM_MISMATCH = 5015;
    case VERSION_ALREADY_INSTALLED = 5016;
    case INSUFFICIENT_STORAGE = 5017;
    case INSTALLATION_FAILED = 5018;
    case UPLOAD_FAILED = 5019;
    case INVALID_TIME_WINDOW = 5020;
    case NO_DIAGNOSTICS_AVAILABLE = 5021;
    case INVALID_CATALOG = 5023;
    case UNSUPPORTED_SERVICE = 5024;
    case CATALOG_TOO_LARGE = 5025;
    case SOFTWARE_GENERIC = 5100;
    case FIRMWARE_ERROR = 5101;
    case CONFIGURATION_ERROR = 5102;
    case STORAGE_ERROR = 5103;
    case WATCHDOG_RESET = 5104;
    case MEMORY_ERROR = 5105;
    case CLOCK_ERROR = 5106;
    case OPERATION_IN_PROGRESS = 5107;
    case CONFIGURATION_KEY_READONLY = 5108;
    case INVALID_CONFIGURATION_VALUE = 5109;
    case RESET_FAILED = 5110;
    case BUFFER_FULL = 5111;
    case FIRMWARE_SIGNATURE_INVALID = 5112;

    // 6xxx - Server Errors (9 codes — v0.11.1 added 6008 COMMAND_PRE_EMPTED)
    case SERVER_GENERIC = 6000;
    case SERVER_INTERNAL_ERROR = 6001;
    case ACK_TIMEOUT = 6002;
    case STATION_OFFLINE = 6003;
    case VALIDATION_ERROR = 6004;
    case SESSION_ALREADY_ACTIVE = 6005;
    case RATE_LIMIT_EXCEEDED = 6006;
    case SERVICE_DEGRADED = 6007;
    /**
     * The server refused to dispatch a command and stopped it locally, so it never
     * reached the station. spec v0.15.0 07-errors.md §3.6.
     *
     * Not the station's own code: 3016 proves the message reached the station,
     * whereas a pre-empt proves only what the server believed, and the server's view
     * can be stale. A server MUST NOT pre-empt a Reset carrying `force: true`.
     *
     * Two kinds, discriminated by `details.reason` — REQUIRED, because it is the one
     * member present on both occurrences:
     *  1. Predicted refusal — the server sees the station would decline (a Reset with
     *     sessions running). `details.wouldBe` MUST carry the code the station would
     *     have answered (3016 for that Reset).
     *  2. Server-protective — the server declines for a reason of its own, the open
     *     command circuit breaker being the defined case. `details.wouldBe` MUST be
     *     ABSENT: the station was never going to answer at all, and inventing a code
     *     it never gave is the borrowing this entry exists to forbid.
     *
     * With `details.wouldBe` absent a receiver MUST treat the command as refused and
     * NOT performed, and MUST NOT infer that it would have succeeded.
     *
     * Widened at spec v0.15.0. Before that the entry described only kind 1 and made
     * `details.wouldBe` unconditionally REQUIRED, which the circuit-breaker path
     * could not satisfy — it had been answering 6002 ACK_TIMEOUT, a code asserting
     * the server SENT a command it had explicitly not dispatched.
     */
    case COMMAND_PRE_EMPTED = 6008;

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
            self::OFFLINE_RECEIPT_MISMATCH,
            self::SERVER_AUTH_NONCE_MISMATCH,
            self::PUMP_SYSTEM,
            self::ELECTRICAL_SYSTEM,
            self::EMERGENCY_STOP,
            self::FIRMWARE_ERROR,
            self::WATCHDOG_RESET,
            self::MEMORY_ERROR,
            self::WEBHOOK_SIGNATURE_INVALID,
            self::KEYPAIR_GENERATION_FAILED,
            self::INSTALLATION_FAILED,
            self::RESET_FAILED,
            self::BUFFER_FULL,
            self::FIRMWARE_SIGNATURE_INVALID => Severity::CRITICAL,

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
            self::OFFLINE_PASS_REVOKED,
            self::OFFLINE_ORG_MISMATCH,
            self::OFFLINE_USER_MISMATCH,
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
            self::PROGRAM_NOT_DECLARED,
            self::TOPOLOGY_MISMATCH,
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
            self::URL_UNREACHABLE,
            self::CSR_INVALID,
            self::CERTIFICATE_CHAIN_INVALID,
            self::RENEWAL_DENIED,
            self::DOWNLOAD_FAILED,
            self::CHECKSUM_MISMATCH,
            self::INSUFFICIENT_STORAGE,
            self::UPLOAD_FAILED,
            self::INVALID_CATALOG,
            // v0.22.0: 5024 moved Warning -> Error when the partial application it
            // mandated was withdrawn. It refuses the whole catalog now, so it is not
            // an advisory. Reached this enum through `default => WARNING`, which is
            // why nothing here had to name it before.
            self::UNSUPPORTED_SERVICE,
            self::CATALOG_TOO_LARGE,
            self::CONFIGURATION_KEY_READONLY,
            self::INVALID_CONFIGURATION_VALUE,
            // v0.8.0 provisioning identity codes — all Severity Error per registry
            self::PROVISIONING_TOKEN_INVALID,
            self::PROVISIONING_KEY_MISMATCH,
            self::PROVISIONING_KEY_REUSE,
            self::PROVISIONING_REQUEST_INVALID,
            self::PROVISIONING_TOKEN_CONSUMED,
            self::PUBLIC_KEY_INVALID,
            self::BAY_COUNT_MISMATCH,
            // 3019: the server's configuration is incomplete — an operator has to act.
            self::SERVICE_NOT_BOUND => Severity::ERROR,

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
            self::OFFLINE_PASS_REVOKED,
            self::OFFLINE_ORG_MISMATCH,
            self::OFFLINE_USER_MISMATCH,
            self::OFFLINE_RECEIPT_MISMATCH,
            self::SERVER_AUTH_NONCE_MISMATCH,
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
            self::PROGRAM_NOT_DECLARED,
            self::OFFLINE_LIMIT_EXCEEDED,
            self::OFFLINE_PER_TX_EXCEEDED,
            self::WEBHOOK_SIGNATURE_INVALID,
            self::PUMP_SYSTEM,
            // 5004: a welded relay or a lost phase persists while the measured
            // voltage reads nominal, so "power came back" does not mean the fault
            // cleared — and a welded relay may leave the bay energised after the
            // station believes it cut power. It is a §7.2 Level 3 entry trigger:
            // physical intervention + operator verification + reboot, never
            // self-clearing. Spec made this false in v0.8.0 (07-errors.md:396);
            // both SDKs kept saying true until check-error-registry caught it.
            self::ELECTRICAL_SYSTEM,
            self::PAYMENT_HARDWARE,
            self::MECHANICAL_SYSTEM,
            self::EMERGENCY_STOP,
            self::FIRMWARE_ERROR,
            self::VALIDATION_ERROR,
            self::RENEWAL_DENIED,
            self::KEYPAIR_GENERATION_FAILED,
            self::CHECKSUM_MISMATCH,
            self::VERSION_ALREADY_INSTALLED,
            self::INSUFFICIENT_STORAGE,
            self::INSTALLATION_FAILED,
            self::INVALID_TIME_WINDOW,
            self::NO_DIAGNOSTICS_AVAILABLE,
            self::INVALID_CATALOG,
            self::UNSUPPORTED_SERVICE,
            self::CATALOG_TOO_LARGE,
            self::CONFIGURATION_KEY_READONLY,
            self::INVALID_CONFIGURATION_VALUE,
            self::RESET_FAILED,
            self::FIRMWARE_SIGNATURE_INVALID,
            // v0.8.0: 2019 and 4015 are recoverable=false per registry — no retry on
            // the same token can succeed. 4016 and 4017 are recoverable=true and fall
            // through to the default: both leave the token unconsumed.
            self::PROVISIONING_TOKEN_INVALID,
            self::PROVISIONING_KEY_MISMATCH => false,

            default => true,
        };
    }

    public function errorText(): string
    {
        return $this->name;
    }

    /**
     * The per-code corrective action from the spec registry (07-errors.md §3).
     *
     * spec 07-errors.md §1.4: `recommendedAction` is a property of the CODE, not of
     * the occurrence — two errors carrying the same `errorCode` MUST carry the same
     * action — and an implementation MUST NOT substitute a generic string derived
     * from `severity` or `recoverable`. The values below are transcribed verbatim
     * from the registry cells; do not paraphrase them here.
     *
     * There is deliberately no matching `errorDescription()` accessor. That field is
     * PER-OCCURRENCE and written by the emitter; §1.4 states that an implementation
     * MUST NOT emit a registry Description cell verbatim and that "a generator MUST
     * NOT be built to do so".
     *
     * Returns null for codes whose registry cell has not been transcribed yet.
     * Callers that must emit the REST Error Object (§2.4), where the field is
     * REQUIRED, are responsible for treating null as a defect rather than emitting
     * an empty string.
     */
    public function recommendedAction(): ?string
    {
        return match ($this) {
            // 07-errors.md §3.2
            self::PROVISIONING_TOKEN_INVALID => 'Station: display the error and **await a new provisioning token** — no retry with this token can succeed. Operator: issue a fresh token. Do not regenerate keys in response to this error; the keys are not what was rejected.',

            // 07-errors.md §3.4 §4.01x — also reachable from certificate renewal
            // (SignCertificate [MSG-022]); the branch is carried in details.phase.
            self::CSR_INVALID => 'Station: recovery depends on `details.phase`, which the server MUST carry. `first-provision` or `renewal` — regenerate the keypair and CSR with correct parameters and resubmit; nothing is bound yet. `retry` — do NOT regenerate: a fresh key is answered `4015`, which is not recoverable. Resubmit a well-formed CSR over the already-bound key, or request a new token if it cannot be produced. If `details.phase` is absent, assume `retry`. Server: log the specific validation failure.',

            // 07-errors.md §3.6 — reachable from every REST endpoint, including the
            // provisioning endpoint (unhandled fault, throttle, degraded crypto material).
            self::SERVER_INTERNAL_ERROR => 'Retry with exponential backoff. Server: log full error with request context, correlate via `X-Request-Id`.',

            self::RATE_LIMIT_EXCEEDED => 'Wait before retrying. The `Retry-After` HTTP header (if present) indicates when to retry. See Chapter 06 §7.1 for rate limit thresholds.',

            self::SERVICE_DEGRADED => 'Non-blocking. The server continues to function with reduced capabilities. Degraded features are listed in the `details` field.',

            // 07-errors.md §3.4
            self::PROVISIONING_KEY_MISMATCH => 'Station: **do NOT retry with this token** — no retry can succeed, because the token is permanently bound to the earlier key. Request a **new** provisioning token from the operator, then provision again with the keys currently held. Server: log the mismatch; the already-issued certificate is unaffected.',

            self::PROVISIONING_KEY_REUSE => 'Station: recovery depends on `details.phase`. `first-provision` — generate a separate key pair for the colliding role and resubmit; this rejection does not consume the token. `retry` — do NOT regenerate: the bound keys are what was certified, and a fresh key is answered `4015`, which is not recoverable. Resubmit the keys already bound, or request a new token. If `details.phase` is absent, assume `retry`. Firmware deriving two roles from one key slot must be updated.',

            self::PROVISIONING_REQUEST_INVALID => 'Station: correct the offending property and resubmit on the **same** token — this rejection does not consume it. Inspect `details` for the failing property path. Do **not** regenerate keys: the keys are not what was rejected, and on a retry a fresh key would be answered `4015`, which is not recoverable. Server: name the failing property and the constraint it violated in `details`.',

            // 4018 — transcribed verbatim from 07-errors.md:362. Branches on
            // `details.reason`; the emitter carries the WHOLE entry (§1.4: a branching
            // entry is emitted in full, never only the selected branch).
            self::PROVISIONING_TOKEN_CONSUMED => 'Station: do NOT regenerate keys on any branch — a fresh key is answered `4015`. Branch on `details.reason`. `already_consumed` — another request holds this token; retry unchanged after a short delay, bounded, until it resolves to the certificate or to the branch below. `consumed_without_certificate` — this token can never issue one; request a new provisioning token. If `details.reason` is absent, assume `already_consumed`. Operator: issue a fresh token.',

            // 4019 — transcribed verbatim from 07-errors.md:363. Branches on
            // `details.phase`, default `retry` (the branch whose failure mode is
            // recoverable, per §1.4).
            self::PUBLIC_KEY_INVALID => 'Station: submit ECDSA P-256 key material only. Recovery depends on `details.phase`. `first-provision` — generate a correct P-256 key for the named role and resubmit on the same token; nothing is bound yet. `retry` — do NOT generate a new key: a fresh key is answered `4015`. Resubmit the key already bound, or request a new token if it cannot be produced. If `details.phase` is absent, assume `retry`. Server: name the rejected member in `details.field`.',

            // 4020 — transcribed verbatim from the 4.02x registry row. Single
            // recovery: reachable only on a first provision (on a replay body drift
            // is ignored), so there is no branch and no discriminator.
            // Shortened to fit Appendix C's 500-char wire bound, which 07-errors.md §1.4
            // expressly permits "provided the corrective action itself survives". The
            // registry's full text is longer; this is NOT drift, and syncing it byte-for-byte
            // with the spec would make every naive emitter produce a non-conforming payload.
            self::BAY_COUNT_MISMATCH => 'Station: correct the declared `bays` and resubmit on the **same** token — it is not consumed. Do **not** regenerate keys: a fresh key later is answered `4015`, not recoverable. Compare `details.declaredBayNumbers` with `details.registeredBayNumbers` — their difference is the fault, and counts mislead because a swapped bay leaves both the same length. A truthful declaration means the operator corrects the station record; an untruthful one, the firmware\'s bay table. Server: carry both sets.',

            default => null,
        };
    }

    /**
     * A conventional HTTP status for this code — an SDK extension, NOT the contract.
     *
     * The specification declines to make status a property of a code.
     * `07-errors.md` §4.4 is headed "The status is not a property of the code": §2.4's
     * mapping table "is illustrative and assigns no code a fixed status", nothing in §3
     * carries an HTTP status column, and one code can honestly appear with more than one
     * status. §2.4's own table lists 2008 under BOTH 401 and 403 — which no function from
     * code to status can represent.
     *
     * So this method answers a question the spec does not define, and `sdk-ts` answers it
     * differently: the two disagree on 51 of the 114 codes. Everything else in the two
     * registries is identical — numbers, names, severity, recoverable, the category
     * partition, the vendored schemas. Recorded in the spec's KNOWN-ISSUES.md together
     * with `category()`, which has the same cause.
     *
     * Treat the result as a default for a server that has no better answer, never as the
     * status a code "has". A server that knows the state it is in knows the truer status;
     * §4.4 requires it to send that one and forbids downgrading it to match an enumeration.
     *
     * The `default => 500` arm is retained rather than made to return null or throw. Both
     * alternatives were considered and rejected: returning null for the unmapped codes
     * still asserts a total function from code to status, merely with a hole in it, and
     * throwing would make an accessor fail on codes that are perfectly valid — neither is
     * more honest than a documented default, and both break callers to no benefit.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::INVALID_MESSAGE_FORMAT, self::PAYLOAD_INVALID, self::VALIDATION_ERROR,
            // v0.8.0: 4017 → 400 at the provisioning endpoint (07-errors.md §3.4);
            // body failed schema validation, evaluated first in the §2 precedence chain.
            self::PROVISIONING_REQUEST_INVALID,
            // v0.8.2 FIX: 4010 is listed under 400 in the §2.4 status table
            // (07-errors.md:241) and §3.4 states "At the provisioning endpoint:
            // HTTP 400 Bad Request". It had no arm and fell through to the default
            // 500, turning a client error into a server error on the wire.
            self::CSR_INVALID,
            // v0.8.3: 4019 → 400 — the bare-key counterpart of 4010; 07-errors.md:363
            // states both answer 400 so the same defect does not vary by packaging.
            self::PUBLIC_KEY_INVALID => 400,
            // v0.5.2: 2014 OFFLINE_PASS_REVOKED aligned cross-SDK to 401 (revoked
            // credential ≡ credential no longer valid; RFC 9110 401 "credential invalid").
            self::OFFLINE_PASS_REVOKED,
            self::JWT_EXPIRED, self::JWT_INVALID, self::ACTION_NOT_PERMITTED,
            self::SESSION_TOKEN_EXPIRED, self::SESSION_TOKEN_INVALID,
            // v0.6.2: 2018 SERVER_AUTH_NONCE_MISMATCH → 401 — ServerSignedAuth replay
            // at the BLE handshake; the auth is REJECTED (station refuses the
            // handshake), unlike 2017 where auth succeeded → 422. Same shape as the
            // 2005 counter-replay / JWT-rejection family (2009-2012).
            self::SERVER_AUTH_NONCE_MISMATCH,
            // v0.8.0: 2019 → 401 — the provisioning token is unusable (expired,
            // superseded, or revoked); the credential itself is rejected.
            self::PROVISIONING_TOKEN_INVALID,
            // v0.9.0: 4008 is reachable from POST /webhooks/payment-gateway/notification,
            // whose ONLY status in 07-errors.md §4.4 is 401. It had no arm and fell to
            // the default 500, turning a rejected signature into a server fault.
            self::WEBHOOK_SIGNATURE_INVALID => 401,
            self::INSUFFICIENT_BALANCE => 402,
            // v0.5.2: 2015 OFFLINE_ORG_MISMATCH + 2016 OFFLINE_USER_MISMATCH aligned
            // cross-SDK to 403 — pass is cryptographically valid but used in a
            // context it wasn't issued for (cross-org / wrong user); RFC 9110 403
            // "authenticated, not permitted for this resource".
            self::OFFLINE_ORG_MISMATCH, self::OFFLINE_USER_MISMATCH => 403,
            // 3017/3018 are MQTT-only -- BootNotification and StartService, neither a
            // REST endpoint -- so §2.4's HTTP status table does not list them and both
            // rows here are this SDK's extension with no clause behind them. 3017
            // follows the registry's own stated analogy, "one code per identifier
            // KIND", where 3005/3006/3012 are 404. Matched byte-for-byte in sdk-ts.
            self::BAY_NOT_FOUND, self::SESSION_NOT_FOUND, self::RESERVATION_NOT_FOUND,
            self::PROGRAM_NOT_DECLARED => 404,
            self::BAY_BUSY, self::BAY_RESERVED, self::SESSION_ALREADY_ACTIVE,
            // v0.8.0: 4015 → 409 — the retry presents an identity that conflicts with
            // the one the token already bound; not a replay, and no second cert issued.
            self::PROVISIONING_KEY_MISMATCH,
            // v0.8.3: 4018 → 409 — the token authenticated but is already consumed
            // and this is not a replay of the provision that consumed it (07-errors.md:362).
            self::PROVISIONING_TOKEN_CONSUMED,
            // v0.9.0: both reachable over REST and both fell to the default 500.
            // 3002 from POST /sessions/start and 3007 from POST /sessions/{id}/stop
            // (07-errors.md §4.4); each endpoint lists 409, and both codes are
            // resource-state preconditions — the same family as BAY_BUSY above.
            self::BAY_NOT_READY,
            self::SESSION_MISMATCH,
            self::OPERATION_IN_PROGRESS,
            // 3018 is a disagreement between two declarations, which is 409's shape.
            self::TOPOLOGY_MISMATCH,
            // 409, not 422: for 3019 the request is well-formed and every value in it is
            // valid — what is incomplete is the server's own configuration. For 6008 the
            // command was never dispatched, so nothing about the request was wrong either.
            self::SERVICE_NOT_BOUND,
            self::COMMAND_PRE_EMPTED => 409,
            // v0.5.2: 2017 OFFLINE_RECEIPT_MISMATCH aligned cross-SDK to 422 —
            // signature itself verified per spec §3.2; the cross-check failure
            // is "syntax correct, instructions inconsistent" ≡ RFC 9110 422
            // Unprocessable Entity (NOT 401 — auth succeeded).
            self::OFFLINE_RECEIPT_MISMATCH,
            self::DURATION_INVALID, self::MAX_DURATION_EXCEEDED, self::INVALID_SERVICE,
            self::STATION_NOT_REGISTERED,
            // v0.8.0: 4016 → 422 — the body is well-formed but two submitted key kinds
            // carry the same key; a defect in the request, visible without stored state.
            self::PROVISIONING_KEY_REUSE,
            // v0.8.4: 4020 -> 422 — the declared bay SET does not match the station's
            // registered bay count; well-formed body, value inconsistent with stored
            // state (07-errors.md 4.02x).
            self::BAY_COUNT_MISMATCH,
            self::INVALID_TIME_WINDOW => 422,
            self::RATE_LIMIT_EXCEEDED => 429,
            self::STATION_OFFLINE => 502,
            // v0.9.0: 6007 answers 503 + Retry-After, and 07-errors.md §4.4 now makes
            // that REQUIRED rather than tolerated: "A server MUST answer 503 there and
            // MUST NOT substitute 500 to make the response match the enumeration."
            // Status follows transience, not numeric range — 500 tells a station to back
            // off blindly, 503 + Retry-After tells it when to return.
            self::SERVICE_DEGRADED => 503,
            self::ACK_TIMEOUT => 504,
            // Everything else: 500. See the docblock above this method — the default is
            // deliberate, and is a default rather than a claim about the code.
            default => 500,
        };
    }
}
