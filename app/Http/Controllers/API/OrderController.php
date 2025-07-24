<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user', 'table', 'orderItems.product']);

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by payment status
        if ($request->has('payment_status') && $request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by user (for user role)
        if ($request->user()->role === 'user') {
            $query->where('user_id', $request->user()->id);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->orderBy('created_at', 'desc')
                       ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'table_id' => 'required|exists:tables,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if table is available
        $table = Table::find($request->table_id);
        if (!$table->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Table is not available'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $subtotal = 0;
            $orderItems = [];

            // Calculate subtotal and prepare order items
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                if (!$product->is_available) {
                    throw new \Exception("Product {$product->name} is not available");
                }

                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$product->name}");
                }

                $itemTotal = $product->price * $item['quantity'];
                $subtotal += $itemTotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'total' => $itemTotal,
                    'notes' => $item['notes'] ?? null
                ];

                // Reduce stock
                $product->decrement('stock', $item['quantity']);
            }

            // Calculate tax (10%)
            $tax = $subtotal * 0.10;
            $total = $subtotal + $tax;

            // Create order
            $order = Order::create([
                'user_id' => $request->user()->id,
                'table_id' => $request->table_id,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'notes' => $request->notes,
                'order_number' => 'ORD-' . date('Ymd') . '-' . str_pad(Order::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT)
            ]);

            // Create order items
            foreach ($orderItems as $item) {
                $order->orderItems()->create($item);
            }

            // Update table status to occupied
            $table->update(['status' => 'occupied']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order->load(['user', 'table', 'orderItems.product'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function show($id)
    {
        $order = Order::with(['user', 'table', 'orderItems.product', 'receipt'])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Check authorization
        if ($order->user_id !== auth()->id() && !auth()->user()->isCashier() && !auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,confirmed,preparing,ready,completed,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $order->status = $request->status;

        if ($request->status === 'completed') {
            $order->completed_at = now();
            $order->payment_status = 'paid';

            // Free up the table
            if ($order->table) {
                $order->table->update(['status' => 'available']);
            }
        }

        if ($request->status === 'cancelled') {
            // Restore stock
            foreach ($order->orderItems as $item) {
                $item->product->increment('stock', $item->quantity);
            }

            // Free up the table
            if ($order->table) {
                $order->table->update(['status' => 'available']);
            }
        }

        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => $order->load(['user', 'table', 'orderItems.product'])
        ]);
    }

    public function updatePaymentStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_status' => 'required|in:unpaid,paid,refunded'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $order->payment_status = $request->payment_status;
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Payment status updated successfully',
            'data' => $order
        ]);
    }

    public function destroy($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Only allow deletion if order is pending or cancelled
        if (!in_array($order->status, ['pending', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete order with current status'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Restore stock if order was not cancelled
            if ($order->status !== 'cancelled') {
                foreach ($order->orderItems as $item) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            // Free up the table
            if ($order->table && $order->table->status === 'occupied') {
                $order->table->update(['status' => 'available']);
            }

            $order->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete order'
            ], 500);
        }
    }

    public function generateReceipt($id)
    {
        $order = Order::with(['user', 'table', 'orderItems.product'])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Check authorization
        if ($order->user_id !== auth()->id() && !auth()->user()->isCashier() && !auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            $receiptData = [
                'order_number' => $order->order_number,
                'table_number' => $order->table->table_number,
                'customer_name' => $order->user->name,
                'date' => $order->created_at->format('d/m/Y H:i'),
                'items' => $order->orderItems->map(function ($item) {
                    return [
                        'name' => $item->product->name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'total' => $item->total,
                        'notes' => $item->notes
                    ];
                }),
                'subtotal' => $order->subtotal,
                'tax' => $order->tax,
                'total' => $order->total,
                'notes' => $order->notes
            ];

            return response()->json([
                'success' => true,
                'data' => $receiptData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate receipt'
            ], 500);
        }
    }
}
