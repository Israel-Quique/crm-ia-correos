<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · Conversa OS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="login-shell">
        <section class="login-art">
            <div class="login-orbit login-orbit-one"></div>
            <div class="login-orbit login-orbit-two"></div>
            <div class="brand"><span class="brand-mark">CR</span><span>Conversa OS</span></div>
            <div class="art-content">
                <div class="eyebrow">▥ &nbsp; CRM Comercial</div>
                <h1>Convierte relaciones en <strong>crecimiento.</strong></h1>
                <p>Organiza clientes, oportunidades y equipos en un solo lugar.</p>
                <div class="login-illustration" aria-hidden="true">
                    <div class="dashboard-screen"><span></span><span></span><i></i><i></i><i></i></div>
                    <div class="trend-line">↗</div>
                    <div class="contact-card"><b>●</b><span></span><span></span></div>
                    <div class="chart-card"><i></i><i></i><i></i><i></i></div>
                </div>
            </div>
        </section>
        <section class="login-form">
            <div class="form-box">
                <div class="eyebrow">Bienvenido de nuevo</div>
                <h2>Inicia sesión</h2>
                <p class="form-sub">Tus credenciales demo ya están listas.</p>
                @if ($errors->any())<div class="login-error">{{ $errors->first() }}</div>@endif
                <form method="POST" action="{{ route('login.store') }}">
                    @csrf
                    <label class="field" for="email">✉ &nbsp; Correo electrónico</label>
                    <input class="input" id="email" type="email" name="email" value="{{ old('email', 'sofia@crm.test') }}" autocomplete="email" required autofocus>
                    <label class="field" for="password">▣ &nbsp; Contraseña</label>
                    <div class="password-wrap"><input class="input" id="password" type="password" name="password" value="NexusCRM2026!" autocomplete="current-password" required><button class="password-toggle" type="button" aria-label="Mostrar contraseña" onclick="togglePassword()">◉</button></div>
                    <div class="form-options"><label><input type="checkbox" name="remember" value="1" checked> <span>Recordarme</span></label><a href="#" onclick="return false;">¿Olvidaste tu contraseña?</a></div>
                    <button class="btn" type="submit">↪ &nbsp; Ingresar a mi cuenta →</button>
                </form>
            </div>
        </section>
    </main>
    <script>function togglePassword(){const input=document.getElementById('password');input.type=input.type==='password'?'text':'password';}</script>
</body>
</html>
