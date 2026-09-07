<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;

class BackendTagController extends Controller
{
    public function index()
    {
        $tags = Tag::withCount('products')->paginate(20);
        return view('backend.tags.index', compact('tags'));
    }

    public function create()
    {
        return view('backend.tags.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255|unique:tags,name',
            'description' => 'nullable|string',
        ]);

        Tag::create($request->only('name', 'description'));

        return redirect()->route('admin.tags.index')
            ->with('success', 'Tag created successfully.');
    }

    public function edit(Tag $tag)
    {
        return view('backend.tags.edit', compact('tag'));
    }

    public function update(Request $request, Tag $tag)
    {
        $request->validate([
            'name'        => 'required|string|max:255|unique:tags,name,' . $tag->id,
            'description' => 'nullable|string',
        ]);

        $tag->update($request->only('name', 'description'));

        return redirect()->route('admin.tags.index')
            ->with('success', 'Tag updated successfully.');
    }

    public function destroy(Tag $tag)
    {
        $tag->products()->detach(); // Remove pivot entries
        $tag->delete();

        return redirect()->route('admin.tags.index')
            ->with('success', 'Tag deleted successfully.');
    }
}
