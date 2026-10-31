<?php

declare(strict_types=1);

namespace Robot;

use RuntimeException;
use SensitiveParameter;

/** Client de l'API REST de Mailpit, en authentification basique : relais.md § Cycle. Lève RuntimeException sur toute réponse autre que 200, avec son code. */
final class Mailpit
{
    private readonly string $url;

    public function __construct(
        string $url,
        private readonly string $user,
        #[SensitiveParameter] private readonly string $password,
    ) {
        $this->url = rtrim($url, '/');
    }

    /** @return list<string> les ID des messages sans étiquette rejet, du plus ancien au plus récent */
    public function pending(): array
    {
        $ids = [];
        // Mailpit liste du plus récent au plus ancien. Un mail arrivé entre deux pages décale les suivantes : une clé ne compte qu'une fois.
        for ($start = 0; ; $start += 50) {
            $messages = $this->json('GET', '/api/v1/search?' . http_build_query(['query' => '!tag:rejet', 'start' => $start, 'limit' => 50]))['messages'];
            $ids += array_fill_keys(array_column($messages, 'ID'), true);
            if (count($messages) < 50) {
                return array_reverse(array_map(strval(...), array_keys($ids)));
            }
        }
    }

    /** @return array<string, mixed> */
    public function message(string $id): array
    {
        return $this->json('GET', '/api/v1/message/' . rawurlencode($id));
    }

    public function part(string $id, string $partId): string
    {
        return $this->request('GET', '/api/v1/message/' . rawurlencode($id) . '/part/' . rawurlencode($partId));
    }

    public function delete(string $id): void
    {
        $this->request('DELETE', '/api/v1/messages', ['IDs' => [$id]]);
    }

    /** @param list<string> $tags toutes les étiquettes du message : Mailpit remplace celles qu'il avait */
    public function tag(string $id, array $tags): void
    {
        $this->request('PUT', '/api/v1/tags', ['IDs' => [$id], 'Tags' => $tags]);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function json(string $method, string $path, ?array $body = null): array
    {
        return json_decode($this->request($method, $path, $body), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed>|null $body */
    private function request(string $method, string $path, ?array $body = null): string
    {
        $curl = curl_init($this->url . $path);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERNAME => $this->user,
            CURLOPT_PASSWORD => $this->password,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
        ] + ($body === null ? [] : [CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR)]));
        $response = curl_exec($curl);
        if ($response === false) {
            throw new RuntimeException('mailpit: ' . curl_error($curl));
        }
        $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($code !== 200) {
            throw new RuntimeException("mailpit $code", $code);
        }

        return $response;
    }
}
