<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('odoo_configs', function (Blueprint $table) {
            $table->id();
            $table->string('url')->nullable();
            $table->string('db')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // will hold encrypted password
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odoo_configs');
    }
};
