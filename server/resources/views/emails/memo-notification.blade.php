<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memo Notification</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #2563eb;
            color: #ffffff;
            padding: 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        .content {
            padding: 24px;
        }
        .memo-info {
            background-color: #f8fafc;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .memo-info p {
            margin: 8px 0;
        }
        .label {
            font-weight: bold;
            color: #64748b;
        }
        .priority {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .priority-urgent { background-color: #fee2e2; color: #dc2626; }
        .priority-high { background-color: #fef3c7; color: #d97706; }
        .priority-normal { background-color: #dbeafe; color: #2563eb; }
        .priority-low { background-color: #dcfce7; color: #16a34a; }
        .message {
            border-left: 4px solid #2563eb;
            padding-left: 16px;
            margin: 20px 0;
            white-space: pre-wrap;
        }
        .button {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: bold;
            margin-top: 20px;
        }
        .footer {
            background-color: #f8fafc;
            padding: 16px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }
        .attachments {
            margin-top: 20px;
            padding: 16px;
            background-color: #f8fafc;
            border-radius: 6px;
        }
        .attachments h4 {
            margin-top: 0;
            color: #64748b;
        }
        .attachment-item {
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .attachment-item:last-child {
            border-bottom: none;
        }
        .attachment-image {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
            margin-top: 8px;
            display: block;
            border: 1px solid #e2e8f0;
        }
        .attachment-name {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                @if($type === 'new_memo')
                    📬 New Memo Received
                @elseif($type === 'acknowledgment_reminder')
                    ⏰ Reminder: Memo Acknowledgment Required
                @elseif($type === 'memo_approved')
                    ✅ Memo Approved
                @else
                    📝 Memo Notification
                @endif
            </h1>
        </div>
        
        <div class="content">
            <p>Dear {{ $recipient->first_name }},</p>
            
            @if($type === 'new_memo')
                <p>You have received a new official memo. Please review and acknowledge at your earliest convenience.</p>
            @elseif($type === 'acknowledgment_reminder')
                <p>This is a friendly reminder that you have not yet acknowledged the memo below. Your acknowledgment is required.</p>
            @elseif($type === 'memo_approved')
                <p>Good news! The memo you submitted has been approved and is now being distributed to recipients.</p>
            @endif
            
            <div class="memo-info">
                <p><span class="label">From:</span> {{ $sender->first_name }} {{ $sender->last_name }}</p>
                <p><span class="label">Subject:</span> {{ $memo->subject }}</p>
                <p><span class="label">Date:</span> {{ $memo->created_at->format('F d, Y \a\t h:i A') }}</p>
                <p>
                    <span class="label">Priority:</span>
                    <span class="priority priority-{{ $memo->priority }}">{{ ucfirst($memo->priority) }}</span>
                </p>
            </div>
            
            @if($memo->message)
            <div class="message">
                {{ $memo->message }}
            </div>
            @endif
            
            @php
                $attachmentsToDisplay = isset($processedAttachments) ? $processedAttachments : [];
                if (empty($attachmentsToDisplay) && isset($memo->attachments) && count($memo->attachments) > 0) {
                    foreach($memo->attachments as $attachment) {
                        $name = 'Attachment';
                        if (is_array($attachment)) {
                            $name = $attachment['file_name'] ?? (isset($attachment['file_path']) ? basename($attachment['file_path']) : 'Attachment');
                        } elseif (is_string($attachment)) {
                            $name = basename($attachment);
                        }
                        
                        $attachmentsToDisplay[] = [
                            'name' => $name,
                            'is_image' => false,
                            'base64' => null
                        ];
                    }
                }
            @endphp

            @if(count($attachmentsToDisplay) > 0)
            <div class="attachments">
                <h4>📎 Attachments ({{ count($attachmentsToDisplay) }})</h4>
                @foreach($attachmentsToDisplay as $attachment)
                <div class="attachment-item">
                    <div class="attachment-name">
                        <span>{{ $attachment['name'] }}</span>
                    </div>
                    @if($attachment['is_image'] && $attachment['base64'])
                        <img src="{{ $attachment['base64'] }}" class="attachment-image" alt="{{ $attachment['name'] }}">
                    @endif
                </div>
                @endforeach
            </div>
            @endif
            
            <p style="margin-top: 24px;">
                To view the full memo and acknowledge receipt, please click the button below:
            </p>
            
            <center>
                <a href="{{ url('/faculty/memos/' . $memo->id) }}" class="button">
                    View Memo
                </a>
            </center>
        </div>
        
        <div class="footer">
            <p>This is an automated message from the BukSU Department Memofy System.</p>
            <p>Please do not reply to this email. For inquiries, please contact your department administrator.</p>
        </div>
    </div>
</body>
</html>
