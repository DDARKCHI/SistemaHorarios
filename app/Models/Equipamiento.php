<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipamiento extends Model
{
    protected $table = 'equipamientos';

    protected $fillable = [
        'universidad_id',
        'nombre',
        'tipo',
        'descripcion',
        'cantidad',
        'disponible',
        'estado',
    ];

    protected $casts = [
        'disponible' => 'boolean',
    ];

    public function universidad()
    {
        return $this->belongsTo(Universidad::class);
    }
}