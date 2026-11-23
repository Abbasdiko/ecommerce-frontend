<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PortalLoginTicketService
{
    public function issue(User $user): string
    {
        DB::table('portal_login_tickets')
            ->where('expires_at', '<=', now())
            ->delete();

        $ticket = Str::random(64);
        DB::table('portal_login_tickets')->insert([
            'ticket_hash' => hash('sha256', $ticket),
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(2),
            'created_at' => now(),
        ]);

        return $ticket;
    }
}
