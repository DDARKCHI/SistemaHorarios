<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    protected $table = 'sedes';

    protected $fillable = [
        'universidad_id',
        'codigo',
        'nombre',
        'direccion',
        'estado',
    ];

    public function universidad()
    {
        return $this->belongsTo(Universidad::class);
    }

    public function carreras()
    {
        return $this->hasMany(Carrera::class);
    }

    public function salas()
    {
        return $this->hasMany(Sala::class);
    }

    public function laboratoriosMoviles()
    {
        return $this->hasMany(LaboratorioMovil::class);
    }
}