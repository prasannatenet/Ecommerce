{{--
    Cart Combo Suggestions Partial
    ─────────────────────────────
    $comboSuggestions = [
        'incomplete' => [ ...suggestions where some products are missing ],
        'complete'   => [ ...combos where ALL products are already in the cart ],
    ]

    • incomplete → "Add [Product C] to get X% off the total"
    • complete   → "🎉 Combo Unlocked! Here's your discounted price"
--}}

{{-- ══════════════════════════════════════════════════════════
     COMPLETE COMBOS — all products are in the cart
     Show a green "Combo Unlocked!" celebration banner
     ══════════════════════════════════════════════════════════ --}}
@if(!empty($comboSuggestions['complete']))
    @foreach($comboSuggestions['complete'] as $completed)
        @php $combo = $completed['combo']; @endphp
        <div class="combo-unlocked-banner mb-3"
             style="border: 2px solid #16a34a; border-radius: 14px; overflow: hidden; background: #f0fdf4;">
            {{-- Banner header --}}
            <div class="d-flex align-items-center gap-2 px-3 py-2"
                 style="background: linear-gradient(90deg, #15803d, #16a34a);">
                <span style="font-size: 1.25rem;">🎉</span>
                <span class="fw-bold text-white" style="font-size: 0.95rem;">
                    Combo Unlocked — {{ $combo->name }}!
                </span>
                <span class="ms-auto badge"
                      style="background: rgba(255,255,255,0.25); color: #fff; font-size: 0.72rem; font-weight: 700;">
                    {{ $completed['savings_percent'] }}% OFF
                </span>
            </div>

            <div class="px-3 py-3">
                {{-- Products included --}}
                <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                    @foreach($completed['in_cart'] as $cp)
                        <span class="badge"
                              style="background: #dcfce7; color: #15803d; border: 1px solid #86efac;
                                     font-size: 0.78rem; font-weight: 600; padding: 5px 10px; border-radius: 999px;">
                            <i class="bi bi-check-circle-fill me-1"></i>{{ $cp->name }}
                        </span>
                    @endforeach
                </div>

                {{-- Price breakdown --}}
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <div style="font-size: 0.82rem; color: #6b7280;">Combined regular price:</div>
                        <div class="d-flex align-items-center gap-2">
                            <s class="text-muted fw-semibold" style="font-size: 0.92rem;">
                                ₹{{ number_format($completed['original_total'], 2) }}
                            </s>
                            <span class="fw-bold" style="font-size: 1.1rem; color: #15803d;">
                                ₹{{ number_format($completed['combo_price'], 2) }}
                            </span>
                            <span class="badge"
                                  style="background: #16a34a; color: #fff; font-size: 0.72rem; padding: 4px 8px; border-radius: 999px;">
                                You save ₹{{ number_format($completed['discount_amount'], 2) }}
                            </span>
                        </div>
                    </div>
                    <div style="font-size: 0.75rem; color: #6b7280; font-style: italic;">
                        Combo price already applied to your order summary
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endif

{{-- ══════════════════════════════════════════════════════════
     INCOMPLETE COMBOS — some products are missing from cart
     Show suggestion cards for the missing product(s)
     ══════════════════════════════════════════════════════════ --}}
