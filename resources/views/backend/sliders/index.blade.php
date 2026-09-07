@extends('layouts.app')
@section('content')

@push('styles')
<style>
    .slider-admin-intro {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 18px 20px;
        margin-bottom: 20px;
        background: linear-gradient(110deg, #fff8f1, #fff);
        border: 1px solid #f0dfcf;
        border-radius: 12px;
    }

    .slider-admin-intro h2 {
        margin: 0 0 4px;
        color: #2a2020;
        font-size: 1.05rem;
        font-weight: 700;
    }

    .slider-admin-intro p {
        margin: 0;
        color: #756a66;
        font-size: 0.86rem;
    }

    .slider-preview-frame {
        width: 112px;
        height: 52px;
        overflow: hidden;
        border: 1px solid var(--df-border-color);
        border-radius: 6px;
        background: var(--df-bg-secondary);
    }

    .slider-preview-frame img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .slider-row-title {
        min-width: 170px;
        color: var(--df-text-primary);
        font-weight: 700;
    }

    .slider-row-meta {
        margin-top: 4px;
        color: var(--df-text-secondary);
        font-size: 0.78rem;
    }

    .slider-drag-handle {
        color: var(--df-text-secondary);
        cursor: grab;
        font-size: 1.1rem;
    }

    @media (max-width: 767px) {
        .slider-admin-intro {
            align-items: flex-start;
            flex-direction: column;
        }

        .slider-preview-frame {
            width: 96px;
            height: 46px;
        }
    }
</style>
@endpush

<div class="df-page-header">
    <h1 class="df-page-title">Hero Banners</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Sliders</span>
        </nav>
        <a href="{{ route('admin.sliders.create') }}" class="df-btn df-btn-primary">
            <i class="bi bi-plus-lg"></i> Add Banner
        </a>
    </div>
</div>

@if(session('success'))
    <div class="df-alert df-alert-success"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
@endif

<div class="slider-admin-intro">
    <div>
        <h2>Build the homepage hero slider</h2>
        <p>Upload complete banner artwork at 1920x445px. Drag rows to set the display order.</p>
    </div>
    <span class="df-badge df-badge-info"><i class="bi bi-arrows-move"></i> Drag to reorder</span>
</div>

<div class="df-card">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-images"></i> Hero Banners</h5>
            <span class="df-badge df-badge-muted">{{ $sliders->count() }} {{ $sliders->count() === 1 ? 'banner' : 'banners' }}</span>
    </div>
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th>#</th>
                                <th style="width:170px;">Preview</th>
                                <th>Banner details</th>
                        <th>Subheading</th>
                        <th>Button</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th style="width:150px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="slider-sortable-body">
                    @forelse($sliders as $slider)
                        <tr draggable="true" data-slider-id="{{ $slider->id }}" class="slider-sort-row" style="cursor:grab;">
                            <td>
                                        <span class="slider-drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></span>
                            </td>
                            <td>{{ $slider->id }}</td>
                            <td>
                                @if($slider->image_path)
                                    <div class="slider-preview-frame">
                                        <img src="{{ asset('storage/' . $slider->image_path) }}" alt="{{ $slider->title }}">
                                    </div>
                                @else
                                    <div class="slider-preview-frame d-flex align-items-center justify-content-center">
                                        <i class="bi bi-image" style="color:var(--df-text-secondary);"></i>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="slider-row-title">{{ $slider->title }}</div>
                                <div class="slider-row-meta">ID #{{ $slider->id }} | {{ $slider->is_active ? 'Visible on homepage' : 'Hidden from homepage' }}</div>
                            </td>
                            <td style="color:var(--df-text-secondary);">{{ \Illuminate\Support\Str::limit($slider->subheading, 60) ?: '—' }}</td>
                            <td>
                                @if($slider->button_text)
                                    <div style="font-size:0.85rem; font-weight:600;">{{ $slider->button_text }}</div>
                                    <div style="font-size:0.78rem; color:var(--df-text-secondary);">{{ \Illuminate\Support\Str::limit($slider->button_link, 30) }}</div>
                                @else
                                    <span style="color:var(--df-text-secondary);">—</span>
                                @endif
                            </td>
                            <td><span class="df-badge df-badge-info">{{ $slider->sort_order }}</span></td>
                            <td>
                                @if($slider->is_active)
                                    <span class="df-badge df-badge-success">Active</span>
                                @else
                                    <span class="df-badge df-badge-muted">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="df-actions">
                                    <a href="{{ route('admin.sliders.edit', $slider) }}" class="df-action-btn warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.sliders.destroy', $slider) }}" style="display:inline" onsubmit="return confirm('Delete this slide?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="df-action-btn danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="df-empty-state">
                                    <div class="empty-icon"><i class="bi bi-images"></i></div>
                                    <p>No slides created yet. <a href="{{ route('admin.sliders.create') }}">Create your first slide</a></p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="slider-order-toast" style="position: fixed; right: 20px; bottom: 20px; z-index: 1080; display: none;">
    <div class="df-alert df-alert-success" style="margin: 0; min-width: 220px; box-shadow: 0 8px 24px rgba(0,0,0,0.2);">
        <i class="bi bi-check-circle-fill"></i> Order saved
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const tbody = document.getElementById('slider-sortable-body');
    if (!tbody) {
        return;
    }

    let draggedRow = null;
    let toastTimer = null;

    const getRows = () => Array.from(tbody.querySelectorAll('.slider-sort-row'));

    const showSavedToast = () => {
        const toast = document.getElementById('slider-order-toast');
        if (!toast) {
            return;
        }

        toast.style.display = 'block';

        if (toastTimer) {
            clearTimeout(toastTimer);
        }

        toastTimer = setTimeout(() => {
            toast.style.display = 'none';
        }, 1800);
    };

    const saveOrder = async () => {
        const orderedIds = getRows().map((row) => Number(row.dataset.sliderId));

        try {
            const response = await fetch('{{ route('admin.sliders.reorder') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ ordered_ids: orderedIds })
            });

            if (!response.ok) {
                throw new Error('Order update failed');
            }

            getRows().forEach((row, index) => {
                const badge = row.querySelector('.df-badge.df-badge-info');
                if (badge) {
                    badge.textContent = String(index);
                }
            });

            showSavedToast();
        } catch (error) {
            console.error('Failed to reorder sliders', error);
            window.location.reload();
        }
    };

    tbody.addEventListener('dragstart', (event) => {
        const row = event.target.closest('.slider-sort-row');
        if (!row) {
            return;
        }

        draggedRow = row;
        row.style.opacity = '0.5';
        event.dataTransfer.effectAllowed = 'move';
    });

    tbody.addEventListener('dragend', (event) => {
        const row = event.target.closest('.slider-sort-row');
        if (row) {
            row.style.opacity = '';
        }
    });

    tbody.addEventListener('dragover', (event) => {
        event.preventDefault();
        const row = event.target.closest('.slider-sort-row');

        if (!row || row === draggedRow) {
            return;
        }

        const rect = row.getBoundingClientRect();
        const shouldInsertBefore = event.clientY < rect.top + rect.height / 2;
        tbody.insertBefore(draggedRow, shouldInsertBefore ? row : row.nextSibling);
    });

    tbody.addEventListener('drop', async (event) => {
        event.preventDefault();
        if (!draggedRow) {
            return;
        }

        draggedRow.style.opacity = '';
        draggedRow = null;
        await saveOrder();
    });
})();
</script>
@endpush
