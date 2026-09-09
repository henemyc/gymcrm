<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('last_login_ip');
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'meeting_at')) {
                $table->timestamp('meeting_at')->nullable()->after('follow_up_at');
            }
            if (! Schema::hasColumn('leads', 'meeting_with')) {
                $table->foreignId('meeting_with')->nullable()->after('meeting_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('leads', 'meeting_notes')) {
                $table->text('meeting_notes')->nullable()->after('meeting_with');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'meeting_with')) {
                $table->dropConstrainedForeignId('meeting_with');
            }
            foreach (['meeting_at', 'meeting_notes'] as $col) {
                if (Schema::hasColumn('leads', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_seen_at')) {
                $table->dropColumn('last_seen_at');
            }
        });
    }
};
