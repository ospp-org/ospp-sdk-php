<?php

declare(strict_types=1);

namespace Ospp\Protocol\StateMachines;

use Ospp\Protocol\Enums\BayStatus;
use Ospp\Protocol\Enums\ReservationStatus;

final class ReservationTransitions
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['active', 'expired', 'cancelled'],
        'active' => [],
        'expired' => [],
        'cancelled' => [],
    ];

    private const MIN_TTL_MINUTES = 1;

    private const MAX_TTL_MINUTES = 15;

    public function canTransition(ReservationStatus $from, ReservationStatus $to): bool
    {
        $allowed = self::TRANSITIONS[$from->value];

        return in_array($to->value, $allowed, true);
    }

    /**
     * @return list<ReservationStatus>
     */
    public function allowedTransitions(ReservationStatus $from): array
    {
        $allowed = self::TRANSITIONS[$from->value];

        return array_map(
            fn (string $s) => ReservationStatus::from($s),
            $allowed,
        );
    }

    /**
     * Validate if a bay can accept a reservation.
     *
     * @return array{allowed: bool, errorCode: int|null, errorText: string|null}
     */
    public function validateBayForReservation(BayStatus $bayStatus): array
    {
        return match ($bayStatus) {
            BayStatus::AVAILABLE => ['allowed' => true, 'errorCode' => null, 'errorText' => null],
            BayStatus::RESERVED => ['allowed' => false, 'errorCode' => 3014, 'errorText' => 'BAY_RESERVED'],
            BayStatus::OCCUPIED => ['allowed' => false, 'errorCode' => 3001, 'errorText' => 'BAY_BUSY'],
            BayStatus::FINISHING => ['allowed' => false, 'errorCode' => 3002, 'errorText' => 'BAY_NOT_READY'],
            BayStatus::FAULTED => ['allowed' => false, 'errorCode' => 3002, 'errorText' => 'BAY_NOT_READY'],
            BayStatus::UNAVAILABLE => ['allowed' => false, 'errorCode' => 3011, 'errorText' => 'BAY_MAINTENANCE'],
            BayStatus::UNKNOWN => ['allowed' => false, 'errorCode' => 3002, 'errorText' => 'BAY_NOT_READY'],
        };
    }

    public function isValidTtl(int $ttlMinutes): bool
    {
        return $ttlMinutes >= self::MIN_TTL_MINUTES && $ttlMinutes <= self::MAX_TTL_MINUTES;
    }

    /**
     * @return array{min: int, max: int}
     */
    public function getTtlConstraints(): array
    {
        return [
            'min' => self::MIN_TTL_MINUTES,
            'max' => self::MAX_TTL_MINUTES,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function getTransitionTable(): array
    {
        return self::TRANSITIONS;
    }

    public function transitionCount(): int
    {
        $count = 0;
        foreach (self::TRANSITIONS as $targets) {
            $count += count($targets);
        }

        return $count;
    }
}
