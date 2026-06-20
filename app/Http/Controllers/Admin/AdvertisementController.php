<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdvertisementController extends Controller
{
    public function index()
    {
        return view('admin.ads.index', [
            'ads' => Advertisement::orderBy('placement')->orderBy('sort')->orderByDesc('id')->get(),
            'placements' => Advertisement::PLACEMENTS,
            'adsEnabled' => \App\Models\Setting::get('ads_enabled') === '1',
            'skipSeconds' => (int) \App\Models\Setting::get('ads_skip_seconds', 5),
        ]);
    }

    /** Master monetization switch + interstitial skip delay. */
    public function saveSettings(Request $request)
    {
        $data = $request->validate(['ads_skip_seconds' => ['nullable', 'integer', 'min:0', 'max:60']]);

        \App\Models\Setting::putMany([
            'ads_enabled' => $request->boolean('ads_enabled') ? '1' : '0',
            'ads_skip_seconds' => (string) ((int) ($data['ads_skip_seconds'] ?? 5)),
        ]);
        AuditLog::record('ad.settings', 'Updated monetization settings');

        return back()->with('status', 'Monetization settings saved.');
    }

    public function create()
    {
        return view('admin.ads.form', [
            'ad' => new Advertisement(['is_active' => true, 'placement' => 'interstitial']),
            'placements' => Advertisement::PLACEMENTS,
        ]);
    }

    public function store(Request $request)
    {
        $ad = new Advertisement;
        $this->fill($ad, $request, $this->validateAd($request));
        $ad->save();
        Advertisement::forgetCache();
        AuditLog::record('ad.create', 'Created ad: '.$ad->name, $ad);

        return redirect()->route('admin.ads')->with('status', 'Ad created.');
    }

    public function edit(Advertisement $ad)
    {
        return view('admin.ads.form', ['ad' => $ad, 'placements' => Advertisement::PLACEMENTS]);
    }

    public function update(Request $request, Advertisement $ad)
    {
        $this->fill($ad, $request, $this->validateAd($request));
        $ad->save();
        Advertisement::forgetCache();
        AuditLog::record('ad.update', 'Updated ad: '.$ad->name, $ad);

        return redirect()->route('admin.ads')->with('status', 'Ad updated.');
    }

    public function toggle(Advertisement $ad)
    {
        $ad->update(['is_active' => ! $ad->is_active]);
        Advertisement::forgetCache();

        return back()->with('status', $ad->is_active ? 'Ad enabled.' : 'Ad disabled.');
    }

    public function destroy(Advertisement $ad)
    {
        $ad->delete();
        Advertisement::forgetCache();
        AuditLog::record('ad.delete', 'Deleted ad: '.$ad->name);

        return redirect()->route('admin.ads')->with('status', 'Ad deleted.');
    }

    /** @return array<string, mixed> */
    private function validateAd(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'placement' => ['required', Rule::in(array_keys(Advertisement::PLACEMENTS))],
            'code' => ['nullable', 'string', 'max:20000'],
            'target_url' => ['nullable', 'url', 'max:2048'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:1024'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function fill(Advertisement $ad, Request $request, array $data): void
    {
        $ad->name = $data['name'];
        $ad->placement = $data['placement'];
        $ad->code = $data['code'] ?? null;
        $ad->target_url = $data['target_url'] ?? null;
        $ad->sort = (int) ($data['sort'] ?? 0);
        $ad->is_active = $request->boolean('is_active');

        if ($request->hasFile('image')) {
            $ad->image_path = $this->storeImage($request->file('image'));
        } elseif ($request->boolean('image_clear')) {
            $ad->image_path = null;
        }
    }

    private function storeImage(\Illuminate\Http\UploadedFile $file): string
    {
        $dir = public_path('uploads/ads');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        // Derive the extension from the file's content, not the client-supplied name.
        $ext = strtolower((string) $file->guessExtension());
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = 'png';
        }
        $name = 'ad-'.Str::random(10).'.'.$ext;
        $file->move($dir, $name);

        return 'uploads/ads/'.$name;
    }
}
