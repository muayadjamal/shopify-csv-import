<?php

use App\Enums\ProductImportStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imported_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('handle')->nullable();
            $table->string('title')->nullable();
            $table->string('sku')->nullable();
            $table->string('status')->default(ProductImportStatus::Pending->value)->index();
            $table->unsignedBigInteger('shopify_product_id')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            // A CSV row maps to exactly one record per run — keeps the job idempotent on retry.
            $table->unique(['import_run_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_products');
    }
};
