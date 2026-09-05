  # OSPP SDK PHP

  PHP SDK for the **Open Self-Service Point Protocol (OSPP)** — a communication protocol for self-service station management systems.

  This package provides the shared protocol layer used by CSMS servers, station simulators, and testing tools.

  ## Requirements

  - PHP 8.3+
  - ext-json
  - ext-openssl (optional — required only for ECDSA offline pass signing)

  ## Installation

  ```bash
  composer require ospp/protocol

  For private repositories, add the VCS source first:

  {
      "repositories": [
          {
              "type": "vcs",
              "url": "git@github.com:ospp-org/ospp-sdk-php.git"
          }
      ]
  }

  What's Included

  ┌──────────────┬────────────────────────────────────────────────────────────────────────────────────────────────────────────────┐
  │    Module    │                                                  Description                                                   │
  ├──────────────┼────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
  │ Enums        │ MessageType, SessionSource, BayStatus, SessionStatus, Severity, SigningMode, OsppErrorCode (119 codes),        │
  │              │ FirmwareUpdateStatus, DiagnosticsStatus, ReservationStatus, BootNotificationStatus, BootReason,               │
  │              │ NetworkConnectionType, TransactionEventStatus, ChangeConfigResultStatus, DataTransferStatus,                  │
  │              │ TriggerMessageStatus, CertificateType, ResetType, SecurityEventType, StationConnectivity,                    │
  │              │ BleServiceStatus, PricingType, LogLevel, SessionEndReason, ConfigurationKey (29 keys with metadata)            │
  ├──────────────┼────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
  │ State        │ Transition tables for Station (6 states), Bay (7 states), Session (6 states), Firmware (10 states),            │
  │ Machines     │ Diagnostics (5 states), Reservation (5 states)                                                                 │
  ├──────────────┼────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
  │ Envelope     │ MessageEnvelope, MessageBuilder — wire-format message construction with correlation support                    │
  ├──────────────┼────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
  │ Crypto       │ HMAC-SHA256 message signing (MacSigner), ECDSA P-256 offline pass signing, canonical JSON serialization,       │
  │              │ MessageSigningRegistry (3 structural signing exemptions), SessionProofCalculator (BLE session proof)          │
  ├──────────────┼────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
  │ Value        │ MessageId (UUID v4), ProtocolVersion (semver)                                                                  │
  │ Objects      │                                                                                                                │
  ├──────────────┼────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
  │ Actions      │ OsppAction — all 30 protocol actions (27 MQTT + 3 API-only) with validation                                   │
  ├──────────────┼────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
  │ JSON         │ 86 schema files (ble, common, mqtt, the root) accessible via SchemaPath::directory()                          │
  │ Schemas      │                                                                                                                │
  └──────────────┴────────────────────────────────────────────────────────────────────────────────────────────────────────────────┘

  Quick Start

  Build and sign a message

  use Ospp\Protocol\Envelope\MessageBuilder;
  use Ospp\Protocol\Crypto\MacSigner;

  $envelope = MessageBuilder::request('StartService')
      ->withPayload(['bayId' => 'bay-1', 'userId' => 'user-123'])
      ->build();

  $signer = new MacSigner($sessionKey);
  $signed = $envelope->withMac($signer->sign($envelope->payload));

  $json = $signed->toJson();

  Check state transitions

  use Ospp\Protocol\Enums\SessionStatus;
  use Ospp\Protocol\StateMachines\SessionTransitions;

  $sessions = new SessionTransitions();
  $allowed = $sessions->canTransition(SessionStatus::PENDING, SessionStatus::AUTHORIZED); // true
  $timeout = $sessions->getTimeout(SessionStatus::ACTIVE); // 3600

  Wire format conversion

  use Ospp\Protocol\Enums\BayStatus;

  $status = BayStatus::fromOspp('Available'); // BayStatus::AVAILABLE
  $wire = BayStatus::OCCUPIED->toOspp();      // 'Occupied'

  Access JSON Schemas

  use Ospp\Protocol\SchemaPath;

  $schemasDir = SchemaPath::directory();
  $bootSchema = json_decode(file_get_contents($schemasDir . '/mqtt/boot-notification-request.schema.json'), true);

  Architecture

  - Zero external dependencies — only PHP extensions (json, openssl)
  - Pure PHP 8.3 — readonly classes, enums, match expressions, named arguments
  - Immutable — all DTOs and value objects are final readonly
  - Framework-agnostic — no Laravel, Symfony, or other framework dependency
  - PSR-4 autoloading — Ospp\Protocol\ namespace

  Testing

  composer install
  vendor/bin/phpunit

  4 test suites:

  ┌─────────────┬───────────────────────────────────────┐
  │    Suite    │                Purpose                │
  ├─────────────┼───────────────────────────────────────┤
  │ Unit        │ Individual class behavior             │
  ├─────────────┼───────────────────────────────────────┤
  │ Regression  │ Pins previously found bugs            │
  ├─────────────┼───────────────────────────────────────┤
  │ Contract    │ Behavioral alignment with CSMS server │
  ├─────────────┼───────────────────────────────────────┤
  │ Integration │ Cross-component workflows             │
  └─────────────┴───────────────────────────────────────┘

  The per-suite test counts are deliberately not printed here. They changed on
  every commit and nothing compared them, so they rotted: this table read
  478/10/153/27 against an actual 482/10/776/26, and the total said 668 against
  1294. A number that must be re-derived by hand on every push is not
  documentation, it is a second place to be wrong.

  Static analysis:

  vendor/bin/phpstan analyse --level=9 src/

  License

  MIT
