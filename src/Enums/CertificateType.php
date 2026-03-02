<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum CertificateType: string
{
    case STATION_CERTIFICATE = 'StationCertificate';
    case MQTT_CLIENT_CERTIFICATE = 'MQTTClientCertificate';
}
