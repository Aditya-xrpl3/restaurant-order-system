<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptController extends Controller
{
    public function index(Request $request)
    {
        $query = Receipt::with('order.user', 'order.table');

        // Search by receipt number or order number
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                  ->orWhereHas('order', function($orderQuery) use ($search) {
                      $orderQuery->where('order_number', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $receipts = $query->orderBy('created_at', 'desc')
                         ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $receipts
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'file_type' => 'in:pdf,png'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::with(['user', 'table', 'orderItems.product'])->find($request->order_id);

        // Check if receipt already exists
        if ($order->receipt) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt already exists for this order'
            ], 422);
        }

        $fileType = $request->file_type ?? 'pdf';

        // Prepare receipt data
        $receiptData = [
            'order_number' => $order->order_number,
            'customer_name' => $order->user->name,
            'table_number' => $order->table->table_number ?? 'N/A',
            'order_date' => $order->created_at->format('Y-m-d H:i:s'),
            'items' => $order->orderItems->map(function($item) {
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

        $receipt = Receipt::create([
            'order_id' => $order->id,
            'file_type' => $fileType,
            'receipt_data' => $receiptData
        ]);

        // Generate receipt file
        $filePath = $this->generateReceiptFile($receipt, $receiptData, $fileType);
        $receipt->update(['file_path' => $filePath]);

        return response()->json([
            'success' => true,
            'message' => 'Receipt created successfully',
            'data' => $receipt->load('order')
        ], 201);
    }

    public function show($id)
    {
        $receipt = Receipt::with('order.user', 'order.table', 'order.orderItems.product')->find($id);

        if (!$receipt) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $receipt
        ]);
    }

    public function download($id)
    {
        $receipt = Receipt::find($id);

        if (!$receipt) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt not found'
            ], 404);
        }

        if (!$receipt->file_path || !Storage::disk('public')->exists($receipt->file_path)) {
            // Regenerate file if not exists
            $filePath = $this->generateReceiptFile($receipt, $receipt->receipt_data, $receipt->file_type);
            $receipt->update(['file_path' => $filePath]);
        }

        return Storage::disk('public')->download($receipt->file_path, $receipt->receipt_number . '.' . $receipt->file_type);
    }

    public function destroy($id)
    {
        $receipt = Receipt::find($id);

        if (!$receipt) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt not found'
            ], 404);
        }

        // Delete file if exists
        if ($receipt->file_path) {
            Storage::disk('public')->delete($receipt->file_path);
        }

        $receipt->delete();

        return response()->json([
            'success' => true,
            'message' => 'Receipt deleted successfully'
        ]);
    }

    private function generateReceiptFile($receipt, $receiptData, $fileType)
    {
        $html = view('receipt-template', compact('receiptData'))->render();

        if ($fileType === 'pdf') {
            $pdf = Pdf::loadHTML($html);
            $fileName = 'receipt_' . $receipt->receipt_number . '.pdf';
            $filePath = 'receipts/' . $fileName;

            Storage::disk('public')->put($filePath, $pdf->output());

            return $filePath;
        } else {
            // For PNG, you would need additional libraries like wkhtmltopdf or puppeteer
            // For now, we'll just save as PDF
            return $this->generateReceiptFile($receipt, $receiptData, 'pdf');
        }
    }
}
