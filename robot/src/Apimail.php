<?php

declare(strict_types=1);

namespace Robot;

use RuntimeException;
use SensitiveParameter;

/** Client de POST /v1/messages, avec le Jeton du Bot : api.md § POST /v1/messages. */
final class Apimail
{
    private readonly string $url;

    public function __construct(string $url, #[SensitiveParameter] private readonly string $token)
    {
        $this->url = rtrim($url, '/');
    }

    /**
     * @param array<string, mixed> $deposit le MessageDeposit
     * @return array{int, array<string, mixed>} le code HTTP et le corps JSON de la réponse, vide s'il n'est pas du JSON
     * @throws RuntimeException sans réponse : délai de 300 s dépassé, erreur réseau
     */
    public function deposit(array $deposit): array
    {
        $curl = curl_init("$this->url/v1/messages");
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($deposit, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            // Sans « Expect: », curl attendrait un « 100 Continue » avant d'envoyer un corps de plus de 1 Mio.
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $this->token", 'Content-Type: application/json', 'Expect:'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 300,
        ]);
        $response = curl_exec($curl);
        if ($response === false) {
            throw new RuntimeException('apimail: ' . curl_error($curl));
        }

        $body = json_decode($response, true);

        return [curl_getinfo($curl, CURLINFO_RESPONSE_CODE), is_array($body) ? $body : []];
    }
}
