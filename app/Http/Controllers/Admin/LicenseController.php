<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LicenseService;
use App\Support\Demo;

class LicenseController extends Controller
{
    public function show(LicenseService $license)
    {
        // Note: the relay URL is author infrastructure and is deliberately NOT exposed to buyers.
        return view('admin.license.index', [
            'license' => $license->status(),
        ]);
    }

    public function recheck(LicenseService $license)
    {
        if (Demo::enabled()) {
            return back()->with('error', 'License re-check is disabled in the live demo.');
        }

        $result = $license->recheck();

        return back()->with(
            ($result['valid'] ?? false) ? 'status' : 'error',
            (string) ($result['message'] ?? 'Re-check complete.')
        );
    }
}
