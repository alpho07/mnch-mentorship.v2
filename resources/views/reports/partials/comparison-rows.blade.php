@php
    $displayRounds = $rounds ?? [['id' => $assessment->id, 'label' => $assessment->round_display]];
    $displayFields = $fields ?? [($field ?? 'response') => null];
    $useBadge = $badge ?? false;
    $header = $labelHeader ?? 'Question';
    $numbered = $numbered ?? false;

    // Sequential "1. / 2. / 3." numbering, with "a) / b) / c)" lettering
    // for a run of 2+ consecutive rows sharing the same group (question
    // groups already carried through since Task 5's comparison merge —
    // same LineItemGrouper convention Health Products' commodity list
    // already uses). indent_level >= 1 always indents visually, even for
    // a lone follow-up question with no group to letter it against.
    $displayRows = $rows;

    if ($numbered) {
        $annotated = \App\Services\FormKernel\LineItemGrouper::annotate(
            $rows,
            fn ($row) => $row['group'] ?? null,
            fn ($row) => (int) ($row['indent_level'] ?? 0),
        );

        $number = 0;
        $displayRows = [];

        foreach ($annotated as ['item' => $row, 'letter' => $letter]) {
            if ($letter !== null) {
                $row['numbered_label'] = "{$letter}) {$row['label']}";
            } else {
                $number++;
                $row['numbered_label'] = "{$number}. {$row['label']}";
            }
            $row['indent'] = (int) ($row['indent_level'] ?? 0) >= 1;
            $displayRows[] = $row;
        }
    }
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
        @foreach($displayRows as $row)
            <tr>
                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; {{ ($row['indent'] ?? false) ? 'padding-left: 28px;' : '' }}">{{ $numbered ? $row['numbered_label'] : $row['label'] }}</td>
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
