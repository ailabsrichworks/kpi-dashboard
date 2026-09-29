<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ports legacy's per-user appearance theme (settings.blade.php /
 * ProfileController::updateTheme(), persisted on the legacy `employees`
 * table) to the Platform's own `users` table — the last gap from the
 * sidebar/nav parity audit. Purely cosmetic, self-service: every column is
 * nullable (no theme set = the Platform's own default look, unchanged), and
 * the existing `users_update_self` RLS policy already lets a caller update
 * their own row — only `prevent_self_role_change()`'s narrow role check
 * blocks anything here, and none of these columns are `role`. No new RLS
 * needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('users', function (Blueprint $table) {
            $table->text('theme_bg')->nullable();
            $table->text('theme_card')->nullable();
            $table->text('theme_accent')->nullable();
            $table->text('theme_accent2')->nullable();
            $table->text('theme_border')->nullable();
            $table->text('theme_text')->nullable();
            $table->text('theme_sidebar_bg')->nullable();
            $table->text('theme_sidebar_accent')->nullable();
            $table->text('theme_sidebar_text')->nullable();
            $table->text('theme_font_family')->nullable();
            $table->text('theme_font_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('users', function (Blueprint $table) {
            $table->dropColumn([
                'theme_bg', 'theme_card', 'theme_accent', 'theme_accent2',
                'theme_border', 'theme_text', 'theme_sidebar_bg',
                'theme_sidebar_accent', 'theme_sidebar_text',
                'theme_font_family', 'theme_font_size',
            ]);
        });
    }
};
