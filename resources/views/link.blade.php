```html
<!DOCTYPE html>
<html>
<head>
    <title>Verify Account</title>
</head>
<body style="margin:0; padding:0; background:#0f172a; font-family:Arial, sans-serif;">

    <div style="
        width:100%;
        min-height:100vh;
        display:flex;
        justify-content:center;
        align-items:center;
        padding:40px 0;
    ">

        <div style="
            width:500px;
            background:#111827;
            border-radius:16px;
            padding:50px 40px;
            text-align:center;
            box-shadow:0 0 20px rgba(0,0,0,0.4);
        ">

            <h1 style="
                color:#ffffff;
                margin-bottom:20px;
                font-size:32px;
            ">
                Verify Your Account
            </h1>

            <p style="
                color:#9ca3af;
                font-size:16px;
                line-height:28px;
                margin-bottom:35px;
            ">
                Hello {{ $name }}, <br><br>
                Click the button below to verify your account and continue to your dashboard.
            </p>

            <a href="{{ $verification_link }}"
               style="
                    display:inline-block;
                    background:#3b82f6;
                    color:#ffffff;
                    text-decoration:none;
                    padding:16px 35px;
                    border-radius:10px;
                    font-size:16px;
                    font-weight:bold;
               ">
                Verify Account
            </a>

            <p style="
                color:#6b7280;
                margin-top:40px;
                font-size:13px;
            ">
                If you did not create this account, please ignore this email.
            </p>

        </div>

    </div>

</body>
</html>
```
