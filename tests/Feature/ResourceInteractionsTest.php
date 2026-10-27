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

    public function test_bookmarks_page_lists_saved_resources_and_the_menu_links_to_it(): void
    {
        $r = $this->resource(['title' => 'Saved item']);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('resources.bookmarks'))->assertOk()->assertSee('You have not saved any resources yet');
        $this->actingAs($user)->postJson(route('resources.bookmark', $r->slug))->assertOk();

        $page = $this->actingAs($user)->get(route('resources.bookmarks'))->assertOk()->assertSee('Saved item');
        $page->assertSee(route('resources.bookmarks'), false);
        foreach (['latest', 'popular', 'title', 'views', 'likes'] as $sort) {
            $this->actingAs($user)->get(route('resources.bookmarks', ['sort' => $sort]))->assertOk();
        }
    }

    public function test_bookmarks_and_downloads_send_guests_to_sign_in(): void
    {
        $this->get(route('resources.bookmarks'))->assertRedirect();
        $this->get(route('resources.downloads'))->assertRedirect();
    }

    public function test_downloads_page_renders_for_a_signed_in_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('resources.downloads'))->assertOk()->assertSee('You have not downloaded any resources yet');
    }

    public function test_with_recaptcha_keys_the_form_shows_the_widget_and_failed_checks_are_rejected(): void
    {
        config(['services.recaptcha.site_key' => 'site-key', 'services.recaptcha.secret' => 'secret']);
        \Illuminate\Support\Facades\Http::fake(fn ($req) => \Illuminate\Support\Facades\Http::response(['success' => $req['response'] === 'good']));
        $r = $this->resource();

        $this->get(route('resources.show', $r->slug))->assertSee('g-recaptcha', false)->assertSee('site-key', false);
        $this->post(route('resources.comment.guest', $r->slug), [
            'author_name' => 'Jane Doe', 'author_email' => 'jane@example.org', 'content' => 'Hello there', 'g-recaptcha-response' => 'bad',
        ])->assertSessionHasErrors('g-recaptcha-response');
        $this->assertDatabaseCount('resource_comments', 0);

        $this->post(route('resources.comment.guest', $r->slug), [
            'author_name' => 'Jane Doe', 'author_email' => 'jane@example.org', 'content' => 'Hello there', 'g-recaptcha-response' => 'good',
        ])->assertSessionHas('success');
        $this->assertDatabaseCount('resource_comments', 1);
    }

    public function test_resource_and_listing_pages_use_the_new_site_layout(): void
    {
        $r = $this->resource(['title' => 'Use of incubators', 'slug' => 'use-of-incubators']);
        $tag = \App\Models\Tag::create(['name' => 'warmth', 'slug' => 'warmth']);
        $r->tags()->attach($tag->id);
        $paths = [
            route('resources.show', 'use-of-incubators'), route('resources.category', 'cat'), route('categories.show', 'cat'),
            route('resources.tag', 'warmth'), route('resources.type', 'pdf'), route('resources.browse'),
            route('resources.search', ['q' => 'incubators']),
        ];

        foreach ($paths as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('class="skip"', $html, "$path is not on the new layout");
            $this->assertStringNotContainsString('max-w-7xl', $html, "$path still renders the old Tailwind markup");
        }
        $this->get(route('resources.show', 'use-of-incubators'))->assertSee('Use of incubators')->assertSee('Details');
    }

    public function test_signed_in_users_get_an_account_menu_on_the_new_header(): void
    {
        $this->resource();

        $this->actingAs(User::factory()->create())->get(route('resources.show', 'sample'))
            ->assertOk()->assertSee(route('resources.bookmarks'), false)->assertSee('Sign out')->assertSee('Admin panel');
    }

    public function test_reply_and_edit_controls_render_for_the_comment_owner(): void
    {
        $r = $this->resource();
        $user = User::factory()->create();
        $r->comments()->create(['user_id' => $user->id, 'content' => 'My comment', 'is_approved' => true, 'ip_address' => '127.0.0.1']);

        $this->actingAs($user)->get(route('resources.show', $r->slug))->assertOk()
            ->assertSee('My comment')->assertSee('toggleReplyForm(', false)->assertSee('editComment(', false);
    }
}
