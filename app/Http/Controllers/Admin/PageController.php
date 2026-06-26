<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Page;
use App\Support\FooterPages;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function index()
    {
        return view('admin.pages.index', [
            'pages' => Page::orderBy('sort')->orderBy('title')->paginate(30),
        ]);
    }

    public function create()
    {
        return view('admin.pages.form', ['page' => new Page(['status' => 'draft'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePage($request);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['title']);
        Page::create($data);
        FooterPages::forget();
        AuditLog::record('page.create', 'Created page: '.$data['title']);

        return redirect()->route('admin.pages.index')->with('status', 'Page saved.');
    }

    public function edit(Page $page)
    {
        return view('admin.pages.form', ['page' => $page]);
    }

    public function update(Request $request, Page $page)
    {
        $data = $this->validatePage($request);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['title'], $page->id);
        $page->update($data);
        FooterPages::forget();
        AuditLog::record('page.update', 'Updated page: '.$page->title);

        return redirect()->route('admin.pages.index')->with('status', 'Page updated.');
    }

    public function destroy(Page $page)
    {
        $page->delete();
        FooterPages::forget();
        AuditLog::record('page.delete', 'Deleted page: '.$page->title);

        return back()->with('status', 'Page deleted.');
    }

    /** @return array<string, mixed> */
    private function validatePage(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:60000'],
            'status' => ['required', 'in:draft,published'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:300'],
        ]);
        $data['show_in_footer'] = $request->boolean('show_in_footer');

        return $data;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::lower(Str::random(8));
        $slug = $base;
        $i = 2;
        while (Page::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
