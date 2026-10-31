<?php

declare(strict_types=1);

namespace Robot\Tests;

use RuntimeException;

/** Le Mailpit des tests, de MAILPIT_URL (phpunit.xml) : on y injecte les mails par son API d'envoi, POST /api/v1/send. */
final class TestMailpit
{
    public static function clear(): void
    {
        self::request('DELETE', '/api/v1/messages');
    }

    /**
     * @param array<string, mixed> $mail le corps de POST /api/v1/send
     * @return string l'ID Mailpit du mail
     */
    public static function send(array $mail): string
    {
        return json_decode(self::request('POST', '/api/v1/send', $mail), true, flags: JSON_THROW_ON_ERROR)['ID'];
    }

    /**
     * Pour un en-tête que l'API d'envoi refuse de poser, comme Date : le mail brut, remis en SMTP à MAILPIT_SMTP.
     *
     * @return string l'ID Mailpit du mail
     */
    public static function smtp(string $raw): string
    {
        $mail = fopen('php://memory', 'r+');
        fwrite($mail, $raw);
        rewind($mail);
        $curl = curl_init((string) getenv('MAILPIT_SMTP'));
        curl_setopt_array($curl, [
            CURLOPT_MAIL_FROM => '<noreply@service.example>',
            CURLOPT_MAIL_RCPT => ['<ci@example.com>'],
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => $mail,
            CURLOPT_TIMEOUT => 30,
        ]);
        if (curl_exec($curl) === false) {
            throw new RuntimeException('Mailpit : SMTP a échoué : ' . curl_error($curl));
        }

        return json_decode(self::request('GET', '/api/v1/messages?limit=1'), true, flags: JSON_THROW_ON_ERROR)['messages'][0]['ID'];
    }

    /** @return array<string, list<string>> les étiquettes de chaque mail gardé, par ID */
    public static function messages(): array
    {
        $messages = json_decode(self::request('GET', '/api/v1/messages?limit=1000'), true, flags: JSON_THROW_ON_ERROR)['messages'];

        return array_column($messages, 'Tags', 'ID');
    }

    /** @param array<string, mixed>|null $body */
    private static function request(string $method, string $path, ?array $body = null): string
    {
        $curl = curl_init(getenv('MAILPIT_URL') . $path);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERNAME => (string) getenv('MAILPIT_USER'),
            CURLOPT_PASSWORD => (string) getenv('MAILPIT_PASS'),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ] + ($body === null ? [] : [CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR)]));
        $response = curl_exec($curl);
        if ($response === false || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) {
            throw new RuntimeException("Mailpit : $method $path a échoué : " . curl_error($curl) . $response);
        }

        return $response;
    }
}
