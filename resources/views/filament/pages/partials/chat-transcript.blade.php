<div class="space-y-3">
    @foreach ($messages as $message)
        <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
            <div class="max-w-lg rounded-xl px-4 py-2 text-sm flex items-center gap-2
                {{ $message['role'] === 'user'
                    ? 'bg-primary-600 text-white'
                    : 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100' }}">
                <span>{{ $message['text'] }}</span>
                @if ($message['role'] === 'user' && ! empty($message['slot']))
                    <button type="button" wire:click="editSlot('{{ $message['slot'] }}')" class="text-xs underline opacity-75 hover:opacity-100">
                        Edit
                    </button>
                @endif
            </div>
        </div>
    @endforeach
</div>
