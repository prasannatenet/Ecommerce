{{-- Shared styles for the combo builder product picker + offer summary --}}
<style>
    .combo-product-list {
        max-height: 320px;
        overflow-y: auto;
        border: 1px solid var(--df-border, #e5e7eb);
        border-radius: 10px;
        background: #fff;
    }
    .combo-product-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 12px;
        border-bottom: 1px solid var(--df-border, #f1f5f9);
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .combo-product-item:last-child { border-bottom: none; }
    .combo-product-item:hover { background: var(--df-bg-secondary, #f8fafc); }
    .combo-product-item input[type="checkbox"] {
        width: 18px; height: 18px;
        accent-color: var(--df-primary);
        flex-shrink: 0;
    }
    .combo-product-item.selected { background: rgba(2, 170, 177, 0.07); }
    .cpi-thumb {
        width: 40px; height: 40px; border-radius: 8px;
        overflow: hidden; flex-shrink: 0;
        background: var(--df-bg-secondary, #f1f5f9);
        display: flex; align-items: center; justify-content: center;
        color: var(--df-text-secondary, #94a3b8);
    }
    .cpi-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .cpi-info { display: flex; flex-direction: column; min-width: 0; flex: 1; }
    .cpi-name {
        font-size: 0.88rem; font-weight: 600; color: var(--df-text, #0f172a);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .cpi-price { font-size: 0.8rem; color: var(--df-text-secondary, #64748b); font-weight: 700; }
    .cpi-check {
        color: var(--df-primary); font-size: 1.1rem;
        opacity: 0; transition: opacity 0.15s ease;
    }
    .combo-product-item.selected .cpi-check { opacity: 1; }

    .combo-summary {
        border: 2px dashed rgba(2, 170, 177, 0.45);
        border-radius: 12px;
        background: linear-gradient(135deg, rgba(2, 170, 177, 0.05), rgba(1, 112, 117, 0.05));
        padding: 14px 16px;
    }
    .combo-summary-title {
        font-size: 0.85rem; font-weight: 800; text-transform: uppercase;
        letter-spacing: 0.4px; color: #017075; margin-bottom: 8px;
    }
    .cs-products { font-size: 0.82rem; color: var(--df-text-secondary, #475569); line-height: 1.7; }
    .cs-product-chip {
        display: inline-flex; align-items: center; gap: 6px;
        background: #fff; border: 1px solid rgba(2, 170, 177, 0.35);
        border-radius: 999px; padding: 3px 10px; margin: 2px 6px 2px 0;
        font-size: 0.78rem; font-weight: 600; color: #013a3c;
        white-space: nowrap; max-width: 220px; overflow: hidden; text-overflow: ellipsis;
    }
    .cs-box {
        background: #fff; border: 1px solid var(--df-border, #e2e8f0);
        border-radius: 10px; padding: 8px 12px; text-align: center;
        height: 100%;
    }
    .cs-box-primary { border-color: rgba(2, 170, 177, 0.55); background: rgba(2, 170, 177, 0.07); }
    .cs-label { display: block; font-size: 0.72rem; font-weight: 700; color: var(--df-text-secondary, #64748b); text-transform: uppercase; letter-spacing: 0.3px; }
    .cs-total { display: block; font-size: 1rem; font-weight: 700; color: var(--df-text-secondary, #64748b); text-decoration: line-through; }
    .cs-discount { display: block; font-size: 1rem; font-weight: 800; color: #b91c1c; }
    .cs-price { display: block; font-size: 1.15rem; font-weight: 800; color: #017075; }
    .cs-save { display: block; font-size: 1rem; font-weight: 800; color: #15803d; }
    .cs-save-note { font-size: 0.78rem; color: var(--df-text-secondary, #64748b); margin-top: 8px; }
    .cs-save-note.ok { color: #15803d; font-weight: 600; }
    .cs-save-note.warn { color: #b45309; font-weight: 600; }
</style>
