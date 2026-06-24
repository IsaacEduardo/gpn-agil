<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use Spatie Permission Model to create permission
        // We need to clear cache first
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        if (! \Spatie\Permission\Models\Permission::where('name', 'gabinete.view_all')->where('guard_name', 'web')->exists()) {
            \Spatie\Permission\Models\Permission::create(['name' => 'gabinete.view_all', 'guard_name' => 'web']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = \Spatie\Permission\Models\Permission::where('name', 'gabinete.view_all')->where('guard_name', 'web')->first();
        if ($permission) {
            $permission->delete();
        }
    }
};
