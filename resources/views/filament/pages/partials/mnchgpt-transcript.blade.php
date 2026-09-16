{{-- animate-bounce isn't in this panel's compiled theme CSS (it only scans
     Filament's own package views, not this app's custom pages), so the
     "thinking" dots below define the animation inline instead of relying
     on the Tailwind utility class. --}}
<style>
    @keyframes mnchgpt-bounce {
        0%, 100% { transform: translateY(-25%); animation-timing-function: cubic-bezier(0.8, 0, 1, 1); }
        50% { transform: none; animation-timing-function: cubic-bezier(0, 0, 0.2, 1); }
    }
    .mnchgpt-bounce-dot { animation: mnchgpt-bounce 1s infinite; }
</style>

<div class="space-y-4">
    @foreach ($messages as $index => $message)
        <div wire:key="mnchgpt-msg-{{ $index }}" class="flex items-end gap-2 {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
            @unless ($message['role'] === 'user')
                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-bold text-white">
                    AI
                </div>
            @endunless

            <div class="max-w-lg space-y-1 {{ $message['role'] === 'user' ? 'items-end' : 'items-start' }} flex flex-col">
                <span class="text-xs font-medium text-gray-400 dark:text-gray-500">
                    {{ $message['role'] === 'user' ? 'You' : 'MNCHGPT' }}
                </span>

                <div class="rounded-xl px-4 py-2 text-sm
                    {{ $message['role'] === 'user'
                        ? 'bg-primary-600 text-white'
                        : 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100' }}">
                    @if ($message['role'] === 'user')
                        <span class="flex items-center gap-2">
                            <span>{{ $message['text'] }}</span>
                            @if (! empty($message['slot']) && collect($this->slots())->contains('id', $message['slot']))
                                <button type="button" wire:click="editSlot('{{ $message['slot'] }}')" class="text-xs underline opacity-75 hover:opacity-100">
                                    Edit
                                </button>
                            @endif
                        </span>
                    @else
                        <div
                            x-data="{
                                plain: @js($message['text'] ?? ''),
                                escape(str) {
                                    return str
                                        .replace(/&/g, '&amp;')
                                        .replace(/</g, '&lt;')
                                        .replace(/>/g, '&gt;')
                                        .replace(/\n/g, '<br>');
                                },
                                type() {
                                    const full = this.$el.innerHTML;
                                    const src = this.plain;
                                    let i = 0;
                                    this.$el.innerHTML = '';
                                    const id = setInterval(() => {
                                        i += 3;
                                        this.$el.innerHTML = this.escape(src.slice(0, i));
                                        if (i >= src.length) {
                                            clearInterval(id);
                                            this.$el.innerHTML = full;
                                        }
                                    }, 15);
                                },
                            }"
                            x-on:mnchgpt-reply.window="if ($el.dataset.last === '1') type()"
                            data-last="{{ $loop->last ? '1' : '0' }}"
                            class="prose prose-sm dark:prose-invert max-w-none"
                        >{!! Str::markdown($message['text'] ?? '', ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach

    <div wire:loading.flex wire:target="sendMessage" class="hidden items-end gap-2 justify-start">
        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-bold text-white">
            AI
        </div>
        <div class="flex items-center gap-1 rounded-xl bg-gray-100 px-4 py-3 dark:bg-gray-800">
            <span class="mnchgpt-bounce-dot h-2 w-2 rounded-full bg-gray-400 dark:bg-gray-500" style="animation-delay: -0.3s"></span>
            <span class="mnchgpt-bounce-dot h-2 w-2 rounded-full bg-gray-400 dark:bg-gray-500" style="animation-delay: -0.15s"></span>
            <span class="mnchgpt-bounce-dot h-2 w-2 rounded-full bg-gray-400 dark:bg-gray-500"></span>
        </div>
    </div>
</div>
