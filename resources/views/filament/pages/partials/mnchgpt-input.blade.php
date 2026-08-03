{{-- disabled:cursor-wait / disabled:opacity-50 aren't in this panel's
     compiled theme CSS (it only scans Filament's own package views), so the
     disabled-state styling below is defined inline instead. --}}
<style>
    .mnchgpt-send-btn:disabled { cursor: wait; opacity: 0.5; }
</style>
<div
    x-data="{
        send() {
            const value = $refs.messageInput.value.trim();
            if (! value) return;
            $wire.sendMessage(value);
            $refs.messageInput.value = '';
            $refs.messageInput.style.height = 'auto';
        },
    }"
    class="flex items-end gap-2"
>
    <textarea
        x-ref="messageInput"
        rows="2"
        placeholder="Message MNCHGPT..."
        wire:loading.attr="disabled"
        wire:target="sendMessage"
        x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
        x-on:keydown.enter.prevent="if (! $event.shiftKey) { send() }"
        style="min-height: 3rem; max-height: 10rem;"
        class="fi-input flex-1 resize-none rounded-xl border-gray-300 py-3 text-sm leading-relaxed dark:border-gray-600"
    ></textarea>
    <button
        type="button"
        x-on:click="send()"
        wire:loading.attr="disabled"
        wire:target="sendMessage"
        class="mnchgpt-send-btn flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-600 text-white transition hover:bg-primary-700"
    >
        <span wire:loading.remove wire:target="sendMessage">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                <path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.897 28.897 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z" />
            </svg>
        </span>
        <span wire:loading wire:target="sendMessage">
            <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </span>
    </button>
</div>
