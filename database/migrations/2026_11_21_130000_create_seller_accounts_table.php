<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('legal_business_name', 200);
            $table->string('display_name', 200)->nullable();
            $table->string('slug', 220)->unique();
            $table->string('business_type', 80)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_mobile', 20)->nullable();
            $table->string('status', 30)->default('pending_review')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_accounts');
    }
};
