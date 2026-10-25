<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // «Merk som feil» i admin. Pakken er et øyeblikksbilde av det som
        // trengs for å feilsøke, tatt da feilen ble merket, og tas med når
        // feilene eksporteres.
        Schema::create('feilmerkinger', function (Blueprint $table) {
            $table->id();
            $table->string('gjelder_type');
            $table->unsignedBigInteger('gjelder_id');
            $table->text('begrunnelse');
            $table->json('pakke');
            $table->boolean('lost')->default(false);
            $table->timestamp('eksportert')->nullable();
            $table->foreignId('merket_av')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['gjelder_type', 'gjelder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feilmerkinger');
    }
};
