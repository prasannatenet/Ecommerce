@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Attributes</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Attributes</span>
        </nav>
        <a href="{{ route('admin.attributes.create') }}" class="df-btn df-btn-primary">
            <i class="bi bi-plus-lg"></i> Add Attribute
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
        <h5 class="df-card-title"><i class="bi bi-tags"></i> All Attributes</h5>
        <span class="df-badge df-badge-muted">{{ $attributes->total() }} attributes</span>
    </div>
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Values</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th style="width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attributes as $attribute)
                        <tr>
                            <td>{{ $attribute->id }}</td>
                            <td><span style="font-weight:600;">{{ $attribute->name }}</span></td>
                            <td><code style="font-size:0.82rem; color:var(--df-text-secondary);">{{ $attribute->slug }}</code></td>
                            <td>
                                <span class="df-badge df-badge-info">{{ $attribute->values_count }}</span>
                            </td>
                            <td>
                                @if($attribute->is_active)
                                    <span class="df-badge df-badge-success">Active</span>
                                @else
                                    <span class="df-badge df-badge-danger">Inactive</span>
                                @endif
                            </td>
                            <td style="color:var(--df-text-secondary); font-size:0.85rem;">{{ $attribute->created_at->format('M d, Y') }}</td>
                            <td>
                                <div class="df-actions">
                                    <a href="{{ route('admin.attributes.edit', $attribute) }}" class="df-action-btn warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.attributes.destroy', $attribute) }}"
                                          style="display:inline" onsubmit="return confirm('Delete this attribute and all its values?')">
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
                                    <div class="empty-icon"><i class="bi bi-tags"></i></div>
                                    <p>No attributes created yet. <a href="{{ route('admin.attributes.create') }}">Create your first attribute</a></p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $attributes->links() }}</div>

@endsection
