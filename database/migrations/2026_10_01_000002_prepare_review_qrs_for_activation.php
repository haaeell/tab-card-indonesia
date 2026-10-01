<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_qrs', function (Blueprint $table) {
            $table->string('place_id')->nullable()->change();
            $table->string('place_name')->nullable()->change();
            $table->text('place_address')->nullable()->change();
            $table->text('maps_url')->nullable()->change();
            $table->text('review_url')->nullable()->change();
            $table->string('activation_pin_hash')->nullable();
            $table->timestamp('activated_at')->nullable();
        });

        DB::table('review_qrs')->whereNotNull('review_url')->update(['activated_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('review_qrs', function (Blueprint $table) {
            $table->dropColumn(['activation_pin_hash', 'activated_at']);
            $table->string('place_id')->nullable(false)->change();
            $table->string('place_name')->nullable(false)->change();
            $table->text('place_address')->nullable(false)->change();
            $table->text('maps_url')->nullable(false)->change();
            $table->text('review_url')->nullable(false)->change();
        });
    }
};
