<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\BookingMailData;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCheckedOut extends Mailable implements ShouldQueue
{
    public int $tries = 3;
    public array $backoff = [10, 60, 300];
    public int $maxExceptions = 3;
    use BookingMailData, Queueable, SerializesModels;

    public function __construct(public readonly Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your rental has started — Booking #'.$this->booking->booking_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-checked-out',
            with: $this->bookingMailData($this->booking),
        );
    }
}
