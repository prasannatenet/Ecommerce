@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Tags</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Tags</span>
        </nav>
        <a href="{{ route('admin.tags.create') }}" class="df-btn df-btn-primary">
            <i class="bi bi-plus-lg"></i> Add New Tag
        </a>
    </div>
</div>

@if(session('success'))
    <div class="df-alert df-alert-success">
        <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
@endif

<div class="df-card">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-bookmark-star"></i> All Tags</h5>
        <span class="df-badge df-badge-muted">{{ $tags->total() }} tags</span>
    </div>
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Description</th>
                        <th>Products</th>
                        <th>Created</th>
                        <th style="width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tags as $tag)
                        <tr>
                            <td>{{ $tag->id }}</td>
                            <td><span style="font-weight:600;">{{ $tag->name }}</span></td>
                            <td><code style="font-size:0.82rem; color:var(--df-text-secondary);">{{ $tag->slug }}</code></td>
                            <td style="color:var(--df-text-secondary);">{{ Str::limit($tag->description, 40) ?: '—' }}</td>
                            <td><span class="df-badge df-badge-info">{{ $tag->products_count }}</span></td>
                            <td style="color:var(--df-text-secondary); font-size:0.85rem;">{{ $tag->created_at->format('M d, Y') }}</td>
                            <td>
                                <div class="df-actions">
                                    <a href="{{ route('admin.tags.edit', $tag) }}" class="df-action-btn warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.tags.destroy', $tag) }}"
                                          style="display:inline" onsubmit="return confirm('Delete this tag?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="df-action-btn danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="df-empty-state">
                                    <div class="empty-icon"><i class="bi bi-bookmark-star"></i></div>
                                    <p>No tags created yet. <a href="{{ route('admin.tags.create') }}">Create your first tag</a></p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $tags->links() }}</div>

@endsection
