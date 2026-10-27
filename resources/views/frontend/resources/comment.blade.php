@php
    $who = $comment->user ? $comment->user->full_name : $comment->author_name;
    $ini = collect(preg_split('/\s+/', trim((string) $who)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
    $mine = auth()->check() && $comment->user_id === auth()->id();
    $isAuthor = $comment->user && $comment->user->id === $resource->author_id;
@endphp
<div class="cm" id="comment-{{ $comment->id }}" style="margin-left:{{ min($depth, 3) * 18 }}px">
<span class="cm-av" style="background:{{ $comment->user ? '#0097A7' : '#7B8E91' }}">{{ $ini }}</span>
<div class="cm-body">
<div class="cm-bubble">
<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
<span style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><b>{{ $who }}</b>
@unless ($comment->user)<span class="chip chip-sm" style="background:#EAF1FA;color:#174F82">Guest</span>@endunless
@if ($isAuthor)<span class="chip chip-sm" style="background:#E7F5EF;color:#0E6A4C">Author</span>@endif</span>
<time class="muted" style="font-size:13px" datetime="{{ $comment->created_at->toISOString() }}">{{ $comment->created_at->diffForHumans() }}</time>
</div>
<div id="comment-content-{{ $comment->id }}" x-show="edit !== {{ $comment->id }}" style="margin-top:8px;white-space:pre-line;overflow-wrap:anywhere">{{ $comment->content }}</div>
@if ($mine)
<form action="{{ route('resources.comment.update', $comment) }}" method="POST" x-show="edit === {{ $comment->id }}" x-cloak style="margin-top:10px">
@csrf @method('PUT')
<label class="sr" for="edit-{{ $comment->id }}">Edit comment</label>
<textarea class="ta" id="edit-{{ $comment->id }}" name="content" rows="3" required minlength="3" maxlength="1000">{{ $comment->content }}</textarea>
<div style="display:flex;gap:8px;margin-top:10px"><button class="btn btn-pri" type="submit" style="min-height:40px">Save</button><button class="btn btn-ghost" type="button" @click="cancelEdit()" style="min-height:40px">Cancel</button></div>
</form>
@endif
</div>
<div style="display:flex;gap:14px;align-items:center;margin-top:4px;flex-wrap:wrap">
@auth
@if ($depth < 3)<button type="button" class="cm-act" @click="toggleReplyForm({{ $comment->id }})">Reply</button>@endif
@if ($mine)
<button type="button" class="cm-act" @click="editComment({{ $comment->id }})">Edit</button>
<form action="{{ route('resources.comment.destroy', $comment) }}" method="POST" style="margin:0" onsubmit="return confirm('Delete this comment?')">@csrf @method('DELETE')<button type="submit" class="cm-act" style="color:#B23149">Delete</button></form>
@endif
@else
<a class="cm-act" href="{{ $loginUrl }}">Sign in to reply</a>
@endauth
</div>
@auth
@if ($depth < 3)
<form action="{{ route('resources.comment.store', $resource->slug) }}" method="POST" x-show="reply === {{ $comment->id }}" x-cloak style="margin-top:8px">
@csrf
<input type="hidden" name="parent_id" value="{{ $comment->id }}">
<label class="sr" for="reply-{{ $comment->id }}">Write a reply</label>
<textarea class="ta" id="reply-{{ $comment->id }}" name="content" rows="3" required minlength="3" maxlength="1000" placeholder="Write a reply"></textarea>
<div style="display:flex;gap:8px;margin-top:10px"><button class="btn btn-pri" type="submit" style="min-height:40px">Reply</button><button class="btn btn-ghost" type="button" @click="toggleReplyForm({{ $comment->id }})" style="min-height:40px">Cancel</button></div>
</form>
@endif
@endauth
@if ($comment->replies && $comment->replies->count() > 0)
<div style="display:flex;flex-direction:column;gap:18px;margin-top:16px">
@foreach ($comment->replies as $reply)@include('frontend.resources.comment', ['comment' => $reply, 'resource' => $resource, 'depth' => $depth + 1])@endforeach
</div>
@endif
</div>
</div>
