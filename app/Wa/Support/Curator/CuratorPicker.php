<?php

namespace App\Wa\Support\Curator;

use Filament\Forms\Components\FileUpload;

/**
 * Stand-in for Awcodes\Curator\Components\Forms\CuratorPicker.
 *
 * The clinic doesn't ship the Curator plugin, so this provides a working
 * upload field (extends Filament's FileUpload) and tolerantly swallows the
 * Curator-only fluent methods the ported resources chain (buttonLabel, color,
 * pathGenerator, ...) so the forms render and uploads work.
 */
class CuratorPicker extends FileUpload
{
    /** Curator-only fluent no-ops kept for API compatibility. */
    public function buttonLabel($label = null): static
    {
        return $this;
    }

    public function color($color = null): static
    {
        return $this;
    }

    public function pathGenerator($generator = null): static
    {
        return $this;
    }

    public function constrainImage($condition = true): static
    {
        return $this;
    }

    /**
     * Swallow any other Curator-specific fluent method not present on
     * FileUpload, returning $this so chains keep working.
     */
    public function __call($name, $arguments)
    {
        try {
            return parent::__call($name, $arguments);
        } catch (\BadMethodCallException $e) {
            return $this;
        }
    }
}
