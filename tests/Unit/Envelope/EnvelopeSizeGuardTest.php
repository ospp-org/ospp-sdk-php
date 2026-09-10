<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Envelope;

use DateTimeImmutable;
use InvalidArgumentException;
use Ospp\Protocol\Enums\MessageType;
use Ospp\Protocol\Envelope\EnvelopeSizeGuard;
use Ospp\Protocol\Envelope\MessageEnvelope;
use Ospp\Protocol\ValueObjects\MessageId;
use Ospp\Protocol\ValueObjects\ProtocolVersion;
use PHPUnit\Framework\TestCase;

/**
 * The envelope cap — `spec/02-transport.md` §10.2.1.
 *
 * Every assertion here is BOUNDARY-EXACT and tested from both sides. A cap tested only
 * with a value far over it proves the comparison fires, not that it fires in the right
 * place, and an off-by-one on this particular number is invisible in every real frame:
 * the largest envelope ever measured on a live deployment is 1 220 bytes, so nothing in
 * normal operation would ever reach the edge to disagree with it.
 */
final class EnvelopeSizeGuardTest extends TestCase
{
    private function envelopeWithPayloadOfSize(int $filler): MessageEnvelope
    {
        return new MessageEnvelope(
            messageId: new MessageId('b0edf290-9bf5-421e-bfc7-417c68f38d2f'),
            messageType: MessageType::REQUEST,
            action: 'UpdateServiceCatalog',
            timestamp: new DateTimeImmutable('2026-09-10T00:00:00.000Z'),
            source: 'Server',
            protocolVersion: ProtocolVersion::fromString('0.3.0'),
            payload: ['blob' => str_repeat('a', $filler)],
        );
    }

    public function test_the_cap_is_the_number_the_specification_states(): void
    {
        self::assertSame(64512, EnvelopeSizeGuard::MAX_ENVELOPE_BYTES);
        self::assertSame(65536, EnvelopeSizeGuard::MQTT_MAX_PACKET_BYTES);
    }

    public function test_the_cap_sits_1024_bytes_inside_the_packet_ceiling(): void
    {
        // Not arithmetic for its own sake: the gap IS the PUBLISH header allowance,
        // and a change to either constant that closes it makes a conformant envelope
        // undeliverable at a size this SDK told the caller was legal.
        self::assertSame(
            1024,
            EnvelopeSizeGuard::MQTT_MAX_PACKET_BYTES - EnvelopeSizeGuard::MAX_ENVELOPE_BYTES,
        );
    }

    public function test_exceeds_is_false_at_the_cap_and_true_one_byte_over(): void
    {
        $atCap = str_repeat('x', EnvelopeSizeGuard::MAX_ENVELOPE_BYTES);
        $overCap = $atCap.'x';

        self::assertFalse(EnvelopeSizeGuard::exceeds($atCap));
        self::assertTrue(EnvelopeSizeGuard::exceeds($overCap));
        self::assertSame(0, EnvelopeSizeGuard::remaining($atCap));
        self::assertSame(-1, EnvelopeSizeGuard::remaining($overCap));
    }

    public function test_length_is_counted_in_bytes_not_characters(): void
    {
        // A two-byte character costs two bytes of the cap. Counting code points would
        // let a UTF-8 envelope pass here and be dropped by the broker, which measures
        // the packet.
        $twoByteChars = str_repeat('é', EnvelopeSizeGuard::MAX_ENVELOPE_BYTES);

        self::assertSame(2 * EnvelopeSizeGuard::MAX_ENVELOPE_BYTES, EnvelopeSizeGuard::byteLength($twoByteChars));
        self::assertTrue(EnvelopeSizeGuard::exceeds($twoByteChars));
    }

    public function test_assert_within_cap_passes_at_the_cap(): void
    {
        EnvelopeSizeGuard::assertWithinCap(str_repeat('x', EnvelopeSizeGuard::MAX_ENVELOPE_BYTES));

        $this->addToAssertionCount(1);
    }

    public function test_assert_within_cap_throws_one_byte_over_and_names_the_action(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/UpdateServiceCatalog envelope is 64513 bytes/');

        EnvelopeSizeGuard::assertWithinCap(
            str_repeat('x', EnvelopeSizeGuard::MAX_ENVELOPE_BYTES + 1),
            'UpdateServiceCatalog',
        );
    }

    public function test_to_json_serialises_an_envelope_that_fits(): void
    {
        // Sized so the whole serialisation lands exactly ON the cap, not merely under
        // it — the positive control for the test below, which must differ by one byte
        // of payload and nothing else.
        $envelope = $this->envelopeWithPayloadOfSize(1);
        $overhead = $envelope->serializedByteLength() - 1;

        $atCap = $this->envelopeWithPayloadOfSize(EnvelopeSizeGuard::MAX_ENVELOPE_BYTES - $overhead);

        self::assertSame(EnvelopeSizeGuard::MAX_ENVELOPE_BYTES, strlen($atCap->toJson()));
    }

    public function test_to_json_refuses_an_envelope_one_byte_over_the_cap(): void
    {
        $envelope = $this->envelopeWithPayloadOfSize(1);
        $overhead = $envelope->serializedByteLength() - 1;

        $overCap = $this->envelopeWithPayloadOfSize(EnvelopeSizeGuard::MAX_ENVELOPE_BYTES - $overhead + 1);

        // serializedByteLength() still answers — a caller that wants to decide rather
        // than be refused is not blocked by the guard on the publishing path.
        self::assertSame(EnvelopeSizeGuard::MAX_ENVELOPE_BYTES + 1, $overCap->serializedByteLength());

        $this->expectException(InvalidArgumentException::class);
        $overCap->toJson();
    }

    public function test_a_realistic_frame_is_nowhere_near_the_cap(): void
    {
        // The measurement the cap was chosen against: the largest envelope observed on
        // a live deployment is 1 220 bytes. If this assertion ever fails, the cap is no
        // longer the generous number it was derived to be and the derivation in
        // §10.2.1 needs re-running against the traffic that broke it.
        $envelope = $this->envelopeWithPayloadOfSize(64);

        self::assertLessThan(2048, $envelope->serializedByteLength());
        self::assertGreaterThan(60000, EnvelopeSizeGuard::remaining($envelope->toJson()));
    }
}
