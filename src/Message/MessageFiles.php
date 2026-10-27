<?php

declare(strict_types=1);

namespace Apimail\Message;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** Le contenu des Messages sous storage/messages/{id}/ (data-model.md § Fichiers) ; aucun nom de fichier ne vient du client. */
final class MessageFiles
{
    public function __construct(private readonly string $storage)
    {
    }

    /** @param list<string> $parts le contenu décodé des pièces jointes, dans l'ordre des partId */
    public function write(string $id, ?string $text, ?string $html, array $parts): void
    {
        $directory = $this->directory($id);
        self::mkdir($directory);
        foreach (['text' => $text, 'html' => $html] as $name => $content) {
            if ($content !== null) {
                self::put("$directory/$name", $content);
            }
        }
        if ($parts !== []) {
            self::mkdir("$directory/parts");
        }
        foreach ($parts as $i => $content) {
            self::put("$directory/parts/" . ($i + 1), $content);
        }
    }

    /** Supprime le dossier du Message, s'il existe. */
    public function delete(string $id): void
    {
        $directory = $this->directory($id);
        if (!is_dir($directory)) {
            return;
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($directory);
    }

    private function directory(string $id): string
    {
        return "$this->storage/messages/$id";
    }

    private static function mkdir(string $directory): void
    {
        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Dossier $directory non créé.");
        }
    }

    private static function put(string $file, string $content): void
    {
        if (file_put_contents($file, $content) !== strlen($content)) {
            throw new RuntimeException("Fichier $file non écrit.");
        }
    }
}
