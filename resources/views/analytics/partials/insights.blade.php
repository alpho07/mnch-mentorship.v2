{{-- $insights: list of {label,bg,ink,icon,title,body}; $n: section number --}}
<section aria-labelledby="ins-h">
@include('analytics.partials.sec', ['n' => $n, 'id' => 'ins-h', 'title' => 'What the data says'])
<div class="grid-auto" style="--min:260px">
@foreach ($insights as $i => $x)
<article class="card rise" style="padding:20px;display:flex;flex-direction:column;gap:10px;animation-delay:{{ number_format(0.1 + $i * 0.06, 2) }}s">
<span class="chip" style="background:{{ $x['bg'] }};color:{{ $x['ink'] }};align-self:flex-start">@include('analytics.partials.icon', ['name' => $x['icon'], 'size' => 13, 'sw' => 2.2]){{ $x['label'] }}</span>
<div class="disp" style="font-size:20px;font-weight:600;line-height:1.2">{{ $x['title'] }}</div>
<p class="muted" style="margin:0;text-wrap:pretty">{{ $x['body'] }}</p>
</article>
@endforeach
</div>
</section>
