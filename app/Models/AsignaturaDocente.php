<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignaturaDocente extends Model
{
    protected $table = 'asignaturas_docentes';

    protected $fillable = [
        'asignatura_id',
        'profesor_id',
        'periodo_academico_id',
        'tipo_participacion',
        'es_principal',
        'estado',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
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