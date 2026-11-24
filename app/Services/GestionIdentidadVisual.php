<?php

namespace App\Services;

use App\Models\{ConfiguracionInstitucion, Universidad};
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\{DB, Storage, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GestionIdentidadVisual
{
    public const IMAGENES = ['logo', 'logo_compacto', 'logo_institucion', 'favicon'];

    public function firma(): string
    {
        return hash('sha256', json_encode([
            DB::table('configuracion_institucion')->orderBy('id')->get()->all(),
            DB::table('universidades')->orderBy('id')->get(['id', 'nombre', 'sigla', 'estado'])->all(),
        ], JSON_THROW_ON_ERROR));
    }

    public function guardar(Request $request, Universidad $universidad): void
    {
        $entrada = $request->all();
        foreach (['nombre_sistema', 'lema_sistema'] as $campo) if (is_string($entrada[$campo] ?? null)) $entrada[$campo] = Str::squish($entrada[$campo]);
        $reglas = [
            'nombre_sistema' => ['required', 'string', 'max:80', 'not_regex:/[<>\x00-\x1f]/u'],
            'lema_sistema' => ['nullable', 'string', 'max:150', 'not_regex:/[<>\x00-\x1f]/u'],
            'firma' => ['required', 'string', 'size:64'],
            'confirmar' => ['accepted'],
        ];
        foreach (['color_principal', 'color_secundario', 'color_fondo'] as $campo) $reglas[$campo] = ['required', 'string', 'regex:/\A#[0-9a-fA-F]{6}\z/'];
        foreach (self::IMAGENES as $campo) {
            $reglas[$campo] = ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp', 'max:2048',
                'dimensions:min_width=32,min_height=32,max_width='.($campo === 'favicon' ? '1024,max_height=1024,ratio=1' : '3000,max_height=3000')];
            $reglas['quitar_'.$campo] = ['sometimes', 'boolean'];
        }
        $mensajes = [
            'nombre_sistema.required' => 'Indica el nombre de la plataforma.', 'nombre_sistema.max' => 'El nombre admite hasta 80 caracteres.',
            'lema_sistema.max' => 'El subtítulo admite hasta 150 caracteres.', '*.not_regex' => 'Usa texto sin etiquetas ni caracteres de control.',
            '*.regex' => 'Indica el color en formato hexadecimal, por ejemplo #D12421.',
            'firma.required' => 'Recarga la configuración antes de aplicar cambios.', 'firma.size' => 'La revisión no es válida. Recarga la configuración.',
            'confirmar.accepted' => 'Confirma que deseas aplicar esta identidad a toda la instalación.',
            '*.image' => 'Selecciona una imagen PNG, JPG o WebP válida.', '*.mimes' => 'Los formatos admitidos son PNG, JPG y WebP.',
            '*.mimetypes' => 'El contenido del archivo debe ser una imagen PNG, JPG o WebP.', '*.max' => 'Cada imagen puede pesar hasta 2 MB.',
            '*.dimensions' => 'Los logos deben medir entre 32 y 3000 píxeles por lado. El favicon debe ser cuadrado, de 32 a 1024 píxeles.',
        ];
        $datos = Validator::make($entrada, $reglas, $mensajes)->validate();
        if (IdentidadVisual::luminancia($datos['color_fondo']) < 0.75) $this->rechazar('color_fondo', 'Elige un fondo claro para mantener legibles formularios, tablas y avisos.');
        if (min(IdentidadVisual::contraste($datos['color_secundario'], '#FFFFFF'), IdentidadVisual::contraste($datos['color_secundario'], $datos['color_fondo'])) < 4.5) {
            $this->rechazar('color_secundario', 'El color de texto necesita mayor contraste. Elige un tono más oscuro.');
        }
        foreach (self::IMAGENES as $campo) if ($request->hasFile($campo) && $request->boolean('quitar_'.$campo)) $this->rechazar($campo, 'Selecciona una imagen nueva o retira la actual; elige una de las dos acciones.');
        $creados = [];
        try {
            DB::transaction(function () use ($request, $universidad, $datos, &$creados) {
                Universidad::orderBy('id')->lockForUpdate()->get();
                ConfiguracionInstitucion::orderBy('id')->lockForUpdate()->get();
                $universidad = Universidad::where('estado', true)->findOrFail($universidad->id);
                $this->comprobarFirma($datos['firma']);
                $config = ConfiguracionInstitucion::where('universidad_id', $universidad->id)->orderBy('id')->first()
                    ?? new ConfiguracionInstitucion(['universidad_id' => $universidad->id]);
                $antes = $config->exists ? $config->getAttributes() : null;
                $activas = ConfiguracionInstitucion::where('identidad_activa', true)->pluck('id')->all();
                $config->fill(collect($datos)->only(['nombre_sistema', 'lema_sistema', 'color_principal', 'color_secundario', 'color_fondo'])->all());
                foreach (['color_principal', 'color_secundario', 'color_fondo'] as $campo) $config->$campo = strtoupper($config->$campo);
                $config->fill(['estado' => true, 'identidad_activa' => true, 'nombre_institucion' => $universidad->nombre, 'sigla' => $universidad->sigla]);
                foreach (self::IMAGENES as $campo) {
                    if ($request->hasFile($campo)) $config->$campo = $this->guardarImagen($request->file($campo), $campo === 'favicon', $creados);
                    elseif ($request->boolean('quitar_'.$campo)) $config->$campo = null;
                }
                $cambio = ! $config->exists || $config->isDirty() || count($activas) > 1;
                if ($cambio) {
                    ConfiguracionInstitucion::where('identidad_activa', true)->when($config->exists, fn ($q) => $q->whereKeyNot($config->id))->update(['identidad_activa' => false]);
                    $config->save();
                    $this->auditar($request, 'Aplicar identidad visual', $config->id, ['antes' => $antes, 'despues' => $config->getAttributes(), 'identidades_activas_antes' => $activas]);
                }
            });
        } catch (\Throwable $e) {
            foreach ($creados as $archivo) Storage::disk('local')->delete($archivo);
            throw $e;
        } finally {
            app(IdentidadVisual::class)->olvidar();
        }
    }

    private function guardarImagen(UploadedFile $archivo, bool $icono, array &$creados): string
    {
        $imagen = @imagecreatefromstring(file_get_contents($archivo->getRealPath()));
        if (! $imagen) $this->rechazar('imagenes', 'No se pudo leer una de las imágenes. Selecciona otro archivo.');
        $factor = min(1, ($icono ? 180 : 1600) / max(imagesx($imagen), imagesy($imagen)));
        $ancho = $icono ? 180 : max(1, (int) round(imagesx($imagen) * $factor));
        $alto = $icono ? 180 : max(1, (int) round(imagesy($imagen) * $factor));
        $salida = imagecreatetruecolor($ancho, $alto);
        imagealphablending($salida, false); imagesavealpha($salida, true);
        imagefill($salida, 0, 0, imagecolorallocatealpha($salida, 255, 255, 255, 127));
        imagecopyresampled($salida, $imagen, 0, 0, 0, 0, $ancho, $alto, imagesx($imagen), imagesy($imagen));
        ob_start(); imagepng($salida); $png = ob_get_clean();
        imagedestroy($imagen); imagedestroy($salida);
        $ruta = 'identidad/'.str_replace('-', '', (string) Str::uuid()).'.png';
        if (! Storage::disk('local')->put($ruta, $png)) throw new \RuntimeException('No se pudo guardar la imagen institucional.');
        $creados[] = $ruta;
        return $ruta;
    }

    public function restaurar(Request $request): void
    {
        $datos = $request->validate(['firma' => ['required', 'string', 'size:64'], 'confirmar_restauracion' => ['accepted']], ['confirmar_restauracion.accepted' => 'Confirma la restauración de la identidad inicial de PROSEC.']);
        DB::transaction(function () use ($request, $datos) {
            Universidad::orderBy('id')->lockForUpdate()->get();
            ConfiguracionInstitucion::orderBy('id')->lockForUpdate()->get();
            $this->comprobarFirma($datos['firma']);
            $activas = ConfiguracionInstitucion::where('identidad_activa', true)->pluck('id')->all();
            if (! $activas) return;
            ConfiguracionInstitucion::where('identidad_activa', true)->update(['identidad_activa' => false]);
            $this->auditar($request, 'Restaurar identidad PROSEC', $activas[0], ['identidades_activas_antes' => $activas]);
        });
        app(IdentidadVisual::class)->olvidar();
    }

    private function comprobarFirma(string $firma): void
    {
        if (! hash_equals($this->firma(), $firma)) $this->rechazar('firma', 'La configuración cambió desde que abriste esta página. Recarga y revisa los datos antes de aplicar.');
    }

    private function auditar(Request $request, string $accion, int $id, array $datos): void
    {
        DB::table('historial_acciones')->insert([
            'usuario_id' => $request->user()->id, 'accion' => $accion, 'modulo' => 'Configuración',
            'tabla_afectada' => 'configuracion_institucion', 'registro_id' => $id,
            'descripcion' => 'Identidad pública de toda la instalación. Los datos académicos y accesos se conservan.',
            'datos' => json_encode($datos, JSON_THROW_ON_ERROR), 'direccion_ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function rechazar(string $campo, string $mensaje): never { throw ValidationException::withMessages([$campo => $mensaje]); }
}
