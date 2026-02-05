<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('populations', function (Blueprint $table) {
            $table->id();

            // Relaciones Nullables
            $table->foreignId('island_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('municipality_id')->nullable()->constrained()->onDelete('cascade');

            $table->integer('year');
            $table->string('gender'); // Hombres, Mujeres, Total
            $table->integer('age');    // String, para permitir "100 o más"
            $table->integer('population');

            // Índice compuesto para evitar duplicados y acelerar consultas
            // Evita que insertemos el mismo dato dos veces
            $table->index(['year', 'gender', 'age', 'island_id', 'municipality_id'], 'pop_index_unique');

        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('populations');
    }
};
