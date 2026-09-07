<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidatoReemplazo extends Model
{
    protected $table = 'candidatos_reemplazo';

    protected $fillable = [
        'asignatura_id',
        'profesor_id',
        'periodo_academico_id',
        'motivo',
        'nivel_compatibilidad',
        'observaciones',
        'estado',
        'estado_activo',
    ];

    protected $casts = [
        'estado_activo' => 'boolean',
    ];

    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class);
    }

    public function profesor()
    {
        return $this->belongsTo(Profesor::class);
    }

    public function periodoAcademico()
    {
        return $this->belongsTo(PeriodoAcademico::class);
    }
}