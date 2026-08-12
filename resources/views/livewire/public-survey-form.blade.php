<div>
    @if ($submitted)
        <div class="rounded-lg bg-green-50 p-6 text-center">
            <h2 class="text-lg font-semibold text-green-800">Thank you!</h2>
            <p class="mt-1 text-sm text-green-700">Your response has been submitted.</p>
        </div>
    @else
        <form wire:submit="submit">
            {{ $this->form }}

            <button type="submit" class="fi-btn fi-btn-color-primary mt-6">
                Submit
            </button>
        </form>
    @endif
</div>
