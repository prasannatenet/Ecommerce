@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Create Combo Offers</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.combos.index') }}">Combo Offers</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Create</span>
    </nav>
</div>

@if($errors->any())
    <div class="df-alert df-alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i> Please fix the highlighted errors below and try again.
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-9">
        <form action="{{ route('admin.combos.store') }}" method="POST" id="comboBuilderForm">
            @csrf

            <div id="comboBlocksWrapper">
                {{-- One block per submitted/fresh combo --}}
                @foreach(old('combos', [0 => null]) as $blockIndex => $blockValues)
                    @include('backend.combos.partials.combo-block', ['i' => $blockIndex, 'products' => $products, 'values' => $blockValues])
                @endforeach
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="button" class="df-btn df-btn-light" id="addComboBlock">
                    <i class="bi bi-plus-lg"></i> Add Another Combo
                </button>
                <button type="submit" class="df-btn df-btn-primary">
                    <i class="bi bi-check2-circle"></i> Create Combo(s)
                </button>
                <a href="{{ route('admin.combos.index') }}" class="df-btn df-btn-light">
                    <i class="bi bi-arrow-left"></i> Cancel
                </a>
            </div>
        </form>
    </div>
    <div class="col-lg-3">
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-lightbulb"></i> Tips</h5>
            </div>
            <div class="df-card-body">
                <p style="color:var(--df-text-secondary); font-size:0.85rem; margin-bottom:10px;">
                    <strong>Build many combos at once.</strong> Click <em>Add Another Combo</em> to create 2, 3 or more combos in a single submission.
                </p>
                <p style="color:var(--df-text-secondary); font-size:0.85rem; margin-bottom:10px;">
                    <strong>Select 2 or more products</strong> per combo — the offer then appears below every product in that combo.
                </p>
                <p style="color:var(--df-text-secondary); font-size:0.85rem; margin-bottom:10px;">
                    <strong>Special discount</strong> can be a percentage of the combined price or a flat ₹ amount.
                </p>
                <p style="color:var(--df-text-secondary); font-size:0.85rem; margin:0;">
                    Set a schedule (starts/expiry) to run the offer for a limited period, or leave it empty for an evergreen offer.
                </p>
            </div>
        </div>
    </div>
</div>

{{-- Template used by JS when adding another combo block --}}
<template id="comboBlockTemplate">
    @include('backend.combos.partials.combo-block', ['i' => '__IDX__', 'products' => $products, 'values' => null])
</template>

@include('backend.combos.partials.combo-styles')
@include('backend.combos.partials.combo-script')

<script>
    document.addEventListener('DOMContentLoaded', function () {
        'use strict';

        var wrapper = document.getElementById('comboBlocksWrapper');
        var template = document.getElementById('comboBlockTemplate');
        var addBtn = document.getElementById('addComboBlock');

        function nextIndex() {
            var max = -1;
            wrapper.querySelectorAll('.combo-block').forEach(function (block) {
                var idx = parseInt(block.getAttribute('data-block-idx'), 10);
                if (!isNaN(idx) && idx > max) max = idx;
            });
            return max + 1;
        }

        function renumberBlocks() {
            wrapper.querySelectorAll('.combo-block').forEach(function (block, position) {
                var title = block.querySelector('.combo-block-title');
                if (title) {
                    title.innerHTML = '<i class="bi bi-gift"></i> Combo #' + (position + 1);
                }
                var removeBtn = block.querySelector('.remove-combo-block');
                if (removeBtn) {
                    removeBtn.style.display = wrapper.querySelectorAll('.combo-block').length > 1 ? '' : 'none';
                }
            });
        }

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var idx = nextIndex();
                var clone = template.content.cloneNode(true);
                var html = clone.firstElementChild.outerHTML
                    .replace(/__IDX__/g, idx)
                    .replace(/__NUM__/g, '');
                wrapper.insertAdjacentHTML('beforeend', html);
                renumberBlocks();
                if (window.updateComboSummary) {
                    window.updateComboSummary(wrapper.querySelector('[data-block-idx="' + idx + '"]'));
                }
                var search = wrapper.querySelector('[data-block-idx="' + idx + '"] .combo-product-search');
                if (search) search.focus();
            });
        }

        document.addEventListener('click', function (e) {
            var removeBtn = e.target.closest('.remove-combo-block');
            if (!removeBtn) return;
            e.preventDefault();
            var block = removeBtn.closest('.combo-block');
            if (!block) return;
            if (wrapper.querySelectorAll('.combo-block').length > 1) {
                block.remove();
                renumberBlocks();
            }
        });

        renumberBlocks();
    });
</script>

@endsection
