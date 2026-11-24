<?php

namespace App\Http\Controllers;

use App\Models\{CierreProgramacion as Cierre, VersionPlanificacion};
use App\Services\CierreProgramacion;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class CierreProgramacionController extends Controller
{
    public function show(VersionPlanificacion $version, CierreProgramacion $cierre)
    {
        abort_unless($version->tipo === 'semestral', 404);
        return response()->view('programacion.cierre', ['version' => $version] + $cierre->revisar($version))->header('Cache-Control', 'no-store, private');
    }
    public function store(Request $request, VersionPlanificacion $version, CierreProgramacion $cierre)
    {
        $datos = $request->validate(['etapa' => ['required', Rule::in(['aprobar', 'publicar'])], 'firma' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'], 'confirmar' => ['accepted']]);
        $version = $cierre->cambiar($version, $datos['etapa'], $datos['firma'], $request->user()->id, $request->ip());
        return redirect()->route('programacion.version.cierre', $version)->with('success', $version->estado === 'Publicado' ? 'Horario publicado para consulta dentro de PROSEC.' : 'Horario aprobado. Puedes revisar el siguiente paso para publicarlo.');
    }
    public function publicada(Request $request, VersionPlanificacion $version, \App\Services\ConsultaPublicacion $consulta)
    {
        $cierre = $this->publicacion($version);
        $datos = $consulta->preparar($request, $cierre);
        $horarios = new LengthAwarePaginator($datos['filas']->forPage($datos['pagina'], 25)->values(), $datos['filas']->count(), 25, $datos['pagina'],
            ['path' => route('programacion.version.publicada', $version), 'query' => $datos['contexto']]);
        if ($horarios->currentPage() > $horarios->lastPage()) return redirect()->to($horarios->url($horarios->lastPage()));
        return response()->view('programacion.publicada', compact('version', 'cierre', 'horarios') + $datos)->header('Cache-Control', 'no-store, private');
    }

    public function pdf(Request $request, VersionPlanificacion $version, \App\Services\ConsultaPublicacion $consulta, \App\Services\ExportacionHorarioPdf $exportacion)
    {
        $cierre = $this->publicacion($version);
        $datos = $consulta->preparar($request, $cierre);
        $pdf = $exportacion->generar($cierre, $datos['filas'], $datos['formato'], $datos['contexto']);
        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="prosec-publicacion-'.$version->id.'-'.$datos['formato'].'.pdf"',
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function publicacion(VersionPlanificacion $version): Cierre
    {
        abort_unless($version->tipo === 'semestral' && $version->estado === 'Publicado', 404);
        return Cierre::where('version_planificacion_id', $version->id)->where('etapa', 'Publicado')->with('usuario')->firstOrFail();
    }
}
