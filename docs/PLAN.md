# OSPP SDK PHP — Shared PHP Package

## Overview

Composer package reutilizabil care contine exclusiv codul de protocol OSPP. Folosit de toate cele 3 aplicatii (CSMS Server, Station Simulator, CSMS Simulator). Zero dependente Laravel — pure PHP 8.3.

- **Package name:** `ospp/protocol`
- **Type:** Composer library
- **PHP:** ^8.3 (readonly classes, enums, named args)
- **Dependencies externe:** zero (doar php-json, php-openssl)
- **Autoload:** PSR-4 `Ospp\Protocol\` -> `src/`

---

## 1. Ce se extrage din CSMS Server

### 1.1 Enums (10 fisiere)

**MessageType** (`src/Enums/MessageType.php`)
- REQUEST — expects response
- RESPONSE — reply to a request
- EVENT — fire-and-forget, no response expected

**BayStatus** (`src/Enums/BayStatus.php`)
- 7 stari: UNKNOWN, AVAILABLE, RESERVED, OCCUPIED, FINISHING, FAULTED, UNAVAILABLE
- Metode: isInitial(), canStartSession(), canReserve(), isFaulted(), fromOspp(), toOspp(), acceptsSessions(), acceptsReservations()

**SessionStatus** (`src/Enums/SessionStatus.php`)
- 6 stari: PENDING, AUTHORIZED, ACTIVE, STOPPING, COMPLETED, FAILED
- Metode: isTerminal(), isActive(), hasTimeout(), isBillable(), isStoppable(), fromOspp(), toOspp()

**SessionSource** (`src/Enums/SessionSource.php`)
- 2 valori: MOBILE_APP, WEB_PAYMENT
- Atentie: NU exista APP, QR, BLE

**FirmwareUpdateStatus** (`src/Enums/FirmwareUpdateStatus.php`)
- 10 stari: IDLE, DOWNLOADING, DOWNLOADED, VERIFYING, VERIFIED, INSTALLING, INSTALLED, REBOOTING, ACTIVATED, FAILED
- Metode: isTerminal(), isActive(), fromNotificationStatus()

**DiagnosticsStatus** (`src/Enums/DiagnosticsStatus.php`)
- 5 stari: PENDING, COLLECTING, UPLOADING, UPLOADED, FAILED
- Metode: isTerminal(), isActive(), allowedTransitions(), canTransitionTo(), fromNotificationStatus()

**ReservationStatus** (`src/Enums/ReservationStatus.php`)
- 5 stari: PENDING, CONFIRMED, ACTIVE, EXPIRED, CANCELLED
- Metode: isTerminal(), isCancellable(), isConvertible(), holdsBay(), triggersRefund()
- Terminal states: ACTIVE (converted to session), EXPIRED, CANCELLED
- ACTIVE este terminal pentru rezervare — inseamna ca rezervarea s-a convertit in sesiune

**SigningMode** (`src/Enums/SigningMode.php`)
- 3 moduri: ALL (~100% overhead), CRITICAL (~30% overhead, default), NONE (dev only)
- Metode: shouldSign(action), shouldVerify(action)

**OsppErrorCode** (`src/Enums/OsppErrorCode.php`)
- 80 coduri eroare (int-backed enum), 6 categorii:
  - 1xxx Transport (15 coduri): TRANSPORT_GENERIC, MQTT_CONNECTION_LOST, MQTT_PUBLISH_FAILED, TLS_HANDSHAKE_FAILED, CERTIFICATE_ERROR, INVALID_MESSAGE_FORMAT, UNKNOWN_ACTION, PROTOCOL_VERSION_MISMATCH, BLE_RADIO_ERROR, DNS_RESOLUTION_FAILED, MESSAGE_TIMEOUT, URL_UNREACHABLE, MAC_VERIFICATION_FAILED, MAC_MISSING, MESSAGE_TOO_LARGE
  - 2xxx Auth (14 coduri): AUTH_GENERIC, STATION_NOT_REGISTERED, OFFLINE_PASS_INVALID, OFFLINE_PASS_EXPIRED, OFFLINE_EPOCH_REVOKED, OFFLINE_COUNTER_REPLAY, OFFLINE_STATION_MISMATCH, COMMAND_NOT_SUPPORTED, ACTION_NOT_PERMITTED, JWT_EXPIRED, JWT_INVALID, SESSION_TOKEN_EXPIRED, SESSION_TOKEN_INVALID, BLE_AUTH_FAILED
  - 3xxx Session & Bay (16 coduri): SESSION_GENERIC, BAY_BUSY, BAY_NOT_READY, SERVICE_UNAVAILABLE, INVALID_SERVICE, BAY_NOT_FOUND, SESSION_NOT_FOUND, SESSION_MISMATCH, DURATION_INVALID, HARDWARE_ACTIVATION_FAILED, MAX_DURATION_EXCEEDED, BAY_MAINTENANCE, RESERVATION_NOT_FOUND, RESERVATION_EXPIRED, BAY_RESERVED, PAYLOAD_INVALID
  - 4xxx Payment (9 coduri): PAYMENT_GENERIC, INSUFFICIENT_BALANCE, OFFLINE_LIMIT_EXCEEDED, OFFLINE_RATE_LIMITED, OFFLINE_PER_TX_EXCEEDED, PAYMENT_FAILED, PAYMENT_TIMEOUT, REFUND_FAILED, WEBHOOK_SIGNATURE_INVALID
  - 5xxx Station Hardware/Software (18 coduri): HARDWARE_GENERIC, PUMP_SYSTEM, WATER_SYSTEM, CHEMICAL_SYSTEM, ELECTRICAL_SYSTEM, PAYMENT_HARDWARE, HEATING_SYSTEM, MECHANICAL_SYSTEM, SENSOR_FAILURE, EMERGENCY_STOP, SOFTWARE_GENERIC(5100), FIRMWARE_ERROR, CONFIGURATION_ERROR, STORAGE_ERROR, WATCHDOG_RESET, MEMORY_ERROR, CLOCK_ERROR, OPERATION_IN_PROGRESS
  - 6xxx Server (8 coduri): SERVER_GENERIC, SERVER_INTERNAL_ERROR, ACK_TIMEOUT, STATION_OFFLINE, VALIDATION_ERROR, SESSION_ALREADY_ACTIVE, RATE_LIMIT_EXCEEDED, SERVICE_DEGRADED
- Metode: category(), severity(), isRecoverable(), errorText(), httpStatus()
- httpStatus() mapeaza coduri OSPP la HTTP status codes (400, 401, 402, 404, 409, 422, 429, 500, 502, 504)

**Severity** (`src/Enums/Severity.php`)
- 4 niveluri: CRITICAL, ERROR, WARNING, INFO
- Metode: isActionRequired() — true for CRITICAL and ERROR
- Folosit de OsppErrorCode::severity() pentru clasificarea erorilor

### 1.2 Value Objects (2 fisiere)

**MessageId** (`src/ValueObjects/MessageId.php`)
- `final readonly class` implementeaza JsonSerializable, Stringable
- Prefixuri valide: `msg_` (mesaje regulare), `cmd_` (comenzi), `err_` (erori)
- Format: prefix + UUID v4
- Factory: generate(prefix = 'msg_'), fromString(value)
- Validare in constructor: prefix must be msg_, cmd_, or err_; empty string rejected
- Metode: equals(self $other): bool — comparatie pe value
- UUID generation: In CSMS foloseste `Illuminate\Support\Str::uuid()`. In package trebuie `random_bytes(16)` + manual UUID v4 formatting (zero Laravel dependency). Alternativ: suporta callable UUID factory injectabil.

**ProtocolVersion** (`src/ValueObjects/ProtocolVersion.php`)
- `final readonly class` implementeaza JsonSerializable, Stringable
- Format: MAJOR.MINOR.PATCH (e.g., "1.0.0")
- Proprietati: major (int), minor (int), patch (int), value (string, computed in constructor)
- Constructor valideaza: componente non-negative
- Factory: fromString(version) — parseaza "X.Y.Z", default(string $version = '1.0.0') — creeaza din string literal, FARA config()
- Compatibilitate: isCompatibleWith(other) — same MAJOR = compatible (regula OSPP, altfel eroare 1007 PROTOCOL_VERSION_MISMATCH)
- Metode: equals(self $other): bool — comparatie pe toate 3 componentele (major, minor, patch)
- DIFERENTA FATA DE CSMS: In CSMS, `default()` apeleaza `config('ospp.default_version')`. In package, `default()` primeste versiunea ca parametru string cu fallback la '1.0.0': `default(string $version = '1.0.0'): self`

### 1.3 Message Envelope (1 fisier)

**MessageEnvelope** (`src/Envelope/MessageEnvelope.php`)
- `final readonly class`
- Proprietati:
  - messageId: MessageId
  - messageType: MessageType (REQUEST | RESPONSE | EVENT)
  - action: string (e.g., "BootNotification")
  - timestamp: DateTimeImmutable (UTC, ISO 8601 cu milisecunde)
  - source: string ("station" sau "server")
  - protocolVersion: ProtocolVersion
  - payload: array (action-specific data)
  - mac: ?string (Base64-encoded HMAC-SHA256 sau null)
- Metode:
  - isSigned(): bool — $mac !== null
  - expectsResponse(): bool — messageType === REQUEST
  - isEvent(): bool — messageType === EVENT
  - toArray(): array — serializare la format OSPP canonical
  - toJson(): string — compact JSON (JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, no whitespace)
  - getPayloadStationId(): ?string — extrage stationId din payload daca exista
  - withMac(string $mac): self — returneaza instanta noua cu MAC setat (immutable)

### 1.4 Message Builder (1 fisier)

**MessageBuilder** (`src/Envelope/MessageBuilder.php`)
- Fluent, immutable builder
- Factory methods:
  - response(string $action): self — pre-configureaza source='server', messageType=RESPONSE
  - request(string $action): self — pre-configureaza source='server', messageType=REQUEST
  - event(string $action): self — pre-configureaza source='server', messageType=EVENT
  - stationRequest(string $action): self — pre-configureaza source='station', messageType=REQUEST
  - stationEvent(string $action): self — pre-configureaza source='station', messageType=EVENT
- Chain methods:
  - withMessageId(MessageId): self
  - correlatedTo(MessageEnvelope $request): self — seteaza messageId sa match request-ul
  - withTimestamp(DateTimeImmutable): self
  - withProtocolVersion(ProtocolVersion): self
  - withPayload(array): self
  - withMac(string): self
- Build:
  - build(): MessageEnvelope — fills defaults (timestamp=now, protocolVersion=1.0.0), valideaza required fields
- NOTA: In CSMS, builder-ul existent are doar response(), request(), event() (toate cu source='server'). Pentru simulator trebuie adaugate stationRequest() si stationEvent().

### 1.5 Crypto (4 fisiere + 2 contracte)

**SigningServiceInterface** (`src/Crypto/Contracts/SigningServiceInterface.php`)
- Contract pentru HMAC-SHA256 message signing (MQTT messages)
- sign(array $payload, string $sessionKey): string
- verify(array $payload, string $mac, string $sessionKey): bool

**EcdsaServiceInterface** (`src/Crypto/Contracts/EcdsaServiceInterface.php`)
- Contract pentru ECDSA P-256 digital signatures (offline passes, receipts)
- Separat de SigningServiceInterface — alt mecanism criptografic, alt use case
- Metode:
  - sign(string $data, string $privateKeyPem): string — semnare raw data, returneaza Base64-encoded DER signature
  - verify(string $data, string $signatureBase64, string $publicKeyPem): bool — verificare semnatura
  - signOfflinePass(array $passData, string $privateKeyPem): string — remove signature/signatureAlgorithm fields, canonicalize cu CanonicalJsonSerializer, sign
  - verifyOfflinePass(array $passData, string $publicKeyPem): bool — extract signature, remove sig fields, canonicalize, verify
  - generateKeyPair(): array{privateKey: string, publicKey: string} — generare pereche chei PEM-encoded (prime256v1 curve)
- Throws: RuntimeException pe chei invalide sau erori OpenSSL
- Dependinta: php-openssl (deja in require)

**EcdsaService** (`src/Crypto/EcdsaService.php`)
- `final class` implementeaza EcdsaServiceInterface
- Constructor: `__construct(private readonly CanonicalJsonSerializer $canonicalJsonSerializer)` — aceeasi dependinta ca MacSigner
- sign(): valideaza ca cheia e EC (OPENSSL_KEYTYPE_EC), foloseste OPENSSL_ALGO_SHA256, returneaza base64_encode(DER)
- verify(): base64_decode strict, openssl_verify(), returneaza result === 1
- signOfflinePass(): unset signature + signatureAlgorithm, canonicalize, delegate la sign()
- verifyOfflinePass(): extract signature string, unset sig fields, canonicalize, delegate la verify()
- generateKeyPair(): openssl_pkey_new cu curve_name=prime256v1, export private + extract public din details

**CanonicalJsonSerializer** (`src/Crypto/CanonicalJsonSerializer.php`)
- serialize(array $data): string
- Reguli:
  - Sortare recursiva chei obiect (ksort)
  - Pastrare ordinea elementelor array (nu sorteaza arrays)
  - JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
  - No whitespace (compact)

**MacSigner** (`src/Crypto/MacSigner.php`)
- Constructor: `__construct(private readonly CanonicalJsonSerializer $serializer)` — dependinta pe CanonicalJsonSerializer injectata
- sign(array $payload, string $sessionKey): string
  1. Remove camp 'mac' din payload (unset)
  2. Serializare cu $this->serializer->serialize($payload)
  3. Base64-decode session key (strict mode, fallback empty string)
  4. HMAC-SHA256(decoded_key, canonical_json) cu hash_hmac('sha256', ..., ..., true) — raw binary
  5. Base64-encode result
- verify(array $payload, string $mac, string $sessionKey): bool
  1. Compute expected MAC via sign()
  2. Base64-decode ambele (received + expected)
  3. Return false daca decode fails
  4. hash_equals(expectedBytes, receivedBytes) — timing-safe comparison pe raw bytes
- canonicalize(array $payload): string — remove 'mac' then delegate la serializer

**CriticalMessageRegistry** (`src/Crypto/CriticalMessageRegistry.php`)
- `final class` cu `private const CRITICAL_ACTIONS` (lista statica)
- Lista celor 14 actiuni critice (semnate in modul 'critical'):
  - Transaction Profile (5): StartService, StopService, ReserveBay, CancelReservation, TransactionEvent
  - Security Profile (2): SecurityEvent, AuthorizeOfflinePass
  - Device Management (3): ChangeConfiguration, Reset, UpdateFirmware
  - Core Profile (1): BootNotification
  - Offline Profile (2): IssueOfflinePass, RevokeOfflinePass
  - Payment Profile (1): WebPaymentAuthorization
- Metode (toate statice):
  - isCritical(string $action): bool — in_array strict
  - allCriticalActions(): array<string> — returneaza lista completa (ATENTIE: nu getCriticalActions())
  - count(): int — returneaza 14

### 1.6 Action Constants (1 fisier)

**OsppAction** (`src/Actions/OsppAction.php`)
- Clasa cu constante string (nu enum, pentru flexibilitate si extensibilitate)
- Total: **24 actiuni** (21 MQTT + 3 API-only)

```
// Core Profile — MQTT (4)
BOOT_NOTIFICATION = 'BootNotification'
HEARTBEAT = 'Heartbeat'
STATUS_NOTIFICATION = 'StatusNotification'
CONNECTION_LOST = 'ConnectionLost'

