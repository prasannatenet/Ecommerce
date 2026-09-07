<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BackendAttributeController extends Controller
{
    public function index()
    {
        $attributes = Attribute::withCount('values')->paginate(20);
        return view('backend.attributes.index', compact('attributes'));
    }

    public function create()
    {
        return view('backend.attributes.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:attributes,name',
            'values' => 'required|array|min:1',
            'values.*' => 'required|string|distinct',
        ]);

        DB::beginTransaction();
        try {
            $attribute = Attribute::create([
                'name' => $validated['name'],
                'is_active' => true,
            ]);

            foreach ($validated['values'] as $index => $value) {
                AttributeValue::create([
                    'attribute_id' => $attribute->id,
                    'value' => $value,
                    'position' => $index,
                ]);
            }

            DB::commit();
            return redirect()->route('admin.attributes.index')
                ->with('success', 'Attribute created successfully');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors($e->getMessage())->withInput();
        }
    }

    public function edit(Attribute $attribute)
    {
        $attribute->load('values');
        return view('backend.attributes.edit', compact('attribute'));
    }

    public function update(Request $request, Attribute $attribute)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:attributes,name,' . $attribute->id,
            'values' => 'required|array|min:1',
            'values.*' => 'required|string|distinct',
        ]);

        DB::beginTransaction();
        try {
            $attribute->update(['name' => $validated['name']]);

            // Delete removed values and update existing ones
            $attribute->values()->delete();

            foreach ($validated['values'] as $index => $value) {
                AttributeValue::create([
                    'attribute_id' => $attribute->id,
                    'value' => $value,
                    'position' => $index,
                ]);
            }

            DB::commit();
            return redirect()->route('admin.attributes.index')
                ->with('success', 'Attribute updated successfully');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors($e->getMessage())->withInput();
        }
    }

    public function destroy(Attribute $attribute)
    {
        $attribute->delete();
        return back()->with('success', 'Attribute deleted successfully');
    }
}
