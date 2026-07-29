<?php

namespace Tests\Feature;

use App\Models\FaqItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FaqItemTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_faq_page_shows_items_in_sort_order(): void
    {
        FaqItem::factory()->create([
            'question' => '第二個問題？',
            'answer' => "第二個回答\n換行內容",
            'sort_order' => 20,
        ]);
        FaqItem::factory()->create([
            'question' => '第一個問題？',
            'answer' => '第一個回答',
            'sort_order' => 10,
        ]);

        $content = $this->get(route('faq'))
            ->assertOk()
            ->assertSee('第一個問題？')
            ->assertSee('第一個回答')
            ->assertSee('第二個問題？')
            ->assertSee('第二個回答', false)
            ->assertSee('換行內容')
            ->getContent();

        $this->assertLessThan(
            strpos($content, '第二個問題？'),
            strpos($content, '第一個問題？')
        );
    }

    public function test_public_faq_page_shows_empty_state_when_no_items(): void
    {
        FaqItem::query()->delete();

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('目前尚無常見問題');
    }

    public function test_guest_cannot_access_admin_faq_list(): void
    {
        $this->get(route('admin.faq-items.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_access_admin_faq_list(): void
    {
        $user = User::factory()->createOne();

        $this->actingAs($user)
            ->get(route('admin.faq-items.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_create_and_edit_forms(): void
    {
        $admin = $this->createFaqManager();
        $faqItem = FaqItem::factory()->create(['question' => '編輯表單問題？']);

        $this->actingAs($admin)
            ->get(route('admin.faq-items.create'))
            ->assertOk()
            ->assertSee('新增常見問題');

        $this->actingAs($admin)
            ->get(route('admin.faq-items.edit', $faqItem))
            ->assertOk()
            ->assertSee('編輯常見問題')
            ->assertSee('編輯表單問題？');
    }

    public function test_admin_can_create_faq_item(): void
    {
        $admin = $this->createFaqManager();

        $this->actingAs($admin)
            ->post(route('admin.faq-items.store'), [
                'question' => '如何登入？',
                'answer' => '點右上角登入按鈕。',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.faq-items.index'))
            ->assertSessionHas('global');

        $this->assertDatabaseHas('faq_items', [
            'question' => '如何登入？',
            'answer' => '點右上角登入按鈕。',
            'sort_order' => 1,
        ]);

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('如何登入？')
            ->assertSee('點右上角登入按鈕。');
    }

    public function test_admin_can_update_faq_item(): void
    {
        $admin = $this->createFaqManager();
        $faqItem = FaqItem::factory()->create([
            'question' => '舊問題？',
            'answer' => '舊回答',
            'sort_order' => 5,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.faq-items.update', $faqItem), [
                'question' => '新問題？',
                'answer' => '新回答',
                'sort_order' => 3,
            ])
            ->assertRedirect(route('admin.faq-items.index'))
            ->assertSessionHas('global');

        $this->assertDatabaseHas('faq_items', [
            'id' => $faqItem->id,
            'question' => '新問題？',
            'answer' => '新回答',
            'sort_order' => 3,
        ]);
    }

    public function test_admin_can_delete_faq_item(): void
    {
        $admin = $this->createFaqManager();
        $faqItem = FaqItem::factory()->create([
            'question' => '要刪除的問題？',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.faq-items.destroy', $faqItem))
            ->assertRedirect(route('admin.faq-items.index'))
            ->assertSessionHas('global');

        $this->assertDatabaseMissing('faq_items', [
            'id' => $faqItem->id,
        ]);
    }

    public function test_store_requires_question_and_answer(): void
    {
        $admin = $this->createFaqManager();

        $this->actingAs($admin)
            ->post(route('admin.faq-items.store'), [
                'question' => '',
                'answer' => '',
                'sort_order' => 1,
            ])
            ->assertSessionHasErrors(['question', 'answer']);
    }

    public function test_admin_faq_list_shows_items(): void
    {
        $admin = $this->createFaqManager();
        FaqItem::factory()->create(['question' => '列表可見問題？', 'sort_order' => 1]);

        $this->actingAs($admin)
            ->get(route('admin.faq-items.index'))
            ->assertOk()
            ->assertSee('常見問題管理')
            ->assertSee('列表可見問題？');
    }

    private function createFaqManager(): User
    {
        $user = User::factory()->createOne();
        $user->givePermission('faq.manage');

        return $user;
    }
}
