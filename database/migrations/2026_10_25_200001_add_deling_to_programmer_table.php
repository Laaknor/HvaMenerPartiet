<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programmer', function (Blueprint $table) {
            $table->string('deling', 20)->default('automatisk')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('programmer', function (Blueprint $table) {
            $table->dropColumn('deling');
        });
    }
};
