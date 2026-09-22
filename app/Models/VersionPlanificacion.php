<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VersionPlanificacion extends Model
{
    protected $table = 'versiones_planificacion';

    protected $fillable = [
        'periodo_academico_id',
        'nombre',
        'version',
        'tipo',
        'descripcion',
        'estado',
        'usuario_id',
        'fecha_publicacion',
        'es_actual',
    ];

    protected $casts = [
        'version' => 'decimal:1',
        'fecha_publicacion' => 'datetime',
        'es_actual' => 'boolean',
    ];

    public function periodoAcademico()
    {
        return $this->belongsTo(PeriodoAcademico::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }

    public function horarios()
    {
        return $this->hasMany(Horario::class);
    }

    public function validaciones()
    {
        return $this->hasMany(ValidacionHorario::class);
    }
}