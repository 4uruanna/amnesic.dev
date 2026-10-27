<?php

declare(strict_types=1);

namespace Apimail\Shared;

use PDO;
use RuntimeException;

/** Applique dans l'ordre les migrations/*.sql absents de schema_migrations (docs/infra.md § Migrations). */
final class Migrator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $directory = __DIR__ . '/../../migrations',
    ) {
    }

    /** @return list<string> les fichiers appliqués, dans l'ordre */
    public function migrate(): array
    {
        // Le verrou porte sur le serveur MySQL entier, partagé sur OVH : son nom inclut celui de la base.
        $lock = $this->pdo->prepare('SELECT GET_LOCK(CONCAT(\'apimail_migrate:\', DATABASE()), 60)');
        $lock->execute();
        if ($lock->fetchColumn() !== 1) {
            throw new RuntimeException('Verrou des migrations non obtenu.');
        }

        try {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
                version    VARCHAR(100) NOT NULL,
                applied_at DATETIME(3) NOT NULL,
                PRIMARY KEY (version)
            )');
            // Lu après le verrou : un appel simultané qui attendait ne réapplique rien.
            $done = $this->pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
            $note = $this->pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, UTC_TIMESTAMP(3))');

            $applied = [];
            foreach (glob($this->directory . '/*.sql') ?: [] as $file) {
                $version = basename($file);
                if (in_array($version, $done, true)) {
                    continue;
                }
                $this->pdo->exec(file_get_contents($file));
                $note->execute([$version]);
                $applied[] = $version;
            }

            return $applied;
        } finally {
            $this->pdo->query('SELECT RELEASE_LOCK(CONCAT(\'apimail_migrate:\', DATABASE()))');
        }
    }
}
