<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignacionLaboratorioMovil extends Model
{
    protected $table = 'asignaciones_laboratorios_moviles';

    protected $fillable = [
        'horario_id',
        'laboratorio_movil_id',
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

    public function laboratorioMovil()
    {
        return $this->belongsTo(LaboratorioMovil::class);
    }
}