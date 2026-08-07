<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your rental has started — #{{ $bookingNumber }}</title>
<style>
  body { margin: 0; padding: 0; background: #f5f5f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1a1a1a; }
  .wrap { max-width: 580px; margin: 32px auto; background: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb; }
  .hero { background: #4f46e5; color: #ffffff; padding: 36px 32px 28px; }
  .hero h1 { margin: 0 0 6px; font-size: 22px; font-weight: 700; }
  .hero p  { margin: 0; font-size: 14px; opacity: .85; }
  .body  { padding: 28px 32px; }
  h2 { font-size: 14px; font-weight: 600; color: #374151; margin: 24px 0 10px; text-transform: uppercase; letter-spacing: .04em; }
  table { width: 100%; border-collapse: collapse; }
  td, th { padding: 8px 0; font-size: 14px; vertical-align: top; }
  th { text-align: left; color: #6b7280; font-weight: 500; }
  .addr { font-size: 14px; line-height: 1.6; color: #374151; }
  .right { text-align: right; }
  .footer { padding: 20px 32px; background: #f9fafb; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
  .cta { display: block; margin: 28px auto 0; width: fit-content; background: #4f46e5; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: 600; }
</style>
</head>
<body>
<div class="wrap">
  <div class="hero">
    <h1>Your rental has started!</h1>
    <p>Reference: <strong>#{{ $bookingNumber }}</strong></p>
  </div>

  <div class="body">
    <p style="font-size:14px;color:#374151;margin:0 0 4px;">
      The vehicle has been picked up and is now checked out to you. Details are below.
    </p>

    <h2>Vehicle</h2>
    <table>
      <tr>
        <td>{{ $vehicle->make }} {{ $vehicle->model }} ({{ $vehicle->year }})</td>
      </tr>
    </table>

    <h2>Pickup &amp; return</h2>
    <table>
      <tr>
        <th>Picked up</th>
        <td class="right">{{ $pickupAt->format('D, d M Y H:i') }}</td>
      </tr>
      <tr>
        <td colspan="2" class="addr">{{ $pickupLocation->name }}, {{ $pickupLocation->city }}</td>
      </tr>
      <tr>
        <th>Return</th>
        <td class="right">{{ $returnAt->format('D, d M Y H:i') }}</td>
      </tr>
      <tr>
        <td colspan="2" class="addr">{{ $returnLocation->name }}, {{ $returnLocation->city }}</td>
      </tr>
    </table>

    @if($trackUrl ?? null)
      <a href="{{ $trackUrl }}" class="cta">Track your booking</a>
    @endif
  </div>

  <div class="footer">
    You received this email because a booking was made at {{ config('app.name') }} using this email address.
    If you have questions, reply to this email.
  </div>
</div>
</body>
</html>
