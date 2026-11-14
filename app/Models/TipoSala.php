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

    public function esDeImportacion(): bool
    {
        return mb_strtolower(trim($this->getRawOriginal('nombre') ?? $this->attributes['nombre'] ?? '')) === 'recurso importado';
    }

    public function getNombreAttribute(?string $value): ?string
    {
        return $this->esDeImportacion() ? null : $value;
    }

    public function scopeClasificados($query)
    {
        return $query->whereRaw('LOWER(TRIM(nombre)) <> ?', ['recurso importado']);
    }

    public function salas()
    {
        return $this->hasMany(Sala::class);
    }

    public function requerimientosActividad()
    {
        return $this->hasMany(RequerimientoActividad::class);
    }
}
