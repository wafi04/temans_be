<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Data produk yang ingin Anda seed
        $products = [
            [
                "name" => "Nike Air Force 1 '07 Next Nature",
                "description" => "Women's Road Running Shoes",
                "sellerId" => 1,
                "category_id" => 1,
                "variants" => [
                    [
                        "image" => "https://static.nike.com/a/images/c_limit,w_592,f_auto/t_product_v1/b0ed852d-8aa0-472c-91f8-26fee8f04048/W+AIR+FORCE+1+%2707+NEXT+NATURE.png",
                        "color" => "Blue",
                        "price" => 899000,
                        "inventory" => [
                            ["size" => "39", "quantity" => 7],
                            ["size" => "40", "quantity" => 1],
                            ["size" => "41", "quantity" => 5]
                        ]
                    ]
                ]
            ],
            [
                "name" => "Nike Revolution 7",
                "description" => "Women's Road Running Shoes",
                "sellerId" => 1,
                "category_id" => 1,
                "variants" => [
                    [
                        "image" => "https://static.nike.com/a/images/c_limit,w_592,f_auto/t_product_v1/34dc1f16-c254-4022-8813-2953827643a1/W+NIKE+REVOLUTION+7.png",
                        "color" => "Blue",
                        "price" => 809000,
                        "inventory" => [
                            ["size" => "37", "quantity" => 7],
                            ["size" => "38", "quantity" => 1],
                            ["size" => "39", "quantity" => 5]
                        ]
                    ]
                ]
            ],
            [
                "name" => "Nike Air Force 1 LV 8",
                "description" => "Created for the hardwood but taken to the streets.",
                "sellerId" => 1,
                "category_id" => 1,
                "variants" => [
                    [
                        "image" => "https://static.nike.com/a/images/c_limit,w_592,f_auto/t_product_v1/da43848d-0101-4f30-9aa6-8bd8b555bd13/AIR+FORCE+1+LV8+%28GS%29.png",
                        "color" => "White",
                        "price" => 1549000,
                        "inventory" => [
                            ["size" => "44", "quantity" => 10],
                            ["size" => "45", "quantity" => 10]
                        ]
                    ]
                ]
            ],
            [
                "name" => "Nike Air Force 1 Low Retro",
                "description" => "Created for the hardwood but taken to the streets, the Nike Dunk Low Retro returns with crisp overlays and original team colours.",
                "sellerId" => 1,
                "category_id" => 1,
                "variants" => [
                    [
                        "image" => "https://static.nike.com/a/images/c_limit,w_592,f_auto/t_product_v1/c2990651-5612-4c09-802c-d8261dfc7fa4/AIR+FORCE+1+LOW+RETRO.png",
                        "color" => "Brown",
                        "price" => 1549000,
                        "inventory" => [
                            ["size" => "40", "quantity" => 7],
                            ["size" => "41", "quantity" => 1],
                            ["size" => "42", "quantity" => 5]
                        ]
                    ]
                ]
            ],
            [
                "name" => "Nike Air Force 1 Shadow",
                "description" => "Created for the hardwood but taken to the streets, the Nike Dunk Low Retro returns with crisp overlays and original team colours.",
                "sellerId" => 1,
                "category_id" => 1,
                "variants" => [
                    [
                        "image" => "https://static.nike.com/a/images/c_limit,w_592,f_auto/t_product_v1/1ba9d50d-d1b4-453c-9e86-944634824b0d/W+AF1+SHADOW.png",
                        "color" => "Red",
                        "price" => 1069000,
                        "inventory" => [
                            ["size" => "43", "quantity" => 10],
                            ["size" => "44", "quantity" => 5],
                            ["size" => "45", "quantity" => 8]
                        ]
                    ]
                ]
            ]
        ];

        foreach ($products as $productData) {
            // Generate unique ID untuk produk
            $productId = Str::random(20);

            // Masukkan produk
            DB::table('products')->insert([
                'id' => $productId,
                'name' => $productData['name'],
                'description' => $productData['description'] ?? null,
                'seller_id' => $productData['sellerId'],
                'category_id' => $productData['category_id'],
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Proses varian produk
            $variants = is_array($productData['variants']) 
                ? $productData['variants'] 
                : [$productData['variants']];

            foreach ($variants as $variantData) {
                // Generate unique ID untuk varian
                $variantId = Str::random(20);

                // Generate SKU
                $sku = strtoupper(Str::random(8));

                // Masukkan varian produk
                DB::table('product_variants')->insert([
                    'id' => $variantId,
                    'product_id' => $productId,
                    'color' => $variantData['color'],
                    'image' => $variantData['image'] ?? null,
                    'price' => $variantData['price'],
                    'sku' => $sku,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Proses inventori untuk varian
                $inventories = $variantData['inventory'];

                foreach ($inventories as $inventoryData) {
                    DB::table('inventories')->insert([
                        'product_variant_id' => $variantId,
                        'size' => $inventoryData['size'],
                        'quantity' => $inventoryData['quantity'],
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
    }
}