<?php

declare(strict_types=1);

namespace Apimail\Shared;

use JsonException;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;

/** Le corps JSON d'une requête : 415 hors application/json, 413 au-delà de 25 Mio, 400 s'il est mal formé, 422 si ce n'est pas un objet. */
final class JsonBody
{
    private const int MAX_BYTES = 25 * 1024 * 1024;

    /** @return array<string, mixed> */
    public static function read(ServerRequestInterface $request): array
    {
        if (strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0])) !== 'application/json') {
            throw new HttpException($request, 'Le corps doit être en application/json.', 415);
        }
        if ((int) $request->getHeaderLine('Content-Length') > self::MAX_BYTES) {
            throw new HttpException($request, 'Corps de plus de 25 Mio.', 413);
        }
        // Content-Length peut manquer ou mentir : on ne lit jamais plus d'un octet au-delà de la limite.
        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
        $json = '';
        while (strlen($json) <= self::MAX_BYTES && !$body->eof()) {
            $json .= $body->read(self::MAX_BYTES + 1 - strlen($json));
        }
        if (strlen($json) > self::MAX_BYTES) {
            throw new HttpException($request, 'Corps de plus de 25 Mio.', 413);
        }
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new HttpBadRequestException($request, 'JSON mal formé.');
        }
        // json_decode rend {} et [] tous deux en [] : seul un tableau non vide trahit une liste.
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new ValidationFailed([['field' => '', 'message' => 'Objet JSON attendu.']]);
        }

        return $data;
    }
}
