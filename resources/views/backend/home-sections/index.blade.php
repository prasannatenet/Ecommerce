@extends('layouts.app')
@section('content')

@push('styles')
<style>
    .home-section-admin-intro {
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
    .home-section-admin-intro h2 {
        margin: 0 0 4px;
        color: #2a2020;
        font-size: 1.05rem;
        font-weight: 700;
    }
    .home-section-admin-intro p {
        margin: 0;
        color: #756a66;
        font-size: 0.86rem;
    }
    .home-section-drag-handle {
        cursor: grab;
        color: var(--df-text-secondary);
        font-size: 1.1rem;
    }
    .home-section-row-label {
        font-weight: 600;
        color: var(--df-text-primary);
    }
    .home-section-row-meta {
        font-size: 0.78rem;
        color: var(--df-text-secondary);
        margin-top: 2px;
    }
    .home-section-preview-frame {
        width: 160px;
        height: 90px;
        border-radius: 6px;
        overflow: hidden;
        border: 1px solid var(--df-border-color);
        background: var(--df-bg-secondary);
    }
    .home-section-preview-frame img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .home-section-preview-frame.video-preview {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #2a2020;
        color: #fff;
    }
    .home-section-preview-frame .icon-stack {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
    }
    .home-section-details-list {
        margin: 0;
        padding: 0;
        list-style: none;
        font-size: 0.82rem;
        color: var(--df-text-secondary);
    }
    .home-section-details-list li {
        margin-bottom: 2px;
    }
    .home-section-details-list li strong {
        color: var(--df-text-primary);
    }
    .home-section-order-toast {
        display: none;
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: #2a2020;
        color: #fff;
        padding: 10px 18px;
        border-radius: 8px;
        font-size: 0.85rem;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
</style>
@endpush

<div class="df-page-header">
    <h1 class="df-page-title">Home Sections</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Home Sections</span>
    </nav>
</div>

@if(session('success'))
    <div class="df-alert df-alert-success">
        <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="df-alert df-alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}
    </div>
@endif

<div class="home-section-admin-intro">
    <div>
        <h2>Manage Homepage Middle Sections</h2>
        <p>Edit the featured promo video and poster, and the large image section. Drag rows to reorder.</p>
    </div>
</div>

<div class="df-card">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-collection"></i> Sections</h5>
    </div>
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th>Section</th>
                        <th style="width:180px;">Preview</th>
                        <th>Details</th>
                        <th style="width:100px;">Status</th>
                        <th style="width:150px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="home-section-sortable-body">
                    @forelse($sections as $section)
                        <tr draggable="true" data-home-section-id="{{ $section->id }}" class="home-section-sort-row" style="cursor:grab;">
                            <td>
                                <span class="home-section-drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></span>
                            </td>
                            <td>
                                <div class="home-section-row-label">{{ $section->isFeaturedSection() ? 'Featured Promo' : 'Large Image Section' }}</div>
                                <div class="home-section-row-meta">ID #{{ $section->id }} | Section key: <code>{{ $section->section_key }}</code></div>
                            </td>
                            <td>
                                @if($section->section_key === 'featured')
                                    @if($section->video_path)
                                        <div class="home-section-preview-frame video-preview" title="Video uploaded">
                                            <div class="icon-stack">
                                                <i class="bi bi-play-circle-fill" style="font-size:1.1rem;"></i>
                                                <span style="font-size:0.75rem;">video</span>
                                            </div>
                                        </div>
                                    @elseif($section->poster_image_path)
                                        <div class="home-section-preview-frame">
                                            <img src="{{ asset('storage/' . $section->poster_image_path) }}" alt="Poster">
                                        </div>
                                    @else
                                        <div class="home-section-preview-frame d-flex align-items-center justify-content-center">
                                            <i class="bi bi-film" style="color:var(--df-text-secondary);"></i>
                                        </div>
                                    @endif
                                @else
                                    @if($section->large_image_path)
                                        <div class="home-section-preview-frame">
                                            <img src="{{ asset('storage/' . $section->large_image_path) }}" alt="Large image">
                                        </div>
                                    @else
                                        <div class="home-section-preview-frame d-flex align-items-center justify-content-center">
                                            <i class="bi bi-image" style="color:var(--df-text-secondary);"></i>
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <ul class="home-section-details-list">
                                    @if($section->title)
                                        <li><strong>Title:</strong> {{ $section->title }}</li>
                                    @endif
                                    @if($section->subtext)
                                        <li><strong>Subtext:</strong> {{ Str::limit($section->subtext, 60) }}</li>
                                    @endif
                                    @if($section->cta_text)
                                        <li><strong>CTA:</strong> {{ $section->cta_text }}</li>
                                    @endif
                                    @if($section->cta_link)
                                        <li><strong>Link:</strong> {{ $section->cta_link }}</li>
                                    @endif
                                    @if($section->video_path)
                                        <li><strong>Video:</strong> {{ basename($section->video_path) }}</li>
                                    @endif
                                    @if($section->poster_image_path)
                                        <li><strong>Poster:</strong> {{ basename($section->poster_image_path) }}</li>
                                    @endif
                                    @if($section->large_image_path)
                                        <li><strong>Large image:</strong> {{ basename($section->large_image_path) }}</li>
                                    @endif
                                </ul>
                            </td>
                            <td>
                                @if($section->is_active)
                                    <span class="df-badge df-badge-success">Active</span>
                                @else
                                    <span class="df-badge df-badge-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.home-sections.edit', $section) }}" class="df-btn df-btn-sm df-btn-outline">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <i class="bi bi-inbox" style="font-size:1.4rem; opacity:0.5;"></i>
                                <div class="mt-2">No home sections found. Run <code>php artisan db:seed --class=HomeSectionsSeeder</code> to create them.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="home-section-order-toast" class="home-section-order-toast">
    <i class="bi bi-check-circle-fill"></i> Order saved!
</div>

@push('scripts')
<script>
(function () {
    const tbody = document.getElementById('home-section-sortable-body');
    if (!tbody) {
        return;
    }

    let draggedRow = null;
    let toastTimer = null;

    const getRows = () => Array.from(tbody.querySelectorAll('.home-section-sort-row'));

    const showSavedToast = () => {
        const toast = document.getElementById('home-section-order-toast');
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
        const orderedIds = getRows().map((row) => Number(row.dataset.homeSectionId));

        try {
            const response = await fetch('{{ route('admin.home-sections.reorder') }}', {
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

            showSavedToast();
        } catch (error) {
            console.error('Failed to reorder home sections', error);
            window.location.reload();
        }
    };

    tbody.addEventListener('dragstart', (event) => {
        const row = event.target.closest('.home-section-sort-row');
        if (!row) {
            return;
        }

        draggedRow = row;
        row.style.opacity = '0.5';
        event.dataTransfer.effectAllowed = 'move';
    });

    tbody.addEventListener('dragend', (event) => {
        const row = event.target.closest('.home-section-sort-row');
        if (row) {
            row.style.opacity = '';
        }
        if (draggedRow) {
            saveOrder();
        }
        draggedRow = null;
    });

    tbody.addEventListener('dragover', (event) => {
        event.preventDefault();
        const row = event.target.closest('.home-section-sort-row');

        if (!row || row === draggedRow) {
            return;
        }

        const rect = row.getBoundingClientRect();
        const shouldInsertBefore = event.clientY < rect.top + rect.height / 2;
        tbody.insertBefore(draggedRow, shouldInsertBefore ? row : row.nextSibling);
    });

    tbody.addEventListener('drop', (event) => {
        event.preventDefault();
    });
});
</script>
@endpush

@endsection
