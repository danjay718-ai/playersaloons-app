<?php

declare(strict_types=1);

namespace App\Mail;

use App\Modules\Community\Models\NewsletterCampaign;
use App\Modules\Identity\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class NewsletterCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public NewsletterCampaign $campaign,
        public User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->campaign->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.newsletter-campaign',
            with: [
                'unsubscribeUrl' => URL::signedRoute('newsletter.unsubscribe', ['user' => $this->recipient->uuid]),
            ],
        );
    }
}
