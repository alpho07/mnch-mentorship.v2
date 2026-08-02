<form wire:submit="sendMessage($refs.messageInput.value); $refs.messageInput.value = ''" class="flex gap-2 pt-2">
    <input
        type="text"
        x-ref="messageInput"
        placeholder="Type anything — e.g. &quot;EmONC mentorship at Kisumu District Hospital, 8 mentees&quot;"
        wire:loading.attr="disabled"
        wire:target="sendMessage"
        class="fi-input flex-1 rounded-lg border-gray-300 dark:border-gray-600 text-sm"
    >
    <button
        type="submit"
        wire:loading.attr="disabled"
        wire:target="sendMessage"
        class="fi-btn fi-btn-color-primary fi-btn-size-md rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60"
    >
        <span wire:loading.remove wire:target="sendMessage">Send</span>
        <span wire:loading wire:target="sendMessage">Thinking…</span>
    </button>
</form>
