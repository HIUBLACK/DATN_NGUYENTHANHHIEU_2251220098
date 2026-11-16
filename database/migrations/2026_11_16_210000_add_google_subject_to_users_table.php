<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_subject')->charset('ascii')->collation('ascii_bin')->nullable();
            $table->unique('google_subject');
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNotNull('google_subject')->exists()) {
            throw new RuntimeException('Cannot remove Google identities while linked accounts exist.');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_google_subject_unique');
            $table->dropColumn('google_subject');
        });
    }
};
