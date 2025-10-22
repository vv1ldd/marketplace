<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendAccountDataMail extends Mailable
{
    use Queueable, SerializesModels;


    /**
     * Create a new message instance.
     */
    public function __construct(public string $login, public string $password, public string $codes)
    {
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->subject('Ваш аккаунт PSN готов')
            ->view('emails.account-data');
    }
}

