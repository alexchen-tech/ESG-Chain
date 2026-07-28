<?php

namespace App\Mail;

use App\Models\CAP;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CapAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly CAP $cap) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "新的矯正行動待處理：{$this->cap->title}",
        );
    }

    public function content(): Content
    {
        $url = rtrim(config('notifications.frontend_url'), '/') . "/supplier/portal/caps/{$this->cap->id}";

        return new Content(
            markdown: 'mail.cap-assigned',
            with: ['cap' => $this->cap, 'url' => $url],
        );
    }
}
