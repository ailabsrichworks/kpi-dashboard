<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email twin of an in-app notification row (see NotificationService::notify()).
 * Deliberately generic — one template for every notification type (appraisal,
 * approval, job description) rather than a Mailable per type, since the
 * content is already fully formed (title/message/link) by the time notify()
 * is called.
 */
class AppNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $title,
        public ?string $message,
        public ?string $link,
    ) {}

    public function build()
    {
        return $this->subject($this->title)
            ->view('emails.notification');
    }
}
