<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignacionSala extends Model
{
    protected $table = 'asignaciones_salas';

    protected $fillable = [
        'horario_id',
        'sala_id',
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

    public function sala()
    {
        return $this->belongsTo(Sala::class);
    }
}