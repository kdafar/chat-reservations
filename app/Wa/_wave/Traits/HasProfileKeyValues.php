<?php

namespace Wave\Traits;

/**
 * No-op shim of Wave\Traits\HasProfileKeyValues for the isolated WhatsApp module.
 * The module imports this trait on its User model but never calls its methods,
 * so a self-contained stub avoids dragging in Wave\ProfileKeyValue + helpers.
 */
trait HasProfileKeyValues
{
    public function profileKeyValues()
    {
        // Module has no profile_key_values table; return an empty relation-like
        // collection guard. Not used by the WhatsApp code paths.
        return collect();
    }

    public function profileKeyValue($key)
    {
        return null;
    }

    public function setProfileKeyValue($key, $value, $type = 'text')
    {
        return null;
    }
}
