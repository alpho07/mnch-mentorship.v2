<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class MentorshipSetupNotice extends Widget {
    protected static string $view = 'filament.components.mentorship-note';
    protected int|string|array $columnSpan = 'full';
}
