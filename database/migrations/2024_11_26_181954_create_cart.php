    <?php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up(): void
        {
            Schema::create('orders', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->unsignedBigInteger('user_id');
                $table->enum('status', [
                    'cart',       
                    'pending', 
                    'processing', 
                    'shipped', 
                    'delivered', 
                    'cancelled'
                ])->default('cart');
                $table->decimal('total_amount', 10, 2)->default(0);
                $table->string('shipping_address')->nullable();
                $table->string('payment_method')->nullable();
                $table->timestamp('checkout_at')->nullable();
                $table->timestamps();

                // Foreign key
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
            });

            Schema::create('order_items', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('order_id');
                $table->string('product_variant_id');
                $table->unsignedBigInteger('inventory_id');
                $table->integer('quantity')->default(1);
                $table->decimal('price', 10, 2);
                $table->decimal('subtotal', 10, 2);
                $table->timestamps();

                // Foreign keys
                $table->foreign('order_id')
                    ->references('id')
                    ->on('orders')
                    ->onDelete('cascade');

                $table->foreign('product_variant_id')
                    ->references('id')
                    ->on('product_variants')
                    ->onDelete('cascade');

                $table->foreign('inventory_id')
                    ->references('id')
                    ->on('inventories')
                    ->onDelete('cascade');

                $table->unique(['order_id', 'product_variant_id', 'inventory_id']);
            });
        }

        public function down(): void
        {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['order_id']);
                $table->dropForeign(['product_variant_id']);
                $table->dropForeign(['inventory_id']);
            });

            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });

            Schema::dropIfExists('order_items');
            Schema::dropIfExists('orders');
        }
    };