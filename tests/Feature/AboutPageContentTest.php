<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\AboutPageContent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AboutPageContentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_about_page_renders_editable_content_and_sections(): void
    {
        $this->get('/about')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Storefront/PublicPage')
                ->where('page', 'about')
                ->has('aboutContent.values')
                ->has('aboutContent.buyer_steps')
                ->has('aboutContent.milestones')
                ->has('aboutContent.team_members')
                ->has('aboutContent.faqs'));
    }

    public function test_admin_can_update_about_page_content_and_team_members(): void
    {
        $user = $this->createUser('about-editor@example.test');
        $user->givePermissionTo(Permission::findOrCreate('setting.view', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('setting.update', 'web'));
        Sanctum::actingAs($user);

        $content = AboutPageContent::get();
        $content['hero_title'] = 'A marketplace made for local business';
        $content['team_members'] = [[
            'name' => 'Ayo Example',
            'role' => 'Head of Operations',
            'photo' => 'https://example.test/ayo.jpg',
            'bio' => 'Keeps our marketplace running smoothly.',
            'link' => 'https://example.test/team/ayo',
        ]];

        $this->putJson('/api/v1/admin/about-page', $content)
            ->assertOk()
            ->assertJsonPath('data.hero_title', 'A marketplace made for local business')
            ->assertJsonPath('data.team_members.0.name', 'Ayo Example');

        $savedContent = Setting::value('about.page_content');
        $this->assertSame('A marketplace made for local business', $savedContent['hero_title']);
        $this->assertSame('Ayo Example', $savedContent['team_members'][0]['name']);

        $this->get('/about')
            ->assertInertia(fn ($page) => $page->where('aboutContent.team_members.0.name', 'Ayo Example'));
    }

    public function test_about_page_update_rejects_incomplete_team_member(): void
    {
        $user = $this->createUser('about-invalid-editor@example.test');
        $user->givePermissionTo(Permission::findOrCreate('setting.update', 'web'));
        Sanctum::actingAs($user);

        $content = AboutPageContent::get();
        $content['team_members'] = [['name' => '', 'role' => '', 'photo' => '', 'bio' => '', 'link' => '']];

        $this->putJson('/api/v1/admin/about-page', $content)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['team_members.0.name', 'team_members.0.role']);
    }

    private function createUser(string $email): User
    {
        return User::create([
            'first_name' => 'About',
            'last_name' => 'Editor',
            'email' => $email,
            'password' => 'password',
        ]);
    }
}
