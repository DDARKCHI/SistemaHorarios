<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RequerimientoActividad extends Model
{
    public const TIPOS = ['Sala' => 'Sala', 'Laboratorio' => 'Laboratorio', 'Laboratorio móvil' => 'Laboratorio móvil', 'Equipamiento' => 'Equipamiento'];

    public static function tipoNormalizado(string $tipo): ?string
    {
        return match (Str::lower(Str::ascii(Str::squish($tipo)))) {
            'sala', 'salas' => 'Sala',
            'laboratorio', 'laboratorios', 'laboratorio fijo', 'laboratorios fijos' => 'Laboratorio',
            'laboratorio movil', 'laboratorios moviles' => 'Laboratorio móvil',
            'equipamiento' => 'Equipamiento',
            default => null,
        };
    }

    protected $table = 'requerimientos_actividad';

    protected $fillable = [
        'actividad_seccion_id',
        'equipamiento_id',
        'tipo_sala_id',
        'prioridad_recurso_id',
        'tipo_requerimiento',
        'nombre_recurso',
        'cantidad',
        'capacidad_minima',
        'observaciones',
        'obligatorio',
        'estado',
    ];

    protected $casts = [
        'obligatorio' => 'boolean',
        'estado' => 'boolean', 'cantidad' => 'integer', 'capacidad_minima' => 'integer',
    ];

    public function actividadSeccion()
    {
        return $this->belongsTo(ActividadSeccion::class);
    }

    public function tipoSala()
    {
        return $this->belongsTo(TipoSala::class);
    }

    public function equipamiento() { return $this->belongsTo(Equipamiento::class); }

    public function prioridadRecurso()
    {
        return $this->belongsTo(PrioridadRecurso::class);
    }
}