// Transaction Profile — MQTT (5)
START_SERVICE = 'StartService'
STOP_SERVICE = 'StopService'
RESERVE_BAY = 'ReserveBay'
CANCEL_RESERVATION = 'CancelReservation'
METER_VALUES = 'MeterValues'

// Device Management Profile — Station Events — MQTT (2)
FIRMWARE_STATUS_NOTIFICATION = 'FirmwareStatusNotification'
DIAGNOSTICS_NOTIFICATION = 'DiagnosticsNotification'

// Device Management Profile — Server Commands — MQTT (7)
GET_CONFIGURATION = 'GetConfiguration'
CHANGE_CONFIGURATION = 'ChangeConfiguration'
UPDATE_FIRMWARE = 'UpdateFirmware'
GET_DIAGNOSTICS = 'GetDiagnostics'
RESET = 'Reset'
SET_MAINTENANCE_MODE = 'SetMaintenanceMode'
UPDATE_SERVICE_CATALOG = 'UpdateServiceCatalog'

// Offline Profile — MQTT (2)
AUTHORIZE_OFFLINE_PASS = 'AuthorizeOfflinePass'
TRANSACTION_EVENT = 'TransactionEvent'

// Security Profile — MQTT (1)
SECURITY_EVENT = 'SecurityEvent'

