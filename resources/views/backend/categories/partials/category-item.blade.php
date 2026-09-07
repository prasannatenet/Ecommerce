<div class="category-item" style="padding-left: {{ $level * 2 }}rem;">
    <div class="category-header">
        <div class="category-info">
            @if($category->children->count())
                <div class="category-toggle me-2">
                    <i class="fa fa-chevron-right"></i>
                </div>
            @else
                <div style="width: 1rem;" class="me-2"></div>
            @endif

            <div class="me-3">
                @if($level === 0)
                    <i class="fa fa-folder text-primary"></i>
                @elseif($level === 1)
                    <i class="fa fa-folder-open text-info"></i>
                @else
                    <i class="fa fa-file text-secondary"></i>
                @endif
            </div>

            <div class="category-details">
                <h6 class="mb-1">{{ $category->name }}</h6>
                @if($category->description)
                    <small>{{ Str::limit($category->description, 50) }}</small>
                @endif
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="category-meta">
                @if($category->is_final)
                    <span class="badge rounded-pill bg-success bg-opacity-10 text-success">
                        <i class="fa fa-check-circle me-1"></i>Final
                    </span>
                @endif

                @if($category->children->count())
                    <span class="category-meta-item">
                        <i class="fa fa-layer-group"></i>{{ $category->children->count() }} {{ Str::plural('subcategory', $category->children->count()) }}
                    </span>
                @endif

                @if(isset($category->products_count))
                    <span class="category-meta-item">
                        <i class="fa fa-box"></i>{{ $category->products_count }} {{ Str::plural('product', $category->products_count) }}
                    </span>
                @endif
            </div>

            <div class="category-actions">
                <a href="{{ route('admin.categories.edit', $category->id) }}" class="btn btn-sm btn-outline-warning" onclick="event.stopPropagation()" title="Edit">
                    <i class="fa fa-edit"></i>
                </a>
                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal{{ $category->id }}" onclick="event.stopPropagation()" title="Delete">
                    <i class="fa fa-trash"></i>
                </button>
            </div>
        </div>
    </div>

    @if($category->children->count())
        <div class="category-children">
            @foreach($category->children as $child)
                @include('backend.categories.partials.category-item', ['category' => $child, 'level' => $level + 1])
            @endforeach
        </div>
    @endif
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal{{ $category->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-semibold text-dark">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3">
                    <i class="fa fa-trash text-danger fa-2x"></i>
                </div>
                <h6 class="text-dark mb-2">Delete "{{ $category->name }}"?</h6>
                <p class="text-muted mb-0">
                    @if($category->children->count())
                        <span class="text-warning d-block mb-2">
                            <i class="fa fa-exclamation-triangle me-1"></i>
                            This category has {{ $category->children->count() }} {{ Str::plural('subcategory', $category->children->count()) }}!
                        </span>
                        <span class="text-danger d-block mb-2">All subcategories will also be deleted.</span>
                    @endif
                    This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="fa fa-times me-2"></i>Cancel
                </button>
                <form action="{{ route('admin.categories.destroy', $category->id) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-trash me-2"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
