<?php

namespace App\Http\Controllers;

use App\Models\Pixel;
use App\Services\Billing\PlanGate;
use Illuminate\Http\Request;

class PixelController extends Controller
{
    public function index(Request $request)
    {
        return view('pixels.index', ['pixels' => $request->user()->pixels()->latest()->get()]);
    }

    public function store(Request $request)
    {
        if (! app(PlanGate::class)->allows($request->user(), 'retargeting')) {
            return back()->with('error', 'Retargeting pixels are available on the Pro plan and above.');
        }

        $data = $request->validate([
            'provider' => ['required', 'in:facebook,google,tiktok,linkedin,twitter,pinterest,quora,bing,snapchat,reddit,gtm'],
            'pixel_id' => ['required', 'string', 'max:120'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $request->user()->pixels()->create($data);

        return back()->with('status', 'Retargeting pixel added.');
    }

    public function destroy(Request $request, Pixel $pixel)
    {
        abort_unless((int) $pixel->user_id === (int) $request->user()->id, 403);
        $pixel->delete();

        return back()->with('status', 'Pixel removed.');
    }
}
