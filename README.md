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
  │ Enums        │ BayStatus, SessionStatus, FirmwareUpdateStatus, DiagnosticsStatus, ReservationStatus, OsppErrorCode,           │
  │              │ SigningMode, Severity, SessionSource, MessageType                                                              │
  ├──────────────┼────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
  │ State        │ Transition tables for Bay (7 states), Session (6 states), Firmware (10 states), Diagnostics (5 states),        │
  │ Machines     │ Reservation (5 states)                                                                                         │
  ├──────────────┼────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
  │ Envelope     │ MessageEnvelope, MessageBuilder — wire-format message construction with correlation support                    │
  ├──────────────┼────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
  │ Crypto       │ HMAC-SHA256 message signing (MacSigner), ECDSA P-256 offline pass signing, canonical JSON serialization,       │
  │              │ critical message registry                                                                                      │
  ├──────────────┼────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
  │ Value        │ MessageId (UUID v4), ProtocolVersion (semver)                                                                  │
  │ Objects      │                                                                                                                │
  ├──────────────┼────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
  │ Actions      │ OsppAction — all 24 protocol actions with validation                                                           │
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

  $allowed = SessionTransitions::canTransition('pending', 'authorized'); // true
  $timeout = SessionTransitions::timeout('active'); // 3600

  Wire format conversion

  use Ospp\Protocol\Enums\BayStatus;

  $status = BayStatus::fromOspp('Available'); // BayStatus::AVAILABLE
  $wire = BayStatus::OCCUPIED->toOspp();      // 'Occupied'

  Architecture

  - Zero external dependencies — only PHP extensions (json, openssl)
  - Pure PHP 8.3 — readonly classes, enums, match expressions, named arguments
  - Immutable — all DTOs and value objects are final readonly
  - Framework-agnostic — no Laravel, Symfony, or other framework dependency
  - PSR-4 autoloading — Ospp\Protocol\ namespace

  Testing

  composer install
  vendor/bin/phpunit

  528 tests across 4 test suites:

  ┌─────────────┬───────┬───────────────────────────────────────┐
  │    Suite    │ Tests │                Purpose                │
  ├─────────────┼───────┼───────────────────────────────────────┤
  │ Unit        │ 343   │ Individual class behavior             │
  ├─────────────┼───────┼───────────────────────────────────────┤
  │ Regression  │ 10    │ Pins previously found bugs            │
  ├─────────────┼───────┼───────────────────────────────────────┤
  │ Contract    │ 148   │ Behavioral alignment with CSMS server │
  ├─────────────┼───────┼───────────────────────────────────────┤
  │ Integration │ 27    │ Cross-component workflows             │
  └─────────────┴───────┴───────────────────────────────────────┘

  Static analysis:

  vendor/bin/phpstan analyse --level=9 src/

  License

  MIT
