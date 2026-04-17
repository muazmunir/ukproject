<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayoutsTable extends Migration
{
    public function up()
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('user_id'); // coach
            $table->bigInteger('amount_minor');    // from wallet
            $table->string('currency', 3);

            // 'airwallex', 'wise', 'starling', etc.
            $table->string('provider', 32);

            // ID returned by provider
            $table->string('provider_payout_id', 128)->nullable();

            $table->enum('status', [
                'pending',      // requested, waiting to send to provider
                'processing',   // sent to provider, waiting callback
                'completed',    // payout success
                'failed',       // permanent failure
            ])->default('pending');

            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payouts');
    }
}
