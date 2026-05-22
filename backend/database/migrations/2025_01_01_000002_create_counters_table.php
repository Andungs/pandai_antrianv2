<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counters', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // e.g. "Loket 1"
            $table->boolean('status')->default(true);        // aktif / tidak aktif
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counters');
    }
};
