@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Edit Variation</h1>
    <form method="POST" action="{{ route('admin.product-variations.update', $item) }}">@csrf @method('PUT')
        <div class="mb-3"><label>sku</label><input name="sku" value="{{ $item->sku }}" class="form-control"></div>
        <div class="mb-3"><label>price</label><input name="price" value="{{ $item->price }}" class="form-control"></div>
        <div class="mb-3"><label>stock</label><input name="stock" value="{{ $item->stock }}" class="form-control"></div>
        <button class="btn btn-primary">Save</button>
    </form>
</div>
@endsection
