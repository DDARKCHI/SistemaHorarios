<?php
namespace App\Http\Controllers;

use App\Services\ManualUsuario;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManualUsuarioController extends Controller
{
    public function index(Request $request, ManualUsuario $guia)
    {
        $datos = $request->validate(['vista' => ['nullable', Rule::in(['guia', 'pdf'])]]);
        $vista = $datos['vista'] ?? 'guia';
        $manual = $guia->paraUsuario($request->user());
        $perfiles = $request->user()->getRoleNames()->implode(' · ');
        return response()->view('ayuda.manual', compact('manual', 'vista', 'perfiles'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function verPdf()
    {
        return response()->file(base_path('docs/manual/Manual_de_usuario_PROSEC.pdf'), [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Manual_de_usuario_PROSEC.pdf"',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private',
        ]);
    }

    public function pdf()
    {
        return response()->download(base_path('docs/manual/Manual_de_usuario_PROSEC.pdf'), 'Manual_de_usuario_PROSEC.pdf', ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff']);
    }
}
