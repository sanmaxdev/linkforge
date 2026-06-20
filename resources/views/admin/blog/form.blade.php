<x-admin-layout :title="$post->exists ? 'Edit post' : 'New post'">
    <x-slot:header>{{ $post->exists ? 'Edit post' : 'New post' }}</x-slot:header>

    <a href="{{ route('admin.blog.index') }}" class="mb-5 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-slate-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        Back to posts
    </a>

    <form method="POST" action="{{ $post->exists ? route('admin.blog.update', $post) : route('admin.blog.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-[1fr_300px]">
        @csrf
        @if ($post->exists) @method('PUT') @endif

        <div class="space-y-5">
            <div class="lf-card p-6 space-y-4">
                <div>
                    <label class="lf-label" for="title">Title</label>
                    <input id="title" name="title" value="{{ old('title', $post->title) }}" class="lf-input" required>
                    @error('title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="lf-label" for="slug">Slug <span class="font-normal text-slate-400">(optional — auto from title)</span></label>
                    <input id="slug" name="slug" value="{{ old('slug', $post->slug) }}" class="lf-input" placeholder="my-post">
                </div>
                <div>
                    <label class="lf-label" for="excerpt">Excerpt</label>
                    <textarea id="excerpt" name="excerpt" rows="2" class="lf-input" placeholder="Short summary shown in listings">{{ old('excerpt', $post->excerpt) }}</textarea>
                </div>
                <div>
                    <label class="lf-label" for="body">Body <span class="font-normal text-slate-400">(Markdown)</span></label>
                    <textarea id="body" name="body" rows="18" class="lf-input font-mono text-xs">{{ old('body', $post->body) }}</textarea>
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="lf-card p-6 space-y-4">
                <div>
                    <label class="lf-label" for="status">Status</label>
                    <select id="status" name="status" class="lf-input">
                        <option value="draft" @selected(old('status', $post->status) === 'draft')>Draft</option>
                        <option value="published" @selected(old('status', $post->status) === 'published')>Published</option>
                    </select>
                </div>
                <div>
                    <span class="lf-label">Cover image</span>
                    @if ($post->cover_image)
                        <img src="{{ \Illuminate\Support\Str::startsWith($post->cover_image, 'http') ? $post->cover_image : asset($post->cover_image) }}" alt="" class="mb-2 aspect-[16/9] w-full rounded-lg object-cover">
                    @endif
                    <input type="file" name="cover_file" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-md file:border-0 file:bg-slate-200 file:px-3 file:py-1.5 file:text-xs file:font-medium">
                    @error('cover_file')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-2 text-xs text-slate-400">Upload an image, or paste a URL:</p>
                    <input id="cover_image" name="cover_image" value="{{ old('cover_image', $post->cover_image) }}" class="lf-input mt-1" placeholder="https://…">
                </div>
            </div>
            <div class="lf-card p-6 space-y-4">
                <h3 class="text-sm font-semibold text-slate-900">SEO</h3>
                <div>
                    <label class="lf-label" for="meta_title">Meta title</label>
                    <input id="meta_title" name="meta_title" value="{{ old('meta_title', $post->meta_title) }}" class="lf-input">
                </div>
                <div>
                    <label class="lf-label" for="meta_description">Meta description</label>
                    <textarea id="meta_description" name="meta_description" rows="3" class="lf-input">{{ old('meta_description', $post->meta_description) }}</textarea>
                </div>
            </div>
            <button type="submit" class="lf-btn w-full">{{ $post->exists ? 'Update post' : 'Create post' }}</button>
        </div>
    </form>
</x-admin-layout>
