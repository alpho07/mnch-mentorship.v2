<x-filament::page>
    <h2 class="text-2xl font-bold mb-4">Organization Unit Explorer</h2>
    <ul class="ml-4">
        @foreach($counties as $county)
            <li class="mb-2">
                <strong class="text-lg">{{ $county->name }}</strong>
                <ul class="ml-6">
                    @foreach($county->subcounties as $subcounty)
                        <li class="mb-2">
                            <span class="text-base text-blue-700">{{ $subcounty->name }}</span>
                            <ul class="ml-6">
                                @foreach($subcounty->facilities->where('is_hub', true) as $hub)
                                    <li class="mb-1">
                                        <span class="text-green-700 font-semibold">{{ $hub->name }} (Hub)</span>
                                        @if($hub->spokes->count())
                                            <ul class="ml-6">
                                                @foreach($hub->spokes as $spoke)
                                                    <li class="mb-1">
                                                        <span class="text-gray-700">{{ $spoke->name }} (Spoke)</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </li>
        @endforeach
    </ul>
</x-filament::page>
