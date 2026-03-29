<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Account Credentials</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="max-width:680px;margin:0 auto;padding:32px 16px;">
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#0f172a,#1e293b);padding:24px 28px;">
                <h1 style="margin:0;font-size:24px;line-height:1.3;color:#ffffff;">
                    Welcome to {{ $appName }}
                </h1>
                <p style="margin:8px 0 0 0;font-size:14px;line-height:1.6;color:#cbd5e1;">
                    Your customer account has been created successfully.
                </p>
            </div>

            <div style="padding:28px;">
                <p style="margin:0 0 16px 0;font-size:15px;line-height:1.8;">
                    Hello <strong>{{ $user->name }}</strong>,
                </p>

                <p style="margin:0 0 20px 0;font-size:15px;line-height:1.8;color:#334155;">
                    Your customer profile is now ready. Please use the credentials below to sign in.
                </p>

                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;margin-bottom:22px;">
                    <div style="margin-bottom:10px;font-size:13px;color:#64748b;text-transform:uppercase;letter-spacing:0.08em;">
                        Account Details
                    </div>

                    <p style="margin:8px 0;font-size:15px;line-height:1.7;">
                        <strong>Name:</strong> {{ $user->name }}
                    </p>

                    <p style="margin:8px 0;font-size:15px;line-height:1.7;">
                        <strong>Email:</strong> {{ $user->email }}
                    </p>

                    <p style="margin:8px 0;font-size:15px;line-height:1.7;">
                        <strong>Password:</strong> {{ $plainPassword }}
                    </p>

                    <p style="margin:8px 0;font-size:15px;line-height:1.7;">
                        <strong>Customer Code:</strong> {{ $customer->code }}
                    </p>

                    @if(!empty($customer->document_no))
                        <p style="margin:8px 0;font-size:15px;line-height:1.7;">
                            <strong>Document No:</strong> {{ $customer->document_no }}
                        </p>
                    @endif
                </div>

                @if($isTemporary)
                    <div style="background:#fff7ed;border:1px solid #fdba74;border-radius:12px;padding:14px 16px;margin-bottom:22px;">
                        <p style="margin:0;font-size:14px;line-height:1.7;color:#9a3412;">
                            This password was generated automatically. Please change it after your first login.
                        </p>
                    </div>
                @endif

                <div style="margin:24px 0;">
                    <a href="{{ $loginUrl }}"
                       style="display:inline-block;background:#0f172a;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:12px;font-size:14px;font-weight:700;">
                        Login to Your Account
                    </a>
                </div>

                <p style="margin:0 0 14px 0;font-size:14px;line-height:1.8;color:#475569;">
                    If the button does not work, copy and open this link:
                </p>

                <p style="margin:0 0 20px 0;font-size:14px;line-height:1.8;word-break:break-all;color:#2563eb;">
                    {{ $loginUrl }}
                </p>

                <p style="margin:0;font-size:14px;line-height:1.8;color:#475569;">
                    Thank you,<br>
                    <strong>{{ $appName }}</strong>
                </p>
            </div>
        </div>
    </div>
</body>
</html>