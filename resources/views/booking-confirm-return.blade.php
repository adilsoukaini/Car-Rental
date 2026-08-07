<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Completing your booking…</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            display: grid;
            place-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f6f8;
            color: #1a1a1a;
        }
        .card {
            text-align: center;
            padding: 2rem;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .spinner {
            width: 28px;
            height: 28px;
            margin: 0 auto 1rem;
            border: 3px solid #dddddd;
            border-top-color: #555555;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="spinner"></div>
        <p>Payment received — completing your booking…</p>
        <noscript>
            <form method="POST" action="{{ route('bookings.confirm', $booking) }}">
                @csrf
                <button type="submit">Continue</button>
            </form>
        </noscript>
    </div>

    {{-- Auto-submits on load; the form is what actually finalizes the
         booking, and only ever via POST with a CSRF token. --}}
    <form id="confirm-return" method="POST" action="{{ route('bookings.confirm', $booking) }}" hidden>
        @csrf
    </form>
    <script>
        document.getElementById('confirm-return').submit();
    </script>
</body>
</html>
