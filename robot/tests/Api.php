<?php

declare(strict_types=1);

namespace Robot\Tests;

use RuntimeException;

/** La vraie API, servie par php -S depuis tests/apimail.php sur la base apimail_test, et de quoi l'appeler. */
final class Api
{
    private static ?string $url = null;

    /** L'URL de l'API, démarrée au premier appel pour toute la suite. */
    public static function url(): string
    {
        return self::$url ??= self::start();
    }

    /**
     * Remet apimail_test à neuf, avec le Bot relais et le user ci lié à ci@example.com.
     *
     * @return array{bot: string, ci: string} leurs jetons
     */
    public static function reset(): array
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/apimail.php'], [1 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException("tests/apimail.php a échoué : $output");
        }

        return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed>|null $body encodé en JSON
     * @return array{int, string} le code HTTP et le corps de la réponse
     */
    public static function request(string $method, string $path, string $token, ?array $body = null): array
    {
        $curl = curl_init(self::url() . $path);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ] + ($body === null ? [] : [CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR)]));
        $response = curl_exec($curl);
        if ($response === false) {
            throw new RuntimeException(curl_error($curl));
        }

        return [curl_getinfo($curl, CURLINFO_RESPONSE_CODE), $response];
    }

    /** @return array<mixed> le corps JSON d'une requête GET */
    public static function get(string $path, string $token): array
    {
        return json_decode(self::request('GET', $path, $token)[1], true, flags: JSON_THROW_ON_ERROR);
    }

    private static function start(): string
    {
        // Un port libre, que le système choisit.
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        $storage = sys_get_temp_dir() . '/robot-storage-' . bin2hex(random_bytes(4));
        // Les limites de l'hébergement OVH (infra.md § Contraintes de l'offre Pro).
        $server = proc_open(
            [PHP_BINARY, '-d', 'post_max_size=130M', '-d', 'memory_limit=512M', '-S', $address, __DIR__ . '/apimail.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            ['APIMAIL_TEST_STORAGE' => $storage] + getenv(),
        );
        register_shutdown_function(fn () => proc_terminate($server));

        [$host, $port] = explode(':', $address);
        for ($try = 0; $try < 100; $try++) {
            $connection = @fsockopen($host, (int) $port);
            if ($connection !== false) {
                fclose($connection);

                return "http://$address";
            }
            usleep(50_000);
        }
        throw new RuntimeException("php -S ne répond pas sur $address");
    }
}
