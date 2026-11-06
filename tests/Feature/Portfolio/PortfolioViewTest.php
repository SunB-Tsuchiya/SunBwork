<?php

namespace Tests\Feature\Portfolio;

use App\Models\Company;
use App\Models\Portfolio\PortfolioCollection;
use App\Models\Portfolio\PortfolioItem;
use App\Models\Portfolio\PortfolioPermission;
use App\Models\Portfolio\PortfolioProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** お客様に見せる閲覧ページ（portfolio.view）のサーバー側のテスト */
class PortfolioViewTest extends TestCase
{
    use DatabaseTransactions;

    private function company(string $type = 'sunbrain'): Company
    {
        return Company::create(['name' => 'T-' . uniqid(), 'code' => 'T' . uniqid(), 'company_type' => $type]);
    }

    private function designer(?Company $company = null): User
    {
        $user = User::factory()->create(['user_role' => 'user', 'company_id' => ($company ?? $this->company())->id, 'name' => '山田花子']);
        PortfolioPermission::create(['user_id' => $user->id, 'enabled' => true]);

        return $user;
    }

    private function item(User $owner, array $attrs = []): PortfolioItem
    {
        return PortfolioItem::create($attrs + [
            'user_id' => $owner->id, 'type' => 'image', 'title' => '作品', 'original_name' => 'a.jpg', 'mime' => 'image/jpeg',
            'size_bytes' => 1, 'file_path' => 'x.jpg', 'thumb_path' => 'x_t.jpg', 'status' => 'ready',
        ]);
    }

    public function test_本人の閲覧ページには完了した作品とプロフィールだけが渡る(): void
    {
        $user = $this->designer();
        $collection = PortfolioCollection::create(['user_id' => $user->id, 'name' => 'カタログ']);
        PortfolioProfile::create(['user_id' => $user->id, 'accent_color' => '#dc2626', 'headline' => '印刷デザイナー']);
        $this->item($user, ['title' => '完成', 'collection_id' => $collection->id]);
        $this->item($user, ['title' => 'アップ中', 'status' => 'uploading']);
        $this->item($user, ['title' => '変換失敗', 'status' => 'failed']);

        $this->actingAs($user)->get(route('portfolio.view'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portfolio/View', false)
                ->where('profile.name', '山田花子')
                ->where('profile.accent_color', '#dc2626')
                ->where('profile.headline', '印刷デザイナー')
                ->has('collections', 1)
                ->has('items', 1)
                ->where('items.0.title', '完成'));
    }

    public function test_表示名を設定していればそれが使われる(): void
    {
        $user = $this->designer();
        PortfolioProfile::create(['user_id' => $user->id, 'display_name' => 'Hanako Yamada']);

        $this->actingAs($user)->get(route('portfolio.view'))
            ->assertInertia(fn ($page) => $page->where('profile.name', 'Hanako Yamada'));
    }

    public function test_許可のないユーザーは閲覧ページも開けない(): void
    {
        $user = User::factory()->create(['user_role' => 'user', 'company_id' => $this->company()->id]);

        $this->actingAs($user)->get(route('portfolio.view'))->assertForbidden();
    }

    public function test_サンエー印刷のユーザーは許可があっても開けない(): void
    {
        $user = User::factory()->create(['user_role' => 'user', 'company_id' => $this->company('general')->id]);
        PortfolioPermission::create(['user_id' => $user->id, 'enabled' => true]);

        $this->actingAs($user)->get(route('portfolio.view'))->assertForbidden();
    }

    public function test_Adminは同じ会社のメンバーの閲覧ページを開ける(): void
    {
        $company = $this->company();
        $designer = $this->designer($company);
        $admin = User::factory()->create(['user_role' => 'admin', 'company_id' => $company->id]);
        $this->item($designer, ['title' => '見せたい作品']);

        $this->actingAs($admin)->get(route('portfolio.view', ['owner_id' => $designer->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('profile.name', '山田花子')->where('items.0.title', '見せたい作品'));
    }

    public function test_Adminは別会社のメンバーの閲覧ページは開けない(): void
    {
        $designer = $this->designer();
        $otherAdmin = User::factory()->create(['user_role' => 'admin', 'company_id' => $this->company()->id]);

        $this->actingAs($otherAdmin)->get(route('portfolio.view', ['owner_id' => $designer->id]))->assertForbidden();
    }

    public function test_ユーザーは他人の閲覧ページを開けない(): void
    {
        $company = $this->company();
        $a = $this->designer($company);
        $b = $this->designer($company);

        $this->actingAs($a)->get(route('portfolio.view', ['owner_id' => $b->id]))->assertForbidden();
    }

    public function test_作品が無くても閲覧ページは開ける(): void
    {
        $user = $this->designer();

        $this->actingAs($user)->get(route('portfolio.view'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('items', 0)->has('collections', 0));
    }
}
