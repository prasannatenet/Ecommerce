{{--
    Applied Combo Price Partial
    ───────────────────────────
    $comboSummary = App\Services\ComboService::summarize($cartItems)

    Rendered under the order summary total whenever every product of an active
    combo is in the cart, so the customer can see the combo price they are
    actually paying (instead of the sum of the individual product prices).
--}}
@if(!empty($comboSummary['combos']))
    <div class="mt-2" id="cart-combo-price-note"
         style="background:#f0fdf4; border:1px solid #86efac; border-radius:10px; padding:8px 10px;">
        @foreach($comboSummary['combos'] as $appliedCombo)
            <div class="d-flex justify-content-between flex-wrap" style="gap:2px 8px; {{ $loop->last ? '' : 'margin-bottom:4px;' }}">
                <span class="fw-semibold" style="font-size:0.78rem; color:#15803d;">
                    <i class="bi bi-gift-fill me-1"></i>{{ $appliedCombo['combo']->name }}@if((int) $appliedCombo['sets'] > 1) × {{ (int) $appliedCombo['sets'] }}@endif
                </span>
                <span style="font-size:0.78rem; color:#15803d;">
                    <s class="text-muted" style="font-size:0.72rem;">₹{{ number_format($appliedCombo['regular_total'], 2) }}</s>
                    <strong>₹{{ number_format($appliedCombo['combo_price'], 2) }}</strong>
                </span>
            </div>
        @endforeach
        <div style="font-size:0.72rem; color:#15803d; margin-top:4px;">
            Combo price applied automatically — you save
            <strong>₹{{ number_format($comboSummary['discount'], 2) }}</strong>
            on this order.
        </div>
    </div>
@endif
