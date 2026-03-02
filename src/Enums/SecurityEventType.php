<?php

declare(strict_types=1);

namespace Ospp\Protocol\Enums;

enum SecurityEventType: string
{
    case MAC_VERIFICATION_FAILURE = 'MacVerificationFailure';
    case CERTIFICATE_ERROR = 'CertificateError';
    case UNAUTHORIZED_ACCESS = 'UnauthorizedAccess';
    case OFFLINE_PASS_REJECTED = 'OfflinePassRejected';
    case TAMPER_DETECTED = 'TamperDetected';
    case BRUTE_FORCE_ATTEMPT = 'BruteForceAttempt';
    case FIRMWARE_INTEGRITY_FAILURE = 'FirmwareIntegrityFailure';
    case FIRMWARE_DOWNGRADE_ATTEMPT = 'FirmwareDowngradeAttempt';
    case HARDWARE_FAULT = 'HardwareFault';
    case SOFTWARE_FAULT = 'SoftwareFault';
    case CLOCK_SKEW = 'ClockSkew';
}
