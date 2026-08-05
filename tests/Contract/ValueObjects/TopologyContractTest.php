<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract\ValueObjects;

use Ospp\Protocol\SchemaPath;
use Ospp\Protocol\ValueObjects\BayTopology;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Topology and programs — the station declares its bays and their programs.
 *
 * Mirrored by sdk-ts tests/types/Topology.test.ts.
 *
 * The bounds are asserted against the VENDORED SCHEMAS rather than against a
 * transcription of them, so a constant that drifts from the wire fails rather
 * than agreeing with a stale copy of it.
 */
final class TopologyContractTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function schema(string $relative): array
    {
        $raw = file_get_contents(SchemaPath::directory().'/'.$relative);
        self::assertIsString($raw, "schema {$relative} must be readable");

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * 01-architecture.md §4.2: "The maximum number of bays per controller is
     * implementation-defined but MUST NOT exceed **64**." and "The maximum
     * number of programs per bay MUST NOT exceed **32**."
     */
    #[Test]
    public function boundsAre64BaysAnd32Programs(): void
    {
        $bayTopology = $this->schema('common/bay-topology.schema.json');

        self::assertSame(64, BayTopology::MAX_BAYS_PER_STATION);
        self::assertSame(32, BayTopology::MAX_PROGRAMS_PER_BAY);

        self::assertSame(BayTopology::MAX_BAYS_PER_STATION, $bayTopology['properties']['bayNumber']['maximum']);
        self::assertSame(BayTopology::MAX_PROGRAMS_PER_BAY, $bayTopology['properties']['programNumbers']['maxItems']);
        self::assertSame(BayTopology::MAX_PROGRAMS_PER_BAY, $bayTopology['properties']['programNumbers']['items']['maximum']);
    }

    /**
     * boot-notification.md §3: "`bays` | array | Yes | The station's re-declared
     * physical topology: one entry per bay, each carrying `bayNumber` and the
     * `programNumbers` that bay can run."
     */
    #[Test]
    public function bootNotificationDeclaresBaysNotBayCount(): void
    {
        $req = $this->schema('mqtt/boot-notification-request.schema.json');

        self::assertContains('bays', $req['required']);
        self::assertNotContains('bayCount', $req['required']);
        self::assertArrayNotHasKey('bayCount', $req['properties']);
    }

    /**
     * A non-dense bay set is legal everywhere, and a server MUST NOT reject a
     * declaration for being non-dense.
     */
    #[Test]
    public function aNonDenseBaySetIsLegal(): void
    {
        $bays = [
            new BayTopology(1, [1, 2, 3]),
            new BayTopology(3, [1]),
        ];

        self::assertSame(3, $bays[1]->bayNumber);
        self::assertSame([1], $bays[1]->programNumbers);
    }

    /**
     * status-notification.md §3: "`programs` | array<object> | Yes | Program
     * availability list, one entry per program this bay declared at
     * provisioning."
     */
    #[Test]
    public function statusNotificationReportsProgramsNotServices(): void
    {
        $s = $this->schema('mqtt/status-notification.schema.json');

        self::assertContains('programs', $s['required']);
        self::assertNotContains('services', $s['required']);
        self::assertArrayNotHasKey('services', $s['properties']);
    }

    /**
     * start-service-request.schema.json: "The ordinal of the PHYSICAL PROGRAM to
     * run on the target bay [...] Carried explicitly so the station acts on a
     * field rather than indexing its own catalog by serviceId."
     */
    #[Test]
    public function programNumberIsRequiredOnStartService(): void
    {
        self::assertContains(
            'programNumber',
            $this->schema('mqtt/start-service-request.schema.json')['required'],
        );
        self::assertContains(
            'programNumber',
            $this->schema('ble/start-service-request.schema.json')['required'],
        );
    }

    /**
     * service-item.schema.json: "`bindings` [...] This is what lets the station
     * act OFFLINE, where no StartService command exists to carry the ordinal."
     */
    #[Test]
    public function programNumberTravelsInTheCatalogAsBindings(): void
    {
        $item = $this->schema('common/service-item.schema.json');

        self::assertContains('bindings', $item['required']);
        self::assertSame(
            ['bayNumber', 'programNumber'],
            $item['properties']['bindings']['items']['required'],
        );
    }

    /**
     * provisioning-response.schema.json: "Server-assigned bay identifiers, each
     * paired EXPLICITLY with the bayNumber the station declared for it. [...]
     * Carrying the pair as an object rather than by array position is what lets
     * a non-dense bay set be expressed at all."
     */
    #[Test]
    public function theProvisioningResponsePairsBayIdWithBayNumberExplicitly(): void
    {
        $res = $this->schema('provisioning-response.schema.json');
        $required = $res['properties']['bays']['items']['required'];
        sort($required);

        self::assertContains('bays', $res['required']);
        self::assertNotContains('bayIds', $res['required']);
        self::assertArrayNotHasKey('bayIds', $res['properties']);
        self::assertSame(['bayId', 'bayNumber'], $required);
        self::assertSame(BayTopology::MAX_BAYS_PER_STATION, $res['properties']['bays']['maxItems']);
    }

    /**
     * bay-topology.schema.json is referenced by BOTH the request's bays[] and
     * the 3018 response's details.expected/declared — "one definition instead of
     * two copies".
     */
    #[Test]
    public function bayTopologyIsTheSharedShape(): void
    {
        $ref = '../common/bay-topology.schema.json';
        $res = $this->schema('mqtt/boot-notification-response.schema.json');

        self::assertSame($ref, $res['properties']['details']['properties']['expected']['items']['$ref']);
        self::assertSame($ref, $res['properties']['details']['properties']['declared']['items']['$ref']);
        self::assertSame(
            $ref,
            $this->schema('mqtt/boot-notification-request.schema.json')['properties']['bays']['items']['$ref'],
        );
    }

    /**
     * "Order carries no meaning; the server compares this as a SET."
     */
    #[Test]
    public function programNumbersCompareAsASet(): void
    {
        $a = new BayTopology(1, [1, 2, 3]);
        $b = new BayTopology(1, [3, 1, 2]);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals(new BayTopology(1, [1, 2])));
        self::assertFalse($a->equals(new BayTopology(2, [1, 2, 3])));
    }

    /**
     * The bounds are enforced on construction: a value outside them could not
     * have come off the wire, because the schema would have refused it.
     */
    #[Test]
    public function boundsAreEnforcedOnConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BayTopology(65, [1]);
    }

    #[Test]
    public function aBayMustDeclareAtLeastOneProgram(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BayTopology(1, []);
    }

    /**
     * 05-state-machines.md §1.5: "The mismatch is symmetric: a bay or a program
     * ordinal present on one side and absent on the other is a mismatch in
     * either direction."
     */
    #[Test]
    public function topologyMatchIsSymmetricAndSetBased(): void
    {
        $provisioned = [new BayTopology(1, [1, 2, 3]), new BayTopology(3, [1])];

        // Re-ordered, same hardware.
        self::assertTrue(BayTopology::topologyMatches($provisioned, [
            new BayTopology(3, [1]),
            new BayTopology(1, [3, 2, 1]),
        ]));

        // A bay present only in the declaration.
        self::assertFalse(BayTopology::topologyMatches($provisioned, [
            new BayTopology(1, [1, 2, 3]),
            new BayTopology(3, [1]),
            new BayTopology(4, [1]),
        ]));

        // A bay present only in the provisioned record.
        self::assertFalse(BayTopology::topologyMatches($provisioned, [
            new BayTopology(1, [1, 2, 3]),
        ]));

        // A program ordinal present on one side only.
        self::assertFalse(BayTopology::topologyMatches($provisioned, [
            new BayTopology(1, [1, 2]),
            new BayTopology(3, [1]),
        ]));
    }

    #[Test]
    public function sameProgramSetIgnoresOrder(): void
    {
        self::assertTrue(BayTopology::sameProgramSet([1, 2, 3], [3, 1, 2]));
        self::assertFalse(BayTopology::sameProgramSet([1, 2, 3], [1, 2]));
    }
}
