<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation to Join BukSU Memofy</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #4285F4 0%, #34A853 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border: 1px solid #ddd;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: #4285F4;
            color: white !important;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 20px;
            padding: 20px;
            border-top: 1px solid #ddd;
        }
        .role-badge {
            display: inline-block;
            padding: 5px 15px;
            background: #34A853;
            color: white;
            border-radius: 20px;
            font-size: 14px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Welcome to BukSU Memofy!</h1>
    </div>
    
    <div class="content">
        <p>Hello <strong>{{ $name }}</strong>,</p>
        
        <p>You have been invited by <strong>{{ $invitedBy }}</strong> to join the BukSU Memofy Portal as a:</p>
        
        <div class="role-badge">{{ ucfirst($role) }}</div>
        
        <p>To complete your account setup and create your password, please click the button below:</p>
        
        <div style="text-align: center;">
            <a href="{{ $setupUrl }}" class="button">Set Up My Account</a>
        </div>
        
        <p><strong>Important Information:</strong></p>
        <ul>
            <li>This invitation link will expire in <strong>48 hours</strong></li>
            <li>Your email address will be your username for login</li>
            <li>After setting your password, you can access the 
                @if($role === 'admin')
                    Admin Dashboard
                @elseif($role === 'secretary')
                    Secretary Dashboard
               @else
                    Faculty Dashboard
                @endif
            </li>
        </ul>
        
        <p>If you did not expect this invitation, please ignore this email or contact your administrator.</p>
        
        <p>Best regards,<br>
        <strong>BukSU Memofy Team</strong></p>
    </div>
    
    <div class="footer">
        <p>This is an automated email, please do not reply.</p>
        <p>&copy; {{ date('Y') }} Bukidnon State University. All rights reserved.</p>
    </div>
</body>
</html>
