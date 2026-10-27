<?php

declare(strict_types=1);

namespace Apimail\Shared;

use RuntimeException;

/** 409 : nom de Compte ou Binding déjà existant. */
final class Conflict extends RuntimeException
{
}
