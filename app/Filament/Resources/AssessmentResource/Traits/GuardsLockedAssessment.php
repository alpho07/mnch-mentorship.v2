<?php

namespace App\Filament\Resources\AssessmentResource\Traits;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use Filament\Notifications\Notification;

/**
 * Blocks access to a section-edit page once an assessment has been marked
 * complete and locked (see ListAssessments::mark_complete). Locking is
 * deliberately absolute here — unlike team-lock toggling, nobody but an
 * admin/super_admin can edit past it, including the assessment's own lead.
 */
trait GuardsLockedAssessment
{
    /**
     * Call from mount(), after $this->record is set:
     *   if ($this->abortIfLocked($this->record)) { return; }
     * Redirects to the summary page (read-only) if the assessment is
     * locked, and reports true so the caller stops setting up the form.
     */
    protected function abortIfLocked(Assessment $record): bool
    {
        if (! $record->is_locked || auth()->user()?->hasRole(['admin', 'super_admin'])) {
            return false;
        }

        Notification::make()
            ->title('Assessment is locked')
            ->body('This assessment has been marked complete and locked. Only an admin can reopen it.')
            ->warning()
            ->send();

        $this->redirect(AssessmentResource::getUrl('summary', ['record' => $record]));

        return true;
    }

    /**
     * Call at the top of mutateFormDataBeforeSave() as defense-in-depth
     * against a save submitted from a page loaded before the lock was set.
     *
     * @throws \Filament\Support\Exceptions\Halt
     */
    protected function haltIfLocked(Assessment $record): void
    {
        if (! $record->is_locked || auth()->user()?->hasRole(['admin', 'super_admin'])) {
            return;
        }

        Notification::make()
            ->title('Assessment is locked')
            ->body('This assessment has been marked complete and locked. Only an admin can reopen it.')
            ->warning()
            ->send();

        $this->halt();
    }
}
