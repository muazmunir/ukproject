<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWalletTransactionsTable extends Migration
{
    public function up()
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('user_id');
            $table->enum('type', ['credit', 'debit']);

            // short label: 'escrow_release', 'payout', 'manual_adjustment', etc.
            $table->string('reason', 50);

            // links (nullable depending on source)
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('reservation_id')->nullable();
            $table->unsignedBigInteger('payout_id')->nullable();

            // amounts in minor units
            $table->bigInteger('amount_minor');
            $table->bigInteger('balance_after_minor'); // snapshot after this txn

            $table->string('currency', 3);

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('wallet_transactions');
    }
}
