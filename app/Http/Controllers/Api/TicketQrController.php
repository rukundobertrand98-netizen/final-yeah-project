<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\QrCodeGenerator;
use App\Services\TicketService;
use Illuminate\Http\Response;

class TicketQrController extends Controller
{
    public function show(Ticket $ticket, TicketService $ticketService, QrCodeGenerator $qr): Response
    {
        $svg = $qr->svg($ticketService->qrPayload($ticket));

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }
}
