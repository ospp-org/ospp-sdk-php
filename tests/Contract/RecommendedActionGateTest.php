<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `scripts/check-recommended-action.php` does what its header claims, and — the
 * load-bearing half — does NOT do the thing 07-errors.md §1.4 forbids.
 *
 * §1.4 permits a server to translate a `recommendedAction`, and concludes that a
 * conformance test therefore **MUST NOT** assert byte-identity against the registry
 * cell. A gate written as a diff would break the section it exists to enforce, and
 * nothing in a green CI run would ever say so — a diff and a coverage check look
 * identical from the outside while every arm happens to be verbatim, which is
 * exactly the state this repository is in today. So the property is asserted here
 * rather than trusted.
 *
 * Each case builds a SYNTHETIC spec tree holding a mutated copy of 07-errors.md and
 * runs the real gate against it. Mutating the spec side rather than the SDK side is
 * what makes the first test possible at all: the arm stays as shipped while the cell
 * is rewritten underneath it, so a gate that compares the two has nowhere to hide.
 */
final class RecommendedActionGateTest extends TestCase
{
    private static function repoRoot(): string
    {
        return \dirname(__DIR__, 2);
    }

    private static function registry(): string
    {
        $specRepo = getenv('SPEC_REPO');

        if ($specRepo === false || $specRepo === '' || ! is_file($specRepo.'/spec/07-errors.md')) {
            return '';
        }

        return (string) file_get_contents($specRepo.'/spec/07-errors.md');
    }

    /**
     * Run the gate against a spec tree whose 07-errors.md is $md.
     *
     * @return array{0: int, 1: string} exit code, combined output
     */
    private static function runGate(string $md): array
    {
        $dir = sys_get_temp_dir().'/ospp-gate-'.bin2hex(random_bytes(6));
        mkdir($dir.'/spec', 0o777, true);
        file_put_contents($dir.'/spec/07-errors.md', $md);

        $cmd = 'php '.escapeshellarg(self::repoRoot().'/scripts/check-recommended-action.php')
            .' '.escapeshellarg($dir).' mutant 2>&1';

        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);

        array_map('unlink', glob($dir.'/spec/*') ?: []);
        rmdir($dir.'/spec');
        rmdir($dir);

