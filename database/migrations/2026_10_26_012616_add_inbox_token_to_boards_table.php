<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->string('inbox_token', 64)->nullable()->unique(); // secret for the iPhone "Add to Kanban" shortcut
        });
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropUnique(['inbox_token']);
            $table->dropColumn('inbox_token');
        });
    }
};
