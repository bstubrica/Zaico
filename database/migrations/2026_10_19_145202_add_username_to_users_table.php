<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * El acceso al sistema pasa a ser por `username` en lugar del correo. La
     * columna se añade nullable para poder rellenarla con las cuentas ya
     * existentes (derivando el usuario de la parte local del correo) y una vez
     * sin huecos se vuelve obligatoria.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 60)->nullable()->unique()->after('name');
        });

        $this->rellenarUsuarios();

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 60)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }

    /**
     * Genera un usuario único por cuenta a partir del correo, normalizado en
     * minúsculas y con el mismo conjunto de caracteres que acepta la validación
     * de Administración → Usuarios.
     */
    private function rellenarUsuarios(): void
    {
        $ocupados = [];

        DB::table('users')->orderBy('id')->get(['id', 'email'])->each(function (object $usuario) use (&$ocupados): void {
            $base = Str::of((string) $usuario->email)
                ->before('@')
                ->lower()
                ->replaceMatches('/[^a-z0-9._-]+/', '')
                ->substr(0, 60)
                ->value();

            if ($base === '') {
                $base = 'usuario';
            }

            $candidato = $base;
            $sufijo = 1;

            while (in_array($candidato, $ocupados, true)) {
                $sufijo++;
                $candidato = Str::substr($base, 0, 60 - strlen((string) $sufijo)).$sufijo;
            }

            $ocupados[] = $candidato;

            DB::table('users')->where('id', $usuario->id)->update(['username' => $candidato]);
        });
    }
};
