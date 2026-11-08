<?php

namespace Tests\Feature\Portfolio;

use App\Models\Company;
use App\Models\Portfolio\PortfolioCollection;
use App\Models\Portfolio\PortfolioItem;
use App\Models\Portfolio\PortfolioPermission;
use App\Models\Portfolio\PortfolioTag;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 作品・コレクションの CRUD と「他人のデータに触れない」ことのテスト。
 * テスト専用DB内のトランザクションで閉じ、ファイルは Storage::fake で実ディスクに残さない。
 */
class PortfolioItemTest extends TestCase
{
    use DatabaseTransactions;

    /** 他テストの残骸で権限エラーになりやすい 'local' は使わず、専用の偽ディスクに差し替える */
    private function fakeDisk(): void
    {
        Storage::fake('portfolio_test');
        config(['filesystems.portfolio_disk' => 'portfolio_test']);
    }

    private function company(string $type = 'sunbrain'): Company
    {
        return Company::create(['name' => 'T-' . uniqid(), 'code' => 'T' . uniqid(), 'company_type' => $type]);
    }

    /** 利用許可をもらったデザイナー（ユーザー） */
    private function designer(?Company $company = null): User
    {
        $user = User::factory()->create(['user_role' => 'user', 'company_id' => ($company ?? $this->company())->id]);
        PortfolioPermission::create(['user_id' => $user->id, 'enabled' => true]);

        return $user;
    }

    /** 完成済み（ready）の作品を、ファイルなしで直接作る（一覧のカードのテスト用） */
    private function readyItem(User $owner, array $attrs = []): PortfolioItem
    {
        return PortfolioItem::create($attrs + [
            'user_id' => $owner->id, 'type' => 'image', 'title' => '作品', 'original_name' => 'a.jpg', 'mime' => 'image/jpeg',
            'size_bytes' => 1, 'file_path' => 'x.jpg', 'thumb_path' => 'x_t.jpg', 'status' => 'ready',
        ]);
    }

    private function manager(string $role, ?Company $company = null): User
    {
        return User::factory()->create(['user_role' => $role, 'company_id' => ($company ?? $this->company())->id]);
    }

    private function upload(User $user, array $extra = [])
    {
        return $this->actingAs($user)->post(route('portfolio.api.items.store'), [
            'file' => UploadedFile::fake()->image('作品1.jpg', 3000, 2000),
        ] + $extra, ['Accept' => 'application/json']);
    }

    public function test_画像をアップすると縮小されサムネイルも作られる(): void
    {
        $this->fakeDisk();
        $user = $this->designer();

        $res = $this->upload($user)->assertCreated();

        $item = PortfolioItem::findOrFail($res->json('id'));
        $this->assertSame('image', $item->type);
        $this->assertSame('作品1', $item->title);
        $this->assertLessThanOrEqual(2400, max($item->width, $item->height));
        Storage::disk('portfolio_test')->assertExists($item->file_path);
        Storage::disk('portfolio_test')->assertExists($item->thumb_path);
    }

