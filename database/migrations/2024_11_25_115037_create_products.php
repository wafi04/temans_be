<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->string('id')->primary(); // String primary key untuk products
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('category_id');
            $table->index('seller_id'); // Add index

            // Foreign keys
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->onDelete('cascade');
            
            $table->foreign('seller_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->string('id')->primary(); // String primary key untuk product_variants
            $table->string('product_id');
            $table->string('color');
            $table->string('image')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('sku')->unique();
            $table->timestamps();

            // Foreign key
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });

        Schema::create('inventories', function (Blueprint $table) {
            $table->id(); // Numerik auto-increment untuk inventories
            $table->string('product_variant_id');
            $table->string('size');
            $table->integer('quantity')->default(0);
            $table->timestamps();

            // Foreign key
            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product_variants')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        // Hapus foreign key terlebih dahulu
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropForeign(['category_id']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
        });

        // Hapus tabel
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};