<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('overrides', function (Blueprint $table) {
            $table->boolean('is_masked')->default(false)->nullable()->comment('Used for obscuring value for certain fields');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('overrides', function (Blueprint $table) {
            $table->dropColumn('is_masked');
        });
    }
};
