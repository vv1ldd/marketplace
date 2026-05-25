<?php

namespace App\Filament\Support\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Models\Ticket;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Support Hub';

    public function mount(): void
    {
        $user = auth()->user();

        // Fetch ticket counts
        $totalTickets = Ticket::count();
        $openTickets = Ticket::where('status', 'open')->count();
        $closedTickets = Ticket::where('status', 'closed')->count();

        response()->view('ops.support', [
            'user' => $user,
            'totalTickets' => $totalTickets,
            'openTickets' => $openTickets,
            'closedTickets' => $closedTickets,
        ])->send();
        exit;
    }
}
