@extends('layouts.crm', ['title' => 'Respuestas automáticas · Conversa OS'])
@section('content')
<main class="content settings-page automatic-responses-page"><div class="welcome"><div><h1>Respuestas automáticas ✦</h1><p>Configura mensajes para eventos de paquetes y cotizaciones.</p></div></div>@include('components.system-preferences')@livewire('automatic-responses')</main>
@endsection
