<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\ConfigurationKey;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigurationKeyTest extends TestCase
{
    #[Test]
    public function it_has_exactly_29_cases(): void
    {
        self::assertCount(29, ConfigurationKey::cases());
    }

    // =========================================================================
    // Profile distribution
    // =========================================================================

    #[Test]
    public function core_profile_has_9_keys(): void
    {
        $count = $this->countByProfile('Core');
        self::assertSame(9, $count);
    }

    #[Test]
    public function transaction_profile_has_6_keys(): void
    {
        $count = $this->countByProfile('Transaction');
        self::assertSame(6, $count);
    }

    #[Test]
    public function security_profile_has_6_keys(): void
    {
        $count = $this->countByProfile('Security');
        self::assertSame(6, $count);
    }

    #[Test]
    public function offline_profile_has_4_keys(): void
    {
        $count = $this->countByProfile('Offline');
        self::assertSame(4, $count);
    }

    #[Test]
    public function device_management_profile_has_4_keys(): void
    {
        $count = $this->countByProfile('DeviceManagement');
        self::assertSame(4, $count);
    }

    #[Test]
    public function profile_counts_sum_to_29(): void
    {
        $sum = $this->countByProfile('Core')
            + $this->countByProfile('Transaction')
            + $this->countByProfile('Security')
            + $this->countByProfile('Offline')
            + $this->countByProfile('DeviceManagement');

        self::assertSame(29, $sum);
    }

    // =========================================================================
    // type()
    // =========================================================================

    #[Test]
    public function type_returns_valid_type_for_all_keys(): void
    {
        $validTypes = ['string', 'integer', 'boolean'];

        foreach (ConfigurationKey::cases() as $key) {
            self::assertContains(
                $key->type(),
                $validTypes,
                "{$key->name} has unexpected type '{$key->type()}'",
            );
        }
    }

    #[Test]
    public function string_typed_keys(): void
    {
        self::assertSame('string', ConfigurationKey::STATION_NAME->type());
        self::assertSame('string', ConfigurationKey::TIME_ZONE->type());
        self::assertSame('string', ConfigurationKey::PROTOCOL_VERSION->type());
        self::assertSame('string', ConfigurationKey::FIRMWARE_VERSION->type());
        self::assertSame('string', ConfigurationKey::CERTIFICATE_SERIAL_NUMBER->type());
        self::assertSame('string', ConfigurationKey::MESSAGE_SIGNING_MODE->type());
        self::assertSame('string', ConfigurationKey::OFFLINE_PASS_PUBLIC_KEY->type());
        self::assertSame('string', ConfigurationKey::DIAGNOSTICS_UPLOAD_URL->type());
        self::assertSame('string', ConfigurationKey::LOG_LEVEL->type());
    }

    #[Test]
    public function boolean_typed_keys(): void
    {
        self::assertSame('boolean', ConfigurationKey::AUTHORIZATION_CACHE_ENABLED->type());
        self::assertSame('boolean', ConfigurationKey::CERTIFICATE_RENEWAL_ENABLED->type());
        self::assertSame('boolean', ConfigurationKey::OFFLINE_MODE_ENABLED->type());
        self::assertSame('boolean', ConfigurationKey::FIRMWARE_UPDATE_ENABLED->type());
        self::assertSame('boolean', ConfigurationKey::AUTO_REBOOT_ENABLED->type());
    }

    #[Test]
    public function integer_typed_keys(): void
    {
        self::assertSame('integer', ConfigurationKey::HEARTBEAT_INTERVAL_SECONDS->type());
        self::assertSame('integer', ConfigurationKey::CONNECTION_TIMEOUT->type());
        self::assertSame('integer', ConfigurationKey::MAX_SESSION_DURATION_SECONDS->type());
        self::assertSame('integer', ConfigurationKey::METER_VALUES_INTERVAL->type());
        self::assertSame('integer', ConfigurationKey::RESERVATION_DEFAULT_TTL->type());
    }

    // =========================================================================
    // defaultValue()
    // =========================================================================

    #[Test]
    public function default_values_for_core_profile(): void
    {
        self::assertSame(30, ConfigurationKey::HEARTBEAT_INTERVAL_SECONDS->defaultValue());
        self::assertSame(60, ConfigurationKey::CONNECTION_TIMEOUT->defaultValue());
        self::assertSame('UTC', ConfigurationKey::TIME_ZONE->defaultValue());
        self::assertSame('0.3.0', ConfigurationKey::PROTOCOL_VERSION->defaultValue());
    }

    #[Test]
    public function default_values_for_transaction_profile(): void
    {
        self::assertSame(60, ConfigurationKey::METER_VALUES_INTERVAL->defaultValue());
        self::assertSame(900, ConfigurationKey::MAX_SESSION_DURATION_SECONDS->defaultValue());
        self::assertSame(100, ConfigurationKey::DEFAULT_CREDITS_PER_SESSION->defaultValue());
    }

    #[Test]
    public function default_values_for_security_profile(): void
    {
        self::assertTrue(ConfigurationKey::AUTHORIZATION_CACHE_ENABLED->defaultValue());
        self::assertSame('All', ConfigurationKey::MESSAGE_SIGNING_MODE->defaultValue());
    }

    #[Test]
    public function null_default_for_keys_without_defaults(): void
    {
        self::assertNull(ConfigurationKey::FIRMWARE_VERSION->defaultValue());
        self::assertNull(ConfigurationKey::CERTIFICATE_SERIAL_NUMBER->defaultValue());
        self::assertNull(ConfigurationKey::OFFLINE_PASS_PUBLIC_KEY->defaultValue());
    }

    // =========================================================================
    // access()
    // =========================================================================

    #[Test]
    public function access_returns_valid_level_for_all_keys(): void
    {
        $validAccess = ['R', 'W', 'RW'];

        foreach (ConfigurationKey::cases() as $key) {
            self::assertContains(
                $key->access(),
                $validAccess,
                "{$key->name} has unexpected access '{$key->access()}'",
            );
        }
    }

    #[Test]
    public function read_only_keys(): void
    {
        self::assertSame('R', ConfigurationKey::PROTOCOL_VERSION->access());
        self::assertSame('R', ConfigurationKey::FIRMWARE_VERSION->access());
        self::assertSame('R', ConfigurationKey::CERTIFICATE_SERIAL_NUMBER->access());
    }

    #[Test]
    public function write_only_keys(): void
    {
        self::assertSame('W', ConfigurationKey::OFFLINE_PASS_PUBLIC_KEY->access());
    }

    #[Test]
    public function read_write_is_default(): void
    {
        self::assertSame('RW', ConfigurationKey::HEARTBEAT_INTERVAL_SECONDS->access());
        self::assertSame('RW', ConfigurationKey::MAX_SESSION_DURATION_SECONDS->access());
        self::assertSame('RW', ConfigurationKey::AUTHORIZATION_CACHE_ENABLED->access());
    }

    // =========================================================================
    // isMutable()
    // =========================================================================

    /**
     * The keys 08-configuration.md marks `Static`. TRANSCRIBED FROM THE SPEC TABLE,
     * not read off `isMutable()` — which is the whole point of the list existing.
     *
     * The previous version of this test named six of them and passed for four minor
     * releases while the enum was wrong, because the six it named were the six the
     * enum happened to return `false` for. It was written from the implementation, so
     * it could only ever confirm the implementation; the seventh key —
     * `MessageSigningMode`, the one whose Static-ness the spec sets in bold and spends
     * a paragraph defending — was absent from both, and the test could not see the gap
     * it shared.
     *
     * If a key is added to or removed from Chapter 08's Static set, EDIT THIS LIST
     * from the spec and let the assertions below fail until the enum follows. Do not
     * derive it from `cases()`, and do not "sync" it with `isMutable()`.
     *
     * `scripts/check-config-registry.php` checks the same thing against the spec text
     * directly and is the stronger guard; this test is what fails first, locally, and
     * without a spec checkout.
     *
     * @var list<ConfigurationKey>
     */
    private const SPEC_STATIC_KEYS = [
        ConfigurationKey::STATION_NAME,
        ConfigurationKey::TIME_ZONE,
        ConfigurationKey::PROTOCOL_VERSION,
        ConfigurationKey::FIRMWARE_VERSION,
        ConfigurationKey::CERTIFICATE_SERIAL_NUMBER,
        ConfigurationKey::MESSAGE_SIGNING_MODE,
        ConfigurationKey::DIAGNOSTICS_UPLOAD_URL,
    ];

    #[Test]
    public function static_keys_are_not_mutable(): void
    {
        self::assertCount(7, self::SPEC_STATIC_KEYS, 'Chapter 08 marks seven keys Static');

        foreach (self::SPEC_STATIC_KEYS as $key) {
            self::assertFalse($key->isMutable(), "{$key->value} is Static in 08-configuration.md");
        }
    }

    #[Test]
    public function every_other_key_is_mutable(): void
    {
        // The complement, asserted rather than sampled. Naming four Dynamic keys by
        // hand is what let a Static key hide in the unnamed remainder — a key can only
        // be missed here if it is missed in BOTH lists, and the count above forbids it.
        foreach (ConfigurationKey::cases() as $key) {
            if (in_array($key, self::SPEC_STATIC_KEYS, true)) {
                continue;
            }

            self::assertTrue($key->isMutable(), "{$key->value} is Dynamic in 08-configuration.md");
        }
    }

    #[Test]
    public function message_signing_mode_is_static_and_the_package_agrees_with_itself(): void
    {
        // The regression this pair exists for. isMutable() returned true from its
        // default arm while Enums\SigningMode's docblock said "The mode is `Static`",
        // so the package contradicted itself across two files and neither was checked
        // against the spec that settles it.
        self::assertFalse(ConfigurationKey::MESSAGE_SIGNING_MODE->isMutable());
        self::assertSame('Security', ConfigurationKey::MESSAGE_SIGNING_MODE->profile());
    }

    // =========================================================================
    // Wire format
    // =========================================================================

    #[Test]
    public function values_are_pascal_case_strings(): void
    {
        foreach (ConfigurationKey::cases() as $key) {
            self::assertMatchesRegularExpression(
                '/^[A-Z]/',
                $key->value,
                "{$key->name} value should start with uppercase",
            );
        }
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(ConfigurationKey::HEARTBEAT_INTERVAL_SECONDS, ConfigurationKey::from('HeartbeatIntervalSeconds'));
        self::assertSame(ConfigurationKey::PROTOCOL_VERSION, ConfigurationKey::from('ProtocolVersion'));
        self::assertSame(ConfigurationKey::HEARTBEAT_INTERVAL_SECONDS, ConfigurationKey::from('HeartbeatIntervalSeconds'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(ConfigurationKey::tryFrom('HEARTBEAT_INTERVAL'));
        self::assertNull(ConfigurationKey::tryFrom('invalid'));
        self::assertNull(ConfigurationKey::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        ConfigurationKey::from('invalid');
    }

    private function countByProfile(string $profile): int
    {
        return count(array_filter(
            ConfigurationKey::cases(),
            fn (ConfigurationKey $key) => $key->profile() === $profile,
        ));
    }
}
