<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset your password</title>
<style>
  body { margin: 0; padding: 0; background: #f5f5f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1a1a1a; }
  .wrap { max-width: 580px; margin: 32px auto; background: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb; }
  .hero { background: #4f46e5; color: #ffffff; padding: 36px 32px 28px; }
  .hero h1 { margin: 0 0 6px; font-size: 22px; font-weight: 700; }
  .hero p  { margin: 0; font-size: 14px; opacity: .85; }
  .body  { padding: 28px 32px; }
  .body p { font-size: 14px; line-height: 1.6; color: #374151; margin: 0 0 16px; }
  .cta { display: block; margin: 28px auto 0; width: fit-content; background: #4f46e5; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: 600; }
  .meta { margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 13px; color: #6b7280; line-height: 1.6; }
  .meta code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: 12px; word-break: break-all; }
  .footer { padding: 20px 32px; background: #f9fafb; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
</style>
</head>
<body>
<div class="wrap">
  <div class="hero">
    <h1>Reset your password</h1>
    <p>{{ $appName }}</p>
  </div>

  <div class="body">
    <p>
      We received a request to reset the password for your {{ $appName }} account
      ({{ $email }}).
    </p>
    <p>
      To choose a new password, click the button below. This link will expire in
      {{ $expires }} minutes.
    </p>
    <a href="{{ $url }}" class="cta">Reset Password</a>

    <div class="meta">
      If the button doesn't work, copy and paste this link into your browser:<br>
      <code>{{ $url }}</code>
      <br><br>
      If you didn't request a password reset, no further action is needed — your
      password will stay the same.
    </div>
  </div>

  <div class="footer">
    You received this email because someone requested a password reset at {{ $appName }} for this email address.
    If you have questions, reply to this email.
  </div>
</div>
</body>
</html>
