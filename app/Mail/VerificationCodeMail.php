<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public string $code)
    {
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->subject('Код подтверждения')
            ->view('emails.verification-code');
    }
}
