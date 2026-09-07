@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Pages</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Pages</span>
        </nav>
        <a href="{{ route('admin.pages.create') }}" class="df-btn df-btn-primary">
            <i class="bi bi-plus-lg"></i> Add Page
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
        <h5 class="df-card-title"><i class="bi bi-file-earmark-text"></i> All Pages</h5>
        <span class="df-badge df-badge-muted">{{ $pages->total() }} pages</span>
    </div>
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th style="width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pages as $page)
                        <tr>
                            <td>{{ $page->id }}</td>
                            <td><span style="font-weight:600;">{{ $page->title }}</span></td>
                            <td><code style="font-size:0.82rem; color:var(--df-text-secondary);">{{ $page->slug }}</code></td>
                            <td>
                                @if($page->is_active)
                                    <span class="df-badge df-badge-success">Active</span>
                                @else
                                    <span class="df-badge df-badge-danger">Draft</span>
                                @endif
                            </td>
                            <td style="color:var(--df-text-secondary); font-size:0.85rem;">{{ $page->updated_at->format('M d, Y') }}</td>
                            <td>
                                <div class="df-actions">
                                    <a href="{{ route('admin.pages.edit', $page) }}" class="df-action-btn warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('admin.pages.destroy', $page) }}" method="POST"
                                          style="display:inline" onsubmit="return confirm('Delete this page?');">
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
                            <td colspan="6">
                                <div class="df-empty-state">
                                    <div class="empty-icon"><i class="bi bi-file-earmark-text"></i></div>
                                    <p>No pages created yet. <a href="{{ route('admin.pages.create') }}">Create your first page</a></p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($pages->hasPages())
    <div class="mt-3">{{ $pages->links() }}</div>
@endif

@endsection
