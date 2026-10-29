<?php

declare(strict_types=1);

namespace Apimail\Message\Dto;

use Apimail\Shared\ValidationFailed;

/** Une pièce jointe de MessageDeposit ; content ressort décodé. */
final readonly class AttachmentDeposit
{
    private function __construct(
        public string $filename,
        public string $contentType,
        public string $content,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $errors = [];
        foreach (array_diff(array_keys($data), ['filename', 'contentType', 'content']) as $field) {
            $errors[] = ['field' => (string) $field, 'message' => 'Champ inconnu.'];
        }
        $filename = $data['filename'] ?? null;
        if ($filename === null) {
            $errors[] = ['field' => 'filename', 'message' => 'Champ obligatoire.'];
        } elseif (!is_string($filename) || $filename === '' || mb_strlen($filename) > 255 || preg_match('/\p{Cc}/u', $filename) === 1) {
            $errors[] = ['field' => 'filename', 'message' => 'Chaîne de 1 à 255 caractères sans caractère de contrôle attendue.'];
        }
        $contentType = $data['contentType'] ?? null;
        if ($contentType === null) {
            $errors[] = ['field' => 'contentType', 'message' => 'Champ obligatoire.'];
        } elseif (
            !is_string($contentType) || mb_strlen($contentType) > 255 || preg_match('/\p{Cc}/u', $contentType) === 1
            // type et sous-type sont des tokens (RFC 9110) ; les paramètres après « ; » sont libres.
            || preg_match('/\A[\w!#$%&\'*+.^`|~-]+\/[\w!#$%&\'*+.^`|~-]+(?:\s*;.*)?\z/s', $contentType) !== 1
        ) {
            $errors[] = ['field' => 'contentType', 'message' => 'Type type/sous-type de 255 caractères au plus, sans caractère de contrôle, attendu.'];
        }
        $content = $data['content'] ?? null;
        // base64_decode(…, true) tolère les blancs et un padding absent : l'alphabet et la longueur sont vérifiés avant.
        $decoded = is_string($content) && strlen($content) % 4 === 0 && preg_match('/[^A-Za-z0-9+\/=]/', $content) === 0
            ? base64_decode($content, true)
            : false;
        if ($content === null) {
            $errors[] = ['field' => 'content', 'message' => 'Champ obligatoire.'];
        } elseif ($decoded === false) {
            $errors[] = ['field' => 'content', 'message' => 'Base64 standard attendu.'];
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return new self($filename, $contentType, $decoded);
    }
}
