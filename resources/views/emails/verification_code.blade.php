<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Veda Billing</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f4f4f4;
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 500px;
            width: 100%;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            color: #1e40af;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }
        .header p {
            color: #6b7280;
            font-size: 16px;
            margin: 10px 0;
        }
        .code-box {
            background-color: #f9fafb;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .code-box p {
            font-size: 36px;
            font-weight: 700;
            color: #1e40af;
            letter-spacing: 4px;
            margin: 0;
            font-family: 'Roboto', monospace;
        }
        .info {
            color: #4b5563;
            font-size: 16px;
            margin: 20px 0;
        }
        .instructions p {
            color: #6b7280;
            font-size: 14px;
            margin: 10px 0;
        }
        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
            margin-top: 20px;
            color: #9ca3af;
            font-size: 12px;
        }
        .footer a {
            color: #1e40af;
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header with Branding -->
        <div class="header">
            <h1>Veda Billing</h1>
            <p>Secure Email Verification</p>
        </div>

        <!-- Verification Code Section -->
        <div class="code-box">
            <p>{{ $code }}</p>
        </div>
        <div class="info">
            This code expires in {{ config('security-features.verification_code_expiry') }} minutes.
        </div>

        <!-- Instructions -->
        <div class="instructions">
            <p>Please enter this code in the verification field to confirm your email address.</p>
            <p>If you didn’t request this code, please ignore this email or contact our <a href="mailto:support@vedabilling.com">support team</a>.</p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; 2025 Veda Billing. All rights reserved.</p>
        </div>
    </div>
</body>
</html>