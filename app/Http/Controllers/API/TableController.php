<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TableController extends Controller
{
    public function index()
    {
        $tables = Table::orderBy('table_number')->get();

        return response()->json([
            'success' => true,
            'data' => $tables
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'table_number' => 'required|string|unique:tables,table_number,' . $id,
            'capacity' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $table->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Table updated successfully',
            'data' => $table
        ]);
    }

    public function destroy($id)
    {
        $table = Table::find($id);

        if (!$table) {
            return response()->json([
                'success' => false,
                'message' => 'Table not found'
            ], 404);
        }

        // Check if table has active orders
        if ($table->orders()->whereIn('status', ['pending', 'confirmed', 'preparing'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete table with active orders'
            ], 422);
        }

        $table->delete();

        return response()->json([
            'success' => true,
            'message' => 'Table deleted successfully'
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:available,occupied,reserved,disabled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $table = Table::find($id);

        if (!$table) {
            return response()->json([
                'success' => false,
                'message' => 'Table not found'
            ], 404);
        }

        $table->status = $request->status;
        $table->save();

        return response()->json([
            'success' => true,
            'message' => 'Table status updated successfully',
            'data' => $table
        ]);
    }

    public function getAvailableTables()
    {
        $tables = Table::where('status', 'available')->orderBy('table_number')->get();

        return response()->json([
            'success' => true,
            'data' => $tables
        ]);
    }
}
