<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;

class HelpController extends Controller
{
    public function index()
    {
        // Deliberate topic order (most-used first); unlisted categories fall to the end.
        $order = [
            'Getting started', 'Short links', 'QR codes', 'Bio pages', 'Custom domains',
            'Analytics', 'Marketing', 'Developers & API', 'Account & security', 'Billing & plans',
        ];

        $groups = HelpArticle::published()->orderBy('sort')->orderBy('title')->get()
            ->groupBy('category')
            ->sortBy(fn ($articles, $category) => ($i = array_search($category, $order, true)) === false ? 999 : $i);

        return view('help.index', ['groups' => $groups]);
    }

    public function show(string $slug)
    {
        $article = HelpArticle::published()->where('slug', $slug)->firstOrFail();
        $article->incrementQuietly('views');

        return view('help.show', [
            'article' => $article,
            'related' => HelpArticle::published()
                ->where('category', $article->category)
                ->where('id', '!=', $article->id)
                ->orderBy('sort')->limit(5)->get(),
        ]);
    }
}
