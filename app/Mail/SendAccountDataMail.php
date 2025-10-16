<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendAccountDataMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $login;
    public string $password;

    /**
     * Create a new message instance.
     */
    public function __construct(string $login, string $password)
    {
        $this->login = $login;
        $this->password = $password;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->subject('Ваши данные для входа')
            ->view('emails.account-data');
    }
}

