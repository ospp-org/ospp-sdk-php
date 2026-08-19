<?php

declare(strict_types=1);

namespace Ospp\Protocol\Tests\Contract;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Every CI gate script is executable IN THE GIT INDEX.
 *
 * `.github/workflows/tests.yml` invokes each gate as `run: scripts/<name>.sh`,
 * which execs the file. A script committed 100644 dies `Permission denied`
 * before its first line runs, and 0.14.0 shipped two gates that way —
 * `check-error-registry.sh` and `check-crypto-vectors.sh`, neither of which had
 * ever once executed. That workflow file still carries the note left behind:
 * *"Check the MODE, not the file list, when adding the fifth one."*
 *
 * A note is not a control, and the class bit again while the sixth gate was
 * being added: this repository has `core.fileMode = false`, so a local
 * `chmod 755` is INVISIBLE to git and the new script entered the index 100644
 * with the correct bit sitting on disk. That is why this test reads
 * `git ls-files -s` and not `is_executable()` — on a `core.fileMode = false`
 * clone the filesystem answers the question the index decides, and the index is
 * what CI checks out.
 *
 * The KNOWN-ISSUES entry that stays open until this exists puts it plainly:
 * a corrected file mode on a script nothing invokes is a repair no run can
 * confirm.
 */
final class GateScriptsAreExecutableTest extends TestCase
{
    private const EXECUTABLE_MODE = '100755';

    /**
     * @return array<string, string> path => mode
     */
    private static function indexedShellScripts(): array
    {
        $repoRoot = \dirname(__DIR__, 2);

        $output = [];
        $exitCode = 0;
        exec(
            'git -C '.escapeshellarg($repoRoot).' ls-files -s -- '.escapeshellarg('scripts/*.sh').' 2>/dev/null',
            $output,
            $exitCode
        );

        // A missing git, or a checkout without one, must FAIL rather than skip.
        // A silent skip is how this check would read as passing in exactly the
        // environment where it cannot see anything.
        self::assertSame(0, $exitCode, 'git ls-files failed — this test cannot verify modes and must not pass vacuously');

        $modes = [];
        foreach ($output as $line) {
            // "<mode> <sha> <stage>\t<path>"
            [$meta, $path] = explode("\t", $line, 2);
            $modes[$path] = explode(' ', $meta)[0];
        }

        return $modes;
    }

    #[Test]
    public function everyGateScriptIsExecutableInTheIndex(): void
    {
        $modes = self::indexedShellScripts();

        // Denominator. Without it this test passes on an empty set — the exact
        // shape of a gate that reports success because it measured nothing.
        // Five at 0.25.0: schemas, error-registry, config-registry,
        // crypto-vectors; vector-corpus is the fifth and the reason this exists.
        self::assertGreaterThanOrEqual(
            5,
            \count($modes),
            'fewer gate scripts than expected — the glob matched nothing or the scripts moved'
        );

        foreach ($modes as $path => $mode) {
            self::assertSame(
                self::EXECUTABLE_MODE,
                $mode,
                "{$path} is mode {$mode} in the git index; CI runs it as `run: {$path}` and it will die "
                .'`Permission denied` before its first line. Fix with `git update-index --chmod=+x '.$path.'` '
                .'— a plain `chmod` does NOT work here, this repo has core.fileMode = false.'
            );
        }
    }

    /**
     * The matcher must be able to see a violation. `git ls-files -s` reports a
     * non-executable file as 100644, and this pins that reading rather than
     * assuming it — a mode assertion whose vocabulary is wrong reports zero
     * offenders and looks identical to a clean tree.
     */
    #[Test]
    public function aNonExecutableFileIsReportedAs100644(): void
    {
        $modes = self::indexedShellScripts();
        self::assertNotEmpty($modes);

        $repoRoot = \dirname(__DIR__, 2);
        $output = [];
        $exitCode = 0;
        exec(
            'git -C '.escapeshellarg($repoRoot).' ls-files -s -- '.escapeshellarg('scripts/*.php').' 2>/dev/null',
            $output,
            $exitCode
        );
        self::assertSame(0, $exitCode);

        // The .php gate helpers are invoked through the interpreter, never
        // exec'd, and are correctly 100644. They are the conformant control:
        // they prove the reading "100644" is what a non-executable file yields,
        // so the assertion above is refusing something it can actually see.
        self::assertNotEmpty($output, 'no non-executable control file found — the matcher is unverified');

        foreach ($output as $line) {
            [$meta, $path] = explode("\t", $line, 2);
            self::assertSame('100644', explode(' ', $meta)[0], "{$path} was expected to be the non-executable control");
        }
    }
}
