<x-filament::section>
    <div class="space-y-4">
        <div class="flex items-start gap-3">
            <x-heroicon-o-information-circle class="w-6 h-6 text-primary-600" />
            <div>
                <h3 class="text-base font-semibold">
                    Prepare this mentorship before starting classes
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Identify facility gaps first, review the mentorship manuals, then create the class, assign modules, and confirm the mentee roster. Each mentee should have a name, email, phone, cadre, and department before invitations or attendance links are sent.
                </p>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <a href="{{ url('/resources/infant-child-mentorship-manual') }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-gray-200 p-3 text-sm hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:hover:bg-gray-800">
                <div class="font-semibold">Infant and Child Mentorship Manual</div>
                <div class="text-gray-600 dark:text-gray-400">Plan what to mentor and how to deliver it.</div>
            </a>
            <a href="https://mnchkenyamentorship.org/resources/newborn-mentorship-mentors-manual" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-gray-200 p-3 text-sm hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:hover:bg-gray-800">
                <div class="font-semibold">Newborn Mentorship Mentor's Manual</div>
                <div class="text-gray-600 dark:text-gray-400">Review newborn care mentorship content.</div>
            </a>
            <a href="{{ url('/resources/emonc-mentorship-manual') }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-gray-200 p-3 text-sm hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:hover:bg-gray-800">
                <div class="font-semibold">EmONC Mentorship Manual</div>
                <div class="text-gray-600 dark:text-gray-400">Review the EmONC knowledge pack and participant manual.</div>
            </a>
            <a href="{{ route('resources.search', ['q' => 'manual']) }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-gray-200 p-3 text-sm hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:hover:bg-gray-800">
                <div class="font-semibold">All Mentorship Manuals</div>
                <div class="text-gray-600 dark:text-gray-400">Search the resource library for manuals.</div>
            </a>
            <a href="{{ route('resources.search', ['q' => 'MNCH guidelines']) }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-gray-200 p-3 text-sm hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:hover:bg-gray-800">
                <div class="font-semibold">MNCH Guidelines</div>
                <div class="text-gray-600 dark:text-gray-400">Review protocols before session delivery.</div>
            </a>
        </div>
    </div>
</x-filament::section>
