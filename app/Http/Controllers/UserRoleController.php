<?php
namespace App\Http\Controllers;
use App\Models\Module; use App\Models\Role; use App\Models\User; use Illuminate\Http\Request; use Illuminate\Support\Str;
class UserRoleController extends Controller
{
    public function index() { return redirect()->route('users.index'); }
    public function users() { return view('users', ['users'=>User::with('roles')->orderBy('name')->get(), 'roles'=>Role::orderBy('name')->get()]); }
    public function roles() { return view('roles', ['roles'=>Role::with('permissions.module')->orderBy('name')->get(), 'modules'=>Module::with('permissions')->orderBy('sort_order')->orderBy('name')->get()]); }
    public function storeUser(Request $r) { $d=$r->validate(['name'=>'required|string|max:120','email'=>'required|email|max:255|unique:users,email','password'=>'required|string|min:8','roles'=>'nullable|array','roles.*'=>'exists:roles,id']); $u=User::create($d); $u->roles()->sync($d['roles']??[]); return back()->with('success','Usuario creado correctamente.'); }
    public function updateUser(Request $r, User $user) { $d=$r->validate(['name'=>'required|string|max:120','email'=>'required|email|max:255|unique:users,email,'.$user->id,'password'=>'nullable|string|min:8','roles'=>'nullable|array','roles.*'=>'exists:roles,id']); if(blank($d['password']??null)) unset($d['password']); $user->update($d); $user->roles()->sync($d['roles']??[]); return back()->with('success','Usuario actualizado correctamente.'); }
    public function storeRole(Request $r) { $d=$r->validate(['name'=>'required|string|max:100','description'=>'nullable|string|max:500','permissions'=>'nullable|array','permissions.*'=>'exists:permissions,id']); $role=Role::create(['name'=>$d['name'],'slug'=>Str::slug($d['name']),'description'=>$d['description']??null]); $role->permissions()->sync($d['permissions']??[]); return back()->with('success','Rol creado correctamente.'); }
    public function updateRole(Request $r, Role $role) { $d=$r->validate(['name'=>'required|string|max:100','description'=>'nullable|string|max:500','permissions'=>'nullable|array','permissions.*'=>'exists:permissions,id']); $role->update(['name'=>$d['name'],'description'=>$d['description']??null]); $role->permissions()->sync($d['permissions']??[]); return back()->with('success','Permisos del rol actualizados.'); }
    public function destroyUser(User $user) { if ($user->id === auth()->id()) return back()->withErrors(['user'=>'No puedes eliminar tu propio usuario.']); $user->delete(); return back()->with('success','Usuario eliminado correctamente.'); }
    public function destroyRole(Role $role) { if ($role->users()->exists()) return back()->withErrors(['role'=>'No puedes eliminar un rol que todavía tiene usuarios asignados.']); $role->delete(); return back()->with('success','Rol eliminado correctamente.'); }
}