    public function test_画像以外は拒否される(): void
    {
        $this->fakeDisk();
        $user = $this->designer();

        $this->actingAs($user)->post(route('portfolio.api.items.store'), [
            'file' => UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo 1;'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_他人の作品は更新も削除も配信もできない(): void
    {
        $this->fakeDisk();
        $owner = $this->designer();
        $other = $this->designer();
        $id = $this->upload($owner)->json('id');

        $this->actingAs($other)->putJson(route('portfolio.api.items.update', ['item' => $id]), ['title' => 'x'])->assertNotFound();
        $this->actingAs($other)->deleteJson(route('portfolio.api.items.destroy', ['item' => $id]))->assertNotFound();
        $this->actingAs($other)->get(route('portfolio.api.files', ['item' => $id, 'kind' => 'file']))->assertNotFound();

        // 本人は配信を受けられる
        $this->actingAs($owner)->get(route('portfolio.api.files', ['item' => $id, 'kind' => 'thumb']))->assertOk();
    }

    public function test_削除するとファイル実体も消える(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $item = PortfolioItem::findOrFail($this->upload($user)->json('id'));
        [$file, $thumb] = [$item->file_path, $item->thumb_path];

        $this->actingAs($user)->deleteJson(route('portfolio.api.items.destroy', ['item' => $item->id]))->assertOk();

        $this->assertDatabaseMissing('portfolio_items', ['id' => $item->id]);
        Storage::disk('portfolio_test')->assertMissing($file);
        Storage::disk('portfolio_test')->assertMissing($thumb);
    }

    public function test_タグは作品更新で作られ使われなくなると消える(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $id = $this->upload($user)->json('id');

        $this->actingAs($user)->putJson(route('portfolio.api.items.update', ['item' => $id]), [
            'title' => 'チラシ', 'tags' => ['チラシ', '飲食', 'チラシ'],
        ])->assertOk();
        $this->assertSame(2, PortfolioTag::where('user_id', $user->id)->count());

        $this->actingAs($user)->putJson(route('portfolio.api.items.update', ['item' => $id]), [
            'title' => 'チラシ', 'tags' => ['飲食'],
        ])->assertOk();
        $this->assertSame(['飲食'], PortfolioTag::where('user_id', $user->id)->pluck('name')->all());
    }

    public function test_コレクション削除で作品は未分類に戻る(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $cid = $this->actingAs($user)->postJson(route('portfolio.api.collections.store'), ['name' => 'カタログ'])->json('id');
        $iid = $this->upload($user, ['collection_id' => $cid])->json('id');
        $this->assertSame($cid, PortfolioItem::find($iid)->collection_id);

        $this->actingAs($user)->deleteJson(route('portfolio.api.collections.destroy', ['collection' => $cid]))->assertOk();

        $this->assertNull(PortfolioItem::find($iid)->collection_id);
    }

    public function test_他人のコレクションには作品を入れられない(): void
    {
        $this->fakeDisk();
        $owner = $this->designer();
        $other = $this->designer();
        $cid = $this->actingAs($owner)->postJson(route('portfolio.api.collections.store'), ['name' => '秘密'])->json('id');

        $this->upload($other, ['collection_id' => $cid])->assertStatus(422);
    }

    public function test_表紙はそのコレクション内の作品だけ指定できる(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $cid = $this->actingAs($user)->postJson(route('portfolio.api.collections.store'), ['name' => 'A'])->json('id');
        $inside = $this->upload($user, ['collection_id' => $cid])->json('id');
        $outside = $this->upload($user)->json('id');

        $url = route('portfolio.api.collections.update', ['collection' => $cid]);
        $this->actingAs($user)->putJson($url, ['name' => 'A', 'cover_item_id' => $outside])->assertStatus(422);
        $this->actingAs($user)->putJson($url, ['name' => 'A', 'cover_item_id' => $inside])->assertOk();
        $this->assertSame($inside, PortfolioCollection::find($cid)->cover_item_id);
    }

    public function test_並べ替えは本人の作品だけに効く(): void
    {
        $this->fakeDisk();
        $owner = $this->designer();
        $other = $this->designer();
        $a = $this->upload($owner)->json('id');
        $b = $this->upload($owner)->json('id');
        $x = $this->upload($other)->json('id');

        $this->actingAs($owner)->postJson(route('portfolio.api.items.reorder'), ['ids' => [$b, $a, $x]])->assertOk();

        $this->assertSame(0, PortfolioItem::find($b)->sort_order);
        $this->assertSame(1, PortfolioItem::find($a)->sort_order);
        $this->assertNotSame(2, PortfolioItem::find($x)->sort_order);
    }

    public function test_Adminは同じ会社のメンバーの作品を閲覧できるが編集削除はできない(): void
    {
        $this->fakeDisk();
        $company = $this->company();
        $designer = $this->designer($company);
        $admin = $this->manager('admin', $company);
        $id = $this->upload($designer)->json('id');

        // 閲覧: 一覧とファイル配信は可能
        $this->actingAs($admin)->getJson(route('portfolio.api.data', ['owner_id' => $designer->id]))
            ->assertOk()->assertJsonPath('items.0.id', $id);
        $this->actingAs($admin)->get(route('portfolio.api.files', ['item' => $id, 'kind' => 'thumb']))->assertOk();

        // 編集・削除は 403（見えるが触れない）。データは変わらない
        $this->actingAs($admin)->putJson(route('portfolio.api.items.update', ['item' => $id]), ['title' => '勝手に修正'])->assertForbidden();
        $this->actingAs($admin)->deleteJson(route('portfolio.api.items.destroy', ['item' => $id]))->assertForbidden();
        $this->assertSame('作品1', PortfolioItem::find($id)->title);
    }

    public function test_スイッチをOFFにしたAdminは自分のポートフォリオも他メンバー宛も作れない(): void
    {
        $this->fakeDisk();
        $company = $this->company();
        $designer = $this->designer($company);
        $admin = $this->manager('admin', $company);
        PortfolioPermission::create(['user_id' => $admin->id, 'enabled' => false]); // OFF にした Admin

        // 自分宛のアップ・コレクション作成・設定変更は不可
        $this->upload($admin)->assertForbidden();
        $this->actingAs($admin)->postJson(route('portfolio.api.collections.store'), ['name' => 'A'])->assertForbidden();
        $this->actingAs($admin)->putJson(route('portfolio.api.profile'), ['accent_color' => '#2563eb'])->assertForbidden();
        // 他メンバー宛（owner_id 指定）も不可
        $this->upload($admin, ['owner_id' => $designer->id])->assertForbidden();
        $this->actingAs($admin)->postJson(route('portfolio.api.collections.store'), ['name' => 'A', 'owner_id' => $designer->id])->assertForbidden();
        $this->assertSame(0, PortfolioItem::count());
    }

    public function test_Adminはスイッチが未設定でも自動でONで自分のポートフォリオを作れる(): void
    {
        $this->fakeDisk();
        $company = $this->company();
        $designer = $this->designer($company);
        $admin = $this->manager('admin', $company);
        $this->assertDatabaseMissing('portfolio_permissions', ['user_id' => $admin->id]); // 行は無い

        $this->actingAs($admin)->postJson(route('portfolio.api.collections.store'), ['name' => '客先用'])->assertCreated();
        $this->upload($admin)->assertCreated();
        // 他メンバー宛は、自動 ON でも不可
        $this->upload($admin, ['owner_id' => $designer->id])->assertForbidden();
    }

    public function test_Adminは別会社のメンバーの作品は閲覧もできない(): void
    {
        $this->fakeDisk();
        $designer = $this->designer();
        $otherAdmin = $this->manager('admin'); // 別の会社
        $id = $this->upload($designer)->json('id');

        $this->actingAs($otherAdmin)->getJson(route('portfolio.api.data', ['owner_id' => $designer->id]))->assertForbidden();
        $this->actingAs($otherAdmin)->get(route('portfolio.api.files', ['item' => $id, 'kind' => 'file']))->assertNotFound();
        $this->actingAs($otherAdmin)->putJson(route('portfolio.api.items.update', ['item' => $id]), ['title' => 'x'])->assertNotFound();
    }

    public function test_SuperAdminはSBの全メンバーを閲覧でき他人の分は編集できず自分の分は編集できる(): void
    {
        $this->fakeDisk();
        $designer = $this->designer();
        $super = $this->manager('superadmin');
        $id = $this->upload($designer)->json('id');

        $this->actingAs($super)->getJson(route('portfolio.api.data', ['owner_id' => $designer->id]))->assertOk()->assertJsonPath('items.0.id', $id);
        $this->actingAs($super)->putJson(route('portfolio.api.items.update', ['item' => $id]), ['title' => 'x'])->assertForbidden();
        $this->actingAs($super)->deleteJson(route('portfolio.api.items.destroy', ['item' => $id]))->assertForbidden();
        // 試用のため、SuperAdmin は自分の分だけ追加できる
        $this->upload($super)->assertCreated();
    }

    public function test_作品追加時にタイトル説明タグを同時に保存できる(): void
    {
        $this->fakeDisk();
        $user = $this->designer();

        $res = $this->upload($user, ['title' => '春のチラシ', 'description' => '桜がテーマ', 'tags' => ['チラシ', '春']])->assertCreated();

        $item = PortfolioItem::with('tags')->findOrFail($res->json('id'));
        $this->assertSame('春のチラシ', $item->title);
        $this->assertSame('桜がテーマ', $item->description);
        $this->assertEqualsCanonicalizing(['チラシ', '春'], $item->tags->pluck('name')->all());
    }

    public function test_サンエー印刷のメンバーは管理者でも閲覧できない(): void
    {
        $this->fakeDisk();
        $sunaUser = User::factory()->create(['user_role' => 'user', 'company_id' => $this->company('general')->id]);
        $super = $this->manager('superadmin');

        $this->actingAs($super)->getJson(route('portfolio.api.data', ['owner_id' => $sunaUser->id]))->assertForbidden();
    }

    public function test_1人分のページは閲覧専用で持ち主の情報を渡し自分の編集画面はユーザーエリア(): void
    {
        $company = $this->company();
        $designer = $this->designer($company);
        $admin = $this->manager('admin', $company);

        $this->actingAs($admin)->get(route('admin.portfolio.members.show', ['member' => $designer->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portfolio/Manage', false)->where('readOnly', true)->where('ownerId', $designer->id)->where('ownerName', $designer->name)->where('canEditOwn', false));
        $this->actingAs($designer)->get(route('user.portfolio.manage'))
            ->assertInertia(fn ($page) => $page->where('readOnly', false)->where('ownerId', $designer->id));
    }

    public function test_スイッチONのAdminは自分のポートフォリオを作れるが他メンバーの分は触れない(): void
    {
        $this->fakeDisk();
        $company = $this->company();
        $designer = $this->designer($company);
        $admin = $this->manager('admin', $company);
        PortfolioPermission::create(['user_id' => $admin->id, 'enabled' => true]);
        $others = $this->upload($designer)->json('id');

        // 自分の分: コレクション作成・作品追加・編集・削除ができる
        $cid = $this->actingAs($admin)->postJson(route('portfolio.api.collections.store'), ['name' => '客先用'])->assertCreated()->json('id');
        $mine = $this->upload($admin, ['collection_id' => $cid])->assertCreated()->json('id');
        $this->assertSame($admin->id, PortfolioItem::find($mine)->user_id);
        $this->actingAs($admin)->putJson(route('portfolio.api.items.update', ['item' => $mine]), ['title' => '修正'])->assertOk();

        // 他メンバーの分: 見える（閲覧）が、追加・編集・削除はできない
        $this->actingAs($admin)->getJson(route('portfolio.api.data', ['owner_id' => $designer->id]))->assertOk();
        $this->upload($admin, ['owner_id' => $designer->id])->assertForbidden();
        $this->actingAs($admin)->putJson(route('portfolio.api.items.update', ['item' => $others]), ['title' => '勝手に修正'])->assertForbidden();
        $this->actingAs($admin)->deleteJson(route('portfolio.api.items.destroy', ['item' => $others]))->assertForbidden();
        $this->assertSame('作品1', PortfolioItem::find($others)->title);

        $this->actingAs($admin)->deleteJson(route('portfolio.api.items.destroy', ['item' => $mine]))->assertOk();
    }

    public function test_スイッチをOFFに戻したAdminは自分の分も書き込めなくなる(): void
    {
        $this->fakeDisk();
        $company = $this->company();
        $admin = $this->manager('admin', $company);
        $perm = PortfolioPermission::create(['user_id' => $admin->id, 'enabled' => true]);
        $this->upload($admin)->assertCreated();

        $perm->update(['enabled' => false]);

        $this->upload($admin)->assertForbidden();
        $this->actingAs($admin)->postJson(route('portfolio.api.collections.store'), ['name' => 'A'])->assertForbidden();
    }

    public function test_作品を持つAdminは一覧に出て自分のカードには編集への入口が付く(): void
    {
        $this->fakeDisk();
        $company = $this->company();
        $viewer = $this->manager('admin', $company);
        $adminOwner = $this->manager('admin', $company);
        PortfolioPermission::create(['user_id' => $adminOwner->id, 'enabled' => true]);
        $this->upload($adminOwner)->assertCreated();
        PortfolioPermission::create(['user_id' => $viewer->id, 'enabled' => false]); // 閲覧する Admin は、作品なし・OFF

        // 他の Admin のカード（編集への入口は出ない）
        $this->actingAs($viewer)->get(route('admin.portfolio.manage'))
            ->assertInertia(fn ($page) => $page->has('members', 1)->where('members.0.id', $adminOwner->id)->where('members.0.can_edit', false)->where('members.0.is_self', false));
        // 本人の一覧では、自分のカードに「編集する」の入口が付く
        $this->actingAs($adminOwner)->get(route('admin.portfolio.manage'))
            ->assertInertia(fn ($page) => $page->has('members', 1)->where('members.0.is_self', true)->where('members.0.can_edit', true));
    }

    // ---- 管理画面「メンバーのポートフォリオ」のカード ----

    public function test_カードは作品数の内訳とサムネイル最大4点と表紙優先と最終更新を持つ(): void
    {
        $company = $this->company();
        $viewer = $this->manager('admin', $company);
        PortfolioPermission::create(['user_id' => $viewer->id, 'enabled' => false]);
        $designer = $this->designer($company);

        $first = $this->readyItem($designer, ['title' => '1', 'sort_order' => 1]);
        $this->readyItem($designer, ['title' => '2', 'sort_order' => 2]);
        $this->readyItem($designer, ['title' => '3', 'sort_order' => 3, 'type' => 'pdf']);
        $this->readyItem($designer, ['title' => '4', 'sort_order' => 4, 'type' => 'video']);
        $cover = $this->readyItem($designer, ['title' => '表紙の作品', 'sort_order' => 5]);
        $this->readyItem($designer, ['title' => '6', 'sort_order' => 6]);
        $noThumb = $this->readyItem($designer, ['title' => 'サムネ無し', 'sort_order' => 0, 'thumb_path' => null]);
        // コレクションの表紙に指定された作品は、並び順が後ろでも、先頭のサムネイルになる
        $collection = \App\Models\Portfolio\PortfolioCollection::create(['user_id' => $designer->id, 'name' => 'カタログ', 'cover_item_id' => $cover->id]);
        $cover->update(['collection_id' => $collection->id]);

        $this->actingAs($viewer)->get(route('admin.portfolio.manage'))
            ->assertInertia(fn ($page) => $page
                ->has('members', 1)
                ->where('members.0.total', 7)
                ->where('members.0.counts', ['image' => 5, 'pdf' => 1, 'video' => 1])
                ->has('members.0.previews', 4) // 最大 4 点
                ->where('members.0.previews.0.id', $cover->id) // 表紙が先頭
                ->where('members.0.more', 2) // サムネイルのある 6 点のうち、4 点を出して、残り 2
                ->where('members.0.problem', ['uploading' => 0, 'failed' => 0])
                ->where('members.0.updated_at', fn ($v) => ! empty($v)));
        $this->assertNotNull($noThumb->id);
        $this->assertNotNull($first->id);
    }

    public function test_問題のある作品は要確認として数えサムネイルには出さない(): void
    {
        $company = $this->company();
        $viewer = $this->manager('admin', $company);
        PortfolioPermission::create(['user_id' => $viewer->id, 'enabled' => false]);
        $designer = $this->designer($company);
        $this->readyItem($designer);
        $this->readyItem($designer, ['status' => 'failed']);
        $this->readyItem($designer, ['status' => 'uploading']);
        $this->readyItem($designer, ['status' => 'processing']);

        $this->actingAs($viewer)->get(route('admin.portfolio.manage'))
            ->assertInertia(fn ($page) => $page
                ->where('members.0.total', 1)                       // 完成した作品だけを数える
                ->where('members.0.problem', ['uploading' => 2, 'failed' => 1])
                ->has('members.0.previews', 1));                    // 問題のある作品は、サムネイルに出さない
    }

    public function test_作品が無い人は名前だけの欄に出てスイッチOFFで作品の無い人は出ない(): void
    {
        $company = $this->company();
        $viewer = $this->manager('admin', $company);
        PortfolioPermission::create(['user_id' => $viewer->id, 'enabled' => false]);
        $withWorks = $this->designer($company);
        $this->readyItem($withWorks);
        $noWorks = $this->designer($company); // スイッチ ON・作品なし
        $off = User::factory()->create(['user_role' => 'user', 'company_id' => $company->id]); // 許可なし・作品なし
        $offWithWorks = User::factory()->create(['user_role' => 'user', 'company_id' => $company->id]); // 許可は無いが、作品がある
        $this->readyItem($offWithWorks);

        $this->actingAs($viewer)->get(route('admin.portfolio.manage'))
            ->assertInertia(fn ($page) => $page
                ->has('members', 2) // 作品のある 2 人（許可が外れていても、作品があれば出す）
                ->has('withoutWorks', 1)
                ->where('withoutWorks.0.id', $noWorks->id));
        $this->assertNotNull($off->id);
    }

    public function test_カードの範囲は閲覧できる人だけでAdminは自社SuperAdminはSB全員_サンエー印刷は出ない(): void
    {
        $company = $this->company();
        $otherSb = $this->company();
        $suna = $this->company('general');
        $admin = $this->manager('admin', $company);
        PortfolioPermission::create(['user_id' => $admin->id, 'enabled' => false]);
        $super = $this->manager('superadmin', $company);

        $mine = $this->designer($company);
        $theirs = $this->designer($otherSb);
        $sunaUser = User::factory()->create(['user_role' => 'user', 'company_id' => $suna->id]);
        PortfolioPermission::create(['user_id' => $sunaUser->id, 'enabled' => true]);
        foreach ([$mine, $theirs, $sunaUser] as $u) {
            $this->readyItem($u);
        }

        $this->actingAs($admin)->get(route('admin.portfolio.manage'))
            ->assertInertia(fn ($page) => $page->has('members', 1)->where('members.0.id', $mine->id));
        $this->actingAs($super)->get(route('admin.portfolio.manage'))
            ->assertInertia(fn ($page) => $page->has('members', 2)); // SB の全員（自社＋他社）。サンエー印刷は出ない
    }

    public function test_1人分のページは同じ会社のAdminだけが開けユーザーや他社のAdminは開けない(): void
    {
        $company = $this->company();
        $designer = $this->designer($company);
        $sameAdmin = $this->manager('admin', $company);
        $otherAdmin = $this->manager('admin');
        $super = $this->manager('superadmin');

        $url = route('admin.portfolio.members.show', ['member' => $designer->id]);
        $this->actingAs($sameAdmin)->get($url)->assertOk();
        $this->actingAs($super)->get($url)->assertOk();
        $this->actingAs($otherAdmin)->get($url)->assertForbidden();
        $this->actingAs($designer)->get($url)->assertForbidden(); // 一般メンバーは admin エリアに入れない
    }
}

