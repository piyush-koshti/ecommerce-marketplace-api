<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('seller_sku', 100);
            $table->string('condition', 40)->default('new');
            $table->decimal('price', 12, 2);
            $table->unsignedSmallInteger('dispatch_sla_days')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamps();
            $table->unique(['seller_account_id', 'seller_sku']);
            $table->unique(['seller_account_id', 'product_variant_id']);
            $table->index(['seller_account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_offers');
    }
};
