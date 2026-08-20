<?php

namespace Tests\Feature;

use App\Enums\SystemConfigKey;
use App\Models\SystemConfig;
use App\Models\User;
use App\SystemConfig\HomepageConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class HomepageConfigTest extends TestCase
{
    use DatabaseTransactions;

    public function test_homepage_shows_default_tagline_when_config_missing(): void
    {
        SystemConfig::query()->where('key', SystemConfigKey::Homepage->value)->delete();

        $this->get(route('index'))
            ->assertOk()
            ->assertSee(HomepageConfig::DEFAULT_TAGLINE, false);
    }

    public function test_authenticated_homepage_shows_revision_cta_instead_of_login(): void
    {
        $user = User::factory()->createOne();

        $this->actingAs($user)
            ->get(route('index'))
            ->assertOk()
            ->assertSee(route('revisions.create'), false)
            ->assertDontSee('>登入</a>', false);
    }

    public function test_homepage_shows_stored_tagline(): void
    {
        SystemConfig::query()->updateOrCreate(
            ['key' => SystemConfigKey::Homepage->value],
            ['value' => ['tagline' => '自訂首頁說明文字']],
        );

        $this->get(route('index'))
            ->assertOk()
            ->assertSee('自訂首頁說明文字', false)
            ->assertDontSee(HomepageConfig::DEFAULT_TAGLINE, false);
    }

    public function test_homepage_falls_back_to_default_when_stored_value_is_invalid(): void
    {
        SystemConfig::query()->updateOrCreate(
            ['key' => SystemConfigKey::Homepage->value],
            ['value' => ['tagline' => '']],
        );

        $this->get(route('index'))
            ->assertOk()
            ->assertSee(HomepageConfig::DEFAULT_TAGLINE, false);
    }

    public function test_guest_cannot_access_homepage_config_edit(): void
    {
        $this->get(route('admin.system-config.homepage.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_access_homepage_config_edit(): void
    {
        $user = User::factory()->createOne();

        $this->actingAs($user)
            ->get(route('admin.system-config.homepage.edit'))
            ->assertForbidden();
    }

    public function test_admin_can_view_homepage_config_edit_form(): void
    {
        $admin = $this->createSystemConfigAdmin();

        $this->actingAs($admin)
            ->get(route('admin.system-config.homepage.edit'))
            ->assertOk()
            ->assertSee('首頁設定')
            ->assertSee(HomepageConfig::DEFAULT_TAGLINE, false);
    }

    public function test_admin_can_update_homepage_tagline(): void
    {
        $admin = $this->createSystemConfigAdmin();

        $this->actingAs($admin)
            ->put(route('admin.system-config.homepage.update'), [
                'tagline' => '更新後的首頁說明',
            ])
            ->assertRedirect(route('admin.system-config.homepage.edit'))
            ->assertSessionHas('global', '首頁設定已更新');

        $this->assertDatabaseHas('system_configs', [
            'key' => SystemConfigKey::Homepage->value,
        ]);

        $this->get(route('index'))
            ->assertOk()
            ->assertSee('更新後的首頁說明', false);
    }

    public function test_homepage_tagline_update_requires_value(): void
    {
        $admin = $this->createSystemConfigAdmin();

        $this->actingAs($admin)
            ->from(route('admin.system-config.homepage.edit'))
            ->put(route('admin.system-config.homepage.update'), [
                'tagline' => '',
            ])
            ->assertRedirect(route('admin.system-config.homepage.edit'))
            ->assertSessionHasErrors('tagline');
    }

    public function test_homepage_tagline_update_rejects_too_long_value(): void
    {
        $admin = $this->createSystemConfigAdmin();

        $this->actingAs($admin)
            ->from(route('admin.system-config.homepage.edit'))
            ->put(route('admin.system-config.homepage.update'), [
                'tagline' => str_repeat('字', 501),
            ])
            ->assertRedirect(route('admin.system-config.homepage.edit'))
            ->assertSessionHasErrors('tagline');
    }

    private function createSystemConfigAdmin(): User
    {
        \App\Models\Permission::query()->firstOrCreate(
            ['name' => 'system-config.manage'],
            [
                'display_name' => '管理系統設定',
                'description' => '修改首頁文字等系統設定',
            ],
        );

        $admin = User::factory()->createOne();
        $admin->givePermission('system-config.manage');

        return $admin;
    }
}
