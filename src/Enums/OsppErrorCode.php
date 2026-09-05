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

    // 5xxx - Station Hardware & Software Errors (35 codes)
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

    /**
     * 5113 — the station cannot determine what a bay delivered.
     *
     * Added by spec 0.33.0 for `start-service.md` §6 rule 12 and
     * `05-state-machines.md` §3.5 rule 6. It is the ONE member of the 5xxx range
     * that asserts no fault was detected: every other member names something the
     * station observed, and this one exists because rule 12's condition is that it
     * observed nothing. Rule 12 mandated a `Faulted` StatusNotification and named
     * no code, while CORE-012 and the schema both require one on `Faulted` — so
     * the message the rule required did not validate, and the `sessionId` it
     * carries, which the rule calls "the whole of its value", went with it.
     */
    case OUTCOME_INDETERMINATE = 5113;

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
            self::OUTCOME_INDETERMINATE,
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
     * All 118 registry codes are transcribed. This method returned a value for
     * ELEVEN of them until 0.28.0 — the provisioning block plus four server codes —
     * and null for the other 107. That was read once as the registry being
     * incomplete; it is not. §3 carries a Recommended Action for 118 of 118 rows
     * with no empty cell, so the gap was a transcription hole on this side of the
     * wire, and `scripts/check-recommended-action.php` now refuses to let one reopen.
     *
     * WHAT §1.4 REQUIRES, AND WHAT IT FORBIDS A TEST FROM ASSERTING
     *
     * The value MUST carry the action §3 gives for the code, and is a property of
     * the CODE, not the occurrence. But equality is on "the corrective action, not
     * on the bytes": a server MAY translate the value, and MAY shorten it to fit
     * Appendix C, provided the action survives. §1.4 draws the conclusion itself —
     * "Byte-identity is not achievable in any case, since translation is expressly
     * permitted, so a conformance test MUST NOT assert it."
     *
     * So the gate does not compare these strings to the registry. It checks the
     * properties that survive a translation: that every code HAS an action, that it
     * fits Appendix C's 1..500 bound, that no two codes share one string (the
     * signature of the generic substitution §1.4 forbids), and that a branching
     * entry still names its `details` discriminator, its branch tokens and the
     * parties it addresses. Coverage and structure, never content.
     *
     * Two of the eleven had drifted from the registry, and they are why the gate is
     * built that way rather than as a diff. `4020` had been reworded to fit the
     * 500-char bound and still said exactly what the cell says — conforming under
     * §1.4, and a byte gate would have failed it. `4010` had NOT stayed equivalent:
     * the cell says an absent `details.phase` means `retry` on REST but `renewal` on
     * SignCertificate [MSG-022], and this SDK said `retry` unconditionally — the
     * opposite recovery on the renewal path, since `renewal` regenerates the keypair
     * and `retry` must not. Both are re-transcribed from the cell at the pinned ref.
     *
     * The values are the registry cell with Markdown links flattened to their label
     * text and whitespace collapsed; nothing else is changed. Every cell fits the
     * wire bound as written at the pinned ref (longest 494 of 500), so no shortening
     * is needed anywhere and none is done — and `scripts/check-doc-claims.php` derives
     * that 494 from the accessor rather than trusting this sentence for it.
     *
     * There is deliberately no matching `errorDescription()` accessor. That field is
     * PER-OCCURRENCE and written by the emitter; §1.4 states that an implementation
     * MUST NOT emit a registry Description cell verbatim and that "a generator MUST
     * NOT be built to do so".
     *
     * The return is `string`, not `?string`, and the `default => null` arm is gone.
     * It was kept at first as a safety net for an enum case added ahead of its
     * transcription — until phpstan level 9 pointed out that with 118 of 118 cases
     * matched the arm is UNREACHABLE. An unreachable net is not a net; it is a null
     * in the signature that no execution can produce, and every caller emitting the
     * REST Error Object (§2.4), where the field is REQUIRED, was being made to
     * handle it. A case added without an arm now raises \UnhandledMatchError at the
     * call rather than returning a null nobody checks, and it cannot reach a release
     * either way: `check-error-registry` reds when the enum and §3 disagree, and
     * `check-recommended-action` reds when a code has no arm.
     */
    public function recommendedAction(): string
    {
        return match ($this) {

            // 07-errors.md §3.1 — Transport (1xxx)
            self::TRANSPORT_GENERIC => 'Retry with exponential backoff; if persistent, report to server.',
            self::MQTT_CONNECTION_LOST => 'Reconnect with exponential backoff (1s→30s cap). Buffer events locally. See §5.1.',
            self::MQTT_PUBLISH_FAILED => 'Retry publish; if repeated, check broker connectivity. Buffer message for later delivery.',
            self::TLS_HANDSHAKE_FAILED => 'Check the negotiated TLS version against the 1.2 floor and the configured cipher suites; the certificate is **not** what was rejected, so do not regenerate, re-provision, or discard credentials in response to this code. Report via SecurityEvent [MSG-012].',
            self::CERTIFICATE_ERROR => 'Station: never enter provisioning mode and never discard stored credentials. Branch on `details.cause`; if it is absent, read your own certificate\'s `notAfter`. `expired` — enter offline-only BLE mode (§4.7.3) and await server-triggered renewal. `revoked` / `invalid-chain` / `self-signed` — keep credentials, stay off the broker, alert the operator. Server: reject the connection, alert the operator.',
            self::INVALID_MESSAGE_FORMAT => 'Log it; do NOT resend the identical bytes — the sender must correct them. On the boot path this does not suspend CORE-011: a station rejected with `1005` MUST keep retrying at the response\'s `retryInterval`, exactly as for `1007` and `2001` (§5.2 — unlimited). One that stops is unrecoverable: it accepts no commands until booted. `recoverable: false` means someone must act, not stop retrying.',
            self::UNKNOWN_ACTION => '**Branch on whether a RESPONSE schema exists for the action.** Known to the protocol but unsupported here: reply `status: "Rejected"` with this code on that action\'s own RESPONSE (§2.1). Unknown to the protocol: no RESPONSE schema exists and all are closed, so log and discard (Chapter 02 §11). Either branch **MAY** be reported as an unsolicited EVENT (§2.2). Sender: verify the action name.',
            self::PROTOCOL_VERSION_MISMATCH => 'Station: keep retrying BootNotification at `retryInterval` (default 30 s) per CORE-011, in the `Rejected` restricted state; do **NOT** stop retrying. Record `supportedVersions` for diagnostics. Operator: upgrade station firmware to a version in `supportedVersions`, or add the station\'s version to the server\'s set. Server: reject with `Rejected`, including both `supportedVersions` and `retryInterval`.',
            self::BLE_RADIO_ERROR => 'Reset BLE stack. If persistent, disable BLE and report via SecurityEvent [MSG-012].',
            self::DNS_RESOLUTION_FAILED => 'Retry after 30s. Verify DNS server configuration. Fall back to IP address if configured.',
            self::MESSAGE_TIMEOUT => 'Retry per the action\'s retry policy (see §5). If max retries exhausted, escalate to ERROR.',
            self::URL_UNREACHABLE => 'Retry with exponential backoff. Verify network connectivity and URL correctness.',
            self::MAC_VERIFICATION_FAILED => 'Reject the message. Log SecurityEvent [MSG-012] with `type: "MacVerificationFailure"`. 3+ failures from same source within 60s → flag as potentially compromised.',
            self::MAC_MISSING => 'Reject the message — never process it unverified. Log SecurityEvent [MSG-012]. Note where the fault is: a conforming sender **refuses to send** rather than sending unsigned (Chapter 06 §5.7), so a message reaching this code was produced by a sender that did not fail closed.',
            self::MESSAGE_TOO_LARGE => 'Reject the message. Sender must reduce payload size — e.g., split MeterValues into multiple messages.',

            // 07-errors.md §3.2 — Authentication & Authorization (2xxx)
            self::AUTH_GENERIC => 'Check credentials and permissions. Contact operator if persistent.',
            self::STATION_NOT_REGISTERED => 'Station: keep retrying BootNotification at `retryInterval` (default 30 s) per CORE-011 — the retry succeeds once the operator acts. Do NOT enter provisioning mode and do NOT alter stored credentials: you hold credentials the broker accepted, and re-provisioning is operator-initiated. Operator: register this `stationId` in the management portal, or correct it if mistyped; check it was not dropped by a tenant move or a database restore.',
            self::OFFLINE_PASS_INVALID => 'App: request a new OfflinePass from the server. Station: log SecurityEvent [MSG-012] with `type: "OfflinePassRejected"`.',
            self::OFFLINE_PASS_EXPIRED => 'App: request a new OfflinePass from the server. Pass has a maximum validity of 24 hours.',
            self::OFFLINE_EPOCH_REVOKED => 'App: request a new OfflinePass with the current epoch. Station epoch is updated via ChangeConfiguration [MSG-013].',
            self::OFFLINE_COUNTER_REPLAY => 'Reject. Station (authorize-time): log SecurityEvent [MSG-012] with `type: "OfflinePassRejected"`. Server (reconcile-time): hard-reject the TransactionEvent and emit the gate SecurityEvent (`reconciliation.md` §6.3). App: if legitimate, request a new OfflinePass.',
            self::OFFLINE_STATION_MISMATCH => 'App: the OfflinePass is not valid for this station. Request a new pass or use an unrestricted pass.',
            self::COMMAND_NOT_SUPPORTED => 'Server: do not retry. Check station capabilities from BootNotification.',
            self::ACTION_NOT_PERMITTED => 'Verify the user\'s role and permissions. Contact the operator admin if elevated access is needed.',
            self::JWT_EXPIRED => 'App: use the refresh token to obtain a new access token. If refresh token is also expired, re-authenticate.',
            self::JWT_INVALID => 'App: clear stored tokens and re-authenticate. May indicate token tampering.',
            self::SESSION_TOKEN_EXPIRED => 'Browser: restart the payment flow from the QR code scan.',
            self::SESSION_TOKEN_INVALID => 'Browser: restart the payment flow. Do not retry with the same token.',
            self::BLE_AUTH_FAILED => 'App: disconnect and retry the BLE handshake. If persistent, report to the server when online.',
            self::OFFLINE_PASS_REVOKED => 'App: request a new OfflinePass. Server: log SecurityEvent [MSG-012] with `type: "OfflinePassRejected"`. The original pass is permanently dead; the device must obtain a new one.',
            self::OFFLINE_ORG_MISMATCH => 'Server: log SecurityEvent [MSG-012] with `type: "OfflinePassRejected"`. Cross-organization use is not permitted. The pass holder must request a pass scoped to the operator they wish to transact with.',
            self::OFFLINE_USER_MISMATCH => 'Server: log SecurityEvent [MSG-012] with `type: "OfflinePassRejected"`. Indicates either a station bug, station-side state corruption, or a deliberate user-id forgery.',
            self::OFFLINE_RECEIPT_MISMATCH => 'Server: log SecurityEvent [MSG-012] with `type: "OfflinePassRejected"`. The `details.field` element identifies the mismatched field (`offlineTxId` / `offlinePassId` / `userId` / `deviceId` / `receipt.data` for the §3 stored-vs-arriving comparison); `details.signedValue` and `details.expectedValue` carry the forensic pair. This is a strong indicator of envelope tampering or station-side state corruption.',
            self::SERVER_AUTH_NONCE_MISMATCH => 'Station: reject the handshake and disconnect. App: SHOULD obtain a fresh `signedAuthorization` bound to the current `appNonce` and retry. Server: log SecurityEvent [MSG-012] with `type: "ServerSignedAuthReplay"` on the next reconciliation.',
            self::PROVISIONING_TOKEN_INVALID => 'Station: display the error and **await a new provisioning token** — no retry with this token can succeed. Operator: issue a fresh token. Do not regenerate keys in response to this error; the keys are not what was rejected.',

            // 07-errors.md §3.3 — Session & Bay (3xxx)
            self::SESSION_GENERIC => 'Inspect the `errorDescription` for specific context.',
            self::BAY_BUSY => 'Wait for the current session to complete, or select a different bay. Server: refund 100% if this rejects a StartService [MSG-005].',
            self::BAY_NOT_READY => 'Wait and retry. Check StatusNotification [MSG-009] for the bay\'s current state; if none has arrived at all, the station is not `Operational` and the boot is what needs attention.',
            self::SERVICE_UNAVAILABLE => 'Branch on `details.cause`; absent means `station-reported`. App: select a different service, or a different bay. Station and server: echo the refused `programNumber` — REQUIRED on a `Rejected` StartService response, `details.programNumber` on REST. `station-reported`: wait for the station to report the ordinal available again; nothing server-side changes. `disabled`: an operator must re-enable it. `consumable`: refill at that ordinal.',
            self::INVALID_SERVICE => 'Verify the service ID against the station\'s UpdateServiceCatalog [MSG-021] data.',
            self::BAY_NOT_FOUND => 'Verify the bay ID. The bay may have been decommissioned or the ID may be incorrect.',
            self::SESSION_NOT_FOUND => 'Verify the session ID. For StopService [MSG-006], the session may have already ended (timer expiry or auto-stop).',
            self::SESSION_MISMATCH => 'Verify the session ID. Use StatusNotification [MSG-009] to determine the active session on the bay.',
            self::DURATION_INVALID => 'Specify a valid duration. Minimum is service-defined (typically 60 seconds).',
            self::HARDWARE_ACTIVATION_FAILED => 'Server: refund 100%. Station: transition bay to `Faulted`, report via SecurityEvent [MSG-012]. Operator: dispatch technician.',
            self::MAX_DURATION_EXCEEDED => 'Reduce the requested duration to at most `MaxSessionDurationSeconds` seconds (default 900s).',
            self::BAY_MAINTENANCE => 'Wait for maintenance to complete. Operator: clear maintenance mode when work is done.',
            self::RESERVATION_NOT_FOUND => 'Do not retry. Start a new reservation flow if needed.',
            self::RESERVATION_EXPIRED => 'Create a new reservation. Default TTL is `ReservationDefaultTTL` (300 seconds).',
            self::BAY_RESERVED => 'Wait for the reservation to expire, or select a different bay.',
            self::PAYLOAD_INVALID => 'Fix the payload values. On a REST route the Error Object carries `details` (Appendix C) naming the failing member. On MQTT it does not exist: the three response schemas that can carry this code are closed without it, so `errorCode` and `errorText` are all the station can say. If the offending member is a well-formed but unknown identifier, use that identifier kind\'s code, not this one.',
            self::ACTIVE_SESSIONS_PRESENT => 'Stop all active sessions first, then retry the operation — or, where the reboot is needed regardless, re-issue the Reset with `force: true`, which the station settles under the operator-disable policy rather than refusing.',
            self::PROGRAM_NOT_DECLARED => 'Station: reject, echo the refused `programNumber` in the response, and run nothing. Do **NOT** substitute a neighbouring ordinal or clamp to the highest declared one — that charges for one thing and delivers another. Server: the service→program binding names an ordinal this station does not have. Correct the binding, or re-provision the station if its hardware genuinely changed. Operator: compare the station\'s declared topology against the catalog binding.',
            self::TOPOLOGY_MISMATCH => 'Station: keep the declaration stable and keep retrying BootNotification per CORE-011; answer commands while `Pending`. Do **NOT** alter the declaration to match the server — it describes hardware, and agreeing silently hides a real change. Operator: read `details`. If the hardware genuinely changed, re-provision the station, which re-creates the bay records. If it did not, correct the station record server-side; the next boot is then accepted.',
            self::SERVICE_NOT_BOUND => 'Operator: create the binding for this (bay, service) pair, naming an ordinal the bay declared at provisioning. Server: name the bay and the service in `details`, and do not dispatch StartService. The customer has not been charged, because nothing was started — say so, rather than reporting a station fault for a condition no station has seen.',

            // 07-errors.md §3.4 — Payment & Credit (4xxx)
            self::PAYMENT_GENERIC => 'Inspect the `errorDescription` for context. Contact support if persistent.',
            self::INSUFFICIENT_BALANCE => 'App: show top-up prompt. Web: redirect to payment page. The user must purchase more credits before starting a session.',
            self::OFFLINE_LIMIT_EXCEEDED => 'App: the user must go online to request a new OfflinePass (or top up credits).',
            self::OFFLINE_RATE_LIMITED => 'Wait the required interval (default 60 seconds) before attempting another offline transaction.',
            self::OFFLINE_PER_TX_EXCEEDED => 'Select a less expensive service or reduce the requested duration.',
            self::PAYMENT_FAILED => 'User: try a different payment method. Web: restart the payment flow.',
            self::PAYMENT_TIMEOUT => 'Check payment status with the processor. If unresolved, mark as expired and inform the user.',
            self::REFUND_FAILED => 'Retry the refund. If persistent, escalate to manual refund by accounting team.',
            self::WEBHOOK_SIGNATURE_INVALID => 'Reject the webhook. Log SecurityEvent. Do NOT process the payment. Alert security team.',
            self::CSR_INVALID => 'Station: branch on `details.phase`. `first-provision` or `renewal` — regenerate the keypair and CSR correctly and resubmit; nothing is bound yet. `retry` — do NOT regenerate: a fresh key is answered `4015`, not recoverable. Resubmit a well-formed CSR over the bound key, or request a new token. Absent on REST means `retry`; on SignCertificate [MSG-022] it is always absent and means `renewal`. Server: log the validation failure.',
            self::CERTIFICATE_CHAIN_INVALID => 'Server: verify the CA chain is complete and correctly ordered. Station: report the specific chain validation error in the response.',
            self::CERTIFICATE_TYPE_MISMATCH => 'Verify the `certificateType` field matches between SignCertificate and CertificateInstall.',
            self::RENEWAL_DENIED => 'Contact the operator. The server administrator must approve the renewal or adjust the policy.',
            self::KEYPAIR_GENERATION_FAILED => 'Log SecurityEvent with `HardwareFault` type. Dispatch technician to inspect the station\'s crypto hardware.',
            self::PROVISIONING_KEY_MISMATCH => 'Station: **do NOT retry with this token** — no retry can succeed, because the token is permanently bound to the earlier key. Request a **new** provisioning token from the operator, then provision again with the keys currently held. Server: log the mismatch; the already-issued certificate is unaffected.',
            self::PROVISIONING_KEY_REUSE => 'Station: recovery depends on `details.phase`. `first-provision` — generate a separate key pair for the colliding role and resubmit; this rejection does not consume the token. `retry` — do NOT regenerate: the bound keys are what was certified, and a fresh key is answered `4015`, which is not recoverable. Resubmit the keys already bound, or request a new token. If `details.phase` is absent, assume `retry`. Firmware deriving two roles from one key slot must be updated.',
            self::PROVISIONING_REQUEST_INVALID => 'Station: correct the offending property and resubmit on the **same** token — this rejection does not consume it. Inspect `details` for the failing property path. Do **not** regenerate keys: the keys are not what was rejected, and on a retry a fresh key would be answered `4015`, which is not recoverable. Server: name the failing property and the constraint it violated in `details`.',
            self::PROVISIONING_TOKEN_CONSUMED => 'Station: do NOT regenerate keys on any branch — a fresh key is answered `4015`. Branch on `details.reason`. `already_consumed` — another request holds this token; retry unchanged after a short delay, bounded, until it resolves to the certificate or to the branch below. `consumed_without_certificate` — this token can never issue one; request a new provisioning token. If `details.reason` is absent, assume `already_consumed`. Operator: issue a fresh token.',
            self::PUBLIC_KEY_INVALID => 'Station: submit ECDSA P-256 key material only. Recovery depends on `details.phase`. `first-provision` — generate a correct P-256 key for the named role and resubmit on the same token; nothing is bound yet. `retry` — do NOT generate a new key: a fresh key is answered `4015`. Resubmit the key already bound, or request a new token if it cannot be produced. If `details.phase` is absent, assume `retry`. Server: name the rejected member in `details.field`.',
            self::BAY_COUNT_MISMATCH => 'Station: correct the declared `bays` and resubmit on the **same** token — it is not consumed. Do **not** regenerate keys: a later retry with a fresh key is answered `4015`, which is unrecoverable. Read `details.declaredBayNumbers` against `details.registeredBayNumbers`; their difference either way is the fault. If the declaration is truthful the operator corrects the station record; if not, the firmware\'s bay table is corrected. Server: carry both **sets** in `details`, never counts alone.',

            // 07-errors.md §3.5 — Station Hardware & Software (5xxx)
            self::HARDWARE_GENERIC => 'Log and monitor. If persistent, transition bay to Faulted and dispatch technician.',
            self::PUMP_SYSTEM => 'Immediately stop active session on affected bay. Dispatch technician. Do not attempt restart without physical inspection.',
            self::FLUID_SYSTEM => 'Log warning. If fluid meter values drop below threshold during session, alert operator. May self-resolve when supply is restored.',
            self::CONSUMABLE_SYSTEM => 'Alert operator to refill consumable supply. Bay MAY continue with reduced-service mode if possible.',
            self::ELECTRICAL_SYSTEM => 'Station: engage emergency shutdown **immediately and unconditionally** — do not gate it on the voltage reading. Bay → `Faulted`, enter Level 3 (§7.2); report via SecurityEvent [MSG-012] with `type: "HardwareFault"`. The bay **MUST NOT** return to service on voltage normalising alone: clearing requires physical intervention, operator verification, and a station reboot. Operator: dispatch a technician to inspect the supply, relays, and incoming phases.',
            self::PAYMENT_HARDWARE => 'Disable local payment option. Mobile app and web payments remain available. Dispatch technician for payment hardware service.',
            self::HEATING_SYSTEM => 'Disable temperature-dependent services. Other services MAY continue. Auto-recoverable if temperature returns to safe range.',
            self::MECHANICAL_SYSTEM => 'Bay → Faulted. Dispatch technician. Requires physical intervention.',
            self::SENSOR_FAILURE => 'Log degraded readings. Switch to time-based billing if metering sensor fails during active session. Alert operator.',
            self::EMERGENCY_STOP => 'Immediately halt all active sessions. All bays → Faulted. Requires physical reset of E-stop button and operator verification before resuming.',
            self::DOWNLOAD_FAILED => 'Verify the `firmwareUrl` is reachable. Retry the UpdateFirmware [MSG-016] command. Check station network connectivity.',
            self::CHECKSUM_MISMATCH => 'Do NOT install. Report via SecurityEvent [MSG-012]. Server: verify the binary and checksum, then retry.',
            self::VERSION_ALREADY_INSTALLED => 'No action required. Server: update its records to reflect the station\'s current firmware version.',
            self::INSUFFICIENT_STORAGE => 'Station: free space from diagnostics logs, buffered telemetry, and cached or partial downloads **only**. Do **NOT** erase, truncate, or overwrite the retained rollback partition to make room. If the binary still does not fit, abort the update, stay on the current firmware, and report `Failed` via FirmwareStatusNotification. Server/Operator: supply a smaller build, or service the station to expand storage.',
            self::INSTALLATION_FAILED => 'Station: report via SecurityEvent [MSG-012]. Dispatch technician — may indicate flash storage failure.',
            self::UPLOAD_FAILED => 'Verify the `uploadUrl` is reachable and accepts uploads. Retry the GetDiagnostics [MSG-018] command.',
            self::INVALID_TIME_WINDOW => 'Fix the time window parameters in the GetDiagnostics request.',
            self::NO_DIAGNOSTICS_AVAILABLE => 'Request a broader time window, or wait for the station to accumulate more diagnostic data.',
            self::INVALID_CATALOG => 'Fix the catalog payload. The response for this message is a **closed** schema with no `details` member (`update-service-catalog-response.schema.json`), so `errorCode` and `errorText` are the whole of what the station can say — the server locates the offending entry by re-validating the payload it sent against the service-item schema, not by reading the reply.',
            self::UNSUPPORTED_SERVICE => 'Station: respond `Rejected` with this code and leave the previous catalog in force. Server/Operator: the catalog names a service this station cannot run. Correct the binding, remove the entry, or re-provision the station if its hardware genuinely changed. Do **NOT** re-send unchanged.',
            self::CATALOG_TOO_LARGE => 'Reduce the number of services in the catalog. Check station capabilities for maximum catalog size.',
            self::SOFTWARE_GENERIC => 'Log error with stack trace (if available). Report via SecurityEvent [MSG-012].',
            self::FIRMWARE_ERROR => 'Station: attempt watchdog-triggered reset. If error persists after reset, roll back to previous firmware partition. Report via SecurityEvent [MSG-012].',
            self::CONFIGURATION_ERROR => 'Station: load default configuration for missing/invalid keys. Report the specific key(s) via SecurityEvent [MSG-012]. Server: push corrected config via ChangeConfiguration [MSG-013].',
            self::STORAGE_ERROR => 'Station: retry the storage operation. If persistent, log SecurityEvent and disable features that require storage (offline tx log).',
            self::WATCHDOG_RESET => 'Station: send BootNotification [MSG-001] with `bootReason: "Watchdog"` after reboot. Server: flag for monitoring — 3+ watchdog resets in 24h triggers operator alert.',
            self::MEMORY_ERROR => 'Station: release non-essential buffers (meter value history, BLE advertising data). If insufficient, perform a soft reset. Report via SecurityEvent.',
            self::CLOCK_ERROR => 'Station: sync clock from next Heartbeat response. If RTC hardware is faulty, use server time exclusively. Flag for operator — large drift may indicate battery failure.',
            self::OPERATION_IN_PROGRESS => 'Retry after the in-progress operation completes. Check FirmwareStatusNotification [MSG-017] or DiagnosticsNotification [MSG-019] for progress. Where the cause is a full command queue, reduce the rate of concurrent commands to this station rather than retrying immediately.',
            self::CONFIGURATION_KEY_READONLY => 'Use a different key, or accept the current value. Read-only keys can only be changed via firmware update or provisioning.',
            self::INVALID_CONFIGURATION_VALUE => 'Check the valid range and type for the configuration key in the configuration registry.',
            self::RESET_FAILED => 'Dispatch technician. A physical power cycle may be required. Report via SecurityEvent [MSG-012].',
            self::BUFFER_FULL => 'Station: reject new StartService requests. Reconnect to MQTT to flush buffered TransactionEvents. Server: prioritize reconnection and reconciliation for this station.',
            self::OUTCOME_INDETERMINATE => 'Station: report the bay `Faulted` and emit the SecurityEvent [MSG-012] that carries the `sessionId`. Server: settle on the estimate, and record that the closing figure is unmeasured rather than observed. Operator: inspect the bay before returning it to service.',
            self::FIRMWARE_SIGNATURE_INVALID => 'Do NOT install. Report via SecurityEvent [MSG-012] with `FirmwareIntegrityFailure` type. Server: verify signing key and re-publish firmware.',

            // 07-errors.md §3.6 — Server (6xxx)
            self::SERVER_GENERIC => 'Retry after 5 seconds. If persistent, contact support.',
            self::SERVER_INTERNAL_ERROR => 'Retry with exponential backoff. Server: log full error with request context, correlate via `X-Request-Id`.',
            self::ACK_TIMEOUT => 'Server: refund 100% if this was a StartService. App: show "Station did not respond" with retry option. Server: check station heartbeat status.',
            self::STATION_OFFLINE => 'App: show "Station is offline" message. Suggest trying again later or using BLE offline mode if available.',
            self::VALIDATION_ERROR => 'Fix the request body per the API schema. The `details` field contains per-field validation errors.',
            self::SESSION_ALREADY_ACTIVE => 'App: show the existing active session. The user must stop or wait for the current session before starting a new one.',
            self::RATE_LIMIT_EXCEEDED => 'Wait before retrying. The `Retry-After` HTTP header (if present) indicates when to retry. See Chapter 06 §7.1 for rate limit thresholds.',
            self::SERVICE_DEGRADED => 'Non-blocking. The server continues to function with reduced capabilities. Degraded features are listed in the `details` field.',
            self::COMMAND_PRE_EMPTED => 'Operator: read `details.reason` — it says which kind of pre-empt this is. If `details.wouldBe` is present, treat it as that code\'s row directs; where it disagrees with the station, the server\'s view is stale — reconcile the server, do not visit the station. If absent, the command did not run and no outcome may be assumed; re-issue once the named condition clears. Server: always carry `details.reason`; carry `details.wouldBe` only for a predicted refusal; never pre-empt a forced command.',
        };
    }

    /**
     * A conventional HTTP status for this code — an SDK extension, NOT the contract.
     *
     * The specification declines to make status a property of a code.
     * `07-errors.md` §4.4 is headed "The status is not a property of the code": §2.4's
     * mapping table "is illustrative and assigns no code a fixed status", nothing in §3
     * carries an HTTP status column, and one code can honestly appear with more than one
     * status — but as of spec 0.32.0 only where its registry entry names the condition
     * that selects between the two. Until 0.32.0 that licence was unconditional, and
     * §2.4's own table listed 2008 under BOTH 401 and 403, which no function from code to
     * status could represent. No code is listed twice today, so the objection now runs to
     * a permitted future rather than to the present contents.
     *
     * So this method answers a question the spec does not define, and `sdk-ts` answers it
     * differently. Re-derived 2026-09-05 by dumping both registries: 118 codes each,
     * identical code sets, names, severity, recoverable, category partition and vendored
     * schemas; 76 agreements and 42 disagreements. The figure recorded in the spec's
     * KNOWN-ISSUES.md read "51 of 114" and was stale on both halves. 40 of the 42 are
     * THIS class falling through to `default => 500` while sdk-ts asserts a value —
     * one library declining to answer, not two libraries disagreeing. Only 2001
     * (php 422 / ts 401) is now a genuine two-sided disagreement; 2008 was the other
     * and is settled above. Recorded in the spec's KNOWN-ISSUES.md together with
     * `category()`, which has the same cause.
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
            self::JWT_EXPIRED, self::JWT_INVALID,
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
            //
            // spec 0.32.0: 2008 ACTION_NOT_PERMITTED moved 401 -> 403. This arm was
            // NOT wrong before — 07-errors.md §2.4 listed 2008 under BOTH 401 and 403,
            // so 401 satisfied the table and nothing could refute it. sdk-ts had
            // chosen 403 and was equally conformant; the two libraries disagreed and
            // the spec licensed both. 0.32.0 gave the multi-status licence a
            // condition — a code listed twice MUST have a registry entry naming the
            // discriminator — and 2008's entry names one condition, "the AUTHENTICATED
            // entity does not have the required RBAC role", which is 403 by
            // construction. The 401 row was unselectable, and it is gone. This is the
            // first time this accessor has been decidable against the specification
            // rather than against the other SDK.
            self::ACTION_NOT_PERMITTED,
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
            // spec 0.31.0: 3003 joins the §2.4 `409` row explicitly. It appeared in
            // NO row of that table until 0.30.0, and the three implementations that
            // had to answer anyway did not agree — the reference server said 503, the
            // TypeScript SDK said 503, and THIS SDK had no arm at all and fell through
            // to `default => 500`, turning a bay-level availability fact into a server
            // fault. A registry that declines to state a mapping does not avoid one; it
            // delegates it, once per implementation.
            //
            // 409, not 503: the name misleads. `3003` says a declared service is not
            // deliverable ON THAT BAY RIGHT NOW — a fact about the addressed resource.
            // `503` asserts the SERVER is unavailable, which is false here and invites
            // a caller to retry the whole endpoint rather than pick another bay. Same
            // shape as 3001 BAY_BUSY, 3014 BAY_RESERVED and 3019 SERVICE_NOT_BOUND,
            // which is why it sits with them.
            self::SERVICE_UNAVAILABLE,
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
