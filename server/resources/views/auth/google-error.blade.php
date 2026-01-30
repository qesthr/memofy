<!DOCTYPE html>
<html>
<head>
    <title>Google Login Error</title>
</head>
<body>
    <h1>Google Login Failed</h1>
    <p>Error: {{ $error }}</p>
    <script>
        // Send error message to the opener (main window)
        window.opener.postMessage({
            type: 'GOOGLE_LOGIN_FAILURE',
            error: "{{ $error }}"
        }, '*');

        // Close this popup after 3 seconds
        setTimeout(() => {
            window.close();
        }, 3000);
    </script>
</body>
</html>
