<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisponibilidadProfesor extends Model
{
    protected $table = 'disponibilidad_profesores';

    protected $fillable = [
        'profesor_id',
        'periodo_academico_id',
        'dia_id',
        'bloque_horario_id',
        'tipo_disponibilidad',
        'observaciones',
        'estado',
    ];

    public function profesor()
    {
        return $this->belongsTo(Profesor::class);
    }

    public function periodoAcademico()
    {
        return $this->belongsTo(PeriodoAcademico::class);
    }

    public function dia()
    {
        return $this->belongsTo(Dia::class);
    }

    public function bloqueHorario()
    {
        return $this->belongsTo(BloqueHorario::class);
    }
}