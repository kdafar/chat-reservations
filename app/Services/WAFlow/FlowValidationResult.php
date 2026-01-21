<?php

namespace App\Services\WAFlow;

class FlowValidationResult
{
    public function __construct(
        public bool $ok,
        public array $errors = [],
        public array $normalized = [],
    ) {}
}
