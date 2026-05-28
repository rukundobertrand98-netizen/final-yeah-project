<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\QrCodeGenerator;
use App\Services\TicketService;
use Illuminate\Http\Response;

class TicketQrWebController extends Controller
{
    public function show(Ticket $ticket, TicketService $ticketService, QrCodeGenerator $qr): Response
    {
        if (auth()->check() && auth()->id() !== $ticket->booking->user_id && ! auth()->user()->isRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Driver, \App\Enums\UserRole::Operator)) {
            abort(403);
        }

        $svg = $qr->svg($ticketService->qrPayload($ticket));

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }
}
