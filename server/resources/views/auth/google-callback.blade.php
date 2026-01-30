<!DOCTYPE html>
<html>
<head>
    <title>Google Login Success</title>
</head>
<body>
    <script>
        const payload = {
            token: "{{ $token }}",
            user: @json($user),
            role: "{{ $role }}"
        };
        
        // Send message to the opener (main window)
        window.opener.postMessage({
            type: 'GOOGLE_LOGIN_SUCCESS',
            payload: payload
        }, '*'); // In production, replace '*' with specific client origin

        // Close this popup
        window.close();
    </script>
</body>
</html>
