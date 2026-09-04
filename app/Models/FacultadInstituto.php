<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacultadInstituto extends Model
{
    protected $table = 'facultades_institutos';

    protected $fillable = [
        'universidad_id',
        'codigo',
        'nombre',
        'tipo',
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
}