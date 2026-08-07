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

class BookingReturned extends Mailable implements ShouldQueue
{
    use BookingMailData, Queueable, SerializesModels;

    public function __construct(public readonly Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your rental is complete — Booking #'.$this->booking->booking_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-returned',
            with: $this->bookingMailData($this->booking),
        );
    }
}
