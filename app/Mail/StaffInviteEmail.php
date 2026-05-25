<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffInviteEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $partnerName;
    public string $roleLabel;
    public string $inviteLink;
    public ?string $inviteeName;

    public function __construct(
        string $partnerName,
        string $roleLabel,
        string $inviteLink,
        ?string $inviteeName = null,
    ) {
        $this->partnerName = $partnerName;
        $this->roleLabel = $roleLabel;
        $this->inviteLink = $inviteLink;
        $this->inviteeName = $inviteeName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Вас приглашают в {$this->partnerName} — MEANLY",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.staff-invite',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
