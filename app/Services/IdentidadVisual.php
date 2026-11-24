<?php

namespace App\Services;

use App\Models\{ConfiguracionInstitucion, Universidad};
use Illuminate\Support\Facades\{Schema, Storage};
use Illuminate\Support\Str;

class IdentidadVisual
{
    public const LOGOS_BASE = [
        'principal' => 'prosec-logo.svg',
        'compacto' => 'prosec-mini.svg',
        'institucion' => 'main-logo.svg',
    ];

    public const ANCHOS_LOGOS_CORREO = ['principal' => 500, 'institucion' => 288];

    public const PREDETERMINADA = [
        'nombre' => 'PROSEC', 'lema' => 'Programación Semestral de Cursos',
        'institucion' => 'Universidad Católica de la Santísima Concepción', 'sigla' => 'UCSC',
        'principal' => '#D12421', 'secundario' => '#1F2937', 'fondo' => '#F5F6F8',
    ];

    public function datos(): array
    {
        $request = request();
        if ($request->attributes->has('identidad_visual')) return $request->attributes->get('identidad_visual');
        $config = null;
        $hayTabla = Schema::hasTable('configuracion_institucion');
        if ($hayTabla && Schema::hasColumn('configuracion_institucion', 'identidad_activa')) {
            $config = ConfiguracionInstitucion::with('universidad')->where('identidad_activa', true)->where('estado', true)
                ->whereHas('universidad', fn ($u) => $u->where('estado', true))->orderBy('id')->first();
        }
        $datos = self::PREDETERMINADA + ['personalizada' => false, 'configuracion_id' => null];
        if (! $config && $hayTabla && ($base = Universidad::where('estado', true)->orderBy('id')->first())) {
            $datos['institucion'] = $base->nombre; $datos['sigla'] = $base->sigla ?: $base->nombre;
        }
        if ($config) {
            $datos = [
                'nombre' => $config->nombre_sistema ?: 'PROSEC', 'lema' => $config->lema_sistema ?? '',
                'institucion' => $config->universidad->nombre, 'sigla' => $config->universidad->sigla ?: $config->universidad->nombre,
                'principal' => $this->color($config->color_principal, '#D12421'),
                'secundario' => $this->color($config->color_secundario, '#1F2937'),
                'fondo' => $this->color($config->color_fondo, '#F5F6F8'),
                'personalizada' => true, 'configuracion_id' => $config->id,
            ];
        }
        $datos['oscuro'] = self::oscurecer($datos['principal'], 0.82);
        $datos['enlace'] = self::legible($datos['principal'], [$datos['fondo'], '#FFFFFF']);
        $datos['muted'] = self::legible('#6B7280', [$datos['fondo'], '#FFFFFF']);
        $datos['sobre_principal'] = self::textoSobre($datos['principal']);
        $datos['sobre_oscuro'] = self::textoSobre($datos['oscuro']);
        $datos['revision'] = substr(hash('sha256', json_encode([$datos, $config?->getAttributes()])), 0, 16);
        $marcaOriginal = Str::upper($datos['nombre']) === self::PREDETERMINADA['nombre']
            && $datos['lema'] === self::PREDETERMINADA['lema'];
        foreach (['principal' => 'logo', 'compacto' => 'logo_compacto', 'institucion' => 'logo_institucion', 'favicon' => 'favicon'] as $tipo => $campo) {
            $archivo = $this->archivoValido($config?->$campo);
            $datos[$tipo.'_archivo'] = $archivo;
            if ($archivo) {
                $url = route('identidad.archivo', basename($archivo));
            } elseif (! $marcaOriginal || ($tipo === 'institucion' && Str::upper($datos['sigla']) !== 'UCSC')) {
                $url = route('identidad.marca', ['tipo' => $tipo, 'v' => $datos['revision']]);
            } elseif ($tipo === 'favicon') {
                $url = asset('favicon.svg');
            } else {
                $logo = self::LOGOS_BASE[$tipo];
                $ruta = public_path('images/'.$logo);
                $url = route('identidad.logo-base', [
                    'archivo' => $logo,
                    'v' => is_file($ruta) ? filemtime($ruta) : null,
                ]);
            }
            $datos[$tipo.'_url'] = $url;
        }
        $datos['favicon_base'] = $marcaOriginal && ! $datos['favicon_archivo'];
        $datos['apple_url'] = $datos['favicon_base']
            ? asset('apple-touch-icon.png').'?v=2'
            : route('identidad.icono', ['v' => $datos['revision']]);
        $request->attributes->set('identidad_visual', $datos);
        return $datos;
    }

    public function olvidar(): void { request()->attributes->remove('identidad_visual'); }

    public function remitenteCorreo(): string
    {
        $identidad = $this->datos();

        return trim($identidad['nombre']).' - '.trim($identidad['sigla']);
    }

