<?php

namespace Aster255\MakerChecker\Exceptions;

use RuntimeException;

class ModelCannotCheckRequests extends RuntimeException
{
    public static function create(string $modelClass): self
    {
        return new self("Cannot approve/decline request: model: {$modelClass} is not allowed to check requests.");
    }
}
