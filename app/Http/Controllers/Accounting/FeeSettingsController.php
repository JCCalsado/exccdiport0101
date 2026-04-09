<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\FeeSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FeeSettingsController extends Controller
{
    // Only accounting can access this controller — enforced via route middleware

    public function index()
    {
        $settings = FeeSetting::where('is_active', true)
            ->orderByRaw("FIELD(category, 'rate', 'miscellaneous', 'other', 'term')")
            ->orderBy('id')
            ->get()
            ->groupBy('category');

        $miscTotal = FeeSetting::whereIn('category', ['miscellaneous', 'other'])
            ->where('is_active', true)
            ->sum('amount');

        return Inertia::render('Accounting/FeeSettings', [
            'settings'  => $settings,
            'miscTotal' => round($miscTotal, 2),
        ]);
    }

    public function update(Request $request, FeeSetting $feeSetting)
    {
        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:0',
                'max:99999.99',
            ],
        ]);

        // Term percentages: validate sum = 100
        if ($feeSetting->category === 'term') {
            $this->validateTermPercentages($feeSetting->key, (float) $validated['amount']);
        }

        $feeSetting->update(['amount' => $validated['amount']]);

        return back()->with('success', "'{$feeSetting->label}' updated to ₱" . number_format($validated['amount'], 2) . ".");
    }

    /**
     * Bulk update — accounting can paste a new rate and it applies to all misc fees proportionally,
     * OR just save all fields at once from the form.
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'settings'         => 'required|array',
            'settings.*.id'    => 'required|integer|exists:fee_settings,id',
            'settings.*.amount'=> 'required|numeric|min:0|max:99999.99',
        ]);

        // Check term percentages sum to 100
        $termUpdates = collect($validated['settings'])->filter(function ($item) {
            $setting = FeeSetting::find($item['id']);
            return $setting && $setting->category === 'term';
        });

        if ($termUpdates->isNotEmpty()) {
            $newTermAmounts = [];
            foreach ($validated['settings'] as $item) {
                $s = FeeSetting::find($item['id']);
                if ($s && $s->category === 'term') {
                    $newTermAmounts[$s->key] = (float) $item['amount'];
                }
            }

            // Merge with existing term values
            $allTerms = FeeSetting::where('category', 'term')->get();
            $total = 0;
            foreach ($allTerms as $term) {
                $total += $newTermAmounts[$term->key] ?? (float) $term->amount;
            }

            if (abs($total - 100.00) > 0.01) {
                return back()->withErrors([
                    'terms' => "Payment term percentages must sum to 100%. Current total: {$total}%",
                ]);
            }
        }

        foreach ($validated['settings'] as $item) {
            FeeSetting::where('id', $item['id'])->update(['amount' => $item['amount']]);
        }

        return back()->with('success', 'Fee settings saved successfully.');
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function validateTermPercentages(string $updatedKey, float $newValue): void
    {
        $allTerms = FeeSetting::where('category', 'term')->get();
        $total = 0;
        foreach ($allTerms as $term) {
            $total += ($term->key === $updatedKey) ? $newValue : (float) $term->amount;
        }
        if (abs($total - 100.00) > 0.01) {
            abort(422, "Payment term percentages must sum to 100%. Current total: {$total}%");
        }
    }
}
