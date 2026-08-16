<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a nodev</title>
</head>
<body style="margin:0; padding:0; background-color:#F1F5F9; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F1F5F9; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; width:100%; background-color:#FFFFFF; border:1px solid #E2E8F0; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 8px; font-size:24px; color:#0F172A;">Hola, {{ $name }} 👋</h1>
                            <p style="margin:0 0 24px; font-size:14px; line-height:1.6; color:#475569;">
                                Te hemos creado una cuenta en el panel de administración de <strong>nodev</strong>.
                                A continuación tienes tus credenciales de acceso:
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px;">
                                <tr>
                                    <td style="padding:16px 20px; font-size:14px; color:#0F172A;">
                                        <strong>Correo:</strong> {{ $email }}<br>
                                        <strong>Contraseña:</strong> <span style="font-family:monospace;">{{ $password }}</span>
                                    </td>
                                </tr>
                            </table>

                            @if ($storeName)
                                <p style="margin:20px 0 0; font-size:14px; color:#475569;">
                                    Tu cuenta está asociada a la tienda: <strong style="color:#0F172A;">{{ $storeName }}</strong>
                                </p>
                            @endif

                            <p style="margin:24px 0 0; font-size:14px; color:#475569;">
                                Para acceder, haz clic en el siguiente enlace:
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;">
                                <tr>
                                    <td align="center" style="padding:14px 24px; background-color:#4F46E5; border-radius:8px;">
                                        <a href="{{ $loginUrl }}" style="display:inline-block; color:#FFFFFF; font-size:14px; font-weight:600; text-decoration:none;">Iniciar sesión</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0; font-size:12px; color:#94A3B8;">
                                Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                                <a href="{{ $loginUrl }}" style="color:#4F46E5; word-break:break-all;">{{ $loginUrl }}</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
