<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dia extends Model
{
    protected $table = 'dias';

    protected $fillable = [
        'nombre',
        'abreviatura',
        'numero',
        'estado',
    ];

    public function disponibilidadesProfesores()
    {
        return $this->hasMany(DisponibilidadProfesor::class);
    }

    public function horarios()
    {
        return $this->hasMany(Horario::class);
    }

    public function opcionesCambioHorario()
    {
        return $this->hasMany(OpcionCambioHorario::class);
    }
}