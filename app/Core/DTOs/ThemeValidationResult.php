<?php

declare(strict_types=1);

namespace App\Core\DTOs;

class ThemeValidationResult
{
    /** @param string[] $errors */
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors,
    ) {}
}
