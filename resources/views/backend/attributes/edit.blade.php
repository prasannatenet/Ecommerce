@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Edit Attribute</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.attributes.index') }}">Attributes</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Edit</span>
    </nav>
</div>

@if($errors->any())
    <div class="df-alert df-alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul class="mb-0">
            @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-tags"></i> Attribute Details</h5>
            </div>
            <div class="df-card-body">
                <form action="{{ route('admin.attributes.update', $attribute) }}" method="POST" id="attributeForm">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="df-form-label">Attribute Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="df-form-control"
                               value="{{ old('name', $attribute->name) }}" required>
                    </div>
                    <div class="mb-4">
                        <label class="df-form-label">Values <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2 mb-2">
                            <input type="text" class="df-form-control" id="valueInput"
                                   placeholder="Type a value and press Enter">
                            <button type="button" class="df-btn df-btn-primary" id="addValueBtn" style="white-space:nowrap;">
                                <i class="bi bi-plus-lg"></i> Add
                            </button>
                        </div>
                        <div id="valuesContainer" class="d-flex flex-wrap gap-2 mb-2"></div>
                        <div id="hiddenInputs"></div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="df-btn df-btn-primary">
                            <i class="bi bi-check2-circle"></i> Update Attribute
                        </button>
                        <a href="{{ route('admin.attributes.index') }}" class="df-btn df-btn-light">
                            <i class="bi bi-arrow-left"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-info-circle"></i> Attribute Info</h5>
            </div>
            <div class="df-card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Status</span>
                    @if($attribute->is_active)
                        <span class="df-badge df-badge-success">Active</span>
                    @else
                        <span class="df-badge df-badge-danger">Inactive</span>
                    @endif
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Values</span>
                    <span class="df-badge df-badge-info">{{ $attribute->values->count() }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Created</span>
                    <span style="font-size:0.85rem; color:var(--df-text-secondary);">{{ $attribute->created_at->format('M d, Y') }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="df-form-label mb-0">Updated</span>
                    <span style="font-size:0.85rem; color:var(--df-text-secondary);">{{ $attribute->updated_at->format('M d, Y') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let values = @json($attribute->values->pluck('value')->toArray());

    function addValue() {
        const input = document.getElementById('valueInput');
        const value = input.value.trim();

        if (value && !values.includes(value)) {
            values.push(value);
            renderValues();
            input.value = '';
        }
        input.focus();
    }

    function removeValue(value) {
        values = values.filter(v => v !== value);
        renderValues();
    }

    function renderValues() {
        const container = document.getElementById('valuesContainer');
        const hiddenContainer = document.getElementById('hiddenInputs');

        container.innerHTML = '';
        hiddenContainer.innerHTML = '';

        values.forEach(value => {
            const tag = document.createElement('span');
            tag.className = 'df-badge df-badge-primary';
            tag.style.cssText = 'font-size:0.88rem; padding:6px 14px; display:inline-flex; align-items:center; gap:6px;';
            tag.innerHTML = `
                ${value}
                <i class="bi bi-x-circle" style="cursor:pointer; opacity:0.7;" onclick="removeValue('${value}')"></i>
            `;
            container.appendChild(tag);

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'values[]';
            hidden.value = value;
            hiddenContainer.appendChild(hidden);
        });
    }

    window.removeValue = removeValue;

    document.getElementById('addValueBtn').addEventListener('click', addValue);

    document.getElementById('valueInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addValue();
        }
    });

    renderValues();
});
</script>

@endsection
