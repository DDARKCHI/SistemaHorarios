<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignacionLaboratorio extends Model
{
    protected $table = 'asignaciones_laboratorios';

    protected $fillable = [
        'horario_id',
        'laboratorio_id',
        'capacidad_asignada',
        'observaciones',
        'es_principal',
        'estado',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
    ];

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function laboratorio()
    {
        return $this->belongsTo(Laboratorio::class);
    }
}