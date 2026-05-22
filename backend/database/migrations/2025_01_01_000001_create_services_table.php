<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');              // e.g. "Customer Service", "Teller"
            $table->string('prefix_code', 5);    // e.g. "A", "B", "C"
            $table->unsignedTinyInteger('digit_length')->default(3); // e.g. 3 → A001
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
