<?php

namespace App\Http\Controllers;

use App\Models\Equipamiento;
use App\Models\EquipamientoEspacio;
use App\Models\LaboratorioMovil;
use App\Models\Sala;
use App\Models\Sede;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EquipamientoEspacioController extends Controller
{
    public function index(Request $request, Equipamiento $equipamiento)
    {
        $validador = Validator::make($request->query(), ['ubicacion' => ['nullable', 'integer', 'min:1']]);
        if ($validador->fails()) return redirect()->route('recursos.ubicaciones.index', $equipamiento)->withErrors($validador);
        $ubicaciones = $equipamiento->ubicaciones()->with(['sala.sede', 'laboratorioMovil.sede'])->orderBy('id')->get();
        $seleccionada = filled($request->query('ubicacion')) ? $ubicaciones->firstWhere('id', (int) $request->query('ubicacion')) : null;
        abort_if(filled($request->query('ubicacion')) && ! $seleccionada, 404);
        $ubicadas = (int) $ubicaciones->sum('cantidad');
        $espacios = Sala::where('universidad_id', $equipamiento->universidad_id)->where('estado', true)->where('disponible', true)->with('sede')->orderBy('codigo')->get()
            ->map(fn ($s) => ['id' => 'sala:'.$s->id, 'sede_id' => $s->sede_id, 'nombre' => 'Sala '.$s->codigo.' · '.$s->sede?->nombre])
            ->concat(LaboratorioMovil::where('universidad_id', $equipamiento->universidad_id)->where('estado', true)->where('disponible', true)->with('sede')->orderBy('codigo')->get()
                ->map(fn ($m) => ['id' => 'movil:'.$m->id, 'sede_id' => $m->sede_id, 'nombre' => 'Laboratorio móvil '.$m->codigo.' · '.$m->sede?->nombre]));
        $sedes = Sede::where('universidad_id', $equipamiento->universidad_id)->orderBy('nombre')->get();
        return view('recursos.ubicaciones', compact('equipamiento', 'ubicaciones', 'seleccionada', 'ubicadas', 'espacios', 'sedes'));
    }

    public function store(Request $request, Equipamiento $equipamiento)
    {
        return $this->guardar($request, $equipamiento);
    }

    public function update(Request $request, Equipamiento $equipamiento, EquipamientoEspacio $ubicacion)
    {
        return $this->guardar($request, $equipamiento, $ubicacion);
    }

    private function guardar(Request $request, Equipamiento $equipamiento, ?EquipamientoEspacio $ubicacion = null)
    {
        DB::transaction(function () use ($request, $equipamiento, $ubicacion) {
            $equipo = Equipamiento::lockForUpdate()->findOrFail($equipamiento->id);
            $actual = $ubicacion ? $equipo->ubicaciones()->lockForUpdate()->findOrFail($ubicacion->id) : null;
            $datos = $request->validate(['espacio' => $actual ? ['prohibited'] : ['required', 'string', 'regex:/^(sala|movil):[1-9]\d*$/D'],
                'cantidad' => ['required', 'integer', 'min:1', 'max:65535']], [
                'espacio.*' => 'Selecciona una sala o laboratorio móvil registrado. Para cambiar de espacio, quita la ubicación anterior y agrega la nueva.',
                'cantidad.*' => 'La cantidad debe ser un entero entre 1 y 65.535.',
            ]);
            if ($actual) {
                abort_unless((bool) $actual->sala_id !== (bool) $actual->laboratorio_movil_id, 422);
                $campo = $actual->sala_id ? 'sala_id' : 'laboratorio_movil_id'; $id = $actual->$campo;
            } else {
                [$tipo, $id] = explode(':', $datos['espacio']); $campo = $tipo === 'sala' ? 'sala_id' : 'laboratorio_movil_id';
                if ($equipo->ubicaciones()->where($campo, $id)->exists()) throw ValidationException::withMessages(['espacio' => 'Este espacio ya tiene una ubicación. Usa Modificar para cambiar su cantidad.']);
            }
            $modelo = $campo === 'sala_id' ? Sala::class : LaboratorioMovil::class;
            $espacio = $modelo::lockForUpdate()->find($id);
            $aumenta = ! $actual || (int) $datos['cantidad'] > $actual->cantidad;
            if (! $espacio || ($aumenta && (! $equipo->estado || ! $equipo->disponible || ! $espacio->estado || ! $espacio->disponible || (int) $espacio->universidad_id !== (int) $equipo->universidad_id))) {
                throw ValidationException::withMessages(['espacio' => 'El equipo y el espacio deben estar activos, habilitados y pertenecer a la misma institución para agregar unidades.']);
            }
            $total = (int) $equipo->ubicaciones()->sum('cantidad') - ($actual?->cantidad ?? 0) + (int) $datos['cantidad'];
            if ($total > $equipo->cantidad) throw ValidationException::withMessages(['cantidad' => 'La distribución supera las '.$equipo->cantidad.' unidades del inventario. Revisa las cantidades ubicadas.']);
            $antes = $actual?->only(['equipamiento_id', 'sala_id', 'laboratorio_movil_id', 'cantidad']);
            $registro = $actual ?? new EquipamientoEspacio(['equipamiento_id' => $equipo->id, $campo => $id]);
            $registro->cantidad = (int) $datos['cantidad'];
            if ($actual && ! $registro->isDirty()) return;
            $registro->save();
            $this->historial($request, $registro, $antes, $registro->only(['equipamiento_id', 'sala_id', 'laboratorio_movil_id', 'cantidad']));
        }, 3);
        return redirect()->route('recursos.ubicaciones.index', $equipamiento)->with('success', 'Ubicación del equipamiento guardada correctamente.');
    }

    public function destroy(Request $request, Equipamiento $equipamiento, EquipamientoEspacio $ubicacion)
    {
        DB::transaction(function () use ($request, $equipamiento, $ubicacion) {
            $equipo = Equipamiento::lockForUpdate()->findOrFail($equipamiento->id);
            $actual = $equipo->ubicaciones()->lockForUpdate()->findOrFail($ubicacion->id);
            $antes = $actual->only(['equipamiento_id', 'sala_id', 'laboratorio_movil_id', 'cantidad']);
            $actual->delete(); $this->historial($request, $actual, $antes, null);
        }, 3);
        return redirect()->route('recursos.ubicaciones.index', $equipamiento)->with('success', 'Ubicación retirada. Las unidades permanecen en el inventario.');
    }

    private function historial(Request $request, EquipamientoEspacio $registro, ?array $antes, ?array $despues): void
    {
        DB::table('historial_acciones')->insert(['usuario_id' => $request->user()->id,
            'accion' => $despues === null ? 'Retirar ubicación de equipamiento' : ($antes ? 'Actualizar ubicación de equipamiento' : 'Ubicar equipamiento'),
            'modulo' => 'Equipamiento y recursos', 'tabla_afectada' => 'equipamientos_espacios', 'registro_id' => $registro->id,
            'descripcion' => 'Distribución de unidades del inventario entre espacios.', 'datos' => json_encode(compact('antes', 'despues'), JSON_THROW_ON_ERROR),
            'direccion_ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
