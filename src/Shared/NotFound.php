<?php

declare(strict_types=1);

namespace Apimail\Shared;

use RuntimeException;

/** 404 : la ressource n'existe pas, ou le Compte ne la voit pas. */
final class NotFound extends RuntimeException
{
}
