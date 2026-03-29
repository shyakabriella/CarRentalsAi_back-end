<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Driver Booking Notification</title>
</head>
<body style="margin:0; padding:0; background:#f8fafc; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">
    <div style="max-width:680px; margin:0 auto; padding:32px 16px;">
        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden;">
            <div style="background:linear-gradient(135deg, #059669, #0f766e); padding:24px 28px;">
                <h1 style="margin:0; font-size:22px; line-height:1.3; color:#ffffff;">
                    SmartCar AI
                </h1>
                <p style="margin:8px 0 0; font-size:14px; color:#dcfce7;">
                    Driver booking notification
                </p>
            </div>

            <div style="padding:28px;">
                <p style="margin:0 0 14px; font-size:16px;">
                    Hello <strong>{{ $driverName }}</strong>,
                </p>

                <p style="margin:0 0 18px; font-size:14px; line-height:1.7; color:#334155;">
                    @if($context === 'new')
                        A new booking has been assigned to you.
                    @elseif($context === 'updated')
                        A booking assigned to you has been updated.
                    @else
                        A booking has been assigned to you.
                    @endif
                    Please review the details below and login to your driver dashboard.
                </p>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:18px;">
                    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                        <tr>
                            <td style="padding:8px 0; font-size:14px; color:#64748b; width:180px;">Booking Code</td>
                            <td style="padding:8px 0; font-size:14px; font-weight:700; color:#0f172a;">{{ $bookingCode }}</td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0; font-size:14px; color:#64748b;">Customer</td>
                            <td style="padding:8px 0; font-size:14px; font-weight:700; color:#0f172a;">{{ $customerName }}</td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0; font-size:14px; color:#64748b;">Vehicle</td>
                            <td style="padding:8px 0; font-size:14px; font-weight:700; color:#0f172a;">{{ $vehicleName }}</td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0; font-size:14px; color:#64748b;">Pickup</td>
                            <td style="padding:8px 0; font-size:14px; font-weight:700; color:#0f172a;">{{ $pickup }}</td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0; font-size:14px; color:#64748b;">Dropoff</td>
                            <td style="padding:8px 0; font-size:14px; font-weight:700; color:#0f172a;">{{ $dropoff }}</td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0; font-size:14px; color:#64748b;">Pickup Time</td>
                            <td style="padding:8px 0; font-size:14px; font-weight:700; color:#0f172a;">{{ $pickupTime ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0; font-size:14px; color:#64748b;">Dropoff Time</td>
                            <td style="padding:8px 0; font-size:14px; font-weight:700; color:#0f172a;">{{ $dropoffTime ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0; font-size:14px; color:#64748b;">Status</td>
                            <td style="padding:8px 0; font-size:14px; font-weight:700; color:#0f172a;">{{ ucfirst(str_replace('_', ' ', $status ?? 'pending')) }}</td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0; font-size:14px; color:#64748b;">Total</td>
                            <td style="padding:8px 0; font-size:14px; font-weight:700; color:#0f172a;">{{ $currency }} {{ $priceTotal }}</td>
                        </tr>
                    </table>
                </div>

                <p style="margin:18px 0 0; font-size:14px; line-height:1.7; color:#334155;">
                    Please login to your driver account to see this booking under your dashboard.
                </p>

                <p style="margin:24px 0 0; font-size:14px; color:#0f172a;">
                    Thank you,<br>
                    <strong>SmartCar AI</strong>
                </p>
            </div>
        </div>
    </div>
</body>
</html>