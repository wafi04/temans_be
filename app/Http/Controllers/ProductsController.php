<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductsController extends Controller
{
   public function index(Request $request) 
{
    $user = auth()->user();
$query = "
    SELECT 
        p.id, 
        p.name, 
        p.description, 
        p.seller_id, 
        p.category_id, 
        p.created_at,
        p.updated_at
    FROM products p
    WHERE 1=1
";

$params = [];

// Filter berdasarkan role
if ($user->role === 'admin') {
    $query .= " AND p.seller_id = ?";
    $params[] = $user->id;
} elseif ($user->role === 'user') {
    $query .= " AND EXISTS (
        SELECT 1 FROM users 
        WHERE users.id = p.seller_id 
        AND users.role = 'admin'
    )";
}

// Filter search
if ($request->search) {
    $query .= " AND p.name LIKE ?";
    $params[] = '%' . $request->search . '%';
}

$query .= " ORDER BY p.created_at DESC";

try {
    $products = DB::select($query, $params);
    
    foreach ($products as &$product) {
        // Ambil category lengkap
        $category = DB::selectOne("
            SELECT 
                id, 
                name, 
                description
            FROM categories 
            WHERE id = ?
        ", [$product->category_id]);
        
        // Ambil seller lengkap
        $seller = DB::selectOne("
            SELECT 
                id, 
                name, 
                email,
                role
            FROM users 
            WHERE id = ?
        ", [$product->seller_id]);
        
        // Ambil semua variants untuk produk
        $variants = DB::select("
            SELECT 
                pv.id,
                pv.product_id,
                pv.color,
                pv.image,
                pv.price,
                pv.sku
            FROM product_variants pv
            WHERE pv.product_id = ?
        ", [$product->id]);
        
        // Untuk setiap variant, ambil inventoriesnya
        foreach ($variants as &$variant) {
            $inventories = DB::select("
                SELECT 
                    id,
                    product_variant_id,
                    size,
                    quantity
                FROM inventories
                WHERE product_variant_id = ?
            ", [$variant->id]);
            
            $variant->inventories = $inventories;
        }
        
        // Tambahkan category, seller, dan variants sebagai objek
        $product->category = $category;
        $product->seller = $seller;
        $product->variants = $variants;
    }
    
    return response()->json([
        'status' => 'success',
        'data' => $products
    ]);
    
} catch (\Exception $e) {
    return response()->json([
        'status' => 'error',
        'message' => 'Failed to fetch products',
        'error' => $e->getMessage()
    ], 500);
}
}

    public function show($id)
    {
        $product = Product::with([
            'variants' => function ($query) {
                $query->with('inventories');
            }
        ])->findOrFail($id);

        return response()->json($product);
    }
public function createProduct(Request $request)
{
    try {
       
        // Persiapkan query insert
        $query = "
            INSERT INTO products (
                id,
                name, 
                description, 
                seller_id, 
                category_id, 
                created_at, 
                updated_at
            ) VALUES (
                :id,
                :name, 
                :description, 
                :seller_id, 
                :category_id, 
                :created_at, 
                :updated_at
            )
        ";

        // Siapkan parameter
        $params = [
            'id'  =>  Str::random(20),
            'name' => $request->name,
            'description' => $request->description ?? null,
            'seller_id' => $request->seller_id,
            'category_id' => $request->category_id,
            'created_at' => now(),
            'updated_at' => now()
        ];

        // Eksekusi query dan dapatkan ID
        DB::beginTransaction();
        
        DB::insert($query, $params);
        $productId = DB::getPdo()->lastInsertId();

        // Ambil produk yang baru dibuat
        $product = DB::selectOne("
            SELECT 
                id, 
                name, 
                description, 
                seller_id, 
                category_id, 
                created_at, 
                updated_at 
            FROM products 
            WHERE id = ?
        ", [$productId]);

        DB::commit();

        return response()->json([
            'status' => 'success',
            'message' => 'Product created successfully', 
            'data' => $product
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to create product',
            'error' => $e->getMessage()
        ], 500);
    }
}

    protected function generateUniqueSku(): string
    {
        do {
            // Generate a unique SKU
            $sku = strtoupper(Str::random(8));
            
            // Check if SKU already exists
            $existingSku = ProductVariant::where('sku', $sku)->exists();
        } while ($existingSku);

        return $sku;
    }   
public function createVariantsAndInventory(Request $request) 
{
    // Validasi input
    $validator = Validator::make($request->all(), [
        'product_id' => 'required|exists:products,id',
        'color' => 'required|string|max:100',
        'image' => 'nullable|string|max:255',
        'price' => 'required|numeric|min:0',
        'inventory' => 'required|array|min:1',
        'inventory.*.size' => 'required|string|max:50',
        'inventory.*.quantity' => 'required|integer|min:0'
    ]);

    // Cek validasi
    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'errors' => $validator->errors()
        ], 400);
    }

    try {
        DB::beginTransaction();

        // Generate unique SKU
        $sku = $this->generateUniqueSku();

        // Generate UUID untuk variant
        $variantId = Str::uuid()->toString();

        // Persiapkan query untuk insert variant
        $variantQuery = "
            INSERT INTO product_variants (
                id,
                product_id, 
                color, 
                image, 
                price, 
                sku, 
                created_at, 
                updated_at
            ) VALUES (
                :id,
                :product_id, 
                :color, 
                :image, 
                :price, 
                :sku, 
                :created_at, 
                :updated_at
            )
        ";

        // Siapkan parameter variant
        $variantParams = [
            'id' => $variantId,
            'product_id' => $request->product_id,
            'color' => $request->color,
            'image' => $request->image ?? null,
            'price' => $request->price,
            'sku' => $sku,
            'created_at' => now(),
            'updated_at' => now()
        ];

        // Insert variant
        DB::insert($variantQuery, $variantParams);

        // Persiapkan query untuk insert inventory
        $inventoryQuery = "
            INSERT INTO inventories (
                product_variant_id, 
                size, 
                quantity, 
                created_at, 
                updated_at
            ) VALUES (
                :product_variant_id, 
                :size, 
                :quantity, 
                :created_at, 
                :updated_at
            )
        ";

        // Simpan inventories
        $inventoryItems = [];
        foreach ($request->inventory as $inventoryData) {
            $inventoryParams = [
                'product_variant_id' => $variantId,
                'size' => $inventoryData['size'],
                'quantity' => $inventoryData['quantity'],
                'created_at' => now(),
                'updated_at' => now()
            ];

            DB::insert($inventoryQuery, $inventoryParams);
            $inventoryId = DB::getPdo()->lastInsertId();

            // Ambil data inventory yang baru dibuat
            $inventoryItem = DB::selectOne("
                SELECT * FROM inventories WHERE id = ?
            ", [$inventoryId]);

            $inventoryItems[] = $inventoryItem;
        }

        // Ambil variant yang baru dibuat dengan inventories
        $variant = DB::selectOne("
            SELECT 
                pv.id, 
                pv.product_id, 
                pv.color, 
                pv.image, 
                pv.price, 
                pv.sku,
                pv.created_at,
                pv.updated_at,
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'id', i.id,
                        'size', i.size,
                        'quantity', i.quantity
                    )
                ) as inventories
            FROM product_variants pv
            LEFT JOIN inventories i ON pv.id = i.product_variant_id
            WHERE pv.id = ?
            GROUP BY 
                pv.id, 
                pv.product_id, 
                pv.color, 
                pv.image, 
                pv.price, 
                pv.sku,
                pv.created_at,
                pv.updated_at
        ", [$variantId]);

        // Convert inventories JSON to array
        $variant->inventories = $variant->inventories ? json_decode($variant->inventories, true) : [];

        DB::commit();

        return response()->json([
            'status' => 'success',
            'message' => 'Variant and Inventory created successfully',
            'variant' => $variant
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();

        // Log error untuk debugging
        Log::error('Variant Creation Error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'data' => $request->all()
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Error creating variant',
            'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
        ], 500);
    }
}
    public function updateProduct(Request $request, $productId)
{
    try {
        // Cek apakah produk ada
        $existingProduct = DB::selectOne("
            SELECT id FROM products WHERE id = ?
        ", [$productId]);

        if (!$existingProduct) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }

        $query = "
            UPDATE products 
            SET 
                name = :name, 
                description = :description, 
                category_id = :category_id, 
                updated_at = :updated_at
            WHERE id = :product_id
        ";

        // Siapkan parameter
        $params = [
            'name' => $request->name,
            'description' => $request->description ?? null,
            'category_id' => $request->category_id,
            'updated_at' => now(),
            'product_id' => $productId
        ];

        // Eksekusi update
        DB::beginTransaction();
        
        DB::update($query, $params);

        // Ambil produk yang sudah diupdate
        $updatedProduct = DB::selectOne("
            SELECT 
                p.id, 
                p.name, 
                p.description, 
                p.category_id, 
                c.name AS category_name,
                p.seller_id,
                s.name AS seller_name,
                p.created_at, 
                p.updated_at 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN users s ON p.seller_id = s.id
            WHERE p.id = ?
        ", [$productId]);

        DB::commit();

        return response()->json([
            'status' => 'success',
            'message' => 'Product updated successfully', 
            'data' => $updatedProduct
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to update product',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function updateVariantsAndInventory(Request $request, $variantId)
{
    // Validasi input
    $validator = Validator::make($request->all(), [
        'color' => 'sometimes|required|string|max:100',
        'image' => 'nullable|string|max:255',
        'price' => 'sometimes|required|numeric|min:0',
        'inventory' => 'sometimes|array',
        'inventory.*.size' => 'required_with:inventory|string|max:50',
        'inventory.*.quantity' => 'required_with:inventory|integer|min:0'
    ]);

    // Cek validasi
    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'errors' => $validator->errors()
        ], 400);
    }

    try {
        DB::beginTransaction();

        // Cek apakah variant exists
        $variantExists = DB::selectOne("SELECT * FROM product_variants WHERE id = ?", [$variantId]);
        
        if (!$variantExists) {
            return response()->json([
                'status' => false,
                'message' => 'Variant not found'
            ], 404);
        }

        // Persiapkan data update untuk variant
        $updateData = [];
        $updateParams = [];

        // Bangun query update variant
        $variantUpdateQuery = "UPDATE product_variants SET ";
        
        if ($request->has('color')) {
            $updateData[] = "color = ?";
            $updateParams[] = $request->color;
        }

        if ($request->has('image')) {
            $updateData[] = "image = ?";
            $updateParams[] = $request->image;
        }

        if ($request->has('price')) {
            $updateData[] = "price = ?";
            $updateParams[] = $request->price;
        }

        // Tambahkan updated_at
        if (!empty($updateData)) {
            $updateData[] = "updated_at = ?";
            $updateParams[] = now();
            
            // Tambahkan parameter variant id
            $updateParams[] = $variantId;

            // Eksekusi update variant
            $variantUpdateQuery .= implode(', ', $updateData) . " WHERE id = ?";
            DB::update($variantUpdateQuery, $updateParams);
        }

        // Proses inventory
        if ($request->has('inventory') && is_array($request->inventory)) {
            // Hapus inventory yang ada
            DB::delete("DELETE FROM inventories WHERE product_variant_id = ?", [$variantId]);

            // Siapkan query insert inventory
            $inventoryQuery = "
                INSERT INTO inventories (
                    product_variant_id, 
                    size, 
                    quantity, 
                    created_at, 
                    updated_at
                ) VALUES (?, ?, ?, ?, ?)
            ";

            // Proses setiap inventory
            foreach ($request->inventory as $inventoryData) {
                DB::insert($inventoryQuery, [
                    $variantId,
                    $inventoryData['size'],
                    max(0, intval($inventoryData['quantity'])),
                    now(),
                    now()
                ]);
            }
        }

        // Ambil data variant yang diupdate
        $updatedVariant = DB::selectOne("
            SELECT 
                pv.id, 
                pv.color, 
                pv.image, 
                pv.price, 
                pv.sku,
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'id', i.id,
                        'size', i.size,
                        'quantity', i.quantity
                    )
                ) as inventories
            FROM product_variants pv
            LEFT JOIN inventories i ON pv.id = i.product_variant_id
            WHERE pv.id = ?
            GROUP BY 
                pv.id, 
                pv.color, 
                pv.image, 
                pv.price, 
                pv.sku
        ", [$variantId]);

        // Convert inventories JSON to array
        $updatedVariant->inventories = $updatedVariant->inventories 
            ? json_decode($updatedVariant->inventories, true) 
            : [];

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Variant and Inventory updated successfully',
            'data' => [
                'variant' => [
                    'id' => $updatedVariant->id,
                    'color' => $updatedVariant->color,
                    'image' => $updatedVariant->image,
                    'price' => $updatedVariant->price,
                    'sku' => $updatedVariant->sku,
                    'inventories' => $updatedVariant->inventories
                ]
            ]
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();

        // Log error untuk debugging
        Log::error('Variant Update Error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'data' => $request->all()
        ]);

        return response()->json([
            'status' => false,
            'message' => 'An error occurred while updating variant and inventory',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}
 public function deleteProduct($productId)
{
    try {
        DB::beginTransaction();

        // Cek apakah produk ada
        $product = DB::selectOne("
            SELECT id FROM products WHERE id = ?
        ", [$productId]);

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Hapus inventories terkait variants
        DB::delete("
            DELETE FROM inventories 
            WHERE product_variant_id IN (
                SELECT id FROM product_variants 
                WHERE product_id = ?
            )
        ", [$productId]);

        // Hapus variants terkait produk
        DB::delete("
            DELETE FROM product_variants 
            WHERE product_id = ?
        ", [$productId]);

        // Hapus produk
        DB::delete("
            DELETE FROM products 
            WHERE id = ?
        ", [$productId]);

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Product and all its variants and inventories deleted successfully'
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        
        // Log error untuk debugging
        Log::error('Error deleting product: ' . $e->getMessage());

        return response()->json([
            'status' => false,
            'message' => 'An error occurred while deleting the product',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function deleteVariant($variantId)
    {
        try {
            DB::beginTransaction();

        // Cek apakah variant exists
        $variant = DB::selectOne("SELECT * FROM product_variants WHERE id = ?", [$variantId]);
        
        if (!$variant) {
            return response()->json([
                'status' => false,
                'message' => 'Variant not found'
            ], 404);
        }

        // Hapus semua inventories terkait
        $inventoryDeleteQuery = "DELETE FROM inventories WHERE product_variant_id = ?";
        $inventoriesDeleted = DB::delete($inventoryDeleteQuery, [$variantId]);

        // Hapus variant
        $variantDeleteQuery = "DELETE FROM product_variants WHERE id = ?";
        $variantDeleted = DB::delete($variantDeleteQuery, [$variantId]);

        // Commit transaksi
        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Variant and all its inventories deleted successfully',
            'deleted' => [
                'variant' => $variantDeleted,
                'inventories' => $inventoriesDeleted
            ]
        ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Variant not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting variant: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while deleting the variant'
            ], 500);
        }
    }
}