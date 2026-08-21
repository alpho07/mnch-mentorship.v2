<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;

class OnlineUsersBadge extends Component
{
    public function render()
    {
        $onlineUsers = User::where('last_seen_at', '>=', now()->subMinutes(5))->get();

        return view('livewire.online-users-badge', ['onlineUsers' => $onlineUsers]);
    }
}

