<?php

namespace Tests\Unit\FormKernel;

use App\Services\FormKernel\GroupedFieldRenderer;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Tests\TestCase;

class GroupedFieldRendererTest extends TestCase
{
    public function test_table_header_resolves_label_from_a_group_wrapped_field(): void
    {
        $radio = Radio::make('question_response_1')->label('Available');
        $group = Group::make([$radio]);

        $rows = [
            [
                'header' => 'Form',
                'label' => 'MoH 204 A',
                'fields' => [$group],
            ],
        ];

        $fieldset = GroupedFieldRenderer::buildTableFieldset('Data Collection Tools', $rows);
        $headerCell = $fieldset->getChildComponents()[1];

        $this->assertSame('Available', $headerCell->getLabel());
    }

    public function test_table_header_still_resolves_label_from_a_bare_field(): void
    {
        $input = TextInput::make('question_response_2')->label('Count');

        $rows = [
            [
                'header' => 'Form',
                'label' => 'Some Row',
                'fields' => [$input],
            ],
        ];

        $fieldset = GroupedFieldRenderer::buildTableFieldset('Bare Field Table', $rows);
        $headerCell = $fieldset->getChildComponents()[1];

        $this->assertSame('Count', $headerCell->getLabel());
    }
}
