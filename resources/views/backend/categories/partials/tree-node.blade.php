@php
    $children = $category->childrenRecursive ?? collect();
    $hasChildren = $children->isNotEmpty();
@endphp

<details class="category-node {{ $hasChildren ? '' : 'is-leaf' }}" {{ ($isRoot ?? false) ? 'open' : '' }}>
    <summary>
        <span class="category-tree-image">
            @if($category->image)
                <img src="{{ asset('storage/' . $category->image) }}" alt="{{ $category->name }}">
            @else
                <i class="bi bi-folder2" style="color:var(--df-text-secondary);"></i>
            @endif
        </span>

        <span class="category-tree-info">
            <span class="category-tree-name">{{ $category->name }}</span>
            <span class="category-tree-meta">
                {{ $category->isRoot() ? 'Parent category' : 'Sub-category' }}
                | {{ $category->slug }}
            </span>
        </span>

        <span class="category-tree-badges">
            <span class="df-badge df-badge-primary">
                {{ $category->products_count ?? $category->products->count() }} products
            </span>
            @if($hasChildren)
                <span class="df-badge df-badge-muted">
                    {{ $children->count() }} sub-categories
                </span>
            @endif
        </span>

        <span class="category-tree-actions" onclick="event.stopPropagation()">
            <a href="{{ route('admin.categories.show', $category) }}" class="df-action-btn info" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <a href="{{ route('admin.categories.edit', $category) }}" class="df-action-btn warning" title="Edit">
                <i class="bi bi-pencil"></i>
            </a>
            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" style="display:inline" onsubmit="return confirm('Delete this category?')">
                @csrf
                @method('DELETE')
                <button class="df-action-btn danger" title="Delete">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        </span>
    </summary>

    @if($hasChildren)
        <div class="category-children">
            @foreach($children as $child)
                @include('backend.categories.partials.tree-node', ['category' => $child, 'isRoot' => false])
            @endforeach
        </div>
    @endif
</details>
