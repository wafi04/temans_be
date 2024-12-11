<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        try {
            
            $categories = DB::select("
                SELECT 
                    id,
                    name,
                    slug,
                    description,
                    image_url,
                    is_active,
                    created_at
                FROM categories
                ORDER BY created_at DESC
            ");

            return response()->json([
                'status' => 'success',
                'data' => $categories,
                'message' => 'Categories retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving categories: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'image_url' => 'nullable|url',
                'is_active' => 'boolean'
            ]);

            $result = DB::insert("
                INSERT INTO categories (name, slug, description, image_url, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ", [
                $request->name,
                Str::slug($request->name),
                $request->description,
                $request->image_url,
                $request->is_active ?? true
            ]);

            if ($result) {
                $category = DB::select("
                    SELECT * FROM categories 
                    WHERE id = LAST_INSERT_ID()
                ")[0];

                return response()->json([
                    'status' => 'success',
                    'data' => $category,
                    'message' => 'Category created successfully'
                ], 201);
            }

        } catch (\Exception $e) {
            Log::error('Error creating category: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $category = DB::select("
                SELECT *
                FROM categories
                WHERE id = ?
                AND deleted_at IS NULL
            ", [$id]);

            if (empty($category)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $category[0],
                'message' => 'Category retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving category: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

   public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'string|max:255',
                'description' => 'nullable|string',
                'image_url' => 'nullable|url',
                'is_active' => 'boolean'
            ]);

            $updates = [];
            $params = [];
            
            if ($request->has('name')) {
                $updates[] = "name = ?";
                $params[] = $request->name;
            }
            if ($request->has('slug')) {
                $updates[] = "slug = ?";
                $params[] = Str::slug($request -> name);
            }
            if ($request->has('description')) {
                $updates[] = "description = ?";
                $params[] = $request->description;
            }
            if ($request->has('image_url')) {
                $updates[] = "image_url = ?";
                $params[] = $request->image_url;
            }
            if ($request->has('is_active')) {
                $updates[] = "is_active = ?";
                $params[] = $request->is_active;
            }

            $updates[] = "updated_at = NOW()";
            
            if (!empty($updates)) {
                $updateQuery = "
                    UPDATE categories 
                    SET " . implode(", ", $updates) . "
                    WHERE id = ? AND deleted_at IS NULL
                ";
                
                $params[] = $id;
                DB::update($updateQuery, $params);

                $category = DB::select("
                    SELECT * FROM categories 
                    WHERE id = ?
                ", [$id])[0];

                return response()->json([
                    'status' => 'success',
                    'data' => $category,
                    'message' => 'Category updated successfully'
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'No fields to update'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error updating category: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update category',
                'error' => $e->getMessage()
            ], 500);
        }
    }
public function destroy($id)
{
    try {
        $result = DB::delete("
            DELETE FROM categories 
            WHERE id = ?
        ", [$id]);

        if ($result === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Category deleted successfully'
        ]);

    } catch (\Exception $e) {
        Log::error('Error deleting category: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to delete category',
            'error' => $e->getMessage()
        ], 500);
    }
}
    // Method untuk mendapatkan kategori aktif
    public function activeCategories()
    {
        $categories = Category::where('is_active', true)->get();
        return response()->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }

    
}