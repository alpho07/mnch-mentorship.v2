<?php

namespace Tests\Unit\FormKernel;

use App\Services\FormKernel\LineItemGrouper;
use PHPUnit\Framework\TestCase;

class LineItemGrouperTest extends TestCase
{
    private function item(string $label, ?string $group, int $indent): object
    {
        return (object) ['label' => $label, 'group' => $group, 'indent' => $indent];
    }

    public function test_letters_a_run_of_indented_siblings_sharing_a_group(): void
    {
        $items = [
            $this->item('Fr-6', 'Suction catheter sizes', 1),
            $this->item('Fr-8', 'Suction catheter sizes', 1),
            $this->item('Fr-10', 'Suction catheter sizes', 1),
        ];

        $annotated = LineItemGrouper::annotate($items, fn ($i) => $i->group, fn ($i) => $i->indent);

        $this->assertSame(['a', 'b', 'c'], array_column($annotated, 'letter'));
        $this->assertSame([true, false, false], array_column($annotated, 'is_group_start'));
    }

    public function test_a_lone_indented_item_gets_no_letter(): void
    {
        $items = [$this->item('Only child', 'Solo group', 1)];

        $annotated = LineItemGrouper::annotate($items, fn ($i) => $i->group, fn ($i) => $i->indent);

        $this->assertNull($annotated[0]['letter']);
    }

    public function test_unindented_items_never_get_a_letter_even_if_grouped(): void
    {
        $items = [
            $this->item('A', 'Some group', 0),
            $this->item('B', 'Some group', 0),
        ];

        $annotated = LineItemGrouper::annotate($items, fn ($i) => $i->group, fn ($i) => $i->indent);

        $this->assertSame([null, null], array_column($annotated, 'letter'));
    }

    public function test_two_adjacent_different_groups_letter_independently(): void
    {
        $items = [
            $this->item('26G', 'IV cannula gauges', 1),
            $this->item('24G', 'IV cannula gauges', 1),
            $this->item('2cc', 'Syringe sizes', 1),
            $this->item('5cc', 'Syringe sizes', 1),
        ];

        $annotated = LineItemGrouper::annotate($items, fn ($i) => $i->group, fn ($i) => $i->indent);

        $this->assertSame(['a', 'b', 'a', 'b'], array_column($annotated, 'letter'));
    }

    public function test_letter_for_wraps_past_z_excel_style(): void
    {
        $this->assertSame('a', LineItemGrouper::letterFor(0));
        $this->assertSame('z', LineItemGrouper::letterFor(25));
        $this->assertSame('aa', LineItemGrouper::letterFor(26));
    }
}
