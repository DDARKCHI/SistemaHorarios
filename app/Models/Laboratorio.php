<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Laboratorio extends Model
{
    protected $table = 'laboratorios';

    protected $fillable = [
        'sala_id',
        'codigo',
        'nombre',
        'capacidad',
        'descripcion',
        'disponible',
        'estado',
    ];

    protected $casts = [
        'disponible' => 'boolean',
    ];

    public function sala()
    {
        return $this->belongsTo(Sala::class);
    }
}