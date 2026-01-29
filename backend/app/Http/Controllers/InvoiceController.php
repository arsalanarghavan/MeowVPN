<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoicePdfService $pdfService
    ) {}

    /**
     * List invoices (users see their own, admins see all)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Invoice::with('reseller');

        if (!$user->isAdmin()) {
            // Users only see their own invoices
            $query->where('reseller_id', $user->id);
        } else {
            // Admins can filter
            if ($request->has('reseller_id')) {
                $query->where('reseller_id', $request->reseller_id);
            }
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
        }

        return response()->json(
            $query->latest()->paginate($request->input('per_page', 20))
        );
    }

    /**
     * Show invoice details
     */
    public function show(Request $request, Invoice $invoice)
    {
        $user = $request->user();

        if (!$user->isAdmin() && $invoice->reseller_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($invoice->load('reseller'));
    }

    /**
     * Generate/regenerate invoice PDF
     */
    public function generatePdf(Request $request, Invoice $invoice)
    {
        $user = $request->user();

        if (!$user->isAdmin() && $invoice->reseller_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $path = $this->pdfService->generateInvoice($invoice);

            return response()->json([
                'message' => 'فاکتور با موفقیت ایجاد شد',
                'path' => $path,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'خطا در ایجاد فاکتور: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download invoice PDF
     */
    public function download(Request $request, Invoice $invoice)
    {
        $user = $request->user();

        if (!$user->isAdmin() && $invoice->reseller_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Generate if not exists
        if (!$invoice->file_path || !Storage::exists($invoice->file_path)) {
            $this->pdfService->generateInvoice($invoice);
            $invoice->refresh();
        }

        if (!$invoice->file_path || !Storage::exists($invoice->file_path)) {
            return response()->json(['error' => 'فاکتور یافت نشد'], 404);
        }

        return Storage::download(
            $invoice->file_path,
            "invoice_{$invoice->id}.pdf"
        );
    }

    /**
     * Generate transaction receipt PDF
     */
    public function receipt(Request $request, Transaction $transaction)
    {
        $user = $request->user();

        if (!$user->isAdmin() && $transaction->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $path = $this->pdfService->generateReceipt($transaction->id);

            return Storage::download($path, "receipt_{$transaction->id}.pdf");
        } catch (\Exception $e) {
            return response()->json(['error' => 'خطا در ایجاد رسید: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mark invoice as paid (admin only)
     */
    public function markPaid(Request $request, Invoice $invoice)
    {
        $invoice->update(['status' => 'paid']);

        return response()->json([
            'message' => 'فاکتور به عنوان پرداخت شده ثبت شد',
            'invoice' => $invoice,
        ]);
    }

    /**
     * Get invoice stats (admin only)
     */
    public function stats()
    {
        return response()->json([
            'total' => Invoice::count(),
            'pending' => Invoice::where('status', 'pending')->count(),
            'paid' => Invoice::where('status', 'paid')->count(),
            'overdue' => Invoice::where('status', 'overdue')->count(),
            'total_pending_amount' => Invoice::where('status', 'pending')->sum('total_amount'),
            'total_paid_amount' => Invoice::where('status', 'paid')->sum('total_amount'),
        ]);
    }
}

