<?php

namespace Tests\Unit;

use App\Services\Chat\FuzzyOptionMatcher;
use Tests\TestCase;

class FuzzyOptionMatcherTest extends TestCase
{
    public function test_exact_query_returns_that_option_first(): void
    {
        $options = [
            1 => 'Chuka County Referral Hospital',
            2 => 'Chuka Sub-District Hospital',
            3 => 'Kisumu District Hospital',
        ];

        $results = FuzzyOptionMatcher::search($options, 'Chuka County Referral Hospital');

        $this->assertSame(1, $results[0]['id']);
    }

    public function test_a_typo_still_finds_the_intended_option(): void
    {
        $options = [
            1 => 'Chuka County Referral Hospital',
            2 => 'Kisumu District Hospital',
        ];

        // Missing the second "u" — a real typo, not an exact or substring match.
        $results = FuzzyOptionMatcher::search($options, 'Chuka Refferal Hospital');

        $this->assertContains(1, array_column($results, 'id'));
    }

    public function test_a_partial_name_matching_multiple_options_returns_all_of_them(): void
    {
        $options = [
            1 => 'Chuka County Referral Hospital',
            2 => 'Chuka Sub-District Hospital',
            3 => 'Kisumu District Hospital',
        ];

        $results = FuzzyOptionMatcher::search($options, 'Chuka');

        $ids = array_column($results, 'id');
        $this->assertContains(1, $ids);
        $this->assertContains(2, $ids);
        $this->assertNotContains(3, $ids);
    }

    public function test_completely_unrelated_input_returns_nothing(): void
    {
        $options = [
            1 => 'Chuka County Referral Hospital',
            2 => 'Kisumu District Hospital',
        ];

        $results = FuzzyOptionMatcher::search($options, 'zzzzzzzzzz nonsense');

        $this->assertSame([], $results);
    }

    public function test_results_are_capped_at_eight(): void
    {
        $options = [];
        for ($i = 1; $i <= 20; $i++) {
            $options[$i] = "Chuka Hospital Branch {$i}";
        }

        $results = FuzzyOptionMatcher::search($options, 'Chuka Hospital');

        $this->assertLessThanOrEqual(8, count($results));
    }

    public function test_empty_query_returns_nothing(): void
    {
        $options = [1 => 'Chuka County Referral Hospital'];

        $this->assertSame([], FuzzyOptionMatcher::search($options, ''));
        $this->assertSame([], FuzzyOptionMatcher::search($options, '   '));
    }

    public function test_empty_options_returns_nothing(): void
    {
        $this->assertSame([], FuzzyOptionMatcher::search([], 'Chuka'));
    }
}
