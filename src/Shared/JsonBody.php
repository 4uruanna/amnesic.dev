<?php

declare(strict_types=1);

namespace Apimail\Shared;

use JsonException;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;

/** Le corps JSON d'une requête : 415 hors application/json, 400 s'il est mal formé, 422 si ce n'est pas un objet. */
final class JsonBody
{
    /** @return array<string, mixed> */
    public static function read(ServerRequestInterface $request): array
    {
        if (strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0])) !== 'application/json') {
            throw new HttpException($request, 'Le corps doit être en application/json.', 415);
        }
        try {
            $data = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
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
