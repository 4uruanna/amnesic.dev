<?php

declare(strict_types=1);

namespace Robot;

/** Un message Mailpit devient un MessageDeposit : relais.md § Conversion. Rien n'est validé ici, l'API est seule juge. */
final class Conversion
{
    /**
     * @param array<string, mixed> $message le JSON de GET /api/v1/message/{ID}
     * @param array<string, string> $parts le contenu de chaque pièce, par PartID
     * @return array<string, mixed>
     */
    public static function deposit(array $message, array $parts): array
    {
        return [
            'from' => self::address($message['From']),
            // Seul To donne accès : Cc et Bcc sont ignorés (ADR 0002).
            'to' => array_map(self::address(...), $message['To']),
            'subject' => $message['Subject'],
            'sentAt' => $message['Date'],
            'text' => $message['Text'] === '' ? null : $message['Text'],
            'html' => $message['HTML'] === '' ? null : $message['HTML'],
            'attachments' => array_map(fn (array $piece) => [
                'filename' => $piece['FileName'] === '' ? "partie-{$piece['PartID']}" : $piece['FileName'],
                'contentType' => $piece['ContentType'],
                'content' => base64_encode($parts[$piece['PartID']]),
            ], self::pieces($message)),
        ];
    }

    /**
     * @param array<string, mixed> $message
     * @return list<array<string, mixed>> les pièces du message, dans l'ordre du dépôt : Attachments, puis Inline
     */
    public static function pieces(array $message): array
    {
        return [...$message['Attachments'], ...$message['Inline']];
    }

    /**
     * @param array{Address: string, Name: string} $address
     * @return array{address: string, name: ?string}
     */
    private static function address(array $address): array
    {
        return ['address' => $address['Address'], 'name' => $address['Name'] === '' ? null : $address['Name']];
    }
}
