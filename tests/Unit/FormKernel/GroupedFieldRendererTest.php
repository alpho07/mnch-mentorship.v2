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

    /**
     * Regression: a yes_no question's built field is a Group wrapping
     * [Radio, conditional explanation Textarea]. Group does respond to
     * hiddenLabel(), but it never renders a label of its own, so calling
     * only that was a no-op — the inner Radio's genuine label
     * ("Available"/"Completeness") kept rendering on every single data
     * row instead of just once in the header.
     */
    public function test_data_row_labels_are_hidden_even_when_the_field_is_a_group_wrapper(): void
    {
        $radio = Radio::make('question_response_1')->label('Available');
        $group = Group::make([$radio]);

        $rows = [
            [
                'header' => 'Form',
                'label' => 'MoH 204 A',
                'fields' => [Radio::make('header_only')->label('Available')],
            ],
            [
                'header' => 'Form',
                'label' => 'NCD Register',
                'fields' => [$group],
            ],
        ];

        GroupedFieldRenderer::buildTableFieldset('Data Collection Tools', $rows);

        $this->assertTrue($radio->isLabelHidden());
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

    public function test_table_fieldset_accepts_a_shared_visibility_closure(): void
    {
        $rows = [
            ['header' => 'Form', 'label' => 'MoH 204 A', 'fields' => [TextInput::make('q1')->label('Available')]],
        ];

        $hiddenFieldset = GroupedFieldRenderer::buildTableFieldset('Data Collection Tools', $rows, fn () => false);
        $this->assertFalse($hiddenFieldset->isVisible());

        $visibleFieldset = GroupedFieldRenderer::buildTableFieldset('Data Collection Tools', $rows, fn () => true);
        $this->assertTrue($visibleFieldset->isVisible());

        $defaultFieldset = GroupedFieldRenderer::buildTableFieldset('Data Collection Tools', $rows);
        $this->assertTrue($defaultFieldset->isVisible());
    }

    public function test_render_runs_applies_the_first_rows_visible_closure_to_the_whole_table(): void
    {
        $visible = fn () => false;

        $runs = [
            [
                'group' => 'Data Collection Tools & Registers|Form|Form A',
                'fields' => [TextInput::make('q1')->label('Available')],
                'visible' => $visible,
            ],
            [
                'group' => 'Data Collection Tools & Registers|Form|Form B',
                'fields' => [TextInput::make('q2')->label('Available')],
                'visible' => $visible,
            ],
        ];

        $fields = GroupedFieldRenderer::renderRuns($runs);

        $this->assertCount(1, $fields);
        $this->assertFalse($fields[0]->isVisible());
    }
}
