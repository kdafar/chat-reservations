<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;

/**
 * Adds a reusable "Help" header action to any Filament Page or Resource page.
 *
 * Usage:
 *   1. `use HasHelpAction;` on the page/list class.
 *   2. Implement `helpContent(): array` returning an array of sections.
 *   3. Call `$this->helpAction()` inside `getHeaderActions()` (or use the
 *      `withHelp($actions)` helper to append it cleanly to existing actions).
 *
 * Section format:
 *   [
 *     ['heading' => 'What is this page?', 'body' => '...one or more paragraphs...'],
 *     ['heading' => 'How to use it',      'items' => ['bullet 1', 'bullet 2', ...]],
 *     ['heading' => 'Common questions',   'items' => [['q' => '...', 'a' => '...'], ...]],
 *   ]
 *
 * Sections with `items` render as a bullet list; sections with `body` render as a
 * paragraph; sections with `items` that are q/a pairs render as a definition list.
 */
trait HasHelpAction
{
    /**
     * Concrete classes override this to provide page-specific help content.
     */
    protected function helpContent(): array
    {
        return [];
    }

    /**
     * Optional override for the help modal heading. Defaults to the page title.
     */
    protected function helpHeading(): ?string
    {
        return null;
    }

    /**
     * Build the Filament header action. Safe to call even when helpContent()
     * is empty — the button just won't show.
     */
    protected function helpAction(): ?Action
    {
        $sections = $this->helpContent();
        if (empty($sections)) {
            return null;
        }

        $heading = $this->helpHeading()
            ?? (method_exists($this, 'getTitle') ? (string) $this->getTitle() : null)
            ?? static::$title
            ?? __('common.actions.help');

        return Action::make('help')
            ->label(__('common.actions.help'))
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->modalHeading(__('help.modal.heading', ['page' => $heading]))
            ->modalDescription(__('help.modal.description'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('common.actions.close'))
            ->modalContent(view('filament.components.help-panel', [
                'sections' => $sections,
            ]))
            ->slideOver();
    }

    /**
     * Append the help action to an existing list of header actions.
     * Returns the merged array; safe when help content is empty.
     */
    protected function withHelp(array $actions): array
    {
        $help = $this->helpAction();

        return $help ? [...$actions, $help] : $actions;
    }
}
