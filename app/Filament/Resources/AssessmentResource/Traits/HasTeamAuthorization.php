<?php

namespace App\Filament\Resources\AssessmentResource\Traits;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use Filament\Notifications\Notification;

/**
 * Drop this trait into any AssessmentResource section edit page.
 * It gates mount() and save() behind team membership + lock status.
 *
 * Usage in a section page:
 *   use HasSectionNavigation, HasTeamAuthorization;
 */
trait HasTeamAuthorization {

    /**
     * Call this at the start of mount() to enforce access control.
     * Redirects to the dashboard with an appropriate notification if denied.
     */
    protected function authorizeTeamAccess(Assessment $record): void {
        $userId = auth()->id();

        // Must be a team member or superior to even view
        if (!$record->canBeViewedBy($userId)) {
            Notification::make()
                    ->title('Access denied')
                    ->body('You are not a member of this assessment team.')
                    ->danger()
                    ->send();

            $this->redirect(AssessmentResource::getUrl());
            return;
        }

        // If the assessment is locked, allow viewing but block saving
        if ($record->isLocked()) {
            // We still allow the page to load — but the form will be disabled
            // and save attempts will be blocked in authorizeTeamSave().
            return;
        }

        // Must be able to edit (team member + unlocked)
        if (!$record->canBeEditedBy($userId)) {
            Notification::make()
                    ->title('Assessment is locked')
                    ->body('This assessment has been locked and cannot be edited.')
                    ->warning()
                    ->send();
        }
    }

    /**
     * Call this in mutateFormDataBeforeSave() or beforeSave() to block
     * saves on locked assessments.
     *
     * @throws \Filament\Exceptions\Halt
     */
    protected function authorizeTeamSave(Assessment $record): void {
        if (!$record->canBeEditedBy(auth()->id())) {
            Notification::make()
                    ->title($record->isLocked() ? 'Assessment is locked' : 'Access denied')
                    ->body($record->isLocked() ? 'This assessment is locked. Unlock it before saving changes.' : 'You do not have permission to edit this assessment.'
                    )
                    ->danger()
                    ->send();

            $this->halt(); // Filament Page::halt() — aborts the save
        }
    }

    /**
     * Returns true if the current user can edit the given assessment.
     * Use this in blade views to conditionally render edit controls.
     */
    protected function currentUserCanEdit(Assessment $record): bool {
        return $record->canBeEditedBy(auth()->id());
    }

    /**
     * Returns the lock status badge HTML for display in section pages.
     */
    protected function getLockBadge(Assessment $record): string {
        if (!$record->is_locked)
            return '';

        $lockedBy = $record->locker?->name ?? 'unknown';
        $lockedAt = $record->locked_at?->format('d M Y H:i') ?? '';

        return <<<HTML
        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-danger-50 border border-danger-200 text-danger-700 text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            Locked by {$lockedBy} on {$lockedAt}
        </div>
        HTML;
    }
}
