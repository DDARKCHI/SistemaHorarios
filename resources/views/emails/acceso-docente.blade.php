<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Acceso a {{ $identidad['nombre'] }}</title></head>
<body style="margin:0;padding:0;background:{{ $identidad['fondo'] }};font-family:Arial,Helvetica,sans-serif;color:{{ $identidad['secundario'] }};">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;background:{{ $identidad['fondo'] }};">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
                <tr><td align="center" style="padding:34px 24px;">
                    <x-logo-correo tipo="principal" />
                </td></tr>
                <tr><td style="height:4px;line-height:4px;background:{{ $identidad['principal'] }};">&nbsp;</td></tr>
                <tr><td style="padding:32px 28px;">
                    <h1 style="margin:0 0 20px;font-size:24px;line-height:1.3;">Tus datos de acceso a {{ $identidad['nombre'] }}</h1>
                    <p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#4b5563;">Hola, {{ $usuario->name }}.</p>
                    <p style="margin:0 0 20px;font-size:15px;line-height:1.7;color:#4b5563;">Ingresa a la plataforma para marcar tu <strong>disponibilidad horaria</strong> del período <strong>{{ $periodo->nombre }}</strong>, antes del <strong>{{ $fechaLimite }}</strong>.</p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px;background:#f5f6f8;border:1px solid #e5e7eb;border-radius:8px;">
                        <tr><td style="padding:16px 20px;font-size:14px;line-height:1.7;">
                            <p style="margin:0 0 8px;color:#6b7280;">RUT de acceso sin puntos ni guion</p>
                            <p style="margin:0;font-size:17px;font-weight:bold;letter-spacing:0.4px;">{{ $rutAcceso }}</p>
                            @if ($claveTemporal !== null)
                                <p style="margin:14px 0 8px;color:#6b7280;">Contraseña temporal</p>
                                <p style="margin:0;font-size:17px;font-weight:bold;letter-spacing:0.4px;">{{ $claveTemporal }}</p>
                            @endif
                        </td></tr>
                    </table>
                    @if ($claveTemporal !== null)
                        <p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#4b5563;">Al iniciar sesión deberás reemplazar la contraseña temporal por una personal. Después selecciona tus bloques disponibles y envía tu respuesta.</p>
                    @else
                        <p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#4b5563;">Utiliza tu contraseña habitual. Si la olvidaste, puedes recuperarla desde el acceso. Después selecciona tus bloques disponibles y envía tu respuesta.</p>
                    @endif
                    <table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:0 auto 24px;">
                        <tr><td align="center" style="border-radius:8px;background:{{ $identidad['principal'] }};">
                            <a href="{{ $url }}" style="display:inline-block;padding:15px 28px;font-size:15px;font-weight:bold;color:{{ $identidad['sobre_principal'] }};text-decoration:none;">Haz clic aquí para ingresar</a>
                        </td></tr>
                    </table>
                    <p style="margin:0;font-size:13px;line-height:1.7;color:#6b7280;">Mantén tus datos de acceso en privado y recuerda enviar tu disponibilidad para que la coordinación pueda preparar los horarios.</p>
                </td></tr>
                <tr><td align="center" style="padding:24px;background:#fafafa;border-top:1px solid #e5e7eb;">
                    <x-logo-correo tipo="institucion" />
                    <p style="margin:0;font-size:12px;line-height:1.5;color:#6b7280;">{{ $identidad['nombre'] }} · {{ $identidad['lema'] }}</p>
                </td></tr>
            </table>
            <p style="margin:18px 0 0;font-size:11px;color:#6b7280;">Este es un mensaje automático.</p>
        </td></tr>
    </table>
</body>
</html>
