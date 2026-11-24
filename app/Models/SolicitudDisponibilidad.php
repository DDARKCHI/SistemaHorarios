<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudDisponibilidad extends Model
{
    protected $table = 'solicitudes_disponibilidad';

    protected $fillable = [
        'profesor_id',
        'periodo_academico_id',
        'fecha_solicitud',
        'fecha_limite',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'fecha_solicitud' => 'datetime',
        'fecha_limite' => 'datetime',
    ];

    public function profesor()
    {
        return $this->belongsTo(Profesor::class);
    }

    public function periodoAcademico()
    {
        return $this->belongsTo(PeriodoAcademico::class);
    }

    public function respuestas()
    {
        return $this->hasMany(RespuestaDisponibilidad::class);
    }

    public function enviosAcceso()
    {
        return $this->hasMany(EnvioAccesoDocente::class, 'solicitud_disponibilidad_id');
    }

    public function recordatorios()
    {
        return $this->hasMany(RecordatorioDisponibilidad::class, 'solicitud_disponibilidad_id');
    }
}
