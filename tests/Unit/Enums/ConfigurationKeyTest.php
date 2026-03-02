<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\ConfigurationKey;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigurationKeyTest extends TestCase
{
    #[Test]
    public function it_has_exactly_41_cases(): void
    {
        self::assertCount(41, ConfigurationKey::cases());
    }

    // =========================================================================
    // Profile distribution
    // =========================================================================

    #[Test]
    public function core_profile_has_12_keys(): void
    {
        $count = $this->countByProfile('Core');
        self::assertSame(12, $count);
    }

    #[Test]
    public function transaction_profile_has_6_keys(): void
    {
        $count = $this->countByProfile('Transaction');
        self::assertSame(6, $count);
    }

    #[Test]
    public function security_profile_has_7_keys(): void
    {
        $count = $this->countByProfile('Security');
        self::assertSame(7, $count);
    }

    #[Test]
    public function offline_profile_has_12_keys(): void
    {
        $count = $this->countByProfile('Offline');
        self::assertSame(12, $count);
    }

    #[Test]
    public function device_management_profile_has_4_keys(): void
    {
        $count = $this->countByProfile('DeviceManagement');
        self::assertSame(4, $count);
    }

    #[Test]
    public function profile_counts_sum_to_41(): void
    {
        $sum = $this->countByProfile('Core')
            + $this->countByProfile('Transaction')
            + $this->countByProfile('Security')
            + $this->countByProfile('Offline')
            + $this->countByProfile('DeviceManagement');

        self::assertSame(41, $sum);
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
        self::assertSame('string', ConfigurationKey::LOCALE->type());
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
        self::assertSame('boolean', ConfigurationKey::BLE_ADVERTISING_ENABLED->type());
        self::assertSame('boolean', ConfigurationKey::FIRMWARE_UPDATE_ENABLED->type());
        self::assertSame('boolean', ConfigurationKey::AUTO_REBOOT_ENABLED->type());
    }

    #[Test]
    public function integer_typed_keys(): void
    {
        self::assertSame('integer', ConfigurationKey::HEARTBEAT_INTERVAL_SECONDS->type());
        self::assertSame('integer', ConfigurationKey::CONNECTION_TIMEOUT->type());
        self::assertSame('integer', ConfigurationKey::MAX_SESSION_DURATION_SECONDS->type());
        self::assertSame('integer', ConfigurationKey::SECURITY_PROFILE->type());
        self::assertSame('integer', ConfigurationKey::BLE_TX_POWER->type());
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
        self::assertSame('0.1.0', ConfigurationKey::PROTOCOL_VERSION->defaultValue());
        self::assertSame('en-US', ConfigurationKey::LOCALE->defaultValue());
    }

    #[Test]
    public function default_values_for_transaction_profile(): void
    {
        self::assertSame(15, ConfigurationKey::METER_VALUES_INTERVAL->defaultValue());
        self::assertSame(600, ConfigurationKey::MAX_SESSION_DURATION_SECONDS->defaultValue());
        self::assertSame(100, ConfigurationKey::DEFAULT_CREDITS_PER_SESSION->defaultValue());
    }

    #[Test]
    public function default_values_for_security_profile(): void
    {
        self::assertSame(2, ConfigurationKey::SECURITY_PROFILE->defaultValue());
        self::assertTrue(ConfigurationKey::AUTHORIZATION_CACHE_ENABLED->defaultValue());
        self::assertSame('Critical', ConfigurationKey::MESSAGE_SIGNING_MODE->defaultValue());
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
        self::assertSame('RW', ConfigurationKey::BLE_ADVERTISING_ENABLED->access());
    }

    // =========================================================================
    // isMutable()
    // =========================================================================

    #[Test]
    public function static_keys_are_not_mutable(): void
    {
        self::assertFalse(ConfigurationKey::STATION_NAME->isMutable());
        self::assertFalse(ConfigurationKey::TIME_ZONE->isMutable());
        self::assertFalse(ConfigurationKey::PROTOCOL_VERSION->isMutable());
        self::assertFalse(ConfigurationKey::FIRMWARE_VERSION->isMutable());
        self::assertFalse(ConfigurationKey::SECURITY_PROFILE->isMutable());
        self::assertFalse(ConfigurationKey::CERTIFICATE_SERIAL_NUMBER->isMutable());
        self::assertFalse(ConfigurationKey::DIAGNOSTICS_UPLOAD_URL->isMutable());
    }

    #[Test]
    public function dynamic_keys_are_mutable(): void
    {
        self::assertTrue(ConfigurationKey::HEARTBEAT_INTERVAL_SECONDS->isMutable());
        self::assertTrue(ConfigurationKey::MAX_SESSION_DURATION_SECONDS->isMutable());
        self::assertTrue(ConfigurationKey::AUTHORIZATION_CACHE_ENABLED->isMutable());
        self::assertTrue(ConfigurationKey::BLE_ADVERTISING_ENABLED->isMutable());
        self::assertTrue(ConfigurationKey::LOG_LEVEL->isMutable());
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
        self::assertSame(ConfigurationKey::BLE_ADVERTISING_ENABLED, ConfigurationKey::from('BLEAdvertisingEnabled'));
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
