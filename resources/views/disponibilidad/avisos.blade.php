@if (session('success'))
    <x-aviso tipo="exito">{{ session('success') }}</x-aviso>
@endif
@if ($errors->any())
    <x-aviso tipo="error" titulo="Revisa la información">
        <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </x-aviso>
@endif
