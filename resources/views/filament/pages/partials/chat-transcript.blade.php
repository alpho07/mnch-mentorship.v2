<div class="space-y-3">
    @foreach ($messages as $message)
        <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
            <div class="max-w-lg rounded-xl px-4 py-2 text-sm
                {{ $message['role'] === 'user'
                    ? 'bg-primary-600 text-white'
                    : 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100' }}">
                {{ $message['text'] }}
            </div>
        </div>
    @endforeach
</div>
