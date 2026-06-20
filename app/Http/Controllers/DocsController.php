<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Serves the bundled buyer documentation (public/docs/) through Laravel.
 *
 * On a root-.htaccess install the document root is the project root, not
 * public/, so a request for the /docs *directory* is not matched by the
 * "serve real files" rule (it only matches files) and is handed to Laravel.
 * Without this route it would fall through to the short-link resolver and a
 * link aliased "docs" could hijack it. This route guarantees /docs always
 * serves the documentation. On a public-docroot install Apache serves the
 * folder statically and this route is simply never reached.
 */
class DocsController extends Controller
{
    public function serve(Request $request, string $path = '')
    {
        $base = realpath(public_path('docs'));
        if ($base === false) {
            abort(404);
        }

        // A bare /docs (or /docs/) request points at the index FILE, root-relative
        // so the browser resolves it against the real origin (immune to a wrong
        // APP_URL / proxy Host) and the page's relative asset URLs resolve under
        // /docs/. On a normal install the web server serves /docs/index.html
        // statically; this just covers the root-.htaccess directory request.
        if ($path === '') {
            return response('', 302, ['Location' => $request->getBaseUrl().'/docs/index.html']);
        }

        $target = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_dir($target)) {
            $target = rtrim($target, '/\\').DIRECTORY_SEPARATOR.'index.html';
        }

        $real = realpath($target);

        // Must resolve to a real file inside public/docs/ (no path traversal).
        if ($real === false || ! str_starts_with($real, $base.DIRECTORY_SEPARATOR) || ! is_file($real)) {
            abort(404);
        }

        return response()->file($real);
    }
}
