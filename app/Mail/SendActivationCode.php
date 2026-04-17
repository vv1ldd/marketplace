<?php

namespace App\Mail;

use App\Models\Order\Order;
use App\Models\WildflowCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendActivationCode extends Mailable
{
    use Queueable, SerializesModels;


    /**
     * Create a new message instance.
     */
    public function __construct(public string $code, public Order $order, public string $support_email = 'sataniyazow@gmail.com')
    {
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->subject('Код активации')
            ->view('emails.activation-code');
    }
}

