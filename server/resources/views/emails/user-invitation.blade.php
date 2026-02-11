<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation to Join BukSU Memofy</title>
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
        .logos {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 30px;
            margin-bottom: 20px;
        }
        .logo-circle {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
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
            padding: 8px 20px;
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            margin: 15px 0;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        }
        .button {
            display: inline-block;
            padding: 14px 35px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white !important;
            text-decoration: none;
            border-radius: 8px;
            margin: 25px 0;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.4);
            transition: transform 0.2s;
        }
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(59, 130, 246, 0.5);
        }
        .password-box {
            background: #f0f9ff;
            border: 2px dashed #3b82f6;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .password-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .password-value {
            font-family: 'Courier New', monospace;
            font-size: 20px;
            font-weight: bold;
            color: #1e3a8a;
            letter-spacing: 2px;
        }
        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            margin: 25px 0;
            border-radius: 6px;
        }
        .info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .info-box li {
            margin: 8px 0;
            color: #1e40af;
        }
        .footer {
            background: #f8fafc;
            text-align: center;
            color: #64748b;
            font-size: 12px;
            padding: 25px 20px;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            margin: 5px 0;
        }
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #cbd5e1, transparent);
            margin: 20px 0;
        }
        .steps {
            background: #fafafa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .step {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin: 15px 0;
        }
        .step-number {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            flex-shrink: 0;
        }
        .step-text {
            color: #475569;
            font-size: 14px;
            padding-top: 3px;
        }
        .step-text strong {
            color: #1e3a8a;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logos">
                <div class="logo-circle">
                    <img src="https://i.imgur.com/your-buksu-logo.png" alt="BukSU Logo" onerror="this.style.display='none'">
                </div>
                <div class="logo-circle">
                    <img src="https://i.imgur.com/your-memofy-logo.png" alt="Memofy Logo" onerror="this.style.display='none'">
                </div>
            </div>
            <h1>Welcome to BukSU Memofy!</h1>
            <p>EDUCATE. INNOVATE. LEAD.</p>
        </div>
        
        <div class="content">
            <div class="greeting">Hello, {{ $invitation->user->first_name }}! 👋</div>
            
            <p>You have been invited by <strong>{{ $invitation->inviter->first_name }} {{ $invitation->inviter->last_name }}</strong> to join the <strong>BukSU Memofy Portal</strong>.</p>
            
            <p>Your assigned role:</p>
            <div class="role-badge">{{ strtoupper($invitation->role) }}</div>
            
            <div class="divider"></div>
            
            <p style="font-size: 16px; color: #1e40af; font-weight: 600; margin-top: 20px;">
                🔐 Your Login Credentials
            </p>
            
            <div class="password-box">
                <div class="password-label">Your Password</div>
                <div class="password-value">{{ $password }}</div>
            </div>
            
            <div class="divider"></div>
            
            <p style="font-size: 16px; color: #1e40af; font-weight: 600; margin-top: 20px;">
                📝 Next Steps
            </p>
            
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-text"><strong>Click the button below</strong> to set up your account and create a new password if you wish to change it.</div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-text"><strong>Log in</strong> using your email and the password provided above.</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-text"><strong>Complete your profile</strong> and start using Memofy.</div>
                </div>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ url('/setup-password?token=' . $invitation->token) }}" class="button">Set Up My Account →</a>
            </div>
            
            <div class="info-box">
                <p style="margin: 0 0 10px 0; font-weight: 600; color: #1e40af;">📋 Important Information:</p>
                <ul>
                    <li>This invitation link expires in <strong>48 hours</strong></li>
                    <li>Your email (<strong>{{ $invitation->email }}</strong>) will be your username</li>
                    <li>After setting your password, you'll be redirected to the 
                        @if($invitation->role === 'admin')
                            <strong>Admin Dashboard</strong>
                        @elseif($invitation->role === 'secretary')
                            <strong>Secretary Dashboard</strong>
                        @else
                            <strong>Faculty Dashboard</strong>
                        @endif
                    </li>
                    <li>Please save your password in a secure location</li>
                    <li>Contact your administrator if you need assistance</li>
                </ul>
            </div>
            
            <p style="color: #64748b; font-size: 14px; margin-top: 25px;">If you did not expect this invitation, please ignore this email or contact your system administrator.</p>
        </div>
        
        <div class="footer">
            <p><strong>BukSU Memofy Team</strong></p>
            <p>Bukidnon State University</p>
            <p style="margin-top: 10px;">This is an automated email, please do not reply.</p>
            <p>&copy; {{ date('Y') }} Bukidnon State University. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
