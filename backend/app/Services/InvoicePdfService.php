<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdfService
{
    /**
     * Get PDF options with Persian font support
     */
    private function getPdfOptions(): array
    {
        return [
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'isFontSubsettingEnabled' => true,
            'defaultFont' => 'tahoma',
            'chroot' => storage_path('fonts'),
        ];
    }

    /**
     * Generate PDF invoice for a reseller
     */
    public function generateInvoice(Invoice $invoice): string
    {
        $reseller = $invoice->reseller;
        
        // Get transactions for the invoice period
        $transactions = $reseller->children()
            ->join('transactions', 'users.id', '=', 'transactions.user_id')
            ->whereBetween('transactions.created_at', [$invoice->start_date, $invoice->end_date])
            ->where('transactions.status', 'completed')
            ->where('transactions.type', 'purchase')
            ->select('transactions.*')
            ->get();

        $data = [
            'invoice' => $invoice,
            'reseller' => $reseller,
            'transactions' => $transactions,
            'companyName' => config('app.name', 'MeowVPN'),
            'companyPhone' => config('app.support_username'),
        ];

        $pdf = Pdf::loadView('invoices.reseller', $data);
        $pdf->setOptions($this->getPdfOptions());
        $pdf->setPaper('a4', 'portrait');

        // Save to storage
        $filename = "invoices/invoice_{$invoice->id}_{$reseller->id}.pdf";
        Storage::put($filename, $pdf->output());

        // Update invoice record
        $invoice->update(['file_path' => $filename]);

        return $filename;
    }

    /**
     * Generate transaction receipt
     */
    public function generateReceipt(int $transactionId): string
    {
        $transaction = \App\Models\Transaction::with('user', 'subscription')->findOrFail($transactionId);

        $data = [
            'transaction' => $transaction,
            'user' => $transaction->user,
            'subscription' => $transaction->subscription,
            'companyName' => config('app.name', 'MeowVPN'),
        ];

        $pdf = Pdf::loadView('invoices.receipt', $data);
        $pdf->setOptions($this->getPdfOptions());
        $pdf->setPaper('a4', 'portrait');

        $filename = "receipts/receipt_{$transaction->id}.pdf";
        Storage::put($filename, $pdf->output());

        return $filename;
    }

    /**
     * Get PDF file content
     */
    public function getPdf(string $path): ?string
    {
        if (Storage::exists($path)) {
            return Storage::get($path);
        }
        return null;
    }

    /**
     * Delete PDF file
     */
    public function deletePdf(string $path): bool
    {
        if (Storage::exists($path)) {
            return Storage::delete($path);
        }
        return false;
    }
}

