<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Enums;

use Ospp\Protocol\Enums\BootReason;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `bootReason` gains two values, and Reset loses its type enum entirely.
 *
 * Mirrored by sdk-ts tests/enums/BootReasonAndReset.test.ts.
 */
final class BootReasonAndResetContractTest extends TestCase
{
    /**
     * boot-notification.md §3: "One of: `PowerOn`, `Watchdog`, `FirmwareUpdate`,
     * `RemoteReset`, `ManualReset`, `ScheduledReset`, `ErrorRecovery`,
     * `Reconnect`. The first seven name an actual boot; `Reconnect` says none
     * occurred."
     */
    #[Test]
    public function bootReasonHasTheEightSpecValuesInSpecOrder(): void
    {
        self::assertSame(
            ['PowerOn', 'Watchdog', 'FirmwareUpdate', 'RemoteReset', 'ManualReset', 'ScheduledReset', 'ErrorRecovery', 'Reconnect'],
            array_map(fn (BootReason $r) => $r->value, BootReason::cases()),
        );
    }

    /**
     * reset.md §5 rule 6: after restarting the station "MUST send a
     * BootNotification with `bootReason: "RemoteReset"` -- the value that says
     * the server asked for this return, distinguishing it from a spontaneous one."
     */
    #[Test]
    public function remoteResetExists(): void
    {
        self::assertSame('RemoteReset', BootReason::REMOTE_RESET->value);
    }

    /**
     * boot-notification.md §5.2: "`Reconnect` is the value for that case, and it
     * is the only member that does not name a boot."
     */
    #[Test]
    public function reconnectIsTheOnlyMemberThatDoesNotNameABoot(): void
    {
        self::assertFalse(BootReason::RECONNECT->namesAnActualBoot());

        foreach (BootReason::cases() as $reason) {
            if ($reason !== BootReason::RECONNECT) {
                self::assertTrue(
                    $reason->namesAnActualBoot(),
                    "{$reason->value} names an actual boot",
                );
            }
        }
    }

    /**
     * Reset: "`Hard`/`Soft` are gone. One reboot operation remains, carrying an
     * optional `force`." The enum that carried them is deleted, not narrowed --
     * there is no remaining value for it to hold.
     */
    #[Test]
    public function resetTypeEnumNoLongerExists(): void
    {
        self::assertFalse(
            enum_exists('Ospp\\Protocol\\Enums\\ResetType'),
            'ResetType must be deleted: reset carries `force`, not a type',
        );
    }
}
