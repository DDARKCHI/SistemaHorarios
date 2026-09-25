<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrupoSeccion extends Model
{
    protected $table = 'grupos_seccion';

    protected $fillable = [
        'actividad_seccion_id',
        'codigo',
        'nombre',
        'numero',
        'cupos',
        'inscritos',
        'estado',
        'estado_activo',
    ];

    protected $casts = [
        'numero' => 'integer',
        'cupos' => 'integer',
        'inscritos' => 'integer',
        'estado_activo' => 'boolean',
    ];

    public function actividadSeccion()
    {
        return $this->belongsTo(ActividadSeccion::class);
    }

    public function horarios()
    {
        return $this->hasMany(Horario::class);
    }

    public function profesores()
    {
        return $this->hasMany(GrupoProfesor::class);
    }
}