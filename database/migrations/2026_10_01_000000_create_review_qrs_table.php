<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_qrs', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 16)->unique();
            $table->string('name');
            $table->string('place_id');
            $table->string('place_name');
            $table->text('place_address');
            $table->text('maps_url');
            $table->text('review_url');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('total_scans')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_qrs');
    }
};
