<?php

namespace App\Wa\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OutgoingWhatsappStatusReceived
{
    use Dispatchable, SerializesModels;

    /** @var array Raw status payload from Meta */
    public array $status;

    public function __construct(array $status)
    {
        $this->status = $status;
    }
}
