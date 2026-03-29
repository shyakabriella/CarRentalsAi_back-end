<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class DriverBookingAssignedNotification extends Notification
{
    use Queueable;

    public Booking $booking;
    public string $context;

    public function __construct(Booking $booking, string $context = 'assigned')
    {
        $this->booking = $booking;
        $this->context = $context;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing([
            'customer.user',
            'driver.user',
            'vehicle',
            'pickupLocation',
            'dropoffLocation',
        ]);

        $driverName = $booking->driver->user->name
            ?? $booking->driver->name
            ?? 'Driver';

        $customerName = $booking->customer->user->name
            ?? $booking->customer->name
            ?? 'Customer';

        $vehicleName = $booking->vehicle->display_name
            ?? $booking->vehicle->name
            ?? trim(
                ($booking->vehicle->year ?? '') . ' ' .
                ($booking->vehicle->make ?? '') . ' ' .
                ($booking->vehicle->model ?? '')
            );

        if (!$vehicleName) {
            $vehicleName = 'Vehicle';
        }

        $pickup = $booking->pickupLocation->name
            ?? data_get($booking->meta, 'pickup_address')
            ?? '—';

        $dropoff = $booking->dropoffLocation->name
            ?? data_get($booking->meta, 'dropoff_address')
            ?? '—';

        $subject = match ($this->context) {
            'new'     => 'New booking assigned to you - ' . ($booking->code ?? ('#' . $booking->id)),
            'updated' => 'Booking updated for you - ' . ($booking->code ?? ('#' . $booking->id)),
            default   => 'Booking assigned to you - ' . ($booking->code ?? ('#' . $booking->id)),
        };

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.driver-booking-assigned', [
                'driverName'   => $driverName,
                'customerName' => $customerName,
                'vehicleName'  => $vehicleName,
                'pickup'       => $pickup,
                'dropoff'      => $dropoff,
                'pickupTime'   => $booking->pickup_time,
                'dropoffTime'  => $booking->dropoff_time,
                'status'       => $booking->status ?? 'pending',
                'currency'     => $booking->currency ?? 'RWF',
                'priceTotal'   => $booking->price_total ?? 0,
                'bookingCode'  => $booking->code ?? ('#' . $booking->id),
                'context'      => $this->context,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id'   => $this->booking->id,
            'booking_code' => $this->booking->code,
            'driver_id'    => $this->booking->driver_id,
            'customer_id'  => $this->booking->customer_id,
            'vehicle_id'   => $this->booking->vehicle_id,
            'context'      => $this->context,
        ];
    }
}