<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Code - Memofy</title>
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
        .code-container {
            background-color: #f0f9ff;
            border: 2px dashed #3b82f6;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }
        .code-label {
            font-size: 14px;
            color: #1e40af;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .code {
            font-size: 42px;
            font-weight: 800;
            letter-spacing: 8px;
            color: #1e293b;
            margin: 10px 0;
        }
        .info-box {
            background: #fff7ed;
            border-left: 4px solid #f97316;
            padding: 15px;
            margin: 25px 0;
            border-radius: 6px;
            font-size: 14px;
            color: #9a3412;
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
            <h1>Password Reset Code</h1>
            <p>BUKSU MEMOFY PORTAL</p>
        </div>
        
        <div class="content">
            <div class="greeting">Hello, {{ $user->first_name }}! 👋</div>
            
            <p>We received a request to reset the password for your Memofy account. Please use the verification code below to proceed with your password reset:</p>
            
            <div class="code-container">
                <div class="code-label">Your Security Code</div>
                <div class="code">{{ $code }}</div>
            </div>

            <div class="info-box">
                <strong>⚠️ Security Notice:</strong>
                <p style="margin: 5px 0 0 0;">This code is valid for <strong>15 minutes</strong> and can only be used <strong>once</strong>. If you did not request this, please secure your account or contact an administrator.</p>
            </div>
            
            <p style="color: #64748b; font-size: 14px; margin-top: 25px;">This is an automated security notification. For your protection, never share this code with anyone.</p>
        </div>
        
        <div class="footer">
            <p><strong>BukSU Memofy Team</strong></p>
            <p>Bukidnon State University</p>
            <p>&copy; {{ date('Y') }} Bukidnon State University. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
