<?php

declare(strict_types=1);

namespace Robot;

/** Un message Mailpit et son mail /raw deviennent un MessageDeposit : relais.md § Conversion. Rien n'est validé ici, l'API est seule juge. */
final class Conversion
{
    /**
     * @param array<string, mixed> $message le JSON de GET /api/v1/message/{ID}
     * @param array<string, string> $parts le contenu de chaque pièce, par PartID
     * @param ?string $raw le mail de GET /api/v1/message/{ID}/raw, ou null pour un dépôt sans Original (ADR 0016)
     * @return array<string, mixed>
     */
    public static function deposit(array $message, array $parts, ?string $raw): array
    {
        $original = $raw === null ? null : self::original($raw);

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
            'raw' => $original === null ? null : base64_encode($original),
        ];
    }

    /**
     * L'Original : le mail de Mailpit sans ce qui nommerait un destinataire caché, tout en-tête Bcc et le Received de Mailpit,
     * dont le for nomme le premier destinataire de l'enveloppe. Le reste, corps compris, est gardé octet pour octet.
     * Null si PCRE échoue sur l'en-tête : le mail part alors sans Original, plutôt qu'avec un en-tête perdu ou un Bcc.
     */
    public static function original(string $raw): ?string
    {
        // L'en-tête s'arrête à la première ligne vide ; Mailpit garde les fins de ligne reçues, CRLF ou LF seul.
        $end = preg_match('/\n\r?\n/', $raw, $blank, PREG_OFFSET_CAPTURE) === 1 ? $blank[0][1] + 1 : strlen($raw);
        // Un champ : sa première ligne, puis ses lignes repliées, qui commencent par une espace ou une tabulation (RFC 5322 § 2.2.3).
        $header = preg_replace_callback(
            '/^([^:\r\n]*):.*(?:\r?\n[ \t].*)*(?:\r?\n|\z)/m',
            fn (array $field) => self::namesAHiddenRecipient($field[1], $field[0]) ? '' : $field[0],
            substr($raw, 0, $end),
        );

        return $header === null ? null : $header . substr($raw, $end);
    }

    /**
     * @param array<string, mixed> $message
     * @return list<array<string, mixed>> les pièces du message, dans l'ordre du dépôt : Attachments, puis Inline
     */
    public static function pieces(array $message): array
    {
        return [...$message['Attachments'], ...$message['Inline']];
    }

    private static function namesAHiddenRecipient(string $name, string $field): bool
    {
        $name = strtolower(rtrim($name));

        return $name === 'bcc' || ($name === 'received' && str_contains($field, '(Mailpit) with SMTP'));
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
