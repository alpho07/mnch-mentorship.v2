@extends('site.layout')

@section('title', $resource->title)
@section('description', $resource->meta_description ?: Str::limit(strip_tags((string) $resource->excerpt), 160))
@section('body-bg', '#F6F9F9')

@push('styles')
.rs-card{background:#FFF;border:1px solid #E1E9EA;border-radius:20px;padding:28px}
.rs-h{margin:0 0 14px;font-size:20px;font-weight:700}
.prose{font-size:16.5px;line-height:1.75;color:#1F3A3F;overflow-wrap:anywhere}
.prose h1,.prose h2,.prose h3,.prose h4{font-family:var(--font-display);color:#0F2A2E;line-height:1.25;margin:1.6em 0 .5em}
.prose h2{font-size:24px}.prose h3{font-size:20px}
.prose p{margin:0 0 1em}.prose ul,.prose ol{padding-left:1.4em;margin:0 0 1em}.prose li{margin:.3em 0}
.prose a{color:#00695C;text-decoration:underline}
.prose img{max-width:100%;height:auto;border-radius:14px}
.prose blockquote{margin:1.2em 0;padding:14px 18px;border-left:4px solid #0097A7;background:#F2F8F7;border-radius:0 12px 12px 0}
.prose table{border-collapse:collapse;width:100%;display:block;overflow-x:auto}.prose th,.prose td{border:1px solid #E1E9EA;padding:8px 12px;text-align:left}
.prose pre{background:#0F2A2E;color:#E4F2F3;padding:16px;border-radius:12px;overflow-x:auto}
.prose code{background:#EEF5F5;padding:2px 6px;border-radius:6px;font-size:.9em}.prose pre code{background:none;padding:0}
.abtn{display:inline-flex;align-items:center;gap:8px;min-height:44px;padding:0 16px;border-radius:12px;border:1px solid rgba(255,255,255,.28);background:rgba(255,255,255,.1);color:#FFF;font:inherit;font-weight:700;font-size:14px;cursor:pointer;text-decoration:none;transition:background .2s,transform .15s}
.abtn:hover{background:rgba(255,255,255,.2);color:#FFF}.abtn:active{transform:scale(.97)}
.abtn.on{background:#FFF;color:#00363B;border-color:#FFF}
.ta{width:100%;border:1px solid #D3DFE0;border-radius:12px;padding:12px 14px;font:inherit;font-size:15px;color:#0F2A2E;background:#FFF;min-height:110px;resize:vertical}
.ta:focus,.fi:focus{outline:2px solid #0097A7;outline-offset:1px;border-color:#0097A7}
.fi{width:100%;height:44px;border:1px solid #D3DFE0;border-radius:12px;padding:0 14px;font:inherit;font-size:15px;color:#0F2A2E;background:#FFF}
.lbl{display:block;margin-bottom:6px;font-size:13.5px;font-weight:700;color:#2E4A4E}
.err{margin:6px 0 0;font-size:13px;color:#B23149}
.drow{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #EDF3F4;font-size:14.5px}
.drow:last-child{border-bottom:0}
.rel{display:block;padding:12px 0;border-bottom:1px solid #EDF3F4;text-decoration:none;color:#0F2A2E}.rel:last-child{border-bottom:0}.rel:hover b{color:#00695C}
.cm{display:flex;gap:14px}
.cm-av{width:40px;height:40px;border-radius:99px;background:#0097A7;color:#FFF;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex:none}
.cm-body{flex:1;min-width:0}
.cm-bubble{background:#F6F9F9;border:1px solid #E6EEEF;border-radius:16px;padding:14px 16px}
.cm-act{display:inline-flex;align-items:center;gap:6px;min-height:36px;padding:0 4px;border:0;background:none;color:#556B6E;font:inherit;font-size:13.5px;font-weight:600;cursor:pointer;text-decoration:none}
.cm-act:hover{color:#00363B}
.tagc{display:inline-flex;align-items:center;height:32px;padding:0 12px;border-radius:99px;background:#EEF5F5;color:#2E4A4E;font-size:13px;font-weight:600;text-decoration:none}.tagc:hover{background:#DDF1F3;color:#00363B}
@endpush

@section('content')
@php
    $author = $resource->author;
    $authorName = $author?->full_name ?? 'MNCH team';
    $initials = collect(preg_split('/\s+/', trim($authorName)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $cat = $resource->category;
    $rootComments = $resource->comments->where('parent_id', null);
    $canDownload = $resource->is_downloadable && ($resource->file_path || $resource->hasPrimaryFile());

    $previewFile = $resource->primaryFile;
    $fileType = $previewFile?->file_type ?? '';
    $isPdf = $fileType === 'application/pdf';
    $isImage = str_starts_with($fileType, 'image/');
    $isVideo = str_starts_with($fileType, 'video/');
    $isAudio = str_starts_with($fileType, 'audio/');
    $isOffice = in_array($fileType, [
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
    $hasPreview = $previewFile && $previewFile->exists();
    $previewUrl = $hasPreview ? route('resources.preview', $resource->slug) : null;
    $officeViewerUrl = ($hasPreview && $isOffice)
        ? 'https://view.officeapps.live.com/op/embed.aspx?src='.urlencode(\App\Http\Controllers\Admin\ResourceFileController::signedTempUrl($previewFile))
        : null;
    $cover = $resource->featured_image ? Storage::disk('thumbnails')->url($resource->featured_image) : null;
@endphp

<div x-data="resourcePage">
<section style="position:relative;overflow:hidden;background:var(--deep);color:#FFF">
<div aria-hidden="true" style="position:absolute;inset:0;background-image:radial-gradient(rgba(127,220,230,.15) 1px,transparent 1.3px);background-size:22px 22px"></div>
<div aria-hidden="true" style="position:absolute;right:-120px;top:-160px;width:520px;height:520px;border-radius:50%;border:2px solid rgba(127,220,230,.14)"></div>
<div class="wrap" style="position:relative;padding-top:28px;padding-bottom:40px">
<nav aria-label="Breadcrumb" style="display:flex;align-items:center;gap:8px;font-size:13px;color:#BFE9EE;flex-wrap:wrap"><a href="{{ route('home') }}" style="color:#BFE9EE;text-decoration:none">Home</a>@include('site.partials.icon', ['name' => 'chevron-right', 'size' => 14])<a href="{{ route('resources.index') }}" style="color:#BFE9EE;text-decoration:none">Resources</a>@if ($cat)@include('site.partials.icon', ['name' => 'chevron-right', 'size' => 14])<a href="{{ route('resources.category', $cat->slug) }}" style="color:#BFE9EE;text-decoration:none">{{ $cat->name }}</a>@endif</nav>
<div class="rise" style="margin-top:24px;max-width:860px">
<div style="display:flex;gap:8px;flex-wrap:wrap">
@if ($cat)<a class="chip" href="{{ route('resources.category', $cat->slug) }}" style="background:rgba(255,255,255,.14);color:#FFF;text-decoration:none">{{ $cat->name }}</a>@endif
@if ($resource->resourceType)<span class="chip" style="background:rgba(255,255,255,.14);color:#FFF">{{ $resource->resourceType->name }}</span>@endif
@if ($resource->difficulty_level)<span class="chip" style="background:#FDF3E3;color:#8A5000">{{ ucfirst($resource->difficulty_level) }}</span>@endif
@if ($resource->is_featured)<span class="chip" style="background:#FFE9A8;color:#6B4A00">Featured</span>@endif
</div>
<h1 class="disp" style="margin:14px 0 0;font-size:clamp(30px,4.2vw,48px);font-weight:800;line-height:1.08">{{ $resource->title }}</h1>
@if ($resource->excerpt)<p style="margin:14px 0 0;font-size:18px;color:#D4EEF1;max-width:720px">{{ $resource->excerpt }}</p>@endif
<div style="display:flex;align-items:center;gap:12px;margin-top:22px;flex-wrap:wrap">
<span class="cm-av" style="width:44px;height:44px">{{ $initials ?: 'M' }}</span>
<span style="line-height:1.3"><b style="display:block">{{ $authorName }}</b><span style="font-size:13.5px;color:#BFE9EE">Published {{ $resource->published_at?->format('j M Y') }}@if ($resource->read_time > 0) · {{ $resource->read_time }} min read @endif</span></span>
</div>
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:24px">
@auth
<button type="button" class="abtn" :class="liked && 'on'" @click="toggleLike()" :aria-pressed="liked">@include('site.partials.icon', ['name' => 'heart', 'size' => 18])<span x-text="likeCount" class="num"></span><span class="sr">Likes</span></button>
<button type="button" class="abtn" :class="bookmarked && 'on'" @click="toggleBookmark()" :aria-pressed="bookmarked">@include('site.partials.icon', ['name' => 'bookmark', 'size' => 18])<span x-text="bookmarked ? 'Saved' : 'Save'"></span></button>
@else
<a class="abtn" href="{{ $loginUrl }}">@include('site.partials.icon', ['name' => 'heart', 'size' => 18])<span class="num">{{ $resource->like_count }}</span><span class="sr">Likes. Sign in to like</span></a>
@endauth
@if ($canDownload)<a class="btn btn-pri btn-lg" href="{{ route('resources.download', $resource->slug) }}">@include('site.partials.icon', ['name' => 'download', 'size' => 18])Download</a>@endif
<button type="button" class="abtn" @click="shareResource()">@include('site.partials.icon', ['name' => 'share', 'size' => 18])Share</button>
</div>
</div>
</div>
</section>

<div class="wrap" style="padding-top:32px;padding-bottom:72px">
<div style="display:flex;flex-wrap:wrap;gap:28px;align-items:flex-start">
<main style="flex:999 1 620px;min-width:0;display:flex;flex-direction:column;gap:22px">

@if ($cover)
<img src="{{ $cover }}" alt="" style="width:100%;max-height:420px;object-fit:cover;border-radius:20px;border:1px solid #E1E9EA">
@endif

@if (trim(strip_tags((string) $resource->content)) !== '' || $resource->learning_outcomes || $resource->prerequisites || $resource->external_url)
<article class="rs-card">
@if (trim(strip_tags((string) $resource->content)) !== '')<div class="prose">{!! $resource->content !!}</div>@endif

@if ($resource->learning_outcomes && count($resource->learning_outcomes) > 0)
<div style="margin-top:24px;padding:20px;background:#E7F5EF;border-radius:16px">
<h2 class="disp" style="margin:0 0 10px;font-size:18px;font-weight:700;color:#0E6A4C">What you'll learn</h2>
<ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px">@foreach ($resource->learning_outcomes as $o)<li style="display:flex;gap:10px;color:#0E4D38">@include('site.partials.icon', ['name' => 'check', 'size' => 18, 'stroke' => '#12805C', 'style' => 'flex:none;margin-top:2px'])<span>{{ $o }}</span></li>@endforeach</ul>
</div>
@endif

@if ($resource->prerequisites && count($resource->prerequisites) > 0)
<div style="margin-top:16px;padding:20px;background:#EAF1FA;border-radius:16px">
<h2 class="disp" style="margin:0 0 10px;font-size:18px;font-weight:700;color:#174F82">Before you start</h2>
<ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px">@foreach ($resource->prerequisites as $p)<li style="display:flex;gap:10px;color:#173F66">@include('site.partials.icon', ['name' => 'chevron-right', 'size' => 18, 'stroke' => '#1D6FB8', 'style' => 'flex:none;margin-top:2px'])<span>{{ $p }}</span></li>@endforeach</ul>
</div>
@endif

@if ($resource->external_url)
<div style="margin-top:16px;padding:20px;background:#F0EDFB;border-radius:16px">
<h2 class="disp" style="margin:0 0 6px;font-size:18px;font-weight:700;color:#4B35A0">Available online</h2>
<p style="margin:0 0 14px;color:#3A2A82">This resource is hosted on an external site.</p>
<a class="btn btn-pri" href="{{ $resource->external_url }}" target="_blank" rel="noopener noreferrer">Open resource @include('site.partials.icon', ['name' => 'chevron-right', 'size' => 16])</a>
</div>
@endif
</article>
@endif

@if ($hasPreview && ($isPdf || $isImage || $isVideo || $isAudio || $isOffice))
<section class="rs-card" style="padding:0;overflow:hidden" aria-label="File preview">
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:16px 22px;border-bottom:1px solid #EDF3F4"><h2 class="disp" style="margin:0;font-size:18px;font-weight:700">Preview</h2>@if ($canDownload)<a href="{{ route('resources.download', $resource->slug) }}" style="font-weight:700;font-size:14px">Download</a>@endif</div>
<div style="padding:14px">
@if ($isPdf)<iframe src="{{ $previewUrl }}" title="{{ $resource->title }}" style="width:100%;height:72vh;border:1px solid #E1E9EA;border-radius:12px"></iframe>
@elseif ($isImage)<div style="display:flex;justify-content:center;background:#F6F9F9;border-radius:12px;padding:14px"><img src="{{ $previewUrl }}" alt="{{ $resource->title }}" style="max-height:65vh;object-fit:contain;border-radius:8px"></div>
@elseif ($isVideo)<video controls style="width:100%;max-height:62vh;border-radius:12px;background:#000"><source src="{{ $previewUrl }}" type="{{ $fileType }}"></video>
@elseif ($isAudio)<audio controls style="width:100%"><source src="{{ $previewUrl }}" type="{{ $fileType }}"></audio>
@elseif ($isOffice && $officeViewerUrl)<iframe src="{{ $officeViewerUrl }}" title="{{ $resource->title }}" allowfullscreen style="width:100%;height:72vh;border:0;border-radius:12px"></iframe><p class="muted" style="font-size:12px;text-align:center;margin:8px 0 0">Powered by Microsoft Office Online, needs internet access.</p>
@endif
</div>
</section>
@endif

<section class="rs-card" id="comments" aria-label="Comments">
<h2 class="disp rs-h num">Comments ({{ $rootComments->count() }})</h2>
@if (session('success'))<div role="status" style="margin-bottom:16px;padding:12px 16px;border-radius:12px;background:#E7F5EF;color:#0E4D38;font-weight:600">{{ session('success') }}</div>@endif
@auth
<form action="{{ route('resources.comment.store', $resource->slug) }}" method="POST" style="margin-bottom:26px">
@csrf
<label class="lbl" for="content">Add a comment</label>
<textarea class="ta" id="content" name="content" required minlength="3" maxlength="1000" placeholder="Share your thoughts about this resource">{{ old('content') }}</textarea>
@error('content')<p class="err">{{ $message }}</p>@enderror
<button class="btn btn-pri" type="submit" style="margin-top:12px">Post comment</button>
</form>
@else
<form action="{{ route('resources.comment.guest', $resource->slug) }}" method="POST" style="margin-bottom:26px;padding:20px;background:#F6F9F9;border:1px solid #E6EEEF;border-radius:16px">
@csrf
<p style="margin:0 0 16px;color:#3D5558">Comment as a guest (reviewed before it appears), or <a href="{{ $loginUrl }}">sign in</a>.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:14px;margin-bottom:14px">
<div><label class="lbl" for="author_name">Name</label><input class="fi" id="author_name" type="text" name="author_name" value="{{ old('author_name') }}" required maxlength="255">@error('author_name')<p class="err">{{ $message }}</p>@enderror</div>
<div><label class="lbl" for="author_email">Email (not shown)</label><input class="fi" id="author_email" type="email" name="author_email" value="{{ old('author_email') }}" required maxlength="255">@error('author_email')<p class="err">{{ $message }}</p>@enderror</div>
</div>
<label class="lbl" for="guest_content">Comment</label>
<textarea class="ta" id="guest_content" name="content" required minlength="3" maxlength="1000">{{ old('content') }}</textarea>
@error('content')<p class="err">{{ $message }}</p>@enderror
<div style="position:absolute;left:-9999px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
@if (config('services.recaptcha.site_key'))
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}" style="margin-top:12px"></div>
@endif
@error('g-recaptcha-response')<p class="err">{{ $message }}</p>@enderror
<button class="btn btn-pri" type="submit" style="margin-top:14px">Submit comment</button>
</form>
@endauth

@if ($rootComments->count() > 0)
<div style="display:flex;flex-direction:column;gap:22px">
@foreach ($rootComments as $comment)@include('frontend.resources.comment', ['comment' => $comment, 'resource' => $resource, 'depth' => 0])@endforeach
</div>
@else
<div style="text-align:center;padding:28px 0" class="muted">No comments yet. Be the first to share your thoughts.</div>
@endif
</section>
</main>

<aside style="flex:1 1 300px;min-width:min(300px,100%);display:flex;flex-direction:column;gap:22px;position:sticky;top:96px">
<section class="rs-card" style="padding:22px" aria-label="Details">
<h2 class="disp rs-h" style="font-size:18px">Details</h2>
<div class="drow"><span class="muted">Views</span><b class="num">{{ number_format($resource->view_count) }}</b></div>
@if ($resource->is_downloadable)<div class="drow"><span class="muted">Downloads</span><b class="num">{{ number_format($resource->download_count) }}</b></div>@endif
<div class="drow"><span class="muted">Likes</span><b class="num" x-text="likeCount">{{ number_format($resource->like_count) }}</b></div>
@if ($resource->file_size)<div class="drow"><span class="muted">File size</span><b>{{ $resource->formatted_file_size }}</b></div>@endif
@if ($resource->duration)<div class="drow"><span class="muted">Duration</span><b>{{ $resource->duration }}</b></div>@endif
@if ($canDownload)<a class="btn btn-pri btn-lg" href="{{ route('resources.download', $resource->slug) }}" style="width:100%;margin-top:16px">@include('site.partials.icon', ['name' => 'download', 'size' => 18])Download resource</a>@endif
</section>

@if ($resource->tags->count() > 0)
<section class="rs-card" style="padding:22px" aria-label="Tags">
<h2 class="disp rs-h" style="font-size:18px">Tags</h2>
<div style="display:flex;gap:8px;flex-wrap:wrap">@foreach ($resource->tags as $tag)<a class="tagc" href="{{ route('resources.tag', $tag->slug) }}">#{{ $tag->name }}</a>@endforeach</div>
</section>
@endif

@if ($relatedResources->count() > 0)
<section class="rs-card" style="padding:22px" aria-label="Related resources">
<h2 class="disp rs-h" style="font-size:18px">Related resources</h2>
@foreach ($relatedResources as $related)
<a class="rel" href="{{ route('resources.show', $related->slug) }}"><b style="display:block;line-height:1.3">{{ $related->title }}</b><span class="muted num" style="font-size:13px">{{ $related->resourceType->name ?? 'Resource' }} · {{ number_format($related->view_count) }} views</span></a>
@endforeach
@if ($cat)<a href="{{ route('resources.category', $cat->slug) }}" style="display:inline-block;margin-top:12px;font-weight:700;font-size:14px">More in {{ $cat->name }} →</a>@endif
</section>
@endif
</aside>
</div>
</div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('resourcePage', () => ({
            liked: {{ in_array('like', $userInteractions) ? 'true' : 'false' }},
            bookmarked: {{ in_array('bookmark', $userInteractions) ? 'true' : 'false' }},
            likeCount: {{ (int) $resource->like_count }},
            reply: null,
            edit: null,

            async post(url) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            },

            async toggleLike() {
                try {
                    const data = await this.post(@json(route('resources.like', $resource->slug)));
                    this.liked = data.liked;
                    this.likeCount = data.like_count;
                    this.showToast(this.liked ? 'Resource liked' : 'Like removed', 'success');
                } catch (error) {
                    this.showToast('Something went wrong. Please try again.', 'error');
                }
            },

            async toggleBookmark() {
                try {
                    const data = await this.post(@json(route('resources.bookmark', $resource->slug)));
                    this.bookmarked = data.bookmarked;
                    this.showToast(data.message || (this.bookmarked ? 'Saved to your bookmarks' : 'Removed from bookmarks'), 'success');
                } catch (error) {
                    this.showToast('Something went wrong. Please try again.', 'error');
                }
            },

            toggleReplyForm(id) { this.edit = null; this.reply = this.reply === id ? null : id; },
            editComment(id) { this.reply = null; this.edit = id; },
            cancelEdit() { this.edit = null; },

            shareResource() {
                const data = { title: @json($resource->title), text: @json((string) $resource->excerpt), url: window.location.href };
                if (navigator.share) {
                    navigator.share(data).catch(() => {});
                } else if (navigator.clipboard) {
                    navigator.clipboard.writeText(data.url).then(
                        () => this.showToast('Link copied to clipboard', 'success'),
                        () => window.prompt('Copy this link', data.url)
                    );
                } else {
                    window.prompt('Copy this link', data.url);
                }
            },

            showToast(message, type = 'info') {
                const toast = document.createElement('div');
                toast.setAttribute('role', 'status');
                toast.style.cssText = 'position:fixed;top:88px;right:16px;z-index:60;padding:12px 18px;border-radius:12px;color:#fff;font-weight:600;box-shadow:0 14px 30px -14px rgba(0,0,0,.5);background:' + (type === 'success' ? '#12805C' : type === 'error' ? '#B23149' : '#1D6FB8');
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }
        }));
    });
</script>
@endpush
