<div class="space-y-4">
    @if($checklist->description)
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $checklist->description }}</p>
    @endif

    @php $hasQty = $checklist->items->contains(fn ($item) => $item->qty !== null); @endphp

    @foreach($checklist->items->groupBy('group_label') as $groupLabel => $items)
        @if($groupLabel)
            <h4 class="font-semibold text-sm uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $groupLabel }}</h4>
        @endif
        <table class="w-full text-sm">
            <tbody>
                @foreach($items as $item)
                    <tr class="border-b border-gray-100 dark:border-gray-700">
                        <td class="py-1">{{ $item->label }}</td>
                        @if($hasQty)
                            <td class="py-1 text-right text-gray-500 dark:text-gray-400">{{ $item->qty }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</div>
