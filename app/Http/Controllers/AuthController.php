<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin() { return view('auth.login'); }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ], ['email.required'=>'Ingresa tu correo electrónico.', 'email.email'=>'Ingresa un correo electrónico válido.', 'password.required'=>'Ingresa tu contraseña.']);
        $credentials = ['email'=>strtolower(trim($data['email'])), 'password'=>$data['password']];
        if (!Auth::attempt($credentials, false)) return back()->withErrors(['email'=>'El correo o la contraseña no son válidos.'])->withInput(['email'=>$credentials['email']]);
        $request->session()->regenerate();
        return redirect()->intended(route('chat'));
    }

    public function logout(Request $request)
    {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
