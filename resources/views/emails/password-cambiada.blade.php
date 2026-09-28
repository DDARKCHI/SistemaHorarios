<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Contraseña actualizada | PROSEC</title>
</head>

<body
    style="
        margin: 0;
        padding: 0;
        background-color: #f3f4f6;
        font-family: Arial, Helvetica, sans-serif;
        color: #1f2937;
    "
>

    <table
        role="presentation"
        width="100%"
        cellspacing="0"
        cellpadding="0"
        border="0"
        style="
            width: 100%;
            background-color: #f3f4f6;
            padding: 36px 16px;
        "
    >
        <tr>
            <td align="center">

                <table
                    role="presentation"
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    style="
                        width: 100%;
                        max-width: 620px;
                        background-color: #ffffff;
                        border: 1px solid #e5e7eb;
                        border-radius: 14px;
                        overflow: hidden;
                    "
                >

                    <tr>
                        <td
                            align="center"
                            style="
                                padding: 38px 40px 30px 40px;
                                background-color: #ffffff;
                            "
                        >

                            @if (file_exists(public_path('images/prosec-logo-email.png')))
                                <img
                                    src="{{ $message->embed(public_path('images/prosec-logo-email.png')) }}"
                                    alt="PROSEC"
                                    style="
                                        display: block;
                                        width: 250px;
                                        max-width: 100%;
                                        height: auto;
                                        margin: 0 auto;
                                    "
                                >
                            @else
                                <strong
                                    style="
                                        font-size: 28px;
                                        color: #d92323;
                                    "
                                >
                                    PROSEC
                                </strong>
                            @endif

                        </td>
                    </tr>

                    <tr>
                        <td
                            style="
                                height: 4px;
                                background-color: #d92323;
                                font-size: 0;
                                line-height: 0;
                            "
                        >
                            &nbsp;
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="
                                padding: 38px 44px 34px 44px;
                            "
                        >

                            <h1
                                style="
                                    margin: 0 0 22px 0;
                                    color: #1f2937;
                                    font-size: 24px;
                                    line-height: 1.3;
                                    font-weight: 700;
                                "
                            >
                                Contraseña actualizada
                            </h1>

                            <p
                                style="
                                    margin: 0 0 16px 0;
                                    color: #4b5563;
                                    font-size: 15px;
                                    line-height: 1.7;
                                "
                            >
                                Hola{{ !empty($usuario->name) ? ', ' . $usuario->name : '' }}:
                            </p>

                            <p
                                style="
                                    margin: 0 0 18px 0;
                                    color: #4b5563;
                                    font-size: 15px;
                                    line-height: 1.7;
                                "
                            >
                                Te informamos que la contraseña de tu cuenta en
                                <strong style="color: #1f2937;">PROSEC</strong>
                                fue actualizada correctamente.
                            </p>

                            <table
                                role="presentation"
                                width="100%"
                                cellspacing="0"
                                cellpadding="0"
                                border="0"
                                style="
                                    width: 100%;
                                    margin: 26px 0;
                                    background-color: #f9fafb;
                                    border: 1px solid #e5e7eb;
                                    border-radius: 8px;
                                "
                            >
                                <tr>
                                    <td
                                        style="
                                            padding: 17px 19px;
                                        "
                                    >

                                        <p
                                            style="
                                                margin: 0 0 8px 0;
                                                color: #374151;
                                                font-size: 13px;
                                                line-height: 1.6;
                                            "
                                        >
                                            <strong>¿Fuiste tú?</strong>
                                        </p>

                                        <p
                                            style="
                                                margin: 0;
                                                color: #6b7280;
                                                font-size: 13px;
                                                line-height: 1.6;
                                            "
                                        >
                                            Si realizaste este cambio, no necesitas hacer nada más.
                                            Si no reconoces esta modificación, comunícate con el administrador del sistema.
                                        </p>

                                    </td>
                                </tr>
                            </table>

                            <p
                                style="
                                    margin: 0;
                                    color: #4b5563;
                                    font-size: 14px;
                                    line-height: 1.7;
                                "
                            >
                                Saludos,<br>
                                <strong style="color: #1f2937;">
                                    Equipo PROSEC
                                </strong>
                            </p>

                        </td>
                    </tr>

                    <tr>
                        <td
                            align="center"
                            style="
                                padding: 26px 40px 22px 40px;
                                border-top: 1px solid #e5e7eb;
                                background-color: #fafafa;
                            "
                        >

                            @if (file_exists(public_path('images/ucsc-logo-email.png')))
                                <img
                                    src="{{ $message->embed(public_path('images/ucsc-logo-email.png')) }}"
                                    alt="Universidad Católica de la Santísima Concepción"
                                    style="
                                        display: block;
                                        height: 48px;
                                        width: auto;
                                        max-width: 220px;
                                        margin: 0 auto 14px auto;
                                    "
                                >
                            @endif

                            <p
                                style="
                                    margin: 0;
                                    color: #6b7280;
                                    font-size: 12px;
                                    line-height: 1.5;
                                "
                            >
                                PROSEC · Programación Semestral de Cursos
                            </p>

                            <p
                                style="
                                    margin: 4px 0 0 0;
                                    color: #9ca3af;
                                    font-size: 11px;
                                    line-height: 1.5;
                                "
                            >
                                Universidad Católica de la Santísima Concepción
                            </p>

                        </td>
                    </tr>

                </table>

                <p
                    style="
                        margin: 18px 0 0 0;
                        color: #9ca3af;
                        font-size: 11px;
                        line-height: 1.5;
                        text-align: center;
                    "
                >
                    Este es un mensaje automático. Por favor, no respondas a este correo.
                </p>

            </td>
        </tr>
    </table>

</body>

</html>