<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $survey->name }}</title>
    @filamentStyles
    @vite('resources/css/app.css')
</head>
<body class="h-full bg-gray-50">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="text-2xl font-bold text-gray-900">{{ $survey->name }}</h1>
        @if ($survey->description)
            <p class="mt-2 text-gray-600">{{ $survey->description }}</p>
        @endif

        <div class="mt-8">
            <livewire:public-survey-form :survey-id="$survey->id" />
        </div>
    </div>

    @livewire('notifications')
    @filamentScripts
    @vite('resources/js/app.js')
</body>
</html>
