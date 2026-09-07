<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;

class BackendTestimonialController extends Controller
{
    public function index()
    {
        $testimonials = Testimonial::query()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(20);

        return view('backend.testimonials.index', compact('testimonials'));
    }

    public function create()
    {
        return view('backend.testimonials.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);

        Testimonial::create($data);

        return redirect()
            ->route('admin.testimonials.index')
            ->with('success', 'Testimonial created successfully.');
    }

    public function show(Testimonial $testimonial)
    {
        return redirect()->route('admin.testimonials.edit', $testimonial);
    }

    public function edit(Testimonial $testimonial)
    {
        return view('backend.testimonials.edit', compact('testimonial'));
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        $data = $this->validateData($request);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', false);

        $testimonial->update($data);

        return redirect()
            ->route('admin.testimonials.index')
            ->with('success', 'Testimonial updated successfully.');
    }

    public function destroy(Testimonial $testimonial)
    {
        $testimonial->delete();

        return redirect()
            ->route('admin.testimonials.index')
            ->with('success', 'Testimonial deleted successfully.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'designation' => 'nullable|string|max:255',
            'content' => 'required|string',
            'rating' => 'required|numeric|min:0|max:5',
            'initials' => 'nullable|string|max:8',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable|boolean',
        ]);
    }
}
