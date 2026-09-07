{{-- Recursive category options for select dropdown --}}
{{-- Accepts: $category (single Category model), $level (int), $selected (id) --}}
<option value="{{ $category->id }}"
        {{ (old('parent_id', $selected ?? old('category_id', '')) == $category->id) ? 'selected' : '' }}>
    {{ str_repeat('— ', $level) }}{{ $category->name }}
</option>
@if($category->children && $category->children->count() > 0)
    @foreach($category->children as $child)
        @include('backend.categories.partials.category-options', [
            'category' => $child,
            'level' => $level + 1,
            'selected' => $selected ?? ''
        ])
    @endforeach
@endif
