<?php

namespace App\Services\Chat;

enum Render: string
{
    case CARDS = 'cards';
    case MULTI_CARDS = 'multi_cards';
    case WIDGET = 'widget';
    case FREE_TEXT = 'free_text';
}
