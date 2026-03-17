<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('student_name');
            $table->string('student_registration', 20);
            $table->string('attendant_name');
            $table->string('subject');
            $table->dateTime('scheduled_at');
            $table->enum('status', ['Confirmado', 'Pendente', 'Realizado', 'Cancelado'])->default('Pendente');
            $table->timestamps();

            $table->index('scheduled_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
