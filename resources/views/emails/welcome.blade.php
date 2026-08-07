<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Welcome to {{ config('app.name') }}</title>
<style>
  body { margin: 0; padding: 0; background: #f5f5f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1a1a1a; }
  .wrap { max-width: 580px; margin: 32px auto; background: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb; }
  .hero { background: #4f46e5; color: #ffffff; padding: 36px 32px 28px; }
  .hero h1 { margin: 0 0 6px; font-size: 22px; font-weight: 700; }
  .hero p  { margin: 0; font-size: 14px; opacity: .85; }
  .body  { padding: 28px 32px; font-size: 15px; line-height: 1.6; color: #374151; }
  .body p { margin: 0 0 16px; }
  .links { margin: 24px 0 8px; }
  .cta { display: block; margin: 12px auto 0; width: fit-content; background: #4f46e5; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: 600; }
  .cta.secondary { background: #f9fafb; color: #374151; border: 1px solid #e5e7eb; }
  .footer { padding: 20px 32px; background: #f9fafb; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
</style>
</head>
<body>
<div class="wrap">
  <div class="hero">
    <h1>Welcome aboard, {{ $user->name }}!</h1>
    <p>Your account at {{ config('app.name') }} is ready.</p>
  </div>

  <div class="body">
    <p>
      Thanks for joining {{ config('app.name') }}. We're glad to have you — your
      account is all set up and you can start browsing our fleet right away.
    </p>

    <p>
      To keep things moving when you're ready to book, complete your driver
      verification ahead of time so your first rental goes smoothly.
    </p>

    <div class="links">
      <a href="{{ url('/vehicles') }}" class="cta">Browse our fleet</a>
      <a href="{{ url('/account/driver-verification') }}" class="cta secondary">Complete driver verification</a>
    </div>
  </div>

  <div class="footer">
    You received this email because an account was created at {{ config('app.name') }} using this email address.
    If this wasn't you, you can safely ignore this email.
  </div>
</div>
</body>
</html>
