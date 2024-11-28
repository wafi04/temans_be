<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductsController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with([
        'variants' => function ($query) {
            $query->with('inventories');
        },
        'category',
        'seller'
    ])
    ->when($request->search, function ($query) use ($request) {
        $query->where('name', 'like', '%' . $request->search . '%');
    })
    ->orderBy('created_at', 'desc')
    ->paginate($request->input('per_page', 15));

    return response()->json([
        'data' => $products->items(),
        'total' => $products->total(),
        'current_page' => $products->currentPage(),
        'last_page' => $products->lastPage()
    ]);
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
        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'seller_id' => $request->seller_id,
            'category_id' => $request->category_id
        ]);

        return response()->json([
            'message' => 'Product created successfully', 
            'data' => $product
        ]);
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
        $validatedData = $request->validate([
            'product_id' => 'required|exists:products,id',
            'color' => 'required|string',
            'image' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'inventory' => 'required|array',
            'inventory.*.size' => 'required|string',
            'inventory.*.quantity' => 'required|integer|min:0'
        ]);

        return DB::transaction(function () use ($validatedData) {
            try {
                // Generate SKU on the server-side
                $sku = $this->generateUniqueSku();

                // Create variant
                $variant = ProductVariant::create([
                    'product_id' => $validatedData['product_id'],
                    'color' => $validatedData['color'],
                    'image' => $validatedData['image'] ?? null,
                    'price' => $validatedData['price'],
                    'sku' => $sku
                ]);

                // Create inventories for the variant
                $inventoryItems = [];
                foreach ($validatedData['inventory'] as $inventoryData) {
                    $inventoryItem = Inventory::create([
                        'product_variant_id' => $variant->id,
                        'size' => $inventoryData['size'],
                        'quantity' => $inventoryData['quantity']
                    ]);
                    $inventoryItems[] = $inventoryItem;
                }

                // Refresh the variant to load inventories
                $variant->load('inventories');

                return response()->json([
                    'message' => 'Variant and Inventory created successfully',
                    'variant' => $variant,
                ], 201);

            } catch (\Exception $e) {
                // Log the full error for debugging
                Log::error('Variant Creation Error', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'data' => $validatedData
                ]);

                return response()->json([
                    'message' => 'Error creating variant',
                    'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
                ], 500);
            }
        });
    }

    public function updateProduct(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);
        $product->update([
            'name' => $request->name,
            'description' => $request->description,
            'category_id' => $request->category_id
        ]);

        return response()->json([
            'message' => 'Product updated successfully', 
            'product' => $product
        ]);
    }
public function updateVariantsAndInventory(Request $request, $variantId)
{
    try {
        return DB::transaction(function () use ($request, $variantId) {
            // Validate that variant data exists
            if (!$request->has('color') || !$request->has('price')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Missing required variant fields'
                ], 400);
            }

            // Update or create the variant
            $variant = ProductVariant::updateOrCreate(
                ['id' => $variantId],
                [
                    'color' => $request->color,
                    'image' => $request->image ?? null,
                    'price' => $request->price,
                    'sku' => $request->sku ?? $this->generateUniqueSku()
                ]
            );

            // Handle inventories if they exist
            if ($request->has('inventory') && is_array($request->inventory)) {
                foreach ($request->inventory as $inventoryData) {
                    // Validate required inventory fields
                    if (!isset($inventoryData['size']) || !isset($inventoryData['quantity'])) {
                        throw new \InvalidArgumentException('Missing required inventory fields');
                    }

                    // Update or create inventory
                    Inventory::updateOrCreate(
                        [
                            'id' => $inventoryData['id'] ?? null,
                            'product_variant_id' => $variant->id
                        ],
                        [
                            'size' => $inventoryData['size'],
                            'quantity' => max(0, intval($inventoryData['quantity'])) // Ensure non-negative quantity
                        ]
                    );
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Variant and Inventory updated successfully',
                'data' => [
                    'variant_id' => $variant->id,
                    'color' => $variant->color,
                    'price' => $variant->price,
                    'sku' => $variant->sku
                ]
            ], 200);
        });
    } catch (\InvalidArgumentException $e) {
        return response()->json([
            'status' => false,
            'message' => $e->getMessage()
        ], 400);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'An error occurred while updating variant and inventory'
        ], 500);
    }
}
 public function deleteProduct($productId)
    {
        try {
            return DB::transaction(function () use ($productId) {
            $product = Product::with(['variants.inventories'])->findOrFail($productId);
                                
                // Delete the product
                $product->delete();

                return response()->json([
                    'status' => true,
                    'message' => 'Product and all its variants and inventories deleted successfully'
                ], 200);
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting product: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while deleting the product'
            ], 500);
        }
    }
public function deleteVariant($variantId)
    {
        try {
            return DB::transaction(function () use ($variantId) {
                $variant = ProductVariant::findOrFail($variantId);
                
                // Delete all related inventories
                $variant->inventories()->delete();
                
                // Delete the variant
                $variant->delete();

                return response()->json([
                    'status' => true,
                    'message' => 'Variant and all its inventories deleted successfully'
                ], 200);
            });
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