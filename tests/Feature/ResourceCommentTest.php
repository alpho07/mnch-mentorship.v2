<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\ResourceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceCommentTest extends TestCase
{
    use RefreshDatabase;

    private function resource(User $author): Resource
    {
        $type = ResourceType::create(['name' => 'PDF', 'slug' => 'pdf', 'is_active' => true]);
        $cat = ResourceCategory::create(['name' => 'Cat', 'slug' => 'cat', 'is_active' => true]);

        return Resource::create([
            'title' => 'Sample', 'slug' => 'sample', 'resource_type_id' => $type->id, 'category_id' => $cat->id,
            'author_id' => $author->id, 'status' => 'published', 'visibility' => 'public', 'published_at' => now()->subDay(),
        ]);
    }

    public function test_a_signed_in_user_can_comment_and_see_it_with_a_reply_on_the_resource_page(): void
    {
        $user = User::factory()->create();
        $resource = $this->resource($user);

        $this->actingAs($user)->post(route('resources.comment.store', $resource->slug), ['content' => 'First comment'])
            ->assertRedirect()->assertSessionHas('success');
        $parent = $resource->comments()->first();
        $this->actingAs($user)->post(route('resources.comment.store', $resource->slug), ['content' => 'A reply', 'parent_id' => $parent->id])
            ->assertRedirect();

        $this->get(route('resources.show', $resource->slug))->assertOk()->assertSee('First comment')->assertSee('A reply');
    }
}
