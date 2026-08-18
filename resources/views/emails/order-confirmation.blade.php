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
                            <h1 style="margin:0 0 8px; font-size:22px; color:#0F172A;">¡Pedido confirmado! 🎉</h1>
                            <p style="margin:0 0 24px; font-size:14px; line-height:1.6; color:#475569;">
                                Hola, {{ $order->customer_name }}. Tu pedido <strong>#{{ $order->id }}</strong> en {{ $store->name }} fue confirmado.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                <tr style="background-color:#F8FAFC; text-align:left;">
                                    <th style="padding:10px 12px; font-size:12px; color:#64748B; border-bottom:1px solid #E2E8F0;">Producto</th>
                                    <th style="padding:10px 12px; font-size:12px; color:#64748B; border-bottom:1px solid #E2E8F0; text-align:center;">Cant.</th>
                                    <th style="padding:10px 12px; font-size:12px; color:#64748B; border-bottom:1px solid #E2E8F0; text-align:right;">Precio</th>
                                </tr>
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td style="padding:10px 12px; font-size:14px; color:#0F172A; border-bottom:1px solid #F1F5F9;">{{ $item->name }}</td>
                                        <td style="padding:10px 12px; font-size:14px; color:#475569; border-bottom:1px solid #F1F5F9; text-align:center;">{{ $item->quantity }}</td>
                                        <td style="padding:10px 12px; font-size:14px; color:#475569; border-bottom:1px solid #F1F5F9; text-align:right;">${{ number_format($item->line_total, 2) }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td colspan="2" style="padding:12px; font-size:14px; font-weight:bold; color:#0F172A;">Total</td>
                                    <td style="padding:12px; font-size:16px; font-weight:bold; color:#0F172A; text-align:right;">${{ number_format($order->total, 2) }}</td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0; font-size:14px; line-height:1.6; color:#475569;">
                                @if ($order->shipping_method === 'delivery')
                                    <strong>Tu pedido será enviado a:</strong> {{ $order->shipping_address }}
                                @else
                                    <strong>Método:</strong> Retiro en tienda ({{ $store->name }})
                                @endif
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
