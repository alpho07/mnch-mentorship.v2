<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class MentorshipGuidanceNotice extends Widget
{
    protected static string $view = 'filament.widgets.mentorship-guidance-notice';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;
}
