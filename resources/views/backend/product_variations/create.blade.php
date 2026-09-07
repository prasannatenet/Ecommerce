@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Create Variation</h1>
    <form method="POST" action="{{ route('admin.product-variations.store') }}">
        @csrf
        <div class="mb-3"><label>product_id</label><input name="product_id" class="form-control"></div>
        <div class="mb-3"><label>sku</label><input name="sku" class="form-control"></div>
        <div class="mb-3"><label>price</label><input name="price" class="form-control"></div>
        <div class="mb-3"><label>stock</label><input name="stock" class="form-control"></div>
        <div class="mb-3"><label>attributes (json)</label><input name="attributes" class="form-control"></div>
        <button class="btn btn-primary">Save</button>
    </form>
</div>
@endsection
