<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;

class ReportService
{
    /**
     * @return array<string, float|int>
     */
    public function summary(string $dateFrom, string $dateTo): array
    {
        $sales = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sale_date', [$dateFrom, $dateTo]);

        $purchases = Purchase::query()
            ->where('status', Purchase::STATUS_RECEIVED)
            ->whereBetween('purchase_date', [$dateFrom, $dateTo]);

        $saleReturns = SaleReturn::query()
            ->where('status', SaleReturn::STATUS_COMPLETED)
            ->whereBetween('return_date', [$dateFrom, $dateTo]);

        $purchaseReturns = PurchaseReturn::query()
            ->where('status', PurchaseReturn::STATUS_COMPLETED)
            ->whereBetween('return_date', [$dateFrom, $dateTo]);

        $grossSales = (float) (clone $sales)->sum('grand_total');
        $salesReturns = (float) (clone $saleReturns)->sum('refund_amount');
        $netSales = max(0, $grossSales - $salesReturns);

        $grossPurchases = (float) (clone $purchases)->sum('grand_total');
        $purchaseReturnsAmount = (float) (clone $purchaseReturns)->sum('total_amount');
        $netPurchases = max(0, $grossPurchases - $purchaseReturnsAmount);

        $inventoryValue = (float) Inventory::query()
            ->selectRaw('COALESCE(SUM(quantity * average_cost), 0) AS value')
            ->value('value');

        return [
            'gross_sales' => $grossSales,
            'sale_returns' => $salesReturns,
            'net_sales' => $netSales,
            'gross_purchases' => $grossPurchases,
            'purchase_returns' => $purchaseReturnsAmount,
            'net_purchases' => $netPurchases,
            'gross_margin_estimate' => $netSales - $netPurchases,
            'customer_due' => (float) (clone $sales)->sum('due_amount'),
            'supplier_due' => (float) (clone $purchases)->sum('due_amount'),
            'inventory_value' => $inventoryValue,
            'sale_count' => (clone $sales)->count(),
            'purchase_count' => (clone $purchases)->count(),
        ];
    }
}
