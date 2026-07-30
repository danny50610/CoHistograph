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

    public function test_public_faq_page_hides_hidden_items(): void
    {
        FaqItem::factory()->create([
            'question' => '可見問題？',
            'answer' => '可見回答',
            'is_hidden' => false,
        ]);
        FaqItem::factory()->hidden()->create([
            'question' => '隱藏問題？',
            'answer' => '隱藏回答',
        ]);

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('可見問題？')
            ->assertDontSee('隱藏問題？')
            ->assertDontSee('隱藏回答');
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
            ->assertSee('新增常見問題')
            ->assertSee('最上方')
            ->assertSee('隱藏此問題');

        $this->actingAs($admin)
            ->get(route('admin.faq-items.edit', $faqItem))
            ->assertOk()
            ->assertSee('編輯常見問題')
            ->assertSee('編輯表單問題？');
    }

    public function test_admin_can_create_faq_item_after_existing_item(): void
    {
        $admin = $this->createFaqManager();
        $existing = FaqItem::factory()->create([
            'question' => '既有問題？',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.faq-items.store'), [
                'question' => '如何登入？',
                'answer' => '點右上角登入按鈕。',
                'place_after_id' => $existing->id,
            ])
            ->assertRedirect(route('admin.faq-items.index'))
            ->assertSessionHas('global');

        $this->assertDatabaseHas('faq_items', [
            'question' => '如何登入？',
            'answer' => '點右上角登入按鈕。',
            'is_hidden' => false,
            'sort_order' => 2,
        ]);

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('如何登入？')
            ->assertSee('點右上角登入按鈕。');
    }

    public function test_admin_can_create_faq_item_at_top(): void
    {
        $admin = $this->createFaqManager();
        FaqItem::factory()->create([
            'question' => '原本第一？',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.faq-items.store'), [
                'question' => '新的第一？',
                'answer' => '新回答',
                'place_after_id' => '',
            ])
            ->assertRedirect(route('admin.faq-items.index'));

        $created = FaqItem::query()->where('question', '新的第一？')->firstOrFail();
        $original = FaqItem::query()->where('question', '原本第一？')->firstOrFail();

        $this->assertSame(1, $created->sort_order);
        $this->assertSame(2, $original->sort_order);
    }

    public function test_admin_can_update_faq_item_placement_and_hide(): void
    {
        $admin = $this->createFaqManager();
        $first = FaqItem::factory()->create([
            'question' => '第一題？',
            'answer' => '第一回答',
            'sort_order' => 1,
        ]);
        $second = FaqItem::factory()->create([
            'question' => '第二題？',
            'answer' => '第二回答',
            'sort_order' => 2,
        ]);
        $third = FaqItem::factory()->create([
            'question' => '第三題？',
            'answer' => '第三回答',
            'sort_order' => 3,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.faq-items.update', $third), [
                'question' => '移到第一下方？',
                'answer' => '新回答',
                'place_after_id' => $first->id,
                'is_hidden' => '1',
            ])
            ->assertRedirect(route('admin.faq-items.index'))
            ->assertSessionHas('global');

        $third->refresh();
        $second->refresh();

        $this->assertSame('移到第一下方？', $third->question);
        $this->assertTrue($third->is_hidden);
        $this->assertSame(2, $third->sort_order);
        $this->assertSame(3, $second->sort_order);

        $this->flushSession();

        $this->get(route('faq'))
            ->assertOk()
            ->assertDontSee('移到第一下方？');

        $this->actingAs($admin)
            ->get(route('admin.faq-items.index'))
            ->assertOk()
            ->assertSee('移到第一下方？')
            ->assertSee('隱藏');
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
            ])
            ->assertSessionHasErrors(['question', 'answer']);
    }

    public function test_cannot_place_after_self(): void
    {
        $admin = $this->createFaqManager();
        $faqItem = FaqItem::factory()->create([
            'question' => '自己？',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.faq-items.update', $faqItem), [
                'question' => '自己？',
                'answer' => '回答',
                'place_after_id' => $faqItem->id,
            ])
            ->assertSessionHasErrors(['place_after_id']);
    }

    public function test_admin_faq_list_shows_items(): void
    {
        $admin = $this->createFaqManager();
        FaqItem::factory()->create(['question' => '列表可見問題？', 'sort_order' => 1]);
        FaqItem::factory()->hidden()->create(['question' => '列表隱藏問題？', 'sort_order' => 2]);

        $this->actingAs($admin)
            ->get(route('admin.faq-items.index'))
            ->assertOk()
            ->assertSee('常見問題管理')
            ->assertSee('列表可見問題？')
            ->assertSee('列表隱藏問題？')
            ->assertSee('隱藏');
    }

    private function createFaqManager(): User
    {
        $user = User::factory()->createOne();
        $user->givePermission('faq.manage');

        return $user;
    }
}
