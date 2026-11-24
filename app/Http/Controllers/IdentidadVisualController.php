<?php

namespace App\Http\Controllers;

use App\Models\{ConfiguracionInstitucion, Universidad};
use App\Services\{GestionIdentidadVisual, IdentidadVisual};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class IdentidadVisualController extends Controller
{
    public function edit(Request $request)
    {
        $datos = $request->validate(['universidad_id' => ['nullable', 'integer', Rule::exists('universidades', 'id')->where('estado', true)]]);
        $universidades = Universidad::where('estado', true)->orderBy('nombre')->get();
        $actual = app(IdentidadVisual::class)->datos();
        $activa = $actual['configuracion_id'] ? ConfiguracionInstitucion::find($actual['configuracion_id'])?->universidad_id : null;
        $universidad = isset($datos['universidad_id']) ? $universidades->firstWhere('id', $datos['universidad_id']) : ($universidades->firstWhere('id', $activa) ?? $universidades->first());
        $configuracion = $universidad ? ConfiguracionInstitucion::where('universidad_id', $universidad->id)->orderBy('id')->first() : null;
        $configuracion ??= new ConfiguracionInstitucion([
            'nombre_sistema' => 'PROSEC', 'lema_sistema' => IdentidadVisual::PREDETERMINADA['lema'],
            'color_principal' => '#D12421', 'color_secundario' => '#1F2937', 'color_fondo' => '#F5F6F8',
        ]);
        $firma = app(GestionIdentidadVisual::class)->firma();
        return view('configuracion.identidad', compact('universidades', 'universidad', 'configuracion', 'firma', 'actual'));
    }

    public function update(Request $request, Universidad $universidad)
    {
        app(GestionIdentidadVisual::class)->guardar($request, $universidad);
        return to_route('configuracion.identidad', ['universidad_id' => $universidad->id])->with('success', 'Identidad aplicada a toda la plataforma. Los datos académicos y las cuentas se conservan.');
    }

    public function restaurar(Request $request)
    {
        app(GestionIdentidadVisual::class)->restaurar($request);
        return to_route('configuracion.identidad')->with('success', 'Se restauró la identidad inicial de PROSEC. Las imágenes y configuraciones anteriores se conservan.');
    }

    public function archivo(string $archivo)
    {
        abort_unless(preg_match('/\A[a-f0-9]{32}\.png\z/', $archivo), 404);
        $disco = Storage::disk('local');
        abort_unless($disco->exists('identidad/'.$archivo), 404);
        return response()->file($disco->path('identidad/'.$archivo), ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    public function marca(string $tipo)
    {
        return response(app(IdentidadVisual::class)->svg($tipo), 200, ['Content-Type' => 'image/svg+xml', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=0, must-revalidate']);
    }

    public function logoBase(Request $request, string $archivo)
    {
        $ruta = public_path('images/'.$archivo);
        abort_unless(in_array($archivo, IdentidadVisual::LOGOS_BASE, true) && is_file($ruta), 404);
        $versionado = $request->query('v') === (string) filemtime($ruta);

        return response()->file($ruta, [
            'Content-Type' => 'image/svg+xml',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $versionado ? 'public, max-age=31536000, immutable' : 'public, max-age=0, must-revalidate',
        ]);
    }

    public function icono()
    {
        return response(app(IdentidadVisual::class)->png('favicon'), 200, ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=0, must-revalidate']);
    }

    public function logoCorreo(Request $request, IdentidadVisual $identidad, string $tipo)
    {
        $version = $identidad->logoCorreoVersion($tipo);
        $respuesta = response('', 200, [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $request->query('v') === $version
                ? 'public, max-age=31536000, immutable'
                : 'public, max-age=0, must-revalidate',
        ])->setEtag($version);

        return $respuesta->isNotModified($request) ? $respuesta : $respuesta->setContent($identidad->logoCorreoPng($tipo));
    }
}
