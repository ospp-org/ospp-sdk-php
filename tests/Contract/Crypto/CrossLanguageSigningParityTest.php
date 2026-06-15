<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\Crypto;

use Ospp\Protocol\Actions\OsppAction;
use Ospp\Protocol\Crypto\CriticalMessageRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cross-language signing-classification parity (ospp-sdk-php side).
 *
 * The shared fixture signing-classification.json is BYTE-IDENTICAL with sdk-ts
 * (tests/crypto/fixtures/signing-classification.json) and encodes spec §5.6 as data.
 *
 * sdk-ts keys its registry by (action, messageType) — it asserts the 31 critical + 3 always-exempt
 * rows directly. ospp-sdk-php keys CriticalMessageRegistry by ACTION ONLY, so here we assert that:
 *   1. PHP's critical set, MINUS the REST-only API superset, collapses to exactly the 16 distinct
 *      critical wire actions in the shared fixture (so PHP and TS agree on the wire);
 *   2. that API-only allowlist is the SDK's own OsppAction::apiOnlyActions() flag — NOT a hand-kept
 *      list — so promoting a REST op onto the wire surfaces as a failure instead of being masked;
 *   3. no action is "split" (critical for some message types, exempt for others) — the soundness
 *      condition for PHP's action-only collapse. If the spec ever introduces a split, this fails,
 *      flagging that the action-only model can no longer represent the classification.
 *
 * Net effect: both SDKs are pinned to the same spec §5.6 fixture, so a change to the critical set in
 * one language that is not mirrored in the other turns one repo's suite RED.
 */
final class CrossLanguageSigningParityTest extends TestCase
{
    /**
     * The full (action, messageType) universe (47), derived from the SDK's OWN request/event
     * classification — not hardcoded — so a newly added wire action automatically joins it.
     *
     * @return list<array{action: string, messageType: string}>
     */
    private static function universe(): array
    {
        $universe = [];
        foreach (OsppAction::requests() as $action) {
            $universe[] = ['action' => $action, 'messageType' => 'Request'];
            $universe[] = ['action' => $action, 'messageType' => 'Response'];
        }
        foreach (OsppAction::events() as $action) {
            $universe[] = ['action' => $action, 'messageType' => 'Event'];
        }

        return $universe;
    }

