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

    public function test_管理画面は閲覧専用でメンバー選択肢が出る(): void
    {
        $company = $this->company();
        $designer = $this->designer($company);
        $admin = $this->manager('admin', $company);

        $this->actingAs($admin)->get(route('admin.portfolio.manage'))
            ->assertInertia(fn ($page) => $page->where('readOnly', true)->has('owners', 1)->where('ownerId', $designer->id));
        $this->actingAs($designer)->get(route('user.portfolio.manage'))
            ->assertInertia(fn ($page) => $page->where('readOnly', false)->where('owners', []));
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

    public function test_作品を持つAdminは閲覧するメンバーの選択肢に自分と並んで出る(): void
    {
        $this->fakeDisk();
        $company = $this->company();
        $viewer = $this->manager('admin', $company);
        $adminOwner = $this->manager('admin', $company);
        PortfolioPermission::create(['user_id' => $adminOwner->id, 'enabled' => true]);
        $this->upload($adminOwner)->assertCreated();

        $this->actingAs($viewer)->get(route('admin.portfolio.manage'))
            ->assertInertia(fn ($page) => $page->has('owners', 1)->where('owners.0.id', $adminOwner->id));
        // 本人は、自分も選択肢に出る（作品があるため）。ラベルに「（自分）」が付く
        $this->actingAs($adminOwner)->get(route('admin.portfolio.manage'))
            ->assertInertia(fn ($page) => $page->has('owners', 1)->where('owners.0.id', $adminOwner->id)->where('owners.0.label', fn ($l) => str_contains($l, '（自分）')));
    }
}

