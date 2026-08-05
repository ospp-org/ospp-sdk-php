<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Crypto;

use Ospp\Protocol\Crypto\CanonicalJsonSerializer;
use Ospp\Protocol\Crypto\MacSigner;
use Ospp\Protocol\Crypto\MessageSigningRegistry;
use Ospp\Protocol\Enums\MessageType;
use Ospp\Protocol\Enums\SigningMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Everything on the wire is signed — spec/06-security.md §5.1, §5.6, §5.7.
 *
 * Mirrored by sdk-ts tests/crypto/EverythingIsSigned.test.ts.
 */
final class EverythingIsSignedContractTest extends TestCase
{
    private MacSigner $signer;

    protected function setUp(): void
    {
        $this->signer = new MacSigner(new CanonicalJsonSerializer());
    }

    /**
     * §5.1: "Two modes are defined: `All` (default) [...] `None`."
     *
     * §5.1: "The middle mode, `Critical`, is removed rather than deprecated.
     * With everything signed it selected nothing."
     */
    #[Test]
    public function theModeEnumHasExactlyAllAndNone(): void
    {
        self::assertSame(
            ['All', 'None'],
            array_map(fn (SigningMode $m) => $m->value, SigningMode::cases()),
        );
    }

    /**
     * §5.1: "`All` **(default)**". The default moves from `Critical` to `All`.
     */
    #[Test]
    public function theDefaultModeIsAll(): void
    {
        self::assertSame(SigningMode::ALL, SigningMode::default());
    }

    /**
     * §5.1: "Both values are PascalCase — `"All"`, `"None"` [...] lowercase
     * spellings that appeared in three places were drift, not an alternative
     * form, and a receiver MUST NOT accept them."
     */
    #[Test]
    public function lowercaseSpellingsAreRefused(): void
    {
        self::assertNull(SigningMode::tryFrom('all'));
        self::assertNull(SigningMode::tryFrom('none'));
        self::assertNull(SigningMode::tryFrom('critical'));
        self::assertNull(SigningMode::tryFrom('Critical'));
    }

    /**
     * §5.6: "every MQTT message MUST carry a valid `mac`, in either direction,
     * with exactly three exceptions. There are no other exemptions, no
     * per-message judgement, and no 'informational' category."
     *
     * The three are structural, and they are per (action, messageType) — the
     * BootNotification REQUEST and its RESPONSE are exempt for two different
     * reasons.
     */
    #[Test]
    public function thereAreExactlyThreeStructuralExemptions(): void
    {
        self::assertSame(
            [
                'BootNotification:Request',
                'BootNotification:Response',
                'ConnectionLost:Event',
            ],
            MessageSigningRegistry::allStructuralExemptions(),
        );
    }

    /**
     * §5.6: "Their exemption is unconditional: it holds in `All` mode, and it is
     * not something a deployment can turn off."
     */
    #[Test]
    public function theExemptionsHoldInEveryMode(): void
    {
        foreach (SigningMode::cases() as $mode) {
            self::assertFalse($mode->requiresMac('BootNotification', MessageType::REQUEST));
            self::assertFalse($mode->requiresMac('BootNotification', MessageType::RESPONSE));
            self::assertFalse($mode->requiresMac('ConnectionLost', MessageType::EVENT));
        }
    }

    /**
     * §5.6: "Of the 47 message types [...] 44 are signed and 3 are exempt."
     *
     * In `All` there is no per-message judgement left: anything that is not one
     * of the three is signed, including a message this SDK has never heard of.
     */
    #[Test]
    public function everythingElseIsSignedInAllMode(): void
    {
        $notExempt = [
            ['Heartbeat', MessageType::REQUEST],
            ['StatusNotification', MessageType::EVENT],
            ['MeterValues', MessageType::EVENT],
            ['GetDiagnostics', MessageType::REQUEST],
            ['StartService', MessageType::REQUEST],
            ['SomeActionThisSdkHasNeverHeardOf', MessageType::REQUEST],
            // ConnectionLost is exempt only as the broker's LWT EVENT.
            ['ConnectionLost', MessageType::REQUEST],
        ];

        foreach ($notExempt as [$action, $type]) {
            self::assertTrue(
                SigningMode::ALL->requiresMac($action, $type),
                "{$action}:{$type->value} must be signed in All mode",
            );
        }
    }

    /**
     * §5.6, Mode `None`: "No message carries a MAC."
     */
    #[Test]
    public function noneSignsNothing(): void
    {
        self::assertFalse(SigningMode::NONE->requiresMac('StartService', MessageType::REQUEST));
        self::assertFalse(SigningMode::NONE->requiresMac('Heartbeat', MessageType::REQUEST));
    }

    /**
     * §5.7 Sending: "A sender holding no key MUST refuse to send. It MUST NOT
     * publish the message unsigned. It MUST log the refusal [...] and MUST NOT
     * silently drop it without a record."
     *
     * Refusing loudly is the only option that is neither of the two the clause
     * forbids, so the signer raises rather than returning something.
     */
    #[Test]
    public function signingWithNoKeyRefusesRatherThanEmittingUnsigned(): void
    {
        foreach (['', '   ', '!!!not-base64!!!'] as $unusable) {
            try {
                $this->signer->sign(['action' => 'StartService'], $unusable);
                self::fail("sign() must refuse an unusable key, got a MAC for ".var_export($unusable, true));
            } catch (\RuntimeException $e) {
                self::assertStringContainsString('session key', strtolower($e->getMessage()));
            }
        }
    }

    /**
     * §5.7 Receiving: "No session key held for the peer | `1013 MAC_MISSING` |
     * Reject the message. A receiver that holds no key cannot verify, and cannot
     * therefore accept."
     *
     * Verification returns false rather than raising: a receiver rejects a
     * message, it does not crash on one.
     */
    #[Test]
    public function verifyingWithNoKeyRejectsRatherThanAccepting(): void
    {
        $payload = ['action' => 'StartService'];

        foreach (['', '   ', '!!!not-base64!!!'] as $unusable) {
            self::assertFalse(
                $this->signer->verify($payload, 'any-mac-at-all', $unusable),
                'verify() must reject when no usable key is held',
            );
        }
    }

    /**
     * The fail-open this replaces: an unusable key silently degraded to the
     * EMPTY HMAC key, so two peers both holding garbage verified each other
     * successfully and an attacker who knew the key was invalid could forge
     * with the empty one.
     */
    #[Test]
    public function anUnusableKeyDoesNotDegradeToTheEmptyHmacKey(): void
    {
        $payload = ['action' => 'StartService'];
        $emptyKeyMac = base64_encode(hash_hmac('sha256', $this->signer->canonicalize($payload), '', true));

        self::assertFalse($this->signer->verify($payload, $emptyKeyMac, ''));
        self::assertFalse($this->signer->verify($payload, $emptyKeyMac, '!!!not-base64!!!'));
    }
}
