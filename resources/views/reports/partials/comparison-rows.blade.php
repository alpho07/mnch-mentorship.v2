@php
    $displayRounds = $rounds ?? [['id' => $assessment->id, 'label' => $assessment->round_display]];
    $displayFields = $fields ?? [($field ?? 'response') => null];
    $useBadge = $badge ?? false;
    $header = $labelHeader ?? 'Question';
@endphp
<table style="width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 24px;">
    <thead>
        <tr>
            <th @if(count($displayFields) > 1) rowspan="2" @endif style="background: #f3f4f6; padding: 12px; text-align: left; border: 1px solid #d1d5db; vertical-align: bottom;">{{ $header }}</th>
            @foreach($displayRounds as $round)
                <th @if(count($displayFields) > 1) colspan="{{ count($displayFields) }}" @endif style="background: #f3f4f6; padding: 12px; text-align: center; border: 1px solid #d1d5db;">{{ $round['label'] }}</th>
            @endforeach
        </tr>
        @if(count($displayFields) > 1)
            <tr>
                @foreach($displayRounds as $round)
                    @foreach($displayFields as $fieldLabel)
                        <th style="background: #f3f4f6; padding: 8px; text-align: center; border: 1px solid #d1d5db; font-size: 12px;">{{ $fieldLabel }}</th>
                    @endforeach
                @endforeach
            </tr>
        @endif
    </thead>
    <tbody>
        @foreach($rows as $row)
            <tr>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb;">{{ $row['label'] }}</td>
                @foreach($displayRounds as $round)
                    @foreach($displayFields as $fieldKey => $fieldLabel)
                        @php $value = $row['values'][$round['id']][$fieldKey] ?? null; @endphp
                        <td style="padding: 10px 12px; border: 1px solid #e5e7eb; text-align: center;">
                            @if($value === null)
                                <span style="color:#9ca3af;">&mdash;</span>
                            @elseif($useBadge)
                                <span class="badge badge-{{ $value === 'Yes' ? 'green' : 'red' }}">{{ $value }}</span>
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    @endforeach
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
