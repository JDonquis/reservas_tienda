<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; background-color:#F1F5F9; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F1F5F9; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; width:100%; background-color:#FFFFFF; border:1px solid #E2E8F0; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 8px; font-size:22px; color:#0F172A;">¡Cita confirmada! 🎉</h1>
                            <p style="margin:0 0 24px; font-size:14px; line-height:1.6; color:#475569;">
                                Hola, {{ $appointment->customer_name }}. Tu cita ha sido confirmada.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px;">
                                <tr>
                                    <td style="padding:16px 20px; font-size:14px; color:#0F172A;">
                                        <strong>Fecha:</strong> {{ \Carbon\Carbon::parse($appointment->start_time)->format('d/m/Y') }}<br>
                                        <strong>Hora:</strong> {{ \Carbon\Carbon::parse($appointment->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($appointment->end_time)->format('H:i') }}<br>
                                        @if ($appointment->service)
                                            <strong>Servicio:</strong> {{ $appointment->service->name }}<br>
                                        @endif
                                        <strong>Tienda:</strong> {{ $appointment->store?->name }}
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0; font-size:12px; color:#94A3B8;">Si necesitas cancelar o reagendar, contacta a la tienda.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
