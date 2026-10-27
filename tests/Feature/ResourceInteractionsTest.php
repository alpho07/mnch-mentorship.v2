<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\ResourceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Like, bookmark, share and guest-comment behaviour on /resources/{slug}. */
class ResourceInteractionsTest extends TestCase
{
    use RefreshDatabase;

    private function resource(array $over = []): Resource
    {
        $author = User::factory()->create();
        $type = ResourceType::create(['name' => 'PDF', 'slug' => 'pdf', 'is_active' => true]);
        $cat = ResourceCategory::create(['name' => 'Cat', 'slug' => 'cat', 'is_active' => true]);

        return Resource::create($over + [
            'title' => 'Sample', 'slug' => 'sample', 'resource_type_id' => $type->id, 'category_id' => $cat->id,
            'author_id' => $author->id, 'status' => 'published', 'visibility' => 'public', 'published_at' => now()->subDay(),
        ]);
    }

    public function test_a_signed_in_user_can_like_and_unlike(): void
    {
        $r = $this->resource();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('resources.like', $r->slug))
            ->assertOk()->assertJson(['liked' => true, 'like_count' => 1]);
        $this->actingAs($user)->postJson(route('resources.like', $r->slug))
            ->assertOk()->assertJson(['liked' => false, 'like_count' => 0]);
    }

    public function test_a_signed_in_user_can_bookmark_and_unbookmark(): void
    {
        $r = $this->resource();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('resources.bookmark', $r->slug))->assertOk()->assertJson(['bookmarked' => true]);
        $this->actingAs($user)->postJson(route('resources.bookmark', $r->slug))->assertOk()->assertJson(['bookmarked' => false]);
    }

    public function test_a_guest_cannot_like_and_is_sent_to_sign_in(): void
    {
        $r = $this->resource();

        $this->postJson(route('resources.like', $r->slug))->assertUnauthorized();
        $this->post(route('resources.like', $r->slug))->assertRedirect();
    }

    public function test_the_page_shows_the_right_controls_to_guests_and_to_signed_in_users(): void
    {
        $r = $this->resource();

        $this->get(route('resources.show', $r->slug))->assertOk()
            ->assertSee('shareResource()', false)->assertSee('admin/login', false)->assertDontSee('@click="toggleLike()"', false);
        $this->actingAs(User::factory()->create())->get(route('resources.show', $r->slug))->assertOk()
            ->assertSee('@click="toggleLike()"', false)->assertSee('@click="toggleBookmark()"', false)->assertSee('shareResource()', false);
    }

    public function test_share_text_is_safe_javascript_for_titles_with_quotes(): void
    {
        $r = $this->resource(['title' => "Mother's \"Care\" guide", 'slug' => 'quotes', 'excerpt' => "It's a guide"]);

        $html = $this->get(route('resources.show', $r->slug))->assertOk()->getContent();

        $this->assertStringNotContainsString("title: 'Mother's", $html);
        $this->assertStringContainsString('title: "Mother\\u0027s \\u0022Care\\u0022 guide"', $html);
    }

    public function test_a_guest_can_submit_a_comment_that_waits_for_approval(): void
    {
        $r = $this->resource();

        $this->post(route('resources.comment.guest', $r->slug), [
            'author_name' => 'Jane Doe', 'author_email' => 'jane@example.org', 'content' => 'Very helpful, thank you',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('resource_comments', ['resource_id' => $r->id, 'author_name' => 'Jane Doe', 'is_approved' => false]);
        $this->get(route('resources.show', $r->slug))->assertOk()->assertDontSee('Very helpful, thank you');
    }

    public function test_the_page_offers_guests_a_comment_form(): void
    {
        $r = $this->resource();

        $this->get(route('resources.show', $r->slug))->assertOk()
            ->assertSee(route('resources.comment.guest', $r->slug), false);
    }

    public function test_guest_comment_rejects_bad_input(): void
    {
        $r = $this->resource();

        $this->post(route('resources.comment.guest', $r->slug), ['author_name' => 'x1', 'author_email' => 'nope', 'content' => 'a'])
            ->assertSessionHasErrors(['author_name', 'author_email', 'content']);
    }
}
