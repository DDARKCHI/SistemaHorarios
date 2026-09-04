<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoSala extends Model
{
    protected $table = 'tipos_sala';

    protected $fillable = [
        'nombre',
        'descripcion',
        'requiere_equipamiento',
        'estado',
    ];

    protected $casts = [
        'requiere_equipamiento' => 'boolean',
    ];

    public function salas()
    {
        return $this->hasMany(Sala::class);
    }

    public function requerimientosActividad()
    {
        return $this->hasMany(RequerimientoActividad::class);
    }
}