// API-Only Actions (3) — nu trec prin MQTT, sunt pe CriticalMessageRegistry
ISSUE_OFFLINE_PASS = 'IssueOfflinePass'
REVOKE_OFFLINE_PASS = 'RevokeOfflinePass'
WEB_PAYMENT_AUTHORIZATION = 'WebPaymentAuthorization'
```

- Metode statice:
  - all(): array — toate 24 actiunile
  - mqttActions(): array — cele 21 actiuni MQTT
  - apiOnlyActions(): array — cele 3 actiuni API-only
  - stationToServer(): array — actiuni trimise de statie catre server (MQTT inbound)
  - serverToStation(): array — actiuni trimise de server catre statie (MQTT outbound)
  - events(): array — actiuni de tip EVENT (no response): StatusNotification, ConnectionLost, MeterValues, FirmwareStatusNotification, DiagnosticsNotification, SecurityEvent
  - requests(): array — actiuni de tip REQUEST (expect response)
  - isValid(string $action): bool
  - isMqtt(string $action): bool — true daca actiunea trece prin MQTT

### 1.7 State Machine Transitions (5 fisiere)

Tabele de tranzitii pure (fara Eloquent, fara DB). Fiecare expune:
- `getTransitions(): array<string, array<string>>` — from => [to, to, ...]
- `canTransition(from, to): bool`
- `allowedTransitions(from): array` — lista starilor tinta valide
- `transitionCount(): int`
- `getTransitionTable(): array` — tabela completa

**BayTransitions** (`src/StateMachines/BayTransitions.php`)
- 7 stari, 18 tranzitii valide:
  - unknown -> available, faulted, unavailable (3)
  - available -> reserved, occupied, faulted, unavailable (4)
  - reserved -> available, occupied, faulted (3)
  - occupied -> finishing, faulted (2)
  - finishing -> available, faulted (2)
  - faulted -> available, unavailable (2)
  - unavailable -> available, faulted (2)

**SessionTransitions** (`src/StateMachines/SessionTransitions.php`)
- 6 stari, 8 tranzitii valide:
  - pending -> authorized, failed (2)
  - authorized -> active, failed (2)
  - active -> stopping, failed (2)
  - stopping -> completed, failed (2)
  - completed -> (none, terminal)
  - failed -> (none, terminal)
- Timeout constants (secunde):
  - pending: 30s — time to authorize
  - authorized: 30s — time for station to start service
  - active: 3600s (1h) — max session duration
  - stopping: 30s — time for station to stop service
- Aceste constante sunt definite ca `DEFAULT_TIMEOUTS` array in SessionStateMachine din CSMS

**FirmwareTransitions** (`src/StateMachines/FirmwareTransitions.php`)
- 10 stari, 14 tranzitii valide:
  - idle -> downloading (1)
  - downloading -> downloaded, failed (2)
  - downloaded -> verifying, failed (2)
  - verifying -> verified, failed (2)
  - verified -> installing (1)
  - installing -> installed, failed (2)
  - installed -> rebooting, failed (2)
  - rebooting -> activated, failed (2)
  - activated -> (none, terminal)
  - failed -> (none, terminal)
- Server-inferred transitions (nu vin de la statie, serverul le deduce):
  - Downloaded -> Verifying: inferred cand statia incepe self-test
  - Verifying -> Verified: inferred cand self-tests pass
  - Installed -> Rebooting: inferred cand se pierde conexiunea dupa Installed
  - Rebooting -> Activated: pe BootNotification cu firmwareVersion nou
  - Rebooting -> Failed: pe BootNotification cu version veche + bootReason=error_recovery
- Station FirmwareStatusNotification raporteaza doar: Downloading, Downloaded, Installing, Installed, Failed

**DiagnosticsTransitions** (`src/StateMachines/DiagnosticsTransitions.php`)
- 5 stari, 6 tranzitii valide:
  - pending -> collecting, failed (2)
  - collecting -> uploading, failed (2)
  - uploading -> uploaded, failed (2)
  - uploaded -> (none, terminal)
  - failed -> (none, terminal)

**ReservationTransitions** (`src/StateMachines/ReservationTransitions.php`)
- 5 stari, 5 tranzitii valide:
  - pending -> confirmed, cancelled (2)
  - confirmed -> active, expired, cancelled (3)
  - active -> (none, terminal — converted to session)
  - expired -> (none, terminal)
  - cancelled -> (none, terminal)
- TTL constraints: MIN_TTL_MINUTES = 1, MAX_TTL_MINUTES = 15
- Metoda aditionala: isValidTtl(int $ttlMinutes): bool
- Metoda aditionala: getTtlConstraints(): array{min: int, max: int}
- Metoda aditionala: validateBayForReservation(BayStatus): permite doar AVAILABLE, rejecteaza cu coduri specifice (3014 BAY_RESERVED, 3001 BAY_BUSY, 3002 BAY_NOT_READY, 3011 BAY_MAINTENANCE)

### 1.8 Schema Resources (67 fisiere)

Copie din `docs/ospp/schemas/` inclusa in package la `resources/schemas/`.

**Common Schemas (18):**
- timestamp.schema.json, bay-status.schema.json, credit-amount.schema.json
- meter-values.schema.json, station-id.schema.json, bay-id.schema.json
- session-id.schema.json, service-id.schema.json, device-id.schema.json
- reservation-id.schema.json, offline-tx-id.schema.json, offline-pass-id.schema.json
- user-id.schema.json, error-response.schema.json, receipt.schema.json
- service-item.schema.json, mqtt-envelope.schema.json, offline-pass.schema.json

**MQTT Schemas (34 perechi request/response):**
- boot-notification-request/response
- heartbeat-request/response
- status-notification (event, no response)
- security-event (event)
- connection-lost (event)
- meter-values-event (event)
- start-service-request/response
- stop-service-request/response
- reserve-bay-request/response
- cancel-reservation-request/response
- transaction-event-request/response
- authorize-offline-pass-request/response
- change-configuration-request/response
- get-configuration-request/response
- reset-request/response
- set-maintenance-mode-request/response
- update-firmware-request/response
- update-service-catalog-request/response
- get-diagnostics-request/response
- firmware-status-notification (event)
- diagnostics-notification (event)

**BLE Schemas (9):** Incluse dar nefolosite deocamdata (ble_support feature flag).

**SchemaValidator** (`src/Validation/SchemaValidator.php`) — optional:
- validate(string $action, string $direction, array $payload): ValidationResult
- Foloseste `opis/json-schema` sau `justinrainbow/json-schema` (singura dependenta externa, optionala)
- Rezolva schema path: `resources/schemas/mqtt/{action}-{direction}.schema.json`
- Returneaza ValidationResult cu lista de erori per camp

---

## 2. Ce NU se extrage

- Eloquent Models, Repositories (depind de DB)
- Laravel Events, Listeners, Jobs, Middleware
- Action classes (business logic)
- HTTP layer (Controllers, Requests, Resources)
- Config files Laravel (raman per-aplicatie)
- MessageFactory (depinde de config pentru schema path)
- MessageDispatcher (depinde de Laravel container si event system)
- PendingCommandRegistry (depinde de Redis)
- HmacService (wrapper thin, fiecare app isi face propriul)
- SignOutgoingMiddleware / VerifyIncomingMiddleware (depind de Laravel pipeline)

---

## 3. Structura Directorului

```
ospp-sdk-php/
├── composer.json
├── .gitignore
├── README.md
├── src/
│   ├── Actions/
│   │   └── OsppAction.php
│   ├── Enums/
│   │   ├── MessageType.php
│   │   ├── BayStatus.php
│   │   ├── SessionStatus.php
│   │   ├── SessionSource.php
│   │   ├── FirmwareUpdateStatus.php
│   │   ├── DiagnosticsStatus.php
│   │   ├── ReservationStatus.php
│   │   ├── SigningMode.php
│   │   ├── OsppErrorCode.php
│   │   └── Severity.php
│   ├── ValueObjects/
│   │   ├── MessageId.php
│   │   └── ProtocolVersion.php
│   ├── Envelope/
│   │   ├── MessageEnvelope.php
│   │   └── MessageBuilder.php
│   ├── Crypto/
│   │   ├── Contracts/
│   │   │   ├── SigningServiceInterface.php
│   │   │   └── EcdsaServiceInterface.php
│   │   ├── CanonicalJsonSerializer.php
│   │   ├── MacSigner.php
│   │   ├── EcdsaService.php
│   │   └── CriticalMessageRegistry.php
│   ├── StateMachines/
│   │   ├── BayTransitions.php
│   │   ├── SessionTransitions.php
│   │   ├── FirmwareTransitions.php
│   │   ├── DiagnosticsTransitions.php
│   │   └── ReservationTransitions.php
│   └── Validation/
│       ├── SchemaValidator.php
│       └── ValidationResult.php
├── resources/
│   └── schemas/
│       ├── common/ (18 files)
│       └── mqtt/ (34+ files)
├── tests/
│   ├── Unit/
│   │   ├── Enums/
│   │   │   ├── MessageTypeTest.php
│   │   │   ├── BayStatusTest.php
│   │   │   ├── SessionStatusTest.php
│   │   │   └── ...
│   │   ├── ValueObjects/
│   │   │   ├── MessageIdTest.php
│   │   │   └── ProtocolVersionTest.php
│   │   ├── Envelope/
│   │   │   ├── MessageEnvelopeTest.php
│   │   │   └── MessageBuilderTest.php
│   │   ├── Crypto/
│   │   │   ├── CanonicalJsonSerializerTest.php
│   │   │   ├── MacSignerTest.php
│   │   │   ├── EcdsaServiceTest.php
│   │   │   └── CriticalMessageRegistryTest.php
│   │   └── StateMachines/
│   │       ├── BayTransitionsTest.php
│   │       ├── SessionTransitionsTest.php
│   │       ├── FirmwareTransitionsTest.php
│   │       ├── DiagnosticsTransitionsTest.php
│   │       └── ReservationTransitionsTest.php
│   └── phpunit.xml
└── docs/
    └── PLAN.md (this file)
