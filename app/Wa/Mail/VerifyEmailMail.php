<?php

namespace App\Wa\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Wave\User;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public string $verificationUrl;

    public function __construct(User $user, string $verificationUrl)
    {
        $this->user = $user;
        $this->verificationUrl = $verificationUrl;
    }

    public function build()
    {
        return $this->subject('Verify Your Email Address')
            ->view('emails.auth.verify_email');
    }
}
