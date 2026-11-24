<x-enlace-submenu :href="route('administracion.perfiles.index')" :activo="request()->routeIs('administracion.perfiles.*')">Perfiles y permisos</x-enlace-submenu>
<x-enlace-submenu :href="route('administracion.docentes.index')" :activo="request()->routeIs('administracion.docentes.*')">Docentes</x-enlace-submenu>
<x-enlace-submenu :href="route('administracion.secciones.index')" :activo="request()->routeIs('administracion.secciones.*')">Oferta por período</x-enlace-submenu>
@foreach (['sedes' => 'Sedes', 'carreras' => 'Carreras'] as $tipoEstructura => $tituloEstructura)
<x-enlace-submenu :href="route('administracion.estructura.index', $tipoEstructura)" :activo="request()->routeIs('administracion.estructura.*') && request()->route('tipo') === $tipoEstructura">{{ $tituloEstructura }}</x-enlace-submenu>
@endforeach
@foreach (['asignaturas' => 'Asignaturas y ramos', 'periodos' => 'Períodos académicos'] as $tipoOferta => $tituloOferta)
<x-enlace-submenu :href="route('administracion.oferta.index', $tipoOferta)" :activo="request()->routeIs('administracion.oferta.*') && request()->route('tipo') === $tipoOferta">{{ $tituloOferta }}</x-enlace-submenu>
@endforeach
@foreach (['facultades' => 'Facultades e institutos'] as $tipoAcademico => $tituloAcademico)
<x-enlace-submenu :href="route('administracion.academicos.index', $tipoAcademico)" :activo="request()->routeIs('administracion.academicos.*') && request()->route('tipo') === $tipoAcademico">{{ $tituloAcademico }}</x-enlace-submenu>
@endforeach
