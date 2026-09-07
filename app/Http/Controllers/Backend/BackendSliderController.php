<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackendSliderController extends Controller
{
    public function index()
    {
        $sliders = Slider::orderBy('sort_order')->orderByDesc('id')->get();

        return view('backend.sliders.index', compact('sliders'));
    }

    public function create()
    {
        return view('backend.sliders.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('sliders', 'public');
        }

        $data['is_active'] = $request->boolean('is_active', true);

        Slider::create($data);

        return redirect()->route('admin.sliders.index')->with('success', 'Slider created successfully.');
    }

    public function show(Slider $slider)
    {
        return redirect()->route('admin.sliders.edit', $slider);
    }

    public function edit(Slider $slider)
    {
        return view('backend.sliders.edit', compact('slider'));
    }

    public function update(Request $request, Slider $slider)
    {
        $data = $this->validateData($request);

        if ($request->hasFile('image')) {
            if (! empty($slider->image_path)) {
                Storage::disk('public')->delete($slider->image_path);
            }

            $data['image_path'] = $request->file('image')->store('sliders', 'public');
        }

        $data['is_active'] = $request->boolean('is_active', false);

        $slider->update($data);

        return redirect()->route('admin.sliders.index')->with('success', 'Slider updated successfully.');
    }

    public function destroy(Slider $slider)
    {
        if (! empty($slider->image_path)) {
            Storage::disk('public')->delete($slider->image_path);
        }

        $slider->delete();

        return redirect()->route('admin.sliders.index')->with('success', 'Slider deleted successfully.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'ordered_ids' => 'required|array|min:1',
            'ordered_ids.*' => 'required|integer|exists:sliders,id',
        ]);

        $orderedIds = array_values(array_unique($data['ordered_ids']));

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                Slider::whereKey($id)->update([
                    'sort_order' => $index,
                ]);
            }
        });

        return response()->json([
            'message' => 'Slider order updated successfully.',
        ]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'subheading' => 'nullable|string|max:500',
            'button_text' => 'nullable|string|max:100',
            'button_link' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable|boolean',
        ]);
    }
}