@if(!empty($comboSuggestions['incomplete']))
    <div class="mb-3">
        <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-lightbulb-fill" style="color: #f59e0b; font-size: 1rem;"></i>
            <span class="fw-bold" style="font-size: 0.88rem; color: #92400e;">
                Add more items to unlock combo savings!
            </span>
        </div>

        @foreach($comboSuggestions['incomplete'] as $suggestion)
            @php
                $combo           = $suggestion['combo'];
                $missingProducts = $suggestion['missing'];
            @endphp
            <div class="combo-suggest-card mb-3"
                 style="border: 1.5px solid #fde68a; border-radius: 14px;
                        background: linear-gradient(135deg, #fffbeb 0%, #fff 100%); overflow: hidden;">

                {{-- Header --}}
                <div class="d-flex align-items-center gap-2 px-3 py-2"
                     style="background: linear-gradient(90deg, #b45309, #d97706); border-radius: 12px 12px 0 0;">
                    <i class="bi bi-gift text-white" style="font-size: 0.9rem;"></i>
                    <span class="fw-bold text-white" style="font-size: 0.88rem;">{{ $combo->name }}</span>
                    <span class="ms-auto badge"
                          style="background: rgba(255,255,255,0.2); color: #fff; font-size: 0.7rem; font-weight: 600;">
                        @if($suggestion['is_restorable'])
                            Save {{ $suggestion['savings_percent'] }}% when restored
                        @else
                            Save {{ $suggestion['savings_percent'] }}% when complete
                        @endif
                    </span>
                </div>

                <div class="px-3 py-3">
                    {{-- Hint text --}}
                    @if($suggestion['is_restorable'])
                        <p class="mb-3" style="font-size: 0.82rem; color: #78350f;">
                            <i class="bi bi-exclamation-triangle-fill me-1" style="color: #d97706;"></i>
                            You removed an item from <strong>{{ $combo->name }}</strong>, so the combo
                            price no longer applies and your remaining items are back at their
                            regular price.
                        </p>

                        {{--
                            One click puts the removed item back and re-arms the whole combo.
                            Adding the product on its own from the product page would create a
                            plain cart line, which the combo engine ignores, so the discount
                            would never come back.
                        --}}
                        <form action="{{ route('cart.restore-combo') }}" method="POST"
                              class="combo-suggest-form mb-3">
                            @csrf
                            <input type="hidden" name="combo_id" value="{{ $combo->id }}">
                            <button type="submit"
                                    class="btn btn-sm fw-bold"
                                    style="background: linear-gradient(135deg, #b45309, #d97706);
                                           color: #fff; border: none; border-radius: 999px;
                                           padding: 7px 16px; font-size: 0.8rem;">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>
                                Restore combo &amp; save ₹{{ number_format($suggestion['discount_amount'], 2) }}
                            </button>
                        </form>
                    @else
                        <p class="mb-3" style="font-size: 0.82rem; color: #78350f;">
                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                            <strong>{{ $suggestion['in_cart']->count() }}</strong>
                            item(s) already in your cart. Add the item(s) below to unlock
                            <strong>₹{{ number_format($suggestion['discount_amount'], 2) }}</strong> combo discount!
                        </p>
                    @endif

                    {{-- Missing products — show original price ONLY (no combo price) --}}
                    <div class="d-flex flex-column gap-2">
                        @foreach($missingProducts as $missingProduct)
                            @php
                                $originalPrice = $missingProduct->display_price;
                                $imageUrl = $missingProduct->images->isNotEmpty()
                                    ? asset('storage/' . $missingProduct->images->first()->image_path)
                                    : asset('images/no-image.png');
                            @endphp
                            <div class="d-flex align-items-center gap-3 p-2"
                                 style="background: #fff; border-radius: 10px; border: 1px solid #fde68a;">
                                {{-- Thumbnail --}}
                                <a href="{{ route('product.show', $missingProduct->slug) }}" class="flex-shrink-0">
                                    <img src="{{ $imageUrl }}"
                                         alt="{{ $missingProduct->name }}"
                                         style="width: 54px; height: 54px; object-fit: cover;
                                                border-radius: 8px; border: 1px solid #fde68a;">
                                </a>

                                {{-- Info --}}
                                <div class="flex-grow-1 min-w-0">
                                    <a href="{{ route('product.show', $missingProduct->slug) }}"
                                       class="text-decoration-none d-block fw-semibold text-truncate"
                                       style="color: #013a3c; font-size: 0.86rem;">
                                        {{ $missingProduct->name }}
                                    </a>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        {{-- Original price only — combo price NOT shown until combo is complete --}}
                                        <span class="fw-bold" style="color: #013a3c; font-size: 0.9rem;">
                                            ₹{{ number_format($originalPrice, 2) }}
                                        </span>
                                        @if($missingProduct->is_on_sale)
                                            <s class="text-muted" style="font-size: 0.76rem;">
                                                ₹{{ number_format($missingProduct->base_price, 2) }}
                                            </s>
                                        @endif
                                    </div>
                                </div>

                                {{-- Add button (goes to product page) --}}
                                <a href="{{ route('product.show', $missingProduct->slug) }}"
                                   class="btn btn-sm flex-shrink-0"
                                   style="background: linear-gradient(135deg, #b45309, #d97706);
                                          color: #fff; border: none; font-weight: 700;
                                          border-radius: 999px; font-size: 0.76rem;
                                          padding: 6px 14px; white-space: nowrap;">
                                    <i class="bi bi-bag-plus me-1"></i>Add
                                </a>
                            </div>
                        @endforeach
                    </div>

                    {{-- Savings teaser --}}
                    <div class="mt-3 d-flex align-items-center gap-2"
                         style="font-size: 0.76rem; color: #92400e; background: #fef3c7;
                                border-radius: 8px; padding: 6px 10px; border: 1px solid #fde68a;">
                        <i class="bi bi-lightning-charge-fill" style="color: #d97706;"></i>
                        Complete this combo &amp; save
                        <strong>₹{{ number_format($suggestion['discount_amount'], 2) }}</strong>
                        ({{ $suggestion['savings_percent'] }}% off the total)!
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
