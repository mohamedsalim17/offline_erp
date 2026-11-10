<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinancialReportsController extends Controller
{
    public function __invoke(Request $request, FinancialReportService $reports): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        if (filled($filters['from'] ?? null) && filled($filters['to'] ?? null) && $filters['from'] > $filters['to']) {
            throw ValidationException::withMessages([
                'from' => 'يجب أن يكون تاريخ البداية قبل أو مساوياً لتاريخ النهاية.',
            ]);
        }

        return view('finance.reports', [
            'filters' => $filters,
            'trialBalance' => $reports->trialBalance($filters),
            'incomeStatement' => $reports->incomeStatement($filters),
            'balanceSheet' => $reports->balanceSheet($filters),
            'treasuries' => $reports->treasuryBalances(),
            'stockRows' => $reports->stockValuation()->paginate(50)->withQueryString(),
            'stockValueTotal' => $reports->stockValueTotal(),
            'lowStockRows' => $reports->lowStockAlerts(),
        ]);
    }
}