    /**
     * @return array{criticalInCriticalMode: list<array{action: string, messageType: string}>, alwaysExempt: list<array{action: string, messageType: string}>, phpApiOnlySuperset: list<string>}
     */
    private static function classification(): array
    {
        $path = __DIR__.'/fixtures/signing-classification.json';
        $json = file_get_contents($path);
        self::assertNotFalse($json, "Missing fixture: {$path}");

        /** @var array{criticalInCriticalMode: list<array{action: string, messageType: string}>, alwaysExempt: list<array{action: string, messageType: string}>, phpApiOnlySuperset: list<string>} $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param  list<array{action: string, messageType: string}>  $rows
     * @return list<string>
     */
    private static function distinctActions(array $rows): array
    {
        $actions = array_values(array_unique(array_map(static fn (array $r): string => $r['action'], $rows)));
        sort($actions);

        return $actions;
    }

    #[Test]
    public function fixture_is_internally_consistent_with_spec_5_6(): void
    {
        $c = self::classification();

        self::assertCount(31, $c['criticalInCriticalMode'], 'spec §5.6 has 31 signed message types in Critical mode');
        self::assertCount(3, $c['alwaysExempt'], 'spec §5.6 has 3 always-exempt message types');
        self::assertCount(16, self::distinctActions($c['criticalInCriticalMode']), 'the 31 critical rows span 16 distinct actions');
        // The universe derived from the SDK enum must be exactly the spec's 47 message types.
        self::assertCount(47, self::universe(), 'OsppAction request/event sets must yield the spec §5.6 47-message-type universe');
    }

    #[Test]
    public function php_critical_wire_set_collapses_to_the_spec_5_6_actions(): void
    {
        $c = self::classification();

        // The 16 distinct critical wire actions, from the SHARED spec §5.6 fixture sdk-ts is also pinned to.
        $specWireActions = self::distinctActions($c['criticalInCriticalMode']);

        // API_ONLY derived from the SDK's own enum flag (see api-only test for the cross-check).
        $apiOnly = OsppAction::apiOnlyActions();

        // PHP keys critical by action only; project away the REST-only superset and compare on the wire.
        $phpWire = array_values(array_diff(CriticalMessageRegistry::allCriticalActions(), $apiOnly));
        sort($phpWire);

        self::assertSame(
            $specWireActions,
            $phpWire,
            'ospp-sdk-php critical wire-action set (minus API_ONLY) diverged from spec §5.6 / sdk-ts.',
        );
    }

    #[Test]
    public function api_only_allowlist_is_backed_by_the_enum_flag_not_hardcoded(): void
    {
        $c = self::classification();

        $fromFixture = $c['phpApiOnlySuperset'];
        sort($fromFixture);

        $fromEnum = OsppAction::apiOnlyActions();
        sort($fromEnum);

        // The allowlist the parity check trusts MUST equal the SDK's own API-only flag. If a REST op is
        // promoted to a wire action (its flag removed), this fails — surfacing the drift, not masking it.
        self::assertSame(
            $fromEnum,
            $fromFixture,
            'phpApiOnlySuperset allowlist is out of sync with OsppAction::apiOnlyActions().',
        );

        foreach ($fromEnum as $action) {
            // Each allow-listed action really is PHP-critical (so subtracting it from the critical set is sound)…
            self::assertTrue(CriticalMessageRegistry::isCritical($action), "{$action} is expected to be PHP-critical.");
            // …and really is NOT an MQTT wire action (it is REST-only — that is the whole justification).
            self::assertFalse(OsppAction::isMqtt($action), "{$action} must not be an MQTT wire action.");
        }
    }

    #[Test]
    public function no_split_action_guard(): void
    {
        $c = self::classification();

        $critical = array_flip(array_map(static fn (array $r): string => $r['action'].':'.$r['messageType'], $c['criticalInCriticalMode']));

        /** @var array<string, array{crit?: true, nonCrit?: true}> $byAction */
        $byAction = [];
        foreach (self::universe() as $row) {
            $key = $row['action'].':'.$row['messageType'];
            $bucket = isset($critical[$key]) ? 'crit' : 'nonCrit';
            $byAction[$row['action']][$bucket] = true;
        }

        $split = [];
        foreach ($byAction as $action => $buckets) {
            if (isset($buckets['crit'], $buckets['nonCrit'])) {
                $split[] = $action;
            }
        }
        sort($split);

        self::assertSame(
            [],
            $split,
            'Split action(s) detected ['.implode(', ', $split).']: critical for some message types but not '
            .'others. ospp-sdk-php CriticalMessageRegistry keys by action only and CANNOT represent a split — '
            .'the registry would need the (action, messageType) axis sdk-ts already uses.',
        );
    }

    #[Test]
    public function registry_behaviour_matches_the_spec_fixture(): void
    {
        $c = self::classification();

        $criticalActions = array_flip(array_map(static fn (array $r): string => $r['action'], $c['criticalInCriticalMode']));
        $alwaysExemptActions = array_flip(array_map(static fn (array $r): string => $r['action'], $c['alwaysExempt']));

        // Every action the spec marks critical → isCritical() true.
        foreach (array_keys($criticalActions) as $action) {
            self::assertTrue(CriticalMessageRegistry::isCritical($action), "{$action} must be PHP-critical.");
        }

        // Always-exempt actions → always-exempt and not critical.
        foreach (array_keys($alwaysExemptActions) as $action) {
            self::assertTrue(CriticalMessageRegistry::isAlwaysExempt($action), "{$action} must be always-exempt.");
            self::assertFalse(CriticalMessageRegistry::isCritical($action), "{$action} must not be critical.");
        }

        // Every plain-exempt wire action → not critical.
        foreach (self::universe() as $row) {
            $action = $row['action'];
            if (isset($criticalActions[$action]) || isset($alwaysExemptActions[$action])) {
                continue;
            }
            self::assertFalse(CriticalMessageRegistry::isCritical($action), "Exempt wire action {$action} must not be PHP-critical.");
        }

        // Always-exempt action set parity.
        $specAlwaysExempt = self::distinctActions($c['alwaysExempt']);
        $phpAlwaysExempt = CriticalMessageRegistry::allAlwaysExemptActions();
        sort($phpAlwaysExempt);
        self::assertSame($specAlwaysExempt, $phpAlwaysExempt, 'Always-exempt action set diverged from spec §5.6.');
    }
}
