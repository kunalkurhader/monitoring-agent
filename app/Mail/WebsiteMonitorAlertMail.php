<?php

namespace App\Mail;

use App\Models\WebsiteMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WebsiteMonitorAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WebsiteMonitor $monitor,
        public string $type,
        public array $details,
        private readonly string $alertSubject,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.config('app.name', 'Monitoring Agent').'] '.$this->alertSubject.': '.$this->monitor->name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.website-monitor-alert');
    }
}
