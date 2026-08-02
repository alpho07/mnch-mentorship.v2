<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipResource\Pages\Concerns\HasMentorshipChatSlots;
use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Setting;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;

class ChatMentorshipSetup extends Page implements HasForms
{
    use HasMentorshipChatSlots;
    use InteractsWithForms;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.chat-mentorship-setup';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(array $parameters = []): bool
    {
        if (! parent::canAccess($parameters)) {
            return false;
        }

        if (request()->filled('training')) {
            return true;
        }

        return Setting::getBool(Setting::CHAT_SETUP_BUTTON_ENABLED);
    }
}