```

---

## 4. Composer Configuration

```json
{
    "name": "ospp/protocol",
    "description": "OSPP v1.0.0 protocol library — enums, envelope, crypto, state machines",
    "type": "library",
    "license": "proprietary",
    "require": {
        "php": "^8.3"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "phpstan/phpstan": "^2.0"
    },
    "suggest": {
        "opis/json-schema": "Required for payload schema validation (^2.3)"
    },
    "autoload": {
        "psr-4": {
            "Ospp\\Protocol\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Ospp\\Protocol\\Tests\\": "tests/"
        }
    }
}
```

---

## 5. Versionare

- Semantic versioning: 1.0.0
- Aliniata cu versiunea protocolului OSPP (v1.0.0-draft.1)
- Consumatorii cer `"ospp/protocol": "^1.0"`
- In development: Composer path repository (`"type": "path", "url": "../ospp-protocol"`)
- In productie: private Packagist sau Satis, sau git repository (`"type": "vcs"`)

---

## 6. Diferente fata de codul CSMS existent

| Aspect | In CSMS Server | In Package |
|--------|---------------|------------|
| ProtocolVersion::default() | Apeleaza config('ospp.default_version') | default(string $version = '1.0.0') — fara config() |
| MessageId::generate() | Illuminate\Support\Str::uuid() | random_bytes(16) + UUID v4 manual — fara Laravel |
| MessageBuilder factory methods | Doar server (response, request, event) | + stationRequest(), stationEvent() |
| CriticalMessageRegistry | allCriticalActions() + count() | Identic — metode statice, naming pastrat |
| MacSigner | Constructor ia CanonicalJsonSerializer | Identic — DI prin constructor |
| StateMachines | Clase cu logica Eloquent | Pure transition tables (no DB) |
| OsppErrorCode | Foloseste App\Shared\Enums\Severity | Severity inclus in package |
| SchemaValidator | Integrat in MessageFactory | Standalone, optional dependency |
| Namespace | App\Shared\... | Ospp\Protocol\... |
| Dependente | Laravel framework | Zero |

---

## 7. Strategia de integrare in CSMS Server

Faza 1 (acum): Package-ul exista standalone. Station Simulator si CSMS Simulator il folosesc.
Faza 2 (ulterior): CSMS Server adauga dependency pe package. Clasele duplicate din app/Shared/ se inlocuiesc cu use statements.
- Aceasta migrare se face incremental, enum by enum, class by class
- Testele existente (2387) valideaza ca nimic nu se rupe
- Backward compatibility: namespace-uri vechi pot ramane ca alias-uri temporare

---

## 8. Test Coverage Target

- 100% pe enums (toate 10: valorile, toate metodele, edge cases)
  - OsppErrorCode: testeaza category(), severity(), isRecoverable(), errorText(), httpStatus() per fiecare categorie
  - Severity: testeaza isActionRequired() pentru fiecare nivel
  - ReservationStatus: testeaza isTerminal(), isCancellable(), isConvertible(), holdsBay(), triggersRefund()
- 100% pe value objects (constructors, factory methods, equals(), edge cases)
  - MessageId: generate cu fiecare prefix, fromString, equals, invalid prefixes, empty string
  - ProtocolVersion: fromString, default(), equals(), isCompatibleWith(), invalid formats, negative components
- 100% pe MessageEnvelope (toArray, toJson, withMac, isSigned, etc.)
- 100% pe MessageBuilder (toate factory methods inclusiv stationRequest/stationEvent, build validation)
- 100% pe crypto (sign, verify, canonicalize, timing-safe comparison, MacSigner cu CanonicalJsonSerializer injectat)
  - EcdsaService: sign/verify raw data, signOfflinePass/verifyOfflinePass (cu canonicalize), generateKeyPair, invalid key handling
  - CriticalMessageRegistry: allCriticalActions(), count(), isCritical() pentru fiecare actiune
- 100% pe state machines (toate 5: tranzitiile valide si invalide, transitionCount(), TTL validation pe ReservationTransitions)
- Optional: schema validation tests cu fixtures din resources/schemas/

---

## 9. Validare Mentala

- [x] Toate 24 actiuni acoperite prin OsppAction constants (21 MQTT + 3 API-only)
- [x] Toate 10 enums cu toate valorile si metodele (inclusiv ReservationStatus, OsppErrorCode, Severity)
- [x] MessageEnvelope — immutable, serializable, cu mac support, fara correlationId (se refoloseste messageId)
- [x] MessageBuilder — fluent, immutable, cu factory methods pentru ambele directii (server + station)
- [x] Value objects cu equals() — MessageId si ProtocolVersion au ambele comparatie explicita
- [x] Crypto complet: HMAC-SHA256 (MacSigner) + ECDSA P-256 (EcdsaService), canonical JSON, ambele cu CanonicalJsonSerializer injectat, critical registry (14 actiuni)
- [x] CriticalMessageRegistry: allCriticalActions() (nu getCriticalActions), count(), isCritical() — toate statice
- [x] State machines: bay (18), session (8), firmware (14), diagnostics (6), reservation (5) — total 51 tranzitii
- [x] Session timeouts documentate: 30s/30s/3600s/30s (pending/authorized/active/stopping)
- [x] Reservation TTL constraints: 1-15 minute, cu validateBayForReservation
- [x] Zero dependente Laravel — MessageId::generate() foloseste random_bytes, nu Str::uuid()
- [x] ProtocolVersion::default() primeste string param, nu apeleaza config()
- [x] PHP 8.3 features: readonly classes, enums, named args
- [x] Schema validation optional (nu forteaza dependenta)
- [x] OsppErrorCode: 80 coduri, 6 categorii, cu severity/recoverability/httpStatus — complet per spec
- [x] Backward compatible cu CSMS Server existent (namespace diferit, integrare incrementala)
