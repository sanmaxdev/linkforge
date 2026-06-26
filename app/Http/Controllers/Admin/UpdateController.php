<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UpdateLog;
use App\Services\Update\Updater;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
    private function pendingPath(): string
    {
        return storage_path('app/updates/pending.zip');
    }

    public function index(Updater $updater)
    {
        $pending = null;
        $issues = [];

        if (is_file($this->pendingPath())) {
            try {
                $pending = $updater->inspect($this->pendingPath());
                $issues = $updater->issues($pending);
            } catch (\Throwable $e) {
                @unlink($this->pendingPath());
                session()->flash('error', $e->getMessage());
            }
        }

        return view('admin.updates.index', [
            'current' => $updater->currentVersion(),
            'pending' => $pending,
            'issues' => $issues,
            'maxUpload' => $this->maxUploadBytes(),
            'history' => UpdateLog::with('user')->latest('id')->take(20)->get(),
        ]);
    }

    /** The effective max upload size PHP allows here (min of upload_max_filesize / post_max_size). */
    private function maxUploadBytes(): int
    {
        $toBytes = function (string $v): int {
            $v = trim($v);
            if ($v === '') {
                return 0;
            }
            $n = (int) $v;

            return match (strtolower(substr($v, -1))) {
                'g' => $n * 1024 ** 3,
                'm' => $n * 1024 ** 2,
                'k' => $n * 1024,
                default => $n,
            };
        };

        $limits = array_filter([
            $toBytes((string) ini_get('upload_max_filesize')),
            $toBytes((string) ini_get('post_max_size')), // 0 = unlimited, filtered out
        ]);

        return $limits ? (int) min($limits) : 0;
    }

    public function upload(Request $request, Updater $updater)
    {
        $request->validate(['package' => ['required', 'file', 'max:51200']]); // 50 MB

        $dir = storage_path('app/updates');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $request->file('package')->move($dir, 'pending.zip');

        try {
            $updater->inspect($this->pendingPath()); // validates it's a real update package
        } catch (\Throwable $e) {
            @unlink($this->pendingPath());

            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('admin.updates')]);
        }

        return redirect()->route('admin.updates')->with('status', 'Update package uploaded. Review it below, then apply.');
    }

    public function apply(Request $request, Updater $updater)
    {
        if (! is_file($this->pendingPath())) {
            return back()->with('error', 'No update package is pending.');
        }

        try {
            $manifest = $updater->inspect($this->pendingPath());
            if ($problems = $updater->issues($manifest)) {
                return back()->with('error', implode(' ', $problems)); // keep the package so the operator can review
            }
            $log = $updater->apply($this->pendingPath(), $manifest, null, $request->user()->id);
        } catch (\Throwable $e) {
            @unlink($this->pendingPath());

            return back()->with('error', 'Update failed: '.$e->getMessage());
        }

        @unlink($this->pendingPath());

        return redirect()->route('admin.updates')->with('status', 'Update applied. '.implode(' ', $log));
    }

    public function discard()
    {
        @unlink($this->pendingPath());

        return redirect()->route('admin.updates')->with('status', 'Pending update discarded.');
    }
}
