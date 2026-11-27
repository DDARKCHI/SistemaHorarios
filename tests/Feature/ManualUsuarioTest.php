<?php
namespace Tests\Feature;
use App\Models\User;
use Tests\IsolatedDatabaseTestCase;
use App\Support\PermisosSistema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ManualUsuarioTest extends IsolatedDatabaseTestCase
{
    public function test_manual_y_pdf_requieren_sesion(): void
    {
        $this->get(route('ayuda.manual'))->assertRedirect(route('login'));
        $this->get(route('ayuda.pdf'))->assertRedirect(route('login'));
        $this->get(route('ayuda.pdf.ver'))->assertRedirect(route('login'));
    }
    public function test_manual_describe_las_funciones_y_permite_descargar_pdf(): void
    {
        $u=User::create(['name'=>'Ejemplo','rut'=>'12345678','email'=>'ejemplo@example.test','password'=>'ClaveFicticia123']);
        $u->assignRole(Role::findOrCreate('Administrador', 'web'));
        foreach (PermisosSistema::NOMBRES as $permiso) $u->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        $this->actingAs($u);
        $this->get(route('ayuda.manual'))->assertOk()->assertSee('Descargar PDF completo')->assertSee('Intercambio y carga docente')->assertSee('Equipamiento y distribución')->assertSee('Perfiles y permisos')
            ->assertSee('Mover una clase')
            ->assertViewHas('manual', fn ($m) => count($m['capitulos']) === 24);
        $this->get(route('ayuda.pdf'))->assertOk()->assertDownload('Manual_de_usuario_PROSEC.pdf')->assertHeader('Content-Type','application/pdf');
        $this->assertDatabaseCount('historial_acciones',0);
    }

    public function test_docente_recibe_guia_personal_sin_instrucciones_administrativas(): void
    {
        $u = $this->cuenta('Docente', ['enviar propuesta horario', 'ver programacion semestral']);
        $this->actingAs($u)->get(route('ayuda.manual'))->assertOk()
            ->assertSee('Registrar mi disponibilidad')->assertSee('Responder una solicitud de cambio')
            ->assertSee('Consultar publicaciones y descargar PDF')->assertSee('Consultar programación semestral')
            ->assertDontSee('Coordinar y revisar')->assertDontSee('Enviar accesos')
            ->assertViewHas('manual', fn ($m) => array_column($m['capitulos'], 'id') === [
                'cuenta', 'navegacion', 'disponibilidad', 'consulta', 'publicacion', 'cambios', 'dudas', 'glosario',
            ])->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_asistente_con_consulta_no_recibe_instrucciones_para_modificar(): void
    {
        $u = $this->cuenta('Asistente académico', ['ver programacion semestral']);
        $this->actingAs($u)->get(route('ayuda.manual'))->assertOk()
            ->assertSee('Asistente académico')->assertSee('Consultar publicaciones y descargar PDF')
            ->assertDontSee('Registrar mi disponibilidad')->assertDontSee('Avanzar de etapa')
            ->assertViewHas('manual', fn ($m) => array_column($m['capitulos'], 'id') === [
                'cuenta', 'navegacion', 'consulta', 'publicacion', 'dudas', 'glosario',
            ]);
    }

    public function test_jefatura_recibe_la_guia_del_panel_y_sus_acciones(): void
    {
        $u = $this->cuenta('Jefe de carrera', [
            'ver programacion semestral', 'asignar profesores', 'asignar horarios', 'generar programacion semestral',
        ]);
        $this->actingAs($u)->get(route('ayuda.manual'))->assertOk()
            ->assertSee('Mover una clase')->assertSee('Asignar y confirmar el docente')
            ->assertSee('Preparar otra versión')->assertDontSee('Perfiles y permisos')
            ->assertViewHas('manual', function (array $manual) {
                $panel = collect($manual['capitulos'])->firstWhere('id', 'panel-carrera');

                return $panel['enlace'] === route('programacion.panel-carrera')
                    && array_column($panel['bloques'], 'id') === [
                        'consultar-panel', 'responsable-grupo', 'modulos-espacios', 'mover-modulo', 'crear-version',
                    ];
            });
    }

    public function test_jefatura_de_consulta_recibe_solo_el_uso_de_lectura_del_panel(): void
    {
        $u = $this->cuenta('Jefe de carrera', ['ver programacion semestral']);
        $this->actingAs($u)->get(route('ayuda.manual'))->assertOk()
            ->assertSee('Consultar una carrera')->assertDontSee('Mover una clase')
            ->assertDontSee('Asignar y confirmar el docente')->assertDontSee('Preparar otra versión')
            ->assertViewHas('manual', function (array $manual) {
                $panel = collect($manual['capitulos'])->firstWhere('id', 'panel-carrera');

                return array_column($panel['bloques'], 'id') === ['consultar-panel'];
            });
    }

    public function test_los_permisos_personalizados_prevalecen_sobre_el_nombre_del_perfil(): void
    {
        $u = $this->cuenta('Docente', ['gestionar salas y recursos']);
        $this->actingAs($u)->get(route('ayuda.manual'))->assertOk()->assertSee('Equipamiento y distribución')
            ->assertSee('Consultar horario de espacios')->assertDontSee('Registrar mi disponibilidad')
            ->assertViewHas('manual', fn ($m) => ! in_array('publicacion', array_column($m['capitulos'], 'id'), true));
    }

    public function test_el_acceso_personal_funciona_con_ficha_docente_activa_sin_permiso_administrativo(): void
    {
        $u = $this->cuenta('Consulta', []);
        $p = \App\Models\Profesor::create(['universidad_id' => \Illuminate\Support\Facades\DB::table('universidades')->value('id'), 'nombre' => 'Docente', 'apellido' => 'Prueba', 'estado' => true]);
        $u->profesor_id = $p->id;
        $u->save();
        $this->actingAs($u)->get(route('ayuda.manual'))->assertOk()->assertSee('Registrar mi disponibilidad')
            ->assertDontSee('Coordinar y revisar')->assertDontSee('Crear la solicitud');
    }

    public function test_todos_los_perfiles_pueden_leer_el_pdf_completo_dentro_del_manual(): void
    {
        $u = $this->cuenta('Docente', ['ver programacion semestral']);
        $this->actingAs($u)->get(route('ayuda.manual', ['vista' => 'pdf']))->assertOk()
            ->assertSee('Lector del manual completo')->assertSee('Abrir en otra pestaña')->assertSee('<iframe', false)
            ->assertSee('data-panel-manual="guia"  hidden', false);
        $r = $this->get(route('ayuda.pdf.ver'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', $r->headers->get('Content-Disposition'));
        $this->get(route('ayuda.pdf'))->assertOk()->assertDownload('Manual_de_usuario_PROSEC.pdf');
    }

    public function test_cuenta_sin_permisos_solo_recibe_ayuda_general(): void
    {
        $u = $this->cuenta('Sin permisos', []);
        $this->actingAs($u)->get(route('ayuda.manual'))->assertOk()
            ->assertViewHas('manual', fn ($m) => array_column($m['capitulos'], 'id') === ['cuenta', 'navegacion', 'dudas', 'glosario']);
    }

    public function test_consulta_de_configuracion_no_muestra_edicion_sin_administracion(): void
    {
        $u = $this->cuenta('Consulta', ['ver configuracion']);
        $this->actingAs($u)->get(route('ayuda.manual'))->assertOk()->assertSee('Consultar la configuración')
            ->assertDontSee('Carga logos y favicon')->assertDontSee('Guardar y aplicar identidad');
    }

    public function test_requerimientos_solo_muestra_edicion_cuando_tiene_ambos_permisos(): void
    {
        $u = $this->cuenta('Consulta', ['gestionar conflictos']);
        $this->actingAs($u)->get(route('ayuda.manual'))->assertOk()->assertDontSee('Agrega o edita el requerimiento')
            ->assertViewHas('manual', fn ($m) => in_array('prioridades', array_column($m['capitulos'], 'id'), true));
        $u->givePermissionTo(Permission::findOrCreate('asignar horarios', 'web'));
        $this->get(route('ayuda.manual'))->assertOk()->assertSee('Agrega o edita el requerimiento');
    }

    public function test_lectura_anterior_no_muestra_pasos_de_importacion(): void
    {
        $u = $this->cuenta('Consulta', ['ver programacion anterior']);
        $this->actingAs($u)->get(route('ayuda.manual'))->assertOk()->assertSee('Consultar programación anterior')
            ->assertDontSee('Importar un archivo')->assertDontSee('El análisis vence a los 30 minutos.');
    }

    private function cuenta(string $perfil, array $permisos): User
    {
        $u = User::create(['name' => 'Cuenta de prueba', 'rut' => '12345678', 'email' => 'manual@example.test', 'password' => 'ClaveFicticia123']);
        $r = Role::findOrCreate($perfil, 'web');
        foreach ($permisos as $permiso) $r->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        $u->assignRole($r);
        return $u;
    }
}
