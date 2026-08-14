<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Order;
use Illuminate\Http\Response;

class InvoiceService
{
    public function getInvoiceData(Order $order): array
    {
        $order->load(['user', 'items.product', 'items.variant', 'payments']);
        $company = CompanyProfile::first();

        return [
            'company' => [
                'name' => $company?->company_name ?? 'Hablun CCTV',
                'contacts' => $company?->contacts ?? [
                    'phone' => '+62 812-3456-7890',
                    'email' => 'info@habluncctv.com',
                    'address' => 'Indonesia',
                ],
            ],
            'invoice' => [
                'order_code' => $order->unique_order_code,
                'order_date' => $order->created_at->format('d M Y H:i'),
                'payment_status' => $order->payment_status,
                'order_status' => $order->status,
                'payment_method' => strtoupper($order->payment_method),
            ],
            'customer' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email ?? $order->user?->email,
                'phone' => $order->guest_phone_e164 ?? $order->user?->phone_e164,
                'installation_address' => $order->installation_address,
                'installation_city' => $order->installation_city,
                'installation_date' => $order->installation_date,
                'installation_time_slot' => $order->installation_time_slot,
            ],
            'items' => $order->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
                'installation_included' => (bool) $item->installation_included,
            ]),
            'totals' => [
                'subtotal' => (float) $order->subtotal,
                'installation_fee' => (float) $order->installation_fee,
                'tax_amount' => 0.00,
                'grand_total' => (float) $order->grand_total,
                'currency' => $order->currency,
            ],
        ];
    }

    public function renderHtml(Order $order): string
    {
        $data = $this->getInvoiceData($order);
        $company = $data['company'];
        $invoice = $data['invoice'];
        $customer = $data['customer'];
        $items = $data['items'];
        $totals = $data['totals'];

        $itemRows = '';
        foreach ($items as $index => $item) {
            $num = $index + 1;
            $name = htmlspecialchars($item['product_name'] . ($item['variant_name'] ? " ({$item['variant_name']})" : ''));
            $sku = htmlspecialchars($item['sku'] ?? '-');
            $unitPrice = number_format($item['unit_price'], 0, ',', '.');
            $lineTotal = number_format($item['line_total'], 0, ',', '.');

            $itemRows .= "
            <tr>
                <td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>{$num}</td>
                <td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>{$name}<br><small style='color:#6b7280;'>SKU: {$sku}</small></td>
                <td style='padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: center;'>{$item['quantity']}</td>
                <td style='padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: right;'>Rp {$unitPrice}</td>
                <td style='padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: right;'>Rp {$lineTotal}</td>
            </tr>";
        }

        $subtotal = number_format($totals['subtotal'], 0, ',', '.');
        $grandTotal = number_format($totals['grand_total'], 0, ',', '.');

        return "
<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='UTF-8'>
    <title>Faktur / Invoice {$invoice['order_code']}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1f2937; margin: 0; padding: 20px; background-color: #f9fafb; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #e5e7eb; background: #ffffff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #3b82f6; padding-bottom: 20px; margin-bottom: 20px; }
        .company-title { font-size: 24px; font-weight: bold; color: #1e3a8a; }
        .invoice-title { font-size: 20px; font-weight: bold; color: #3b82f6; text-align: right; }
        .details-grid { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .details-col { width: 48%; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f3f4f6; padding: 10px; text-align: left; border-bottom: 2px solid #d1d5db; font-size: 14px; }
        .totals-table { width: 50%; float: right; margin-top: 10px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .badge-paid { background: #dcfce7; color: #15803d; }
        .badge-pending { background: #fef9c3; color: #a16207; }
    </style>
</head>
<body>
    <div class='invoice-box'>
        <div class='header'>
            <div>
                <div class='company-title'>{$company['name']}</div>
                <div style='font-size: 12px; color: #6b7280;'>CCTV & Security Solutions System</div>
            </div>
            <div>
                <div class='invoice-title'>INVOICE</div>
                <div style='font-size: 14px; color: #4b5563;'>#{$invoice['order_code']}</div>
            </div>
        </div>

        <div class='details-grid'>
            <div class='details-col'>
                <strong>Penerima / Pelanggan:</strong><br>
                {$customer['name']}<br>
                {$customer['installation_address']}<br>
                Phone: {$customer['phone']}<br>
                Email: {$customer['email']}
            </div>
            <div class='details-col' style='text-align: right;'>
                <strong>Tanggal Order:</strong> {$invoice['order_date']}<br>
                <strong>Metode Pembayaran:</strong> {$invoice['payment_method']}<br>
                <strong>Status Pembayaran:</strong> <span class='badge " . ($invoice['payment_status'] === 'paid' ? 'badge-paid' : 'badge-pending') . "'>{$invoice['payment_status']}</span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Deskripsi Produk</th>
                    <th style='text-align: center;'>Qty</th>
                    <th style='text-align: right;'>Harga Satuan</th>
                    <th style='text-align: right;'>Total</th>
                </tr>
            </thead>
            <tbody>
                {$itemRows}
            </tbody>
        </table>

        <div style='clear: both;'></div>

        <table class='totals-table'>
            <tr>
                <td style='padding: 6px;'>Subtotal</td>
                <td style='padding: 6px; text-align: right;'>Rp {$subtotal}</td>
            </tr>
            <tr>
                <td style='padding: 6px;'>Pajak / PPN (0%)</td>
                <td style='padding: 6px; text-align: right;'>Rp 0</td>
            </tr>
            <tr style='font-weight: bold; font-size: 16px; border-top: 2px solid #1f2937;'>
                <td style='padding: 8px;'>Grand Total</td>
                <td style='padding: 8px; text-align: right; color: #1e3a8a;'>Rp {$grandTotal}</td>
            </tr>
        </table>

        <div style='clear: both; margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; text-align: center;'>
            Terima kasih telah berbelanja di Hablun CCTV. Faktur ini dicetak secara otomatis dan berlaku sebagai bukti transaksi sah.
        </div>
    </div>
</body>
</html>
";
    }

    public function download(Order $order): Response
    {
        $html = $this->renderHtml($order);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="invoice-' . $order->unique_order_code . '.html"',
        ]);
    }
}
