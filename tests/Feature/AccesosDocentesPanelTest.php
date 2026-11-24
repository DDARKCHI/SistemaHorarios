<?php

namespace Tests\Feature;

use App\Models;
use App\Services\CuentasDocentes;
use App\Services\EnviosAccesosDocentes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class AccesosDocentesPanelTest extends IsolatedDatabaseTestCase
{
    private Models\User $administrador;
    private Models\PeriodoAcademico $periodo;
    private Models\Profesor $ana;
    private Models\Profesor $pedro;

    protected function setUp(): void
    {
        parent::setUp();
        $universidad = DB::table('universidades')->value('id');
        $this->administrador = Models\User::create([
            'name' => 'Coordinación', 'rut' => '17777777', 'email' => 'coord-accesos@example.test', 'password' => 'prueba',
        ]);
        $this->administrador->givePermissionTo(Permission::findOrCreate('asignar profesores', 'web'));
        $this->actingAs($this->administrador);
        $this->periodo = Models\PeriodoAcademico::create([
            'universidad_id' => $universidad, 'nombre' => '2026-2', 'anio' => 2026, 'numero_semestre' => 2, 'estado' => true,
        ]);
        $this->ana = $this->profesor('12345678', 'Ana', 'ana-accesos@example.test');
        $this->pedro = $this->profesor('87654321', 'Pedro', 'pedro-accesos@example.test');
    }

    private function profesor(string $rut, string $nombre, ?string $correo): Models\Profesor
    {
        return Models\Profesor::create([
            'universidad_id' => $this->periodo->universidad_id, 'rut' => $rut,
            'nombre' => $nombre, 'apellido' => 'Prueba', 'correo_institucional' => $correo, 'estado' => true,
        ]);
    }

    private function datos(array $extra = []): array
    {
        return $extra + [
            'periodo_id' => $this->periodo->id,
            'lote' => (string) Str::uuid(),
            'confirmar_envio' => 1,
            'cantidad_confirmada' => count($extra['profesores'] ?? [1]),
            'fecha_limite' => today()->addDays(7)->toDateString(),
        ];
    }

    public function test_lista_filtra_y_advierte_el_correo_sin_configuracion_sin_enviar(): void
    {
        $this->mock(EnviosAccesosDocentes::class, function (MockInterface $mock) {
            $mock->shouldReceive('correoDisponible')->once()->andReturn(false);
            $mock->shouldNotReceive('encolar');
        });
        $respuesta = $this->get(route('disponibilidad.index', ['q' => 'ANA']));
        $respuesta->assertOk()->assertSee('Ana')->assertDontSee('pedro-accesos@example.test')
            ->assertSee('Seleccionar todos los resultados')->assertSee('Correo saliente pendiente de configuración');
        $this->assertSame([$this->ana->id], $respuesta->viewData('seleccionables'));
        $this->assertDatabaseCount('envios_accesos_docentes', 0);
    }

    public function test_no_prepara_correos_sin_confirmacion_expresa(): void
    {
        $this->mock(EnviosAccesosDocentes::class, fn (MockInterface $m) => $m->shouldNotReceive('encolar'));
        $datos = $this->datos(['profesores' => [$this->ana->id]]);
        unset($datos['confirmar_envio']);
        $this->post(route('disponibilidad.accesos.enviar'), $datos)->assertSessionHasErrors('confirmar_envio');
        $this->assertDatabaseCount('envios_accesos_docentes', 0);
        $this->assertDatabaseCount('solicitudes_disponibilidad', 0);
    }

    public function test_una_cantidad_distinta_a_la_confirmada_no_prepara_correos(): void
    {
        $this->mock(EnviosAccesosDocentes::class, fn (MockInterface $m) => $m->shouldNotReceive('encolar'));
        $this->post(route('disponibilidad.accesos.enviar'), $this->datos([
            'profesores' => [$this->ana->id, $this->pedro->id], 'cantidad_confirmada' => 1,
        ]))->assertSessionHasErrors('cantidad_confirmada');
        $this->assertDatabaseCount('envios_accesos_docentes', 0);
    }

    public function test_seleccionar_todos_tambien_comprueba_la_cantidad_confirmada(): void
    {
        $this->mock(EnviosAccesosDocentes::class, function (MockInterface $m) {
            $m->shouldReceive('correoDisponible')->once()->andReturn(true);
            $m->shouldNotReceive('encolar');
        });
        $lista = $this->get(route('disponibilidad.index'))->assertOk();
        $this->post(route('disponibilidad.accesos.enviar'), $this->datos([
            'todos' => 1, 'seleccion_token' => $lista->viewData('seleccionToken'), 'cantidad_confirmada' => 1,
        ]))->assertSessionHasErrors('cantidad_confirmada');
        $this->assertDatabaseCount('envios_accesos_docentes', 0);
    }

    public function test_envia_solo_los_docentes_elegidos(): void
    {
        $datos = $this->datos(['profesores' => [$this->pedro->id]]);
        $this->mock(EnviosAccesosDocentes::class, function (MockInterface $mock) use ($datos) {
            $mock->shouldReceive('correoDisponible')->once()->andReturn(true);
            $mock->shouldReceive('encolar')->once()->withArgs(function ($periodo, $ids, $actor, $ip, $lote, $fecha) use ($datos) {
                return $periodo->id === $this->periodo->id && $ids === [$this->pedro->id]
                    && $actor === $this->administrador->id && $lote === $datos['lote'] && $fecha === $datos['fecha_limite'];
            })->andReturn(['enviados' => 1, 'fallidos' => 0, 'pendientes' => 0, 'resultados' => []]);
        });
        $this->post(route('disponibilidad.accesos.enviar'), $datos)
            ->assertRedirect(route('disponibilidad.index', ['periodo_id' => $this->periodo->id]))
            ->assertSessionHas('success', '1 correo enviado.');
    }

    public function test_seleccionar_todos_conserva_el_criterio_y_no_incluye_altas_posteriores(): void
    {
        $otraAna = $this->profesor('22222222', 'Ana María', 'ana2-accesos@example.test');
        $this->mock(EnviosAccesosDocentes::class, function (MockInterface $mock) {
            $mock->shouldReceive('correoDisponible')->twice()->andReturn(true);
            $mock->shouldReceive('encolar')->once()->withArgs(fn ($periodo, $ids) =>
                $periodo->id === $this->periodo->id && $ids === [$this->ana->id])
                ->andReturn(['enviados' => 1, 'fallidos' => 0, 'pendientes' => 0, 'resultados' => []]);
        });
        $respuesta = $this->get(route('disponibilidad.index', ['q' => 'Ana']))->assertOk();
        $this->profesor('33333333', 'Ana nueva', 'ana3-accesos@example.test');
        $this->post(route('disponibilidad.accesos.enviar'), $this->datos([
            'todos' => 1, 'seleccion_token' => $respuesta->viewData('seleccionToken'), 'excluir' => [$otraAna->id],
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    public function test_una_seleccion_vencida_o_vacia_no_prepara_envios(): void
    {
        $this->mock(EnviosAccesosDocentes::class, function (MockInterface $mock) {
            $mock->shouldReceive('correoDisponible')->once()->andReturn(true);
            $mock->shouldNotReceive('encolar');
        });
        $respuesta = $this->get(route('disponibilidad.index'))->assertOk();
        $this->travel(21)->minutes();
        $this->post(route('disponibilidad.accesos.enviar'), $this->datos([
            'todos' => 1, 'seleccion_token' => $respuesta->viewData('seleccionToken'),
        ]))->assertSessionHasErrors('profesores');
        $this->post(route('disponibilidad.accesos.enviar'), $this->datos())->assertSessionHasErrors('profesores');
    }

    public function test_el_listado_informa_ingreso_y_respuesta_sin_exponer_la_clave_temporal(): void
    {
        $usuario = Models\User::create(['name' => 'Ana Prueba', 'rut' => $this->ana->rut, 'email' => $this->ana->correo_institucional, 'password' => 'prueba']);
        $usuario->forceFill([
            'profesor_id' => $this->ana->id, 'debe_cambiar_password' => true,
            'primer_ingreso_en' => now(), 'ultimo_ingreso_en' => now(), 'clave_temporal' => 'TemporalPrivada987',
        ])->save();
        $solicitud = Models\SolicitudDisponibilidad::create([
            'profesor_id' => $this->ana->id, 'periodo_academico_id' => $this->periodo->id,
            'fecha_solicitud' => today(), 'fecha_limite' => today()->addDays(7), 'estado' => 'En revisión',
        ]);
        $solicitud->respuestas()->create([
            'profesor_id' => $this->ana->id, 'respuesta' => 'Pendiente de revisión', 'fecha_respuesta' => now(), 'bloques' => [],
        ]);
        $this->mock(EnviosAccesosDocentes::class, fn (MockInterface $mock) => $mock->shouldReceive('correoDisponible')->once()->andReturn(true));
        $this->get(route('disponibilidad.index', ['q' => 'Ana']))->assertOk()
            ->assertSee('Ingresó')->assertSee('Cambio de contraseña pendiente')->assertSee('En revisión')
            ->assertDontSee('TemporalPrivada987')->assertViewHas('resumen', fn ($resumen) =>
                $resumen['ingresaron'] === 1 && $resumen['respondieron'] === 1);
    }

    public function test_no_envia_a_docentes_de_otra_institucion_o_inactivos(): void
    {
        $this->pedro->update(['estado' => false]);
        $this->mock(EnviosAccesosDocentes::class, fn (MockInterface $mock) => $mock->shouldNotReceive('encolar'));
        $this->post(route('disponibilidad.accesos.enviar'), $this->datos(['profesores' => [$this->pedro->id]]))
            ->assertSessionHasErrors('profesores');
        $otra = Models\Universidad::create(['nombre' => 'Otra institución', 'sigla' => 'OTRA', 'estado' => true]);
        $this->pedro->update(['estado' => true, 'universidad_id' => $otra->id]);
        $this->post(route('disponibilidad.accesos.enviar'), $this->datos(['profesores' => [$this->pedro->id]]))
            ->assertSessionHasErrors('profesores');
    }

    public function test_un_transporte_sin_entrega_real_se_muestra_como_pendiente(): void
    {
        $this->mock(EnviosAccesosDocentes::class, function (MockInterface $mock) {
            $mock->shouldReceive('correoDisponible')->once()->andReturn(false);
            $mock->shouldReceive('encolar')->once()
                ->andReturn(['enviados' => 0, 'fallidos' => 0, 'pendientes' => 1, 'resultados' => []]);
        });
        $this->post(route('disponibilidad.accesos.enviar'), $this->datos(['profesores' => [$this->ana->id]]))
            ->assertSessionHas('aviso_accesos', '1 envío pendiente de configuración de correo.')
            ->assertSessionMissing('success');
    }

    public function test_los_envios_encolados_se_informan_como_programados(): void
    {
        $this->mock(EnviosAccesosDocentes::class, function (MockInterface $mock) {
            $mock->shouldReceive('correoDisponible')->once()->andReturn(true);
            $mock->shouldReceive('encolar')->once()
                ->andReturn(['enviados' => 0, 'fallidos' => 0, 'pendientes' => 2, 'resultados' => []]);
        });
        $this->post(route('disponibilidad.accesos.enviar'), $this->datos(['profesores' => [$this->ana->id, $this->pedro->id]]))
            ->assertSessionHas('success', '2 envíos programados. Actualiza el seguimiento para consultar su entrega.')
            ->assertSessionMissing('aviso_accesos');
    }

    public function test_solo_los_perfiles_de_coordinacion_pueden_entrar(): void
    {
        auth()->logout();
        $this->get(route('disponibilidad.accesos.index'))->assertRedirect(route('login'));
        $sinPermiso = Models\User::create(['name' => 'Consulta', 'rut' => '44444444', 'email' => 'consulta-accesos@example.test', 'password' => 'prueba']);
        $this->actingAs($sinPermiso)->get(route('disponibilidad.accesos.index'))->assertRedirect();
    }

    public function test_acceso_anterior_redirige_al_panel_unico_manteniendo_filtros(): void
    {
        $filtros = ['periodo_id' => $this->periodo->id, 'q' => 'Ana', 'estado' => 'Aprobada', 'fecha_limite' => today()->toDateString()];

        $this->get(route('disponibilidad.accesos.index', $filtros))->assertRedirect(route('disponibilidad.index', $filtros));
        $this->get(route('disponibilidad.index', $filtros))->assertOk()
            ->assertSee('Seguimiento por docente')
            ->assertDontSee('aria-label="Accesos y seguimiento"', false);
    }

    public function test_el_listado_muestra_el_recordatorio_enviado_y_el_siguiente_programado(): void
    {
        $cuenta = app(CuentasDocentes::class)->asegurarCuenta($this->ana);
        $solicitud = Models\SolicitudDisponibilidad::create([
            'profesor_id' => $this->ana->id, 'periodo_academico_id' => $this->periodo->id,
            'fecha_solicitud' => today()->subDays(5), 'fecha_limite' => today()->addDays(2), 'estado' => 'Pendiente',
        ]);
        $envio = $solicitud->enviosAcceso()->create([
            'profesor_id' => $this->ana->id, 'periodo_academico_id' => $this->periodo->id, 'user_id' => $cuenta->id,
            'enviado_por' => $this->administrador->id, 'lote' => (string) Str::uuid(),
            'estado' => 'enviado', 'enviado_en' => now()->subDays(5), 'correo' => $this->ana->correo_institucional,
        ]);
        $solicitud->recordatorios()->create([
            'envio_acceso_docente_id' => $envio->id, 'dias_antes' => 3, 'estado' => 'enviado',
            'enviar_el' => today()->subDay()->toDateString(), 'enviado_en' => now()->subDay(),
        ]);

        $ruta = route('disponibilidad.index', ['q' => 'Ana', 'estado' => 'Pendiente', 'fecha_limite' => today()->addDays(2)->toDateString()]);
        $this->get($ruta)
            ->assertOk()->assertSee('Invitación enviada')
            ->assertSeeInOrder(['Recordatorios', '3 días antes', 'Enviado', '1 día antes', 'Previsto']);
        $this->ana->update(['rut' => 'PRUEBA-ANA']);
        $this->get($ruta)->assertOk()->assertSee('3 días antes')->assertDontSee('Previsto');
    }
}
