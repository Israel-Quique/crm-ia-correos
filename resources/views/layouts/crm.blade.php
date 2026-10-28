<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Conversa OS' }}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body>
<div class="app-shell"><aside class="sidebar"><a class="nav-item {{ request()->routeIs('automatic-responses') ? 'active' : '' }}" href="{{ route('automatic-responses') }}"><span>•</span><em>Respuestas automáticas</em></a>
<div class="brand"><span class="brand-mark">CRM</span><span><small style="display:block;font:500 10px 'DM Sans'">CRM COMERCIAL</small>Conversa OS</span></div>
<div class="nav-label">☰ &nbsp; NACIONAL <b>▾</b></div>
<a class="nav-item {{ request()->routeIs('postal-campaigns.national') ? 'active' : '' }}" href="{{ route('postal-campaigns.national') }}"><span>▤</span><em>Campañas Nacional</em></a>
<a class="nav-item {{ request()->routeIs('postal-campaigns.national.registry') ? 'active' : '' }}" href="{{ route('postal-campaigns.national.registry') }}"><span>▤</span><em>Registro de campañas</em></a>
<a class="nav-item" href="#"><span>○</span><em>Pronts Nacional</em></a>
<div class="nav-label">◎ &nbsp; INTERNACIONALES <b>▾</b></div>
<a class="nav-item {{ request()->routeIs('postal-campaigns.international') ? 'active' : '' }}" href="{{ route('postal-campaigns.international') }}"><span>▤</span><em>Campañas Internacional</em></a>
<a class="nav-item {{ request()->routeIs('postal-campaigns.international.registry') ? 'active' : '' }}" href="{{ route('postal-campaigns.international.registry') }}"><span>▤</span><em>Registro de campañas</em></a>
<a class="nav-item" href="#"><span>○</span><em>Pronts Internacional</em></a>
<div class="nav-label">♧ &nbsp; CLIENTES <b>▾</b></div><a class="nav-item {{ request()->routeIs('chat') ? 'active' : '' }}" href="{{ route('chat') }}"><span>▢</span><em>Chat</em></a><a class="nav-item" href="#"><span>▢</span><em>Contactos</em></a><a class="nav-item" href="#"><span>▢</span><em>Agrupaciones</em></a><a class="nav-item" href="#"><span>▢</span><em>Mensajes personalizados</em></a>
<div class="nav-label">♙ &nbsp; USUARIOS <b>▾</b></div><a class="nav-item" href="#"><span>○</span><em>Usuarios</em></a>
<div class="nav-label">▣ &nbsp; SISTEMA <b>▾</b></div><a class="nav-item" href="#"><span>○</span><em>Reporte de logs</em></a><a class="nav-item {{ request()->routeIs('settings') ? 'active' : '' }}" href="{{ route('settings') }}"><span>○</span><em>Configuraciones</em></a><a class="nav-item {{ request()->routeIs('tests') ? 'active' : '' }}" href="{{ route('tests') }}"><span>✓</span><em>Pruebas</em></a><a class="nav-item {{ request()->routeIs('assistant') ? 'active' : '' }}" href="{{ route('assistant') }}"><span>✦</span><em>Asistente IA</em></a>
<div class="sidebar-bottom"><a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span>⌂</span><em>Resumen</em></a><form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-item" style="background:none;border:0;width:100%;text-align:left;cursor:pointer"><span>↪</span><em>Cerrar sesión</em></button></form></div>
</aside><section class="main"><header class="topbar"><div class="search">⌕ &nbsp; Buscar contactos, ventas, paquetes...</div><div class="profile"><div class="avatar purple-bg">SC</div><div><strong>Sofía Castillo</strong><small>Administradora</small></div></div></header>@yield('content')</section></div>
</body><script>document.querySelectorAll('.sidebar .nav-item').forEach(function(item){var text=item.textContent.trim().toLowerCase();if(text.includes('mensajes personalizados')){item.href='{{ route('automatic-responses') }}';item.classList.toggle('active',location.pathname==='/respuestas-automaticas')}if(text.includes('respuestas automáticas')&&item.parentElement.classList.contains('sidebar'))item.remove()});</script></html>
