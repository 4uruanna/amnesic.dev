<?php

declare(strict_types=1);

namespace Robot;

use Closure;
use RuntimeException;
use Throwable;

/** Un cycle du Robot : relever Mailpit, déposer chaque mail, puis le supprimer ou l'étiqueter (relais.md § Cycle, § Erreurs). */
final class Relais
{
    /** @param Closure(string): void $journal reçoit chaque ligne de relais.md § Journal, jamais un jeton ni un contenu de mail */
    public function __construct(
        private readonly Mailpit $mailpit,
        private readonly Apimail $apimail,
        private readonly Closure $journal,
    ) {
    }

    /** Au premier échec, de Mailpit ou de l'API, le cycle est abandonné : le suivant reprend les mails restés dans Mailpit. */
    public function cycle(): void
    {
        try {
            foreach ($this->mailpit->pending() as $id) {
                $this->relay($id);
            }
        } catch (Throwable $e) {
            ($this->journal)("retry {$e->getMessage()}");
        }
    }

    private function relay(string $id): void
    {
        try {
            $message = $this->mailpit->message($id);
            $parts = [];
            foreach (Conversion::pieces($message) as $piece) {
                $parts[$piece['PartID']] = $this->mailpit->part($id, $piece['PartID']);
            }
        } catch (RuntimeException $e) {
            // Une 400 ne passera jamais : un en-tête Date « +2400 », que Mailpit lit mais ne sait pas rendre en JSON.
            if ($e->getCode() !== 400) {
                throw $e;
            }
            ($this->journal)("rejected $id mailpit 400");
            $this->mailpit->tag($id, ['rejet']);

            return;
        }

        [$code, $body] = $this->apimail->deposit(Conversion::deposit($message, $parts));

        if ($code === 201) {
            ($this->journal)("deposited $id {$body['id']}");
            $this->mailpit->delete($id);
        } elseif (in_array($code, [400, 413, 415, 422], true)) {
            // Seule une 422 a des errors ; les autres n'ont que leur detail.
            foreach ($body['errors'] ?? [['field' => '', 'message' => $body['detail'] ?? '']] as $error) {
                ($this->journal)("rejected $id $code {$error['field']}: {$error['message']}");
            }
            $this->mailpit->tag($id, [...$message['Tags'], 'rejet']);
        } else {
            // 401, 403 : jeton faux ou révoqué ; 5xx, ou tout autre code : rien n'est touché.
            throw new RuntimeException("apimail $code");
        }
    }
}
