<?php

namespace App\Wa\Events;

use App\Wa\Hub\Models\WhatsappSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncomingWhatsappMessageReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WhatsappSession $session,
        public array $messageData
    ) {}
}
