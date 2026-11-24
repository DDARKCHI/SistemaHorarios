<?php

namespace App\Http\Controllers;

use App\Services\GestionPerfiles;
use App\Support\PermisosSistema;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class PerfilController extends Controller
{
    public function index()
    {
        $perfiles = Role::where('guard_name', 'web')->with('permissions')->withCount('users')->orderBy('name')->get();
        return view('administracion.perfiles.index', compact('perfiles'));
    }

    public function create()
    {
        return $this->formulario();
    }

    public function edit(Role $perfil)
    {
        abort_unless($perfil->guard_name === 'web', 404);
        return $this->formulario($perfil);
    }

    private function formulario(?Role $perfil = null)
    {
        $gestion = app(GestionPerfiles::class);
        return view('administracion.perfiles.formulario', [
            'perfil' => $perfil, 'grupos' => PermisosSistema::grupos(),
            'firma' => $perfil ? $gestion->firma($perfil) : null,
            'protegido' => $perfil && $gestion->protegido($perfil),
            'cuentas' => $perfil?->users()->orderBy('name')->get(['users.id', 'users.name']) ?? collect(),
        ]);
    }

    public function store(Request $request)
    {
        app(GestionPerfiles::class)->guardar($request->all(), $request->user());
        return to_route('administracion.perfiles.index')->with('success', 'Perfil creado. Ya puedes asignarlo a las cuentas desde Administración.');
    }

    public function update(Request $request, Role $perfil)
    {
        abort_unless($perfil->guard_name === 'web', 404);
        app(GestionPerfiles::class)->guardar($request->all(), $request->user(), $perfil);
        return to_route('administracion.perfiles.index')->with('success', 'Perfil actualizado. Sus permisos se aplican a las cuentas que lo utilizan.');
    }
}
