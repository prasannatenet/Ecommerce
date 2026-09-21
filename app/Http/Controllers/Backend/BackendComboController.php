<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BackendComboController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $combos = Combo::with('products.images')->orderByDesc('created_at')->paginate(15);

        return view('backend.combos.index', compact('combos'));
    }

    /**
     * Show the form for creating (one or many) combos.
     */
    public function create()
    {
        $products = $this->productOptions();

        return view('backend.combos.create', compact('products'));
    }

    /**
     * Store one or multiple newly created combos in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'combos'                    => 'required|array|min:1',
            'combos.*.name'             => 'required|string|max:255',
            'combos.*.description'      => 'nullable|string|max:2000',
            'combos.*.product_ids'      => 'required|array|min:2',
            'combos.*.product_ids.*'    => 'integer|exists:products,id',
            'combos.*.discount_type'    => 'required|in:percent,fixed',
            'combos.*.discount_value'   => 'required|numeric|min:0.01',
            'combos.*.starts_at'        => 'nullable|date',
            'combos.*.expires_at'       => 'nullable|date',
            'combos.*.is_active'        => 'nullable|boolean',
        ], [
            'combos.*.product_ids.min' => 'A combo needs at least 2 products selected together.',
            'combos.*.product_ids.required' => 'Please select at least 2 products for this combo.',
            'combos.*.discount_value.required' => 'Please enter a special discount for this combo.',
        ]);

        $this->assertComboBusinessRules($validated['combos']);

        $created = 0;
        DB::transaction(function () use ($validated, &$created) {
            foreach ($validated['combos'] as $index => $data) {
                $combo = Combo::create([
                    'name'           => trim($data['name']),
                    'description'    => $data['description'] ?? null,
                    'discount_type'  => $data['discount_type'],
                    'discount_value' => $data['discount_value'],
                    'starts_at'      => $data['starts_at'] ?? null,
                    'expires_at'     => $data['expires_at'] ?? null,
                    'is_active'      => (bool) ($data['is_active'] ?? false),
                ]);

                $combo->products()->sync($this->productSyncPayload($data['product_ids']));
                $created++;
            }
        });

        $message = $created === 1
            ? 'Combo created successfully.'
            : "{$created} combos created successfully.";

        return redirect()->route('admin.combos.index')->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(Combo $combo)
    {
        return redirect()->route('admin.combos.edit', $combo);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Combo $combo)
    {
        $combo->load('products.images');
        $products = $this->productOptions();

        return view('backend.combos.edit', compact('combo', 'products'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Combo $combo)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string|max:2000',
            'product_ids'      => 'required|array|min:2',
            'product_ids.*'    => 'integer|exists:products,id',
            'discount_type'    => 'required|in:percent,fixed',
            'discount_value'   => 'required|numeric|min:0.01',
            'starts_at'        => 'nullable|date',
            'expires_at'       => 'nullable|date',
        ], [
            'product_ids.min'         => 'A combo needs at least 2 products selected together.',
            'product_ids.required'    => 'Please select at least 2 products for this combo.',
            'discount_value.required' => 'Please enter a special discount for this combo.',
        ]);

        $this->assertSingleComboBusinessRules($validated);

        $combo->update([
            'name'           => trim($validated['name']),
            'description'    => $validated['description'] ?? null,
            'discount_type'  => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'starts_at'      => $validated['starts_at'] ?? null,
            'expires_at'     => $validated['expires_at'] ?? null,
        ]);

        $combo->products()->sync($this->productSyncPayload($validated['product_ids']));

        return redirect()->route('admin.combos.index')->with('success', 'Combo updated successfully.');
    }

    /**
     * Toggle the active state of the combo.
     */
    public function toggle(Combo $combo)
    {
        $combo->update(['is_active' => ! $combo->is_active]);

        return back()->with('success', $combo->is_active ? 'Combo activated.' : 'Combo deactivated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Combo $combo)
    {
        $combo->delete();

        return redirect()->route('admin.combos.index')->with('success', 'Combo deleted successfully.');
    }

    /* ───── Private helpers ───── */

    /** Products available for combo selection (active only). */
    private function productOptions()
    {
        $products = Product::where('is_active', true)
            ->with(['images', 'variations'])
            ->orderBy('name')
            ->get(['id', 'name', 'base_price', 'sale_price', 'primary_image', 'product_type']);

        // Pre-compute the effective display price from eager-loaded data so the
        // picker does not trigger an extra query per variable product.
        $products->each(function ($product) {
            $product->picker_price = $product->isSimple()
                ? ($product->sale_price ?? $product->base_price)
                : ($product->variations->where('is_active', true)->min('price') ?? $product->base_price);
        });

        return $products;
    }

    /** Build the pivot payload keeping the admin's selection order. */
    private function productSyncPayload(array $productIds): array
    {
        $payload = [];
        foreach (array_values(array_unique(array_map('intval', $productIds))) as $index => $id) {
            $payload[$id] = ['sort_order' => $index];
        }

        return $payload;
    }

    /** Business rules for the multi-combo create payload. */
    private function assertComboBusinessRules(array $combos): void
    {
        $messages = [];

        foreach ($combos as $index => $data) {
            $messages = array_merge($messages, $this->businessRuleErrors($index, $data));
        }

        if (! empty($messages)) {
            throw ValidationException::withMessages($messages);
        }
    }

    /** Business rules for the single-combo update payload. */
    private function assertSingleComboBusinessRules(array $data): void
    {
        $messages = $this->businessRuleErrors(null, $data, true);

        if (! empty($messages)) {
            throw ValidationException::withMessages($messages);
        }
    }

    /**
     * Shared discount / schedule checks. Returns error messages keyed by field.
     * When $flat is true the keys use flat field names (edit form) instead of
     * the combos.* array names (multi-create form).
     */
    private function businessRuleErrors($index, array $data, bool $flat = false): array
    {
        $prefix = $flat ? '' : "combos.{$index}.";
        $products = Product::whereIn('id', (array) ($data['product_ids'] ?? []))->get();
        $total = round((float) $products->sum(fn ($p) => (float) $p->display_price), 2);
        $discountValue = (float) ($data['discount_value'] ?? 0);
        $errors = [];
        $label = $data['name'] ?? "Combo #" . ((int) $index + 1);

        if (($data['discount_type'] ?? '') === 'percent' && $discountValue > 100) {
            $errors[$prefix . 'discount_value'] = "\"{$label}\": percent discount cannot be more than 100%.";
        }

        if (($data['discount_type'] ?? '') === 'fixed' && $total > 0 && $discountValue >= $total) {
            $errors[$prefix . 'discount_value'] = "\"{$label}\": fixed discount ₹" . number_format($discountValue, 2)
                . " cannot be equal to or more than the combined product price ₹" . number_format($total, 2) . '.';
        }

        $startsAt = $data['starts_at'] ?? null;
        $expiresAt = $data['expires_at'] ?? null;
        if ($startsAt && $expiresAt && strtotime($expiresAt) < strtotime($startsAt)) {
            $errors[$prefix . 'expires_at'] = "\"{$label}\": the expiry date must be after the start date.";
        }

        return $errors;
    }
}