    public function logoCorreoUrl(string $tipo): ?string
    {
        $base = rtrim(config('mail.asset_url') ?: (string) config('app.url'), '/');
        if (! filter_var($base, FILTER_VALIDATE_URL)) {
            return null;
        }

        $url = parse_url($base);
        $host = strtolower(rtrim($url['host'] ?? '', '.'));
        if (! in_array($url['scheme'] ?? '', ['http', 'https'], true)
            || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
            return null;
        }
        $ip = trim($host, '[]');
        $publico = filter_var($ip, FILTER_VALIDATE_IP)
            ? filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false
            : str_contains($host, '.') && ! preg_match('/\.(?:localhost|local|test|internal|invalid|example|home\.arpa)\z/', $host);
        if (! $publico) {
            return null;
        }

        return $base.route('identidad.logo-correo', ['tipo' => $tipo, 'v' => $this->logoCorreoVersion($tipo)], false);
    }

    public function logoCorreoVersion(string $tipo): string
    {
        $archivo = public_path('images/'.($tipo === 'institucion' ? 'ucsc-logo-email.png' : 'prosec-logo-email.png'));

        return substr(hash('sha256', $this->datos()['revision'].':'.filemtime($archivo).':'.self::ANCHOS_LOGOS_CORREO[$tipo]), 0, 16);
    }

    public function logoCorreoPng(string $tipo): string
    {
        $original = imagecreatefromstring($this->png($tipo));
        $ancho = self::ANCHOS_LOGOS_CORREO[$tipo];
        $alto = max(1, (int) round(imagesy($original) * $ancho / imagesx($original)));
        $reducida = imagecreatetruecolor($ancho, $alto);
        imagealphablending($reducida, false);
        imagesavealpha($reducida, true);
        imagecopyresampled($reducida, $original, 0, 0, 0, 0, $ancho, $alto, imagesx($original), imagesy($original));

        ob_start();
        imagepng($reducida, null, 9);

        return ob_get_clean();
    }

    public function archivoValido(?string $archivo): ?string
    {
        return $archivo && preg_match('/\Aidentidad\/[a-f0-9]{32}\.png\z/', $archivo) && Storage::disk('local')->exists($archivo) ? $archivo : null;
    }

    private function color(?string $color, string $defecto): string
    {
        return $color && preg_match('/\A#[a-f0-9]{6}\z/i', $color) ? strtoupper($color) : $defecto;
    }

    public static function luminancia(string $hex): float
    {
        $rgb = array_map(function ($v) { $v = hexdec($v) / 255; return $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4; }, str_split(ltrim($hex, '#'), 2));
        return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
    }

