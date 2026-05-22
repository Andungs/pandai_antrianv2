<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->foreignId('counter_id')->nullable()->constrained()->onDelete('set null');
            $table->date('date');                                    // Scope hari ini, reset harian
            $table->string('queue_number', 10);                     // e.g. "A001"
            $table->enum('status', ['menunggu', 'dipanggil', 'dilayani', 'terlewat'])->default('menunggu');
            $table->string('visitor_photo_path')->nullable();        // Path foto dari KIOSK camera
            $table->timestamp('called_at')->nullable();              // Waktu dipanggil
            $table->timestamp('served_at')->nullable();              // Waktu mulai dilayani
            $table->timestamps();

            // Index untuk query harian yang sering dilakukan
            $table->index(['date', 'service_id']);
            $table->index(['date', 'status']);
            $table->index(['date', 'counter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};
