<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipamientoEspacio extends Model
{
    protected $table = 'equipamientos_espacios';
    protected $fillable = ['equipamiento_id', 'sala_id', 'laboratorio_movil_id', 'cantidad'];
    protected $casts = ['cantidad' => 'integer'];

    public function equipamiento() { return $this->belongsTo(Equipamiento::class); }
    public function sala() { return $this->belongsTo(Sala::class); }
    public function laboratorioMovil() { return $this->belongsTo(LaboratorioMovil::class); }
}
