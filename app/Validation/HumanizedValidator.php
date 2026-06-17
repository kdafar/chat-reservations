<?php

namespace App\Validation;

use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Makes validation messages for NESTED array fields read like a human wrote
 * them. By default Laravel exposes the raw path as the attribute, so a rule on
 * `lines.*.account_id` produces:
 *
 *     "The lines.1.account_id field is required."
 *
 * which leaks internal field structure to the user. This validator rewrites the
 * displayable attribute for any dotted path to just its field label:
 *
 *     "The account field is required."
 *
 * It is registered globally (AppServiceProvider) so every form on every page
 * benefits with no per-field config. Explicit custom attributes / the
 * `validation.attributes` lang array still win — parent returns those without a
 * dot, and we only post-process the raw nested fallback.
 */
class HumanizedValidator extends Validator
{
    public function getDisplayableAttribute($attribute)
    {
        $display = parent::getDisplayableAttribute($attribute);

        // A developer-specified name never contains a dot; only the raw nested
        // fallback (e.g. "lines.1.account id") does. Leave explicit names alone.
        if (! str_contains($display, '.')) {
            return $display;
        }

        // "lines.1.account_id" -> drop indices/wildcards -> "account_id" -> "account"
        $segments = array_values(array_filter(
            explode('.', $attribute),
            static fn ($s) => $s !== '*' && ! ctype_digit($s)
        ));
        $field = end($segments) ?: $attribute;
        $field = preg_replace('/_id$/', '', $field);

        return str_replace('_', ' ', Str::snake($field));
    }
}
