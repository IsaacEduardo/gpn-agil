<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A senha do certificado P12 deixou de ser armazenada: passa a ser fornecida pelo
 * utilizador no momento de cada assinatura. Esta migration remove a coluna
 * (e, com ela, quaisquer senhas anteriormente persistidas).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('user_certificates', 'p12_password')) {
            Schema::table('user_certificates', function (Blueprint $table) {
                $table->dropColumn('p12_password');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('user_certificates', 'p12_password')) {
            Schema::table('user_certificates', function (Blueprint $table) {
                $table->text('p12_password')->nullable()->after('encrypted_p12');
            });
        }
    }
};
