<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
use App\Models\Module;
use App\Models\Permission;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(['email' => 'sofia@crm.test'], [
            'name' => 'Sofía Castillo',
            'password' => Hash::make('NexusCRM2026!'),
        ]);
        $catalog = [
            'Dashboard' => ['Ver dashboard'], 'Clientes y chat' => ['Ver chat', 'Responder mensajes'],
            'Operaciones postales' => ['Ver rastreo', 'Gestionar campañas', 'Gestionar tarifas'],
            'Automatización' => ['Gestionar respuestas automáticas', 'Gestionar flujos'],
            'Usuarios y roles' => ['Ver usuarios', 'Crear usuarios', 'Editar usuarios', 'Gestionar roles'],
            'Configuración' => ['Gestionar configuración'], 'Auditoría' => ['Ver auditoría'],
        ];
        foreach ($catalog as $moduleName => $permissionNames) {
            $module = Module::updateOrCreate(['slug' => str($moduleName)->slug()], ['name' => $moduleName]);
            foreach ($permissionNames as $permissionName) {
                Permission::updateOrCreate(['module_id' => $module->id, 'slug' => str($permissionName)->slug()], ['name' => $permissionName, 'action' => str($permissionName)->slug()]);
            }
        }
        $admin = Role::updateOrCreate(['slug' => 'administrador'], ['name' => 'Administrador', 'description' => 'Acceso completo al sistema.']);
        $admin->permissions()->sync(Permission::pluck('id'));
        $user->roles()->sync([$admin->id]);
        $israel = User::updateOrCreate(['email' => 'israelquique714@gmail.com'], [
            'name' => 'Israel Quique',
            'password' => Hash::make('CH1MU3LO'),
        ]);
        $israel->roles()->sync([$admin->id]);
    }
}
