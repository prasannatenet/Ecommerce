{{-- Shared JS for the combo product picker + live offer summary --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        'use strict';

        var money = function (value) {
            return '₹' + Number(value || 0).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };

        var trim = function (name, max) {
            return name.length > max ? name.slice(0, max - 1) + '…' : name;
        };

        // ── Live offer summary below the selected products ──
        function updateSummary(block) {
            var checked = Array.prototype.slice.call(block.querySelectorAll('.combo-product-item input:checked'));
            var countBadge = block.querySelector('.combo-selected-count');
            var note = block.querySelector('.cs-save-note');
            var total = 0;

            checked.forEach(function (input) {
                var item = input.closest('.combo-product-item');
                total += parseFloat(item.getAttribute('data-price')) || 0;
            });
            total = Math.round(total * 100) / 100;

            var typeEl = block.querySelector('.combo-discount-type');
            var valueEl = block.querySelector('.combo-discount-value');
            var suffix = block.querySelector('.combo-discount-suffix');
            var type = typeEl ? typeEl.value : 'percent';
            var value = parseFloat(valueEl && valueEl.value ? valueEl.value : 0) || 0;

            if (suffix) suffix.textContent = type === 'percent' ? '%' : '₹';

            var discount = type === 'percent' ? total * value / 100 : value;
            if (discount > total) discount = total;
            if (discount < 0) discount = 0;
            discount = Math.round(discount * 100) / 100;

            var comboPrice = Math.round((total - discount) * 100) / 100;
            var savePct = total > 0 ? Math.round((discount / total) * 1000) / 10 : 0;

            if (countBadge) {
                countBadge.textContent = checked.length + ' selected';
                countBadge.classList.toggle('df-badge-info', checked.length >= 2);
                countBadge.classList.toggle('df-badge-danger', checked.length < 2);
            }

            var productsEl = block.querySelector('.cs-products');
            if (productsEl) {
                if (checked.length === 0) {
                    productsEl.textContent = 'No products selected yet.';
                } else {
                    productsEl.innerHTML = checked.map(function (input) {
                        var name = input.closest('.combo-product-item').querySelector('.cpi-name').textContent;
                        return '<span class="cs-product-chip" title="' + name + '"><i class="bi bi-box-seam"></i>' + trim(name, 30) + '</span>';
                    }).join('');
                }
            }

            var setText = function (selector, text) {
                var el = block.querySelector(selector);
                if (el) el.textContent = text;
            };
            setText('.cs-total', money(total));
            setText('.cs-discount', '- ' + money(discount));
            setText('.cs-price', money(comboPrice));
            setText('.cs-save', money(discount) + ' (' + savePct + '%)');

            if (note) {
                if (checked.length < 2) {
                    note.textContent = 'Select at least 2 products to activate this combo offer.';
                    note.className = 'cs-save-note warn';
                } else if (value <= 0) {
                    note.textContent = 'Add a special discount to complete the combo offer.';
                    note.className = 'cs-save-note';
                } else {
                    note.textContent = 'This combo offer will be visible below all ' + checked.length + ' selected products.';
                    note.className = 'cs-save-note ok';
                }
            }
        }

        // ── Product search filter ──
        document.addEventListener('input', function (e) {
            if (!e.target.classList.contains('combo-product-search')) return;
            var block = e.target.closest('.combo-block');
            if (!block) return;
            var term = e.target.value.trim().toLowerCase();
            block.querySelectorAll('.combo-product-item').forEach(function (item) {
                var name = item.getAttribute('data-name') || '';
                item.style.display = (!term || name.indexOf(term) !== -1) ? '' : 'none';
            });
        });

        // ── Selection / discount changes refresh the summary ──
        document.addEventListener('change', function (e) {
            var block = e.target.closest('.combo-block');
            if (!block) return;
            if (e.target.matches('.combo-product-item input, .combo-discount-type')) {
                updateSummary(block);
            }
        });
        document.addEventListener('input', function (e) {
            var block = e.target.closest('.combo-block');
            if (!block) return;
            if (e.target.classList.contains('combo-discount-value')) {
                updateSummary(block);
            }
        });

        // ── Highlight selection state ──
        document.addEventListener('change', function (e) {
            if (e.target.matches('.combo-product-item input')) {
                e.target.closest('.combo-product-item').classList.toggle('selected', e.target.checked);
            }
        });

        window.updateComboSummary = updateSummary;
        window.refreshAllComboSummaries = function () {
            document.querySelectorAll('.combo-block').forEach(updateSummary);
        };
        window.refreshAllComboSummaries();
    });
</script>
