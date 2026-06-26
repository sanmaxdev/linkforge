<?php

namespace App\Http\Controllers;

use App\Models\BioBlock;
use App\Models\BioMessage;
use App\Models\BioPage;
use App\Models\BioSubscriber;
use App\Services\Ai\ClaudeClient;
use App\Services\Analytics\BioAnalytics;
use App\Services\Billing\PlanGate;
use App\Services\Linking\AliasGenerator;
use App\Support\BioChat;
use App\Support\BioEmbed;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BioController extends Controller
{
    public function __construct(private AliasGenerator $aliases) {}

    public function index(Request $request)
    {
        return view('bio.index', ['pages' => $request->user()->bioPages()->latest()->get()]);
    }

    public function create()
    {
        return view('bio.edit', ['page' => null, 'aiEnabled' => app(ClaudeClient::class)->enabled()]);
    }

    public function store(Request $request)
    {
        if (! app(PlanGate::class)->canCreate($request->user(), 'max_bio')) {
            return back()->withInput()->with('error', "You've reached your plan's bio page limit. Upgrade to add more.");
        }

        $data = $this->validateBio($request, null);
        $page = $request->user()->bioPages()->create($this->attributes($request, $data, null));
        $this->syncBlocks($request, $page);

        return redirect()->route('bio.edit', $page)->with('status', 'Bio page created.');
    }

    public function edit(Request $request, BioPage $bioPage)
    {
        abort_unless((int) $bioPage->user_id === (int) $request->user()->id, 403);

        return view('bio.edit', ['page' => $bioPage->load('blocks'), 'aiEnabled' => app(ClaudeClient::class)->enabled()]);
    }

    public function update(Request $request, BioPage $bioPage)
    {
        abort_unless((int) $bioPage->user_id === (int) $request->user()->id, 403);

        $data = $this->validateBio($request, $bioPage->id);
        $bioPage->update($this->attributes($request, $data, $bioPage));
        $this->syncBlocks($request, $bioPage);

        return redirect()->route('bio.edit', $bioPage)->with('status', 'Bio page saved.');
    }

    public function destroy(Request $request, BioPage $bioPage)
    {
        abort_unless((int) $bioPage->user_id === (int) $request->user()->id, 403);
        $bioPage->delete();

        return redirect()->route('bio.index')->with('status', 'Bio page deleted.');
    }

    /** Live preview: render the bio from unsaved builder state (no persistence). */
    public function preview(Request $request)
    {
        $page = new BioPage([
            'slug' => $request->input('slug', 'preview'),
            'title' => $request->input('title'),
            'theme' => $this->json($request->input('design')),
            'settings' => $this->json($request->input('settings')),
            'social_links' => $this->json($request->input('social')),
        ]);
        $page->setRelation('blocks', collect($this->blocks($request))->map(
            fn ($b, $i) => new BioBlock(['type' => $b['type'], 'content' => $b['content'], 'sort' => $i, 'is_active' => true])
        ));

        return view('bio.show', ['page' => $page]);
    }

    /** @return array<string, mixed> */
    private function attributes(Request $request, array $data, ?BioPage $existing): array
    {
        $settings = $this->json($request->input('settings'));
        $existingSettings = (array) ($existing?->settings ?? []);

        // Password: hash a new one, clear on request, otherwise keep the existing hash.
        if ($request->filled('bio_password')) {
            $settings['password'] = Hash::make($request->input('bio_password'));
        } elseif (! $request->boolean('bio_password_remove') && isset($existingSettings['password'])) {
            $settings['password'] = $existingSettings['password'];
        } else {
            unset($settings['password']);
        }

        // Hiding branding is a white-label (paid) feature.
        if (! empty($settings['hide_branding']) && ! app(PlanGate::class)->allows($request->user(), 'white_label')) {
            unset($settings['hide_branding']);
        }

        return [
            'slug' => $data['slug'],
            'title' => $data['title'] ?? null,
            'theme' => $this->json($request->input('design')),
            'settings' => $settings,
            'social_links' => array_values(array_filter($this->json($request->input('social')), fn ($s) => ! empty($s['url']))),
            'is_published' => $request->boolean('is_published'),
        ];
    }

    // Public gates ---------------------------------------------------------

    public function unlock(Request $request, string $slug)
    {
        $page = BioPage::where('slug', $slug)->where('is_published', true)->firstOrFail();
        $hash = $page->setting('password');

        if ($hash && Hash::check((string) $request->input('password'), $hash)) {
            session()->put("bio_unlocked.{$page->id}", true);

            return redirect('/'.$page->slug);
        }

        return redirect('/'.$page->slug)->with('bio_gate_error', 'Incorrect password. Please try again.');
    }

    public function reveal(Request $request, string $slug)
    {
        $page = BioPage::where('slug', $slug)->where('is_published', true)->firstOrFail();
        session()->put("bio_ack.{$page->id}", true);

        return redirect('/'.$page->slug);
    }

    /** Upload an image (avatar / background / image block) and return its public URL. */
    public function upload(Request $request)
    {
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096']]); // 4 MB

        $name = $this->storeUpload($request->file('image'), ['jpg', 'png', 'gif', 'webp'], 'png');

        return response()->json(['url' => asset('uploads/bio/'.$name)]);
    }

    /** Upload an audio / PDF / video file for a media block and return its public URL. */
    public function uploadFile(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:16384', 'mimes:mp3,wav,ogg,m4a,aac,pdf,mp4,webm,mov']]); // 16 MB

        $name = $this->storeUpload($request->file('file'), ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'pdf', 'mp4', 'webm', 'mov'], 'bin');

        return response()->json(['url' => asset('uploads/bio/'.$name)]);
    }

    /**
     * Store an upload under public/uploads/bio with a random name and a SAFE
     * extension derived from the file's actual content (via guessExtension),
     * never the client-supplied name — so a polyglot can't land as an
     * executable script (.php) in the web root.
     *
     * @param  list<string>  $allowed
     */
    private function storeUpload(UploadedFile $file, array $allowed, string $fallback): string
    {
        $ext = strtolower((string) $file->guessExtension());
        if (! in_array($ext, $allowed, true)) {
            $ext = $fallback;
        }

        $dir = public_path('uploads/bio');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = Str::random(28).'.'.$ext;
        $file->move($dir, $name);

        return $name;
    }

    /** Stream a vCard (.vcf) built from a "vCard" block's saved fields. */
    public function vcard(string $slug, BioBlock $block)
    {
        $page = BioPage::where('slug', $slug)->where('is_published', true)->first();
        abort_unless($page && $block->bio_page_id === $page->id && $block->type === 'vcard', 404);

        $c = (array) $block->content;
        $field = fn (string $k) => str_replace(["\r", "\n"], ' ', trim((string) ($c[$k] ?? '')));

        $lines = ['BEGIN:VCARD', 'VERSION:3.0', 'FN:'.$field('label')];
        foreach (['org' => 'ORG', 'title' => 'TITLE', 'phone' => 'TEL', 'email' => 'EMAIL', 'url' => 'URL'] as $key => $prop) {
            if ($field($key) !== '') {
                $lines[] = $prop.':'.$field($key);
            }
        }
        $lines[] = 'END:VCARD';

        $filename = (Str::slug($field('label')) ?: 'contact').'.vcf';

        return response(implode("\r\n", $lines), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /** Tracked bio block click: record the click after-response, then redirect out. */
    public function trackClick(Request $request, string $slug, BioBlock $block)
    {
        $page = BioPage::where('slug', $slug)->where('is_published', true)->first();
        $url = $block->content['url'] ?? null;

        abort_unless($page && $block->bio_page_id === $page->id && $block->type === 'link' && $url, 404);

        $bio = app(BioAnalytics::class);
        app()->terminating(fn () => $bio->record($page->id, $block->id, 'click', $request));

        return redirect()->away($url);
    }

    // Lead capture (public) -----------------------------------------------

    /** Newsletter signup from a bio "Newsletter" block. */
    public function subscribe(Request $request, string $slug)
    {
        $page = BioPage::where('slug', $slug)->where('is_published', true)->firstOrFail();

        if ($request->filled('website')) { // honeypot: bots fill hidden fields
            return redirect('/'.$slug);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        BioSubscriber::firstOrCreate(
            ['bio_page_id' => $page->id, 'email' => mb_strtolower($data['email'])],
            ['name' => $data['name'] ?? null, 'ip_hash' => hash('sha256', (string) $request->ip())],
        );

        return redirect('/'.$slug)->with('bio_form_ok', 'subscribe');
    }

    /** Message from a bio "Contact form" block. */
    public function contact(Request $request, string $slug)
    {
        $page = BioPage::where('slug', $slug)->where('is_published', true)->firstOrFail();

        if ($request->filled('website')) {
            return redirect('/'.$slug);
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        BioMessage::create([
            'bio_page_id' => $page->id,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'message' => $data['message'],
            'ip_hash' => hash('sha256', (string) $request->ip()),
        ]);

        return redirect('/'.$slug)->with('bio_form_ok', 'contact');
    }

    // Leads dashboard (owner) ---------------------------------------------

    public function leads(Request $request, BioPage $bioPage)
    {
        abort_unless((int) $bioPage->user_id === (int) $request->user()->id, 403);

        return view('bio.leads', [
            'page' => $bioPage,
            'subscribers' => $bioPage->subscribers()->paginate(50, ['*'], 'subs'),
            'messages' => $bioPage->messages()->paginate(50, ['*'], 'msgs'),
        ]);
    }

    /** Export captured leads as a CSV download. */
    public function exportLeads(Request $request, BioPage $bioPage)
    {
        abort_unless((int) $bioPage->user_id === (int) $request->user()->id, 403);

        $type = $request->query('type') === 'messages' ? 'messages' : 'subscribers';
        $filename = "{$bioPage->slug}-{$type}.csv";

        $rows = $type === 'messages'
            ? $bioPage->messages()->get(['name', 'email', 'message', 'created_at'])
            : $bioPage->subscribers()->get(['name', 'email', 'created_at']);

        $headers = $type === 'messages'
            ? ['Name', 'Email', 'Message', 'Date']
            : ['Name', 'Email', 'Date'];

        return response()->streamDownload(function () use ($rows, $headers, $type) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $r) {
                fputcsv($out, $type === 'messages'
                    ? [$r->name, $r->email, $r->message, (string) $r->created_at]
                    : [$r->name, $r->email, (string) $r->created_at]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, mixed> */
    private function validateBio(Request $request, ?int $ignoreId): array
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9\-_]+$/', Rule::unique('bio_pages', 'slug')->ignore($ignoreId)],
            'title' => ['nullable', 'string', 'max:150'],
            'design' => ['nullable', 'string', 'max:8192'],
            'settings' => ['nullable', 'string', 'max:8192'],
            'social' => ['nullable', 'string', 'max:8192'],
            'blocks' => ['nullable', 'string', 'max:65535'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        if ($this->aliases->isReserved($data['slug'])) {
            throw ValidationException::withMessages(['slug' => 'That handle is reserved. Please choose another.']);
        }

        return $data;
    }

    private function syncBlocks(Request $request, BioPage $page): void
    {
        $page->blocks()->delete();

        $sort = 0;
        foreach ($this->blocks($request) as $b) {
            $page->blocks()->create(['type' => $b['type'], 'content' => $b['content'], 'sort' => $sort++, 'is_active' => true]);
        }
    }

    /**
     * Normalise the submitted blocks JSON into [{type, content}] rows, dropping
     * anything invalid (e.g. a link with no real URL).
     *
     * @return list<array{type:string, content:array<string,mixed>}>
     */
    private function blocks(Request $request): array
    {
        $out = [];
        foreach ($this->json($request->input('blocks')) as $row) {
            $type = $row['type'] ?? 'link';
            $label = trim((string) ($row['label'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            $text = trim((string) ($row['text'] ?? ''));

            $phone = trim((string) ($row['phone'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $image = trim((string) ($row['image'] ?? ''));
            $query = trim((string) ($row['query'] ?? ''));
            $date = trim((string) ($row['date'] ?? ''));
            $price = trim((string) ($row['price'] ?? ''));
            $button = trim((string) ($row['button'] ?? ''));
            $count = (int) ($row['count'] ?? 0);
            $org = trim((string) ($row['org'] ?? ''));
            $jobTitle = trim((string) ($row['title'] ?? ''));
            $carouselImgs = array_values(array_filter(
                array_map('trim', preg_split('/\r\n|\r|\n/', $text)),
                fn ($u) => (bool) filter_var($u, FILTER_VALIDATE_URL),
            ));
            $chatProvider = array_key_exists((string) ($row['provider'] ?? ''), BioChat::PROVIDERS) ? (string) $row['provider'] : '';
            $chatId = preg_replace('/[^A-Za-z0-9_\/.-]/', '', (string) ($row['id'] ?? ''));
            $ppUser = preg_replace('/[^A-Za-z0-9_.-]/', '', (string) ($row['username'] ?? ''));
            $ios = trim((string) ($row['ios'] ?? ''));
            $android = trim((string) ($row['android'] ?? ''));

            $content = match ($type) {
                'heading', 'text' => $text !== '' ? ['text' => $text] : null,
                'image' => filter_var($url, FILTER_VALIDATE_URL) ? ['url' => $url, 'label' => $label] : null,
                'divider' => [],
                'phone' => $phone !== '' ? ['label' => $label !== '' ? $label : $phone, 'phone' => $phone] : null,
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? ['label' => $label !== '' ? $label : $email, 'email' => $email] : null,
                'whatsapp' => $phone !== '' ? ['label' => $label !== '' ? $label : 'WhatsApp', 'phone' => $phone, 'message' => trim((string) ($row['message'] ?? ''))] : null,
                'featured' => filter_var($url, FILTER_VALIDATE_URL) ? ['label' => $label !== '' ? $label : $url, 'url' => $url, 'image' => $image] : null,
                // Embed accepts a URL only if a known provider can render it (see BioEmbed).
                'embed', 'video' => filter_var($url, FILTER_VALIDATE_URL) && BioEmbed::resolve($url) ? ['url' => $url] : null,
                'map' => $query !== '' ? ['query' => $query] : null,
                'countdown' => $date !== '' ? ['label' => $label, 'date' => $date] : null,
                'faq' => $text !== '' ? ['label' => $label, 'text' => $text] : null,
                'product' => ($label !== '' || filter_var($url, FILTER_VALIDATE_URL)) ? [
                    'label' => $label, 'text' => $text, 'price' => $price,
                    'image' => filter_var($image, FILTER_VALIDATE_URL) ? $image : '',
                    'url' => filter_var($url, FILTER_VALIDATE_URL) ? $url : '',
                ] : null,
                'newsletter', 'contact' => ['label' => $label, 'text' => $text, 'button' => $button],
                'rss' => filter_var($url, FILTER_VALIDATE_URL) ? ['label' => $label, 'url' => $url, 'count' => $count > 0 ? min($count, 20) : 5] : null,
                'tagline' => $text !== '' ? ['text' => $text] : null,
                'html' => ($cleanHtml = HtmlSanitizer::clean((string) ($row['html'] ?? ''))) !== '' ? ['html' => $cleanHtml] : null,
                'vcard' => ($label !== '' || $phone !== '' || $email !== '') ? [
                    'label' => $label, 'phone' => $phone, 'email' => $email, 'org' => $org, 'title' => $jobTitle,
                    'url' => filter_var($url, FILTER_VALIDATE_URL) ? $url : '',
                ] : null,
                'carousel' => $carouselImgs !== [] ? ['images' => $carouselImgs] : null,
                'chat' => ($chatProvider !== '' && $chatId !== '') ? ['provider' => $chatProvider, 'id' => $chatId] : null,
                'paypal' => $ppUser !== '' ? ['username' => $ppUser, 'amount' => preg_replace('/[^0-9.]/', '', $price), 'label' => $label] : null,
                'audio', 'pdf', 'videofile' => filter_var($url, FILTER_VALIDATE_URL) ? ['url' => $url, 'label' => $label] : null,
                'spacer' => ['size' => in_array($row['size'] ?? 'md', ['sm', 'md', 'lg'], true) ? $row['size'] : 'md'],
                'gallery' => $carouselImgs !== [] ? ['images' => $carouselImgs] : null,
                'apps' => (filter_var($ios, FILTER_VALIDATE_URL) || filter_var($android, FILTER_VALIDATE_URL)) ? [
                    'ios' => filter_var($ios, FILTER_VALIDATE_URL) ? $ios : '',
                    'android' => filter_var($android, FILTER_VALIDATE_URL) ? $android : '',
                ] : null,
                'testimonial' => $text !== '' ? ['text' => $text, 'label' => $label, 'image' => filter_var($image, FILTER_VALIDATE_URL) ? $image : ''] : null,
                default => filter_var($url, FILTER_VALIDATE_URL) ? ['label' => $label !== '' ? $label : $url, 'url' => $url] : null,
            };

            $allowed = ['link', 'heading', 'text', 'image', 'divider', 'phone', 'email', 'whatsapp', 'featured', 'video', 'embed', 'map', 'countdown', 'faq', 'product', 'newsletter', 'contact', 'rss', 'tagline', 'html', 'vcard', 'carousel', 'chat', 'paypal', 'audio', 'pdf', 'videofile', 'spacer', 'gallery', 'apps', 'testimonial'];
            if ($content !== null) {
                $out[] = ['type' => in_array($type, $allowed, true) ? $type : 'link', 'content' => $content];
            }
        }

        return $out;
    }

    /** @return array<mixed> */
    private function json(?string $raw): array
    {
        return json_decode($raw ?? '[]', true) ?: [];
    }
}