        return [$exit, implode("\n", $output)];
    }

    /** The full source line of the §3 row for $code. */
    private static function row(string $md, int $code): string
    {
        foreach (preg_split('/\r?\n/', $md) as $line) {
            if (preg_match('/^\|\s*'.$code.'\s*\|/', $line) === 1) {
                return $line;
            }
        }

        self::fail("no §3 row for code {$code}");
    }

    /** Replace the trailing *Recommended Action* cell of $code with $action. */
    private static function withAction(string $md, int $code, string $action): string
    {
        $old = self::row($md, $code);
        $trailing = strrpos(rtrim($old), '|');
        self::assertNotFalse($trailing);
        $head = substr($old, 0, (int) strrpos($old, '|', $trailing - strlen($old) - 1));

        return str_replace($old, $head.'| '.$action.' |', $md);
    }

    private function requireSpec(): string
    {
        $md = self::registry();

        if ($md === '') {
            // A skip is the correct answer on a developer's machine with no spec
            // checkout to hand. In CI it is the WRONG answer, and it is the failure
            // mode this whole file exists to rule out one level down: seven green
            // skips read exactly like seven green passes in the run summary, so the
            // gate would be unguarded and the column would still be green. The
            // `test` job in tests.yml clones the spec and exports SPEC_REPO for
            // precisely this reason; if that step is ever dropped, this fails rather
            // than quietly stops testing anything.
            if (getenv('CI') !== false) {
                self::fail(
                    'SPEC_REPO is unset under CI — these mutation tests would SKIP, which reads as green. '
                    .'The `test` job must clone the spec at .spec-ref and export SPEC_REPO.'
                );
            }

            self::markTestSkipped('SPEC_REPO is not set to a spec checkout — this gate test needs the registry');
        }

        // Anti-vacuity on the FIXTURE, before it is used to prove anything. A
        // truncated or moved registry would otherwise make every case below pass
        // for the wrong reason.
        self::assertGreaterThanOrEqual(
            100,
            preg_match_all('/^\|\s*\d{4}\s*\|\s*`[A-Z_]+`\s*\|/m', $md),
            'the fixture registry parsed as fewer than 100 rows — the mutations below would prove nothing'
        );

        return $md;
    }

    #[Test]
    public function itPassesUnmutated(): void
    {
        [$exit, $out] = self::runGate($this->requireSpec());

        self::assertSame(0, $exit, "the gate must pass against the pinned registry:\n".$out);
        self::assertStringContainsString('covered 119/119', $out);
    }

    /**
     * THE ONE THAT MATTERS. The cell is rewritten end to end — a different language,
     * different words, different length — keeping only what a translation keeps: the
     * protocol tokens and the fact that two parties are addressed. The shipped arm is
     * untouched and is now nothing like it.
     *
     * A gate with any byte comparison in it fails here. This one must stay green,
     * because §1.4 says a translation is conforming and a conformance test may not
     * say otherwise.
     */
    #[Test]
    public function itAcceptsACellRewrittenInAnotherLanguage(): void
    {
        $md = self::withAction($this->requireSpec(), 4018,
            'Statie: NU regenera chei pe niciun brat - o cheie noua primeste raspunsul `4015`. '
            .'Ramifica pe `details.reason`. `already_consumed` - alta cerere detine acest token; reia neschimbat. '
            .'`consumed_without_certificate` - cere un token nou. Operator: emite un token proaspat.');

        [$exit, $out] = self::runGate($md);

        self::assertSame(0, $exit, "a translated cell must not fail the gate — §1.4 permits translation:\n".$out);
    }

    #[Test]
    public function itFailsOnACodeWithNoArm(): void
    {
        $md = $this->requireSpec();
        $md = str_replace(
            self::row($md, 6008),
            self::row($md, 6008)."\n| 6009 | `NEW_SERVER_CODE` | Error | true | A code no SDK has seen. | Server: do the new thing. |",
            $md
        );

        [$exit, $out] = self::runGate($md);

        self::assertSame(1, $exit, 'a registry code with no arm must red the gate');
        self::assertStringContainsString('COVERAGE', $out);
        self::assertStringContainsString('6009', $out);
    }

    /**
     * A gate that reports a pass over an empty set is worse than no gate: it is a
     * green column that means nothing. Every one of the eight checks passes
     * vacuously over zero rows, so the floor is asserted before any of them run.
     */
    #[Test]
    public function itRefusesToPassOverAnEmptyRegistry(): void
    {
        $md = (string) preg_replace('/^\|\s*\d{4}\s*\|.*$/m', '', $this->requireSpec());

        [$exit, $out] = self::runGate($md);

        self::assertSame(1, $exit, 'an empty registry must fail, never report "all 0 codes agree"');
        self::assertStringContainsString('parsed only 0 rows', $out);
    }

    #[Test]
    public function itFailsWhenAnArmDropsABranchDiscriminator(): void
    {
        $md = self::withAction($this->requireSpec(), 1010,
            'Retry per the action\'s retry policy. Branch on `details.newBranch`.');

        [$exit, $out] = self::runGate($md);

        self::assertSame(1, $exit, 'a `details.<member>` the cell names must survive into the arm');
        self::assertStringContainsString('DISCRIMINATOR', $out);
    }

    #[Test]
    public function itFailsWhenAnArmDropsAnAddressedParty(): void
    {
        $md = self::withAction($this->requireSpec(), 1010,
            'Station: retry per the policy. Server: log the timeout.');

        [$exit, $out] = self::runGate($md);

        self::assertSame(1, $exit, 'a part addressed by the cell must still be addressed by the arm');
        self::assertStringContainsString('PARTY', $out);
    }

    #[Test]
    public function itFailsWhenAnArmDropsACitedRegistryCode(): void
    {
        $md = self::withAction($this->requireSpec(), 1010,
            'Retry per the policy; a fresh key is answered `4015`.');

        [$exit, $out] = self::runGate($md);

        self::assertSame(1, $exit, 'a registry code the cell cites is a cross-reference and must survive');
        self::assertStringContainsString('CODE-REF', $out);
    }
}
