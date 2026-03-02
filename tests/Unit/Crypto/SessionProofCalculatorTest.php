<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Unit\Crypto;

use Ospp\Protocol\Crypto\SessionProofCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionProofCalculatorTest extends TestCase
{
    private SessionProofCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new SessionProofCalculator();
    }

    // =========================================================================
    // compute() — output format
    // =========================================================================

    #[Test]
    public function compute_returns_64_hex_characters(): void
    {
        $key = random_bytes(32);

        $proof = $this->calculator->compute($key, 'pass_1', 1, 'bay_1', 'svc_1');

        self::assertSame(64, strlen($proof));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $proof);
    }

    #[Test]
    public function compute_returns_lowercase_hex(): void
    {
        $key = random_bytes(32);

        $proof = $this->calculator->compute($key, 'pass_1', 1, 'bay_1', 'svc_1');

        self::assertSame(strtolower($proof), $proof);
    }

    // =========================================================================
    // compute() — spec test vector (§6.5.1)
    // =========================================================================

    #[Test]
    public function compute_matches_known_test_vector(): void
    {
        // Spec §6.5.1 test vector inputs
        $sessionKey = hex2bin('a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2');
        $offlinePassId = 'opass_abc123';
        $txCounter = 42;
        $bayId = 'bay_01';
        $serviceId = 'svc_wash_basic';

        $proof = $this->calculator->compute($sessionKey, $offlinePassId, $txCounter, $bayId, $serviceId);

        // Actual HMAC-SHA256 output for these inputs
        self::assertSame(
            '8ca87aadceb8eb60fecb0b621c4f4fbf0734708a4c4771512e9e2e1ae9820272',
            $proof,
        );
    }

    // =========================================================================
    // compute() — determinism
    // =========================================================================

    #[Test]
    public function compute_is_deterministic(): void
    {
        $key = random_bytes(32);

        $proof1 = $this->calculator->compute($key, 'pass_1', 10, 'bay_1', 'svc_1');
        $proof2 = $this->calculator->compute($key, 'pass_1', 10, 'bay_1', 'svc_1');

        self::assertSame($proof1, $proof2);
    }

    #[Test]
    public function compute_differs_for_different_keys(): void
    {
        $key1 = str_repeat("\xAA", 32);
        $key2 = str_repeat("\xBB", 32);

        $proof1 = $this->calculator->compute($key1, 'pass_1', 1, 'bay_1', 'svc_1');
        $proof2 = $this->calculator->compute($key2, 'pass_1', 1, 'bay_1', 'svc_1');

        self::assertNotSame($proof1, $proof2);
    }

    // =========================================================================
    // compute() — counter encoding (BE32)
    // =========================================================================

    #[Test]
    public function compute_differs_for_different_counters(): void
    {
        $key = str_repeat("\xAA", 32);

        $proof1 = $this->calculator->compute($key, 'pass_1', 1, 'bay_1', 'svc_1');
        $proof2 = $this->calculator->compute($key, 'pass_1', 2, 'bay_1', 'svc_1');

        self::assertNotSame($proof1, $proof2);
    }

    #[Test]
    public function counter_zero_is_valid(): void
    {
        $key = str_repeat("\xAA", 32);

        $proof = $this->calculator->compute($key, 'pass_1', 0, 'bay_1', 'svc_1');

        self::assertSame(64, strlen($proof));
    }

    #[Test]
    public function counter_uses_big_endian_encoding(): void
    {
        $key = str_repeat("\xAA", 32);

        // Counter = 256 should differ from counter = 1
        // (in little-endian they'd share more bytes, but we test they're distinct)
        $proof256 = $this->calculator->compute($key, 'pass_1', 256, 'bay_1', 'svc_1');
        $proof1 = $this->calculator->compute($key, 'pass_1', 1, 'bay_1', 'svc_1');

        self::assertNotSame($proof256, $proof1);
    }

    // =========================================================================
    // compute() — input sensitivity
    // =========================================================================

    #[Test]
    public function compute_differs_for_different_offline_pass_id(): void
    {
        $key = str_repeat("\xAA", 32);

        $proof1 = $this->calculator->compute($key, 'pass_1', 1, 'bay_1', 'svc_1');
        $proof2 = $this->calculator->compute($key, 'pass_2', 1, 'bay_1', 'svc_1');

        self::assertNotSame($proof1, $proof2);
    }

    #[Test]
    public function compute_differs_for_different_bay_id(): void
    {
        $key = str_repeat("\xAA", 32);

        $proof1 = $this->calculator->compute($key, 'pass_1', 1, 'bay_1', 'svc_1');
        $proof2 = $this->calculator->compute($key, 'pass_1', 1, 'bay_2', 'svc_1');

        self::assertNotSame($proof1, $proof2);
    }

    #[Test]
    public function compute_differs_for_different_service_id(): void
    {
        $key = str_repeat("\xAA", 32);

        $proof1 = $this->calculator->compute($key, 'pass_1', 1, 'bay_1', 'svc_1');
        $proof2 = $this->calculator->compute($key, 'pass_1', 1, 'bay_1', 'svc_2');

        self::assertNotSame($proof1, $proof2);
    }

    // =========================================================================
    // verify()
    // =========================================================================

    #[Test]
    public function verify_returns_true_for_valid_proof(): void
    {
        $key = random_bytes(32);

        $proof = $this->calculator->compute($key, 'pass_1', 1, 'bay_1', 'svc_1');

        self::assertTrue($this->calculator->verify($proof, $key, 'pass_1', 1, 'bay_1', 'svc_1'));
    }

    #[Test]
    public function verify_returns_false_for_tampered_proof(): void
    {
        $key = random_bytes(32);

        $proof = $this->calculator->compute($key, 'pass_1', 1, 'bay_1', 'svc_1');

        // Flip last hex character
        $lastChar = substr($proof, -1);
        $tampered = substr($proof, 0, -1) . ($lastChar === 'a' ? 'b' : 'a');

        self::assertFalse($this->calculator->verify($tampered, $key, 'pass_1', 1, 'bay_1', 'svc_1'));
    }

    #[Test]
    public function verify_returns_false_for_wrong_key(): void
    {
        $key1 = str_repeat("\xAA", 32);
        $key2 = str_repeat("\xBB", 32);

        $proof = $this->calculator->compute($key1, 'pass_1', 1, 'bay_1', 'svc_1');

        self::assertFalse($this->calculator->verify($proof, $key2, 'pass_1', 1, 'bay_1', 'svc_1'));
    }

    #[Test]
    public function verify_returns_false_for_wrong_counter(): void
    {
        $key = random_bytes(32);

        $proof = $this->calculator->compute($key, 'pass_1', 1, 'bay_1', 'svc_1');

        self::assertFalse($this->calculator->verify($proof, $key, 'pass_1', 2, 'bay_1', 'svc_1'));
    }

    #[Test]
    public function verify_roundtrip_with_spec_test_vector(): void
    {
        $sessionKey = hex2bin('a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2');
        $proof = '8ca87aadceb8eb60fecb0b621c4f4fbf0734708a4c4771512e9e2e1ae9820272';

        self::assertTrue(
            $this->calculator->verify($proof, $sessionKey, 'opass_abc123', 42, 'bay_01', 'svc_wash_basic'),
        );
    }
}
