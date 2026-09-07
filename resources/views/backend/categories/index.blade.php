@extends('layouts.app')
@section('content')

@push('styles')
<style>
    .category-tree {
        display: grid;
        gap: 10px;
    }

    .category-node {
        overflow: hidden;
        border: 1px solid var(--df-border-color);
        border-radius: 10px;
        background: var(--df-bg-primary, #fff);
    }

    .category-node > summary {
        display: flex;
        align-items: center;
        gap: 12px;
        min-height: 76px;
        padding: 12px 16px;
        cursor: pointer;
        list-style: none;
    }

    .category-node > summary::-webkit-details-marker {
        display: none;
    }

    .category-node > summary::before {
        content: '\F282';
        flex: 0 0 18px;
        color: var(--df-text-secondary);
        font-family: bootstrap-icons;
        transition: transform 0.2s ease;
    }

    .category-node[open] > summary::before {
        transform: rotate(90deg);
    }

    .category-node.is-leaf > summary::before {
        content: '\F4FE';
        font-size: 0.7rem;
    }

    .category-tree-image {
        display: flex;
        flex: 0 0 52px;
        align-items: center;
        justify-content: center;
        width: 52px;
        height: 52px;
        overflow: hidden;
        border-radius: 8px;
        background: var(--df-bg-secondary);
    }

    .category-tree-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .category-tree-info {
        min-width: 0;
        flex: 1;
    }

    .category-tree-name {
        color: var(--df-text-primary);
        font-weight: 700;
    }

    .category-tree-meta {
        margin-top: 3px;
        color: var(--df-text-secondary);
        font-size: 0.78rem;
    }

    .category-tree-badges {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .category-tree-actions {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .category-children {
        display: grid;
        gap: 8px;
        margin: 0 16px 12px 50px;
        padding-left: 16px;
        border-left: 2px solid var(--df-border-color);
    }

    .category-children .category-node > summary {
        min-height: 64px;
        padding: 8px 12px;
    }

    @media (max-width: 767px) {
        .category-node > summary {
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .category-tree-badges {
            width: 100%;
            margin-left: 82px;
        }

        .category-tree-actions {
            margin-left: auto;
        }

        .category-children {
            margin-left: 20px;
            padding-left: 10px;
        }
    }
</style>
@endpush

<div class="df-page-header">
    <h1 class="df-page-title">All Categories</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Categories</span>
        </nav>
        <a href="{{ route('admin.categories.create') }}" class="df-btn df-btn-primary">
            <i class="bi bi-plus-lg"></i> Add Category
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
        <h5 class="df-card-title"><i class="bi bi-folder2-open"></i> Category Tree</h5>
        <span class="df-badge df-badge-muted">{{ $allCategories->count() }} categories</span>
    </div>
    <div class="df-card-body">
        @if($categories->isEmpty())
            <div class="df-empty-state">
                <div class="empty-icon"><i class="bi bi-folder2-open"></i></div>
                <p>No categories yet. <a href="{{ route('admin.categories.create') }}">Create your first category</a></p>
            </div>
        @else
            <div class="category-tree">
                @foreach($categories as $category)
                    @include('backend.categories.partials.tree-node', ['category' => $category, 'isRoot' => true])
                @endforeach
            </div>
        @endif
    </div>
</div>

@endsection