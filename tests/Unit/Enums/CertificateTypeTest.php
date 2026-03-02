<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Enums;

use Ospp\Protocol\Enums\CertificateType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CertificateTypeTest extends TestCase
{
    #[Test]
    public function it_has_exactly_two_cases(): void
    {
        self::assertCount(2, CertificateType::cases());
    }

    #[Test]
    public function all_cases_have_correct_values(): void
    {
        self::assertSame('StationCertificate', CertificateType::STATION_CERTIFICATE->value);
        self::assertSame('MQTTClientCertificate', CertificateType::MQTT_CLIENT_CERTIFICATE->value);
    }

    #[Test]
    public function it_can_be_created_from_valid_string(): void
    {
        self::assertSame(CertificateType::STATION_CERTIFICATE, CertificateType::from('StationCertificate'));
        self::assertSame(CertificateType::MQTT_CLIENT_CERTIFICATE, CertificateType::from('MQTTClientCertificate'));
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        self::assertNull(CertificateType::tryFrom('STATION_CERTIFICATE'));
        self::assertNull(CertificateType::tryFrom('invalid'));
        self::assertNull(CertificateType::tryFrom(''));
    }

    #[Test]
    public function it_throws_for_invalid_string_with_from(): void
    {
        $this->expectException(\ValueError::class);
        CertificateType::from('invalid');
    }
}
