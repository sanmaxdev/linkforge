<div class="grid gap-3 sm:grid-cols-2">
    <div>
        <label class="lf-label">Campaign <span class="font-normal text-slate-400">(optional)</span></label>
        <select name="campaign_id" class="lf-input">
            <option value="">No campaign</option>
            @foreach (($campaigns ?? []) as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="lf-label">Tags <span class="font-normal text-slate-400">(applied to all)</span></label>
        <input name="tags" type="text" class="lf-input" placeholder="import, q2">
    </div>
</div>
