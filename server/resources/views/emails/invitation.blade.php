<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation to BukSU Memofy</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #60a5fa 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        .logo-circle {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }
        .logo-circle img {
            max-width: 60px;
            max-height: 60px;
        }
        .header h1 {
            margin: 10px 0 5px 0;
            font-size: 28px;
            font-weight: 700;
        }
        .header p {
            margin: 0;
            font-size: 14px;
            color: #FFD700;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1e3a8a;
            margin-bottom: 20px;
        }
        .role-badge {
            display: inline-block;
            padding: 6px 16px;
            background: #e0f2fe;
            color: #0369a1;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin: 10px 0;
        }
        .button-container {
            text-align: center;
            margin: 35px 0;
        }
        .button {
            display: inline-block;
            padding: 14px 35px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.4);
        }
        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            margin: 25px 0;
            border-radius: 6px;
        }
        .info-box ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
        .info-box li {
            margin: 8px 0;
            color: #1e40af;
            font-size: 14px;
        }
        .footer {
            background: #f8fafc;
            text-align: center;
            color: #64748b;
            font-size: 12px;
            padding: 25px 20px;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo-circle">
                <img src="https://i.imgur.com/your-memofy-logo.png" alt="Memofy Logo" onerror="this.src='https://via.placeholder.com/60?text=M'">
            </div>
            <h1>Account Invitation</h1>
            <p>BUKSU MEMOFY PORTAL</p>
        </div>
        
        <div class="content">
            <div class="greeting">Hello! 👋</div>
            
            <p>An account has been created for you on the <strong>BukSU Memofy Portal</strong>. You have been assigned the following role:</p>
            
            <div class="role-badge">{{ strtoupper($invitation->role) }}</div>
            
            <p style="margin-top: 20px;">To activate your account and set up your password, please click the button below:</p>
            
            <div class="button-container">
                <a href="{{ config('app.frontend_url') . '/setup-password?token=' . $invitation->token }}" class="button">Set Up My Account →</a>
            </div>

            <div class="info-box">
                <p style="margin: 0; font-weight: 600; color: #1e3a8a;">📋 Important Details:</p>
                <ul>
                    <li><strong>Department:</strong> {{ $invitation->department }}</li>
                    <li><strong>Link Expiration:</strong> 48 hours</li>
                    <li><strong>Activation:</strong> Single-use link</li>
                </ul>
            </div>
            
            <p style="color: #64748b; font-size: 14px; margin-top: 25px;">If you weren't expecting this invitation, you can safely ignore this email.</p>
        </div>
        
        <div class="footer">
            <p><strong>BukSU Memofy Team</strong></p>
            <p>Bukidnon State University</p>
            <p>&copy; {{ date('Y') }} Bukidnon State University. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