    public static function contraste(string $a, string $b): float
    {
        $a = self::luminancia($a); $b = self::luminancia($b);
        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    public static function oscurecer(string $hex, float $factor): string
    {
        return '#'.implode('', array_map(fn ($v) => sprintf('%02X', (int) round(hexdec($v) * $factor)), str_split(substr($hex, 1), 2)));
    }

    public static function legible(string $hex, array $fondos): string
    {
        for ($i = 0; $i < 30 && min(array_map(fn ($f) => self::contraste($hex, $f), $fondos)) < 4.5; $i++) $hex = self::oscurecer($hex, 0.88);
        return $hex;
    }

    public static function textoSobre(string $color): string
    {
        return self::contraste($color, '#FFFFFF') >= 4.5 ? '#FFFFFF' : '#000000';
    }

    public function iniciales(?string $nombre = null): string
    {
        $nombre ??= $this->datos()['nombre'];
        if (Str::upper($nombre) === 'PROSEC') return 'PS';
        $palabras = preg_split('/[^\pL\pN]+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY);
        return Str::upper(count($palabras) > 1 ? mb_substr($palabras[0], 0, 1).mb_substr($palabras[1], 0, 1) : mb_substr($palabras[0] ?? 'SI', 0, 2));
    }

    public function svg(string $tipo): string
    {
        $d = $this->datos(); $compacto = in_array($tipo, ['compacto', 'favicon'], true);
        $e = fn ($s) => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
        if ($compacto) {
            $letras = $this->iniciales();
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128"><rect x="2" y="2" width="124" height="124" rx="26" fill="white"/><text x="64" y="85" text-anchor="middle" font-family="Arial,sans-serif" font-size="64" font-weight="800"><tspan fill="'.$d['secundario'].'">'.$e(mb_substr($letras, 0, 1)).'</tspan><tspan fill="'.$d['principal'].'">'.$e(mb_substr($letras, 1, 1)).'</tspan></text></svg>';
        }
        $nombre = $tipo === 'institucion' ? $d['sigla'] : $d['nombre'];
        $lema = $tipo === 'institucion' ? $d['institucion'] : $d['lema'];
        if ($tipo === 'institucion') {
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 150"><text x="300" y="100" text-anchor="middle" fill="'.$d['secundario'].'" font-family="Arial,sans-serif" font-size="104" font-weight="800" textLength="'.min(570, max(230, mb_strlen($nombre) * 70)).'" lengthAdjust="spacingAndGlyphs">'.$e($nombre).'</text><text x="300" y="140" text-anchor="middle" fill="'.$d['secundario'].'" font-family="Arial,sans-serif" font-size="18" textLength="'.min(570, max(1, mb_strlen($lema) * 9)).'" lengthAdjust="spacingAndGlyphs">'.$e($lema).'</text></svg>';
        }
        $contenido = $e($nombre);
        if ($nombre === 'PROSEC' && $tipo !== 'institucion') $contenido = '<tspan>PRO</tspan><tspan fill="'.$d['principal'].'">SEC</tspan>';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 150"><text x="12" y="86" fill="'.$d['secundario'].'" font-family="Arial,sans-serif" font-size="72" font-weight="800" textLength="'.min(576, max(100, mb_strlen($nombre) * 48)).'" lengthAdjust="spacingAndGlyphs">'.$contenido.'</text><text x="14" y="122" fill="'.$d['secundario'].'" font-family="Arial,sans-serif" font-size="18" textLength="'.min(570, max(1, mb_strlen($lema) * 9)).'" lengthAdjust="spacingAndGlyphs">'.$e($lema).'</text></svg>';
    }

    public function png(string $tipo = 'principal'): string
    {
        $d = $this->datos();
        if ($d[$tipo.'_archivo'] ?? null) {
            $png = Storage::disk('local')->get($d[$tipo.'_archivo']);
            if (! in_array($tipo, ['principal', 'institucion'], true)) return $png;
            // Correo y PDF usan un lienzo horizontal para no deformar logos altos.
            $original = imagecreatefromstring($png); $lienzo = imagecreatetruecolor(900, 220);
            imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
            $factor = min(860 / imagesx($original), 180 / imagesy($original));
            $ancho = max(1, (int) round(imagesx($original) * $factor)); $alto = max(1, (int) round(imagesy($original) * $factor));
            imagecopyresampled($lienzo, $original, (int) ((900 - $ancho) / 2), (int) ((220 - $alto) / 2), 0, 0, $ancho, $alto, imagesx($original), imagesy($original));
            ob_start(); imagepng($lienzo); $resultado = ob_get_clean(); imagedestroy($original); imagedestroy($lienzo);
            return $resultado;
        }
        if (! $d['personalizada'] && ($tipo !== 'institucion' || Str::upper($d['sigla']) === 'UCSC')) return file_get_contents(public_path(match ($tipo) {
            'institucion' => 'images/ucsc-logo-email.png', 'favicon', 'compacto' => 'apple-touch-icon.png', default => 'images/prosec-logo-email.png',
        }));
        $cuadrado = in_array($tipo, ['favicon', 'compacto'], true);
        $imagen = imagecreatetruecolor($cuadrado ? 180 : 900, $cuadrado ? 180 : 220);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 255, 255, 255));
        $rgb = fn ($hex) => array_map('hexdec', str_split(substr($hex, 1), 2));
        $tinta = imagecolorallocate($imagen, ...$rgb($d['secundario']));
        $acento = imagecolorallocate($imagen, ...$rgb($d['principal']));
        $fuente = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        if ($cuadrado) {
            $nombre = $this->iniciales(); $tamano = 65;
            $caja = imagettfbbox($tamano, 0, $fuente, $nombre); $x = (int) ((180 - ($caja[2] - $caja[0])) / 2);
            imagettftext($imagen, $tamano, 0, $x, 119, $tinta, $fuente, mb_substr($nombre, 0, 1));
            $primera = imagettfbbox($tamano, 0, $fuente, mb_substr($nombre, 0, 1));
            imagettftext($imagen, $tamano, 0, $x + $primera[2], 119, $acento, $fuente, mb_substr($nombre, 1, 1));
        } else {
            $nombre = $tipo === 'institucion' ? $d['sigla'] : $d['nombre'];
            $lema = $tipo === 'institucion' ? $d['institucion'] : $d['lema'];
            $tamano = 65;
            while ($tamano > 10 && imagettfbbox($tamano, 0, $fuente, $nombre)[2] > 860) $tamano--;
            $x = (int) ((900 - imagettfbbox($tamano, 0, $fuente, $nombre)[2]) / 2);
            imagettftext($imagen, $tamano, 0, $x, 115, $tinta, $fuente, $nombre);
            if ($nombre === 'PROSEC' && $tipo === 'principal') imagettftext($imagen, $tamano, 0, $x + imagettfbbox($tamano, 0, $fuente, 'PRO')[2], 115, $acento, $fuente, 'SEC');
            if ($lema !== '') {
                $tamano = 19;
                while ($tamano > 7 && imagettfbbox($tamano, 0, $fuente, $lema)[2] > 860) $tamano--;
                $x = (int) ((900 - imagettfbbox($tamano, 0, $fuente, $lema)[2]) / 2);
                imagettftext($imagen, $tamano, 0, $x, 170, $tinta, $fuente, $lema);
            }
        }
        ob_start(); imagepng($imagen); $png = ob_get_clean(); imagedestroy($imagen);
        return $png;
    }
}
