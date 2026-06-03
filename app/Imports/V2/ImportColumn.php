<?php

namespace App\Imports\V2;

/**
 * One column in an import template. Drives three things at once:
 *   - the spreadsheet template header + example cell,
 *   - the per-row validation rules,
 *   - the human-readable "Instructions" sheet.
 */
class ImportColumn
{
    public function __construct(
        public string $key,            // header text, and the array key expected on upload
        public string $label,          // friendly label for the instructions sheet
        public bool $required = false,
        public array $rules = [],      // Laravel validation rules for the raw cell value
        public ?string $example = null,
        public ?string $note = null,   // extra guidance shown in the instructions sheet
        public array $allowed = [],    // enum of accepted values (shown in instructions)
    ) {}

    public static function make(string $key, string $label): static
    {
        return new static($key, $label);
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    public function example(string $example): static
    {
        $this->example = $example;

        return $this;
    }

    public function note(string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function allowed(array $allowed): static
    {
        $this->allowed = $allowed;

        return $this;
    }

    /** Effective validation rules (prepends required/nullable). */
    public function effectiveRules(): array
    {
        $rules = $this->rules;
        array_unshift($rules, $this->required ? 'required' : 'nullable');
        if ($this->allowed) {
            $rules[] = 'in:'.implode(',', $this->allowed);
        }

        return $rules;
    }
}
