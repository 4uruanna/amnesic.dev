<?php

declare(strict_types=1);

namespace Robot\Tests;

use PHPUnit\Framework\TestCase;

/** La configuration lue par bin/relais.php au démarrage : relais.md § Configuration. */
final class ConfigurationTest extends TestCase
{
    private const ENV = [
        'APIMAIL_URL' => 'http://127.0.0.1:1',
        'APIMAIL_TOKEN' => 'jeton-tres-secret-0123456789abcdef',
        'MAILPIT_URL' => 'http://127.0.0.1:1',
        'MAILPIT_USER' => 'utilisateur-secret',
        'MAILPIT_PASS' => 'mot-de-passe-secret',
        'POLL_SECONDS' => '5',
    ];

    public function testStartsAndLogsStarted(): void
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/../bin/relais.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, self::ENV);
        $line = fgets($pipes[1]);
        proc_terminate($process);
        proc_close($process);

        self::assertSame("started\n", $line);
    }

    public function testAMissingVariableStopsTheRobotAndIsNamedWithoutAnyValue(): void
    {
        $env = self::ENV;
        unset($env['APIMAIL_TOKEN']);

        [$code, $output] = $this->relais($env);

        self::assertSame(1, $code);
        self::assertStringContainsString('APIMAIL_TOKEN', $output);
        self::assertNoValueIn($output);
    }

    public function testAnEmptyVariableStopsTheRobotToo(): void
    {
        [$code, $output] = $this->relais(['MAILPIT_PASS' => ''] + self::ENV);

        self::assertSame(1, $code);
        self::assertStringContainsString('MAILPIT_PASS', $output);
        self::assertNoValueIn($output);
    }

    public function testEveryMissingVariableIsNamedAtOnce(): void
    {
        [$code, $output] = $this->relais([]);

        self::assertSame(1, $code);
        foreach (array_keys(self::ENV) as $name) {
            self::assertStringContainsString($name, $output);
        }
    }

    public function testPollSecondsMustBeAPositiveInteger(): void
    {
        foreach (['0', '-1', '2.5', 'cinq'] as $value) {
            [$code, $output] = $this->relais(['POLL_SECONDS' => $value] + self::ENV);

            self::assertSame(1, $code, $value);
            self::assertStringContainsString('POLL_SECONDS', $output, $value);
            self::assertNoValueIn($output);
        }
    }

    /**
     * bin/relais.php jusqu'à son arrêt, avec ces seules variables d'environnement.
     *
     * @param array<string, string> $env
     * @return array{int, string} le code de sortie, puis la sortie standard et la sortie d'erreur mises bout à bout
     */
    private function relais(array $env): array
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/../bin/relais.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);

        return [proc_close($process), $output];
    }

    private static function assertNoValueIn(string $output): void
    {
        foreach (['jeton-tres-secret', 'utilisateur-secret', 'mot-de-passe-secret', '127.0.0.1'] as $value) {
            self::assertStringNotContainsString($value, $output);
        }
    }
}
