<template>
  <AppLayout :title="sheet.name + ' - 進行管理表'">
    <template #header>
      <div class="flex flex-col gap-1">
        <div class="flex items-center gap-3">
          <h2 class="text-xl font-semibold leading-tight text-gray-800">
            進行管理表：{{ sheet.name }}
          </h2>
        </div>
        <!-- 案件情報バー -->
        <div class="flex flex-wrap items-center gap-x-4 gap-y-0.5 text-sm text-gray-600">
          <span v-if="projectJob.client_name" class="font-medium text-gray-700">{{ projectJob.client_name }}</span>
          <span v-if="projectJob.client_name && projectJob.title" class="text-gray-400">/</span>
          <span class="font-medium text-indigo-700">{{ projectJob.title }}</span>
          <span v-if="projectJob.size_name" class="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-700">サイズ: {{ projectJob.size_name }}</span>
          <span v-if="projectJob.page_count" class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">総{{ projectJob.page_count }}ページ</span>
        </div>
      </div>
    </template>

    <div class="rounded bg-white p-6 shadow">

      <!-- ── ツールバー ──────────────────────────────── -->
      <div class="mb-4 flex flex-wrap items-center gap-3">
        <Link
          :href="route('coordinator.project_jobs.show', { projectJob: projectJob.id })"
          class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
        >
          ← 案件詳細に戻る
        </Link>

        <template v-if="canEdit">
          <button
            type="button"
            class="rounded px-3 py-1.5 text-sm font-medium"
            :class="editMode ? 'bg-gray-600 text-white hover:bg-gray-700' : 'bg-indigo-600 text-white hover:bg-indigo-700'"
            @click="editMode = !editMode"
          >
            {{ editMode ? '編集モードを終了' : '編集モード' }}
          </button>

          <!-- テンプレートとして登録 -->
          <button
            v-if="!editMode"
            type="button"
            class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
            @click="showRegisterModal = true"
          >
            テンプレートとして登録
          </button>

          <!-- セット方式で初期化 -->
          <button
            v-if="!editMode"
            type="button"
            class="rounded border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-sm font-medium text-indigo-700 hover:bg-indigo-100"
            @click="v2InitRounds = [{ label: '初校' }]; showV2InitModal = true"
          >
            セット方式で初期化
          </button>

          <!-- シート削除 -->
          <button
            type="button"
            class="rounded border border-red-200 bg-white px-3 py-1.5 text-sm text-red-500 hover:bg-red-50"
            @click="confirmDelete"
          >
            シート削除
          </button>
        </template>

        <!-- 変更保存ボタン（セル編集後） -->
        <button
          v-if="pendingCells.length > 0 && !editMode"
          type="button"
          class="rounded bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700"
          @click="saveCells"
        >
          変更を保存 ({{ pendingCells.length }})
        </button>

      </div>

      <!-- ── 編集モード：行管理 + 列ツリー ──────────────── -->
      <div v-if="editMode && canEdit" class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">

        <!-- シート名編集 -->
        <div class="col-span-1 lg:col-span-2">
          <label class="block text-sm font-medium text-gray-700">シート名</label>
          <input
            v-model="localSheetName"
            type="text"
            class="mt-1 w-full max-w-sm rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
            placeholder="シート名"
          />
        </div>

        <!-- 左：行管理（台割） -->
        <div>
          <h3 class="mb-2 font-semibold text-gray-700">行管理（台割）</h3>

          <!-- 行追加フォーム -->
          <div class="mb-3 flex gap-2">
            <input
              v-model="newRowLabel"
              type="text"
              class="flex-1 rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
              placeholder="例: P.1-4"
              @keydown.enter.prevent="addRow"
            />
            <button
              type="button"
              class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
              @click="addRow"
            >
              追加
            </button>
          </div>

          <!-- すべてに子要素を追加 -->
          <div class="mb-3 flex gap-2">
            <input
              v-model="bulkChildLabel"
              type="text"
              class="flex-1 rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
              placeholder="例: 学部学科"
              @keydown.enter.prevent="addChildToAllGroups"
            />
            <button
              type="button"
              class="rounded bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"
              @click="addChildToAllGroups"
            >
              すべてに子要素を追加
            </button>
          </div>

          <!-- テキストエリアで一括インポート -->
          <details class="mb-3">
            <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700">一括インポート（改行区切り）</summary>
            <div class="mt-2 flex flex-col gap-2">
              <textarea
                v-model="importText"
                rows="5"
                class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
                placeholder="P.1-4&#10;P.5-8&#10;表紙"
              />
              <button
                type="button"
                class="self-start rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
                @click="importRows"
              >
                インポート
              </button>
            </div>
          </details>

          <!-- 行一覧（ツリー表示） -->
          <div class="rounded border border-gray-200 p-2 space-y-2">
              <template v-for="(row, idx) in topLevelRows" :key="row.id">
                <div>

              <!-- グループ親行 -->
              <div v-if="childrenOf[row.id]?.length > 0">
                <div class="flex items-center gap-2 rounded border border-indigo-200 bg-indigo-50 px-3 py-1.5">
                  <span class="flex-1 text-sm font-medium text-indigo-700">{{ row.label }}</span>
                  <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-600">見出し</span>
                  <button type="button" class="rounded bg-blue-50 px-1.5 py-0.5 text-xs text-blue-600 hover:bg-blue-100" @click="startAddChild(row.id)">＋子行</button>
                  <button v-if="idx > 0" type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="moveTopRowUp(idx)">↑</button>
                  <button v-if="idx < topLevelRows.length - 1" type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="moveTopRowDown(idx)">↓</button>
                  <button type="button" class="text-xs text-red-400 hover:text-red-600" @click="deleteRow(row)">✕</button>
                </div>
                <!-- 子行 -->
                <div class="ml-6 mt-1 space-y-1 border-l border-indigo-200 pl-2">
                  <div
                    v-for="child in childrenOf[row.id]"
                    :key="child.id"
                    class="flex items-center gap-2 rounded border border-gray-200 bg-white px-3 py-1"
                  >
                    <span class="flex-1 text-sm">{{ child.label }}</span>
                    <button type="button" class="text-xs text-red-400 hover:text-red-600" @click="deleteRow(child)">✕</button>
                  </div>
                  <!-- 子行追加インライン入力 -->
                  <div v-if="addingChildTo === row.id" class="flex gap-2 pt-1">
                    <input
                      v-model="newChildLabel"
                      type="text"
                      class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none"
                      placeholder="子行ラベル"
                      @keydown.enter.prevent="confirmAddChild(row)"
                      @keydown.escape="addingChildTo = null"
                    />
                    <button type="button" class="rounded bg-blue-600 px-2 py-1 text-xs font-medium text-white hover:bg-blue-700" @click="confirmAddChild(row)">追加</button>
                    <button type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="addingChildTo = null">✕</button>
                  </div>
                </div>
              </div>

              <!-- フラット行 -->
              <div v-else>
                <div class="flex items-center gap-2 rounded border border-gray-200 px-3 py-1.5">
                  <span class="flex-1 text-sm">{{ row.label }}</span>
                  <button type="button" class="rounded bg-indigo-50 px-1.5 py-0.5 text-xs text-indigo-600 hover:bg-indigo-100" @click="startAddChild(row.id)" title="グループ化して子行を追加">グループ化</button>
                  <button v-if="idx > 0" type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="moveTopRowUp(idx)">↑</button>
                  <button v-if="idx < topLevelRows.length - 1" type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="moveTopRowDown(idx)">↓</button>
                  <button type="button" class="text-xs text-red-400 hover:text-red-600" @click="deleteRow(row)">✕</button>
                </div>
                <!-- グループ化インライン入力 -->
                <div v-if="addingChildTo === row.id" class="ml-6 mt-1 flex gap-2 border-l border-indigo-200 pl-2">
                  <input
                    v-model="newChildLabel"
                    type="text"
                    class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none"
                    placeholder="最初の子行ラベル"
                    @keydown.enter.prevent="confirmAddChild(row)"
                    @keydown.escape="addingChildTo = null"
                  />
                  <button type="button" class="rounded bg-blue-600 px-2 py-1 text-xs font-medium text-white hover:bg-blue-700" @click="confirmAddChild(row)">追加</button>
                  <button type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="addingChildTo = null">✕</button>
                </div>
              </div>

                </div>
              </template>
          </div>
            <div v-if="topLevelRows.length === 0" class="py-2 text-center text-sm text-gray-400">行がありません</div>

          <!-- 並び替え保存ボタン -->
          <button
            v-if="rowOrderChanged"
            type="button"
            class="mt-3 rounded bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700"
            @click="saveRowOrder"
          >
            並び順を保存
          </button>
        </div>

        <!-- 右：列構成エディタ -->
        <div>
          <h3 class="mb-2 font-semibold text-gray-700">列・ステージ構成</h3>
          <ColumnTreeEditor
            :nodes="localColumnConfig"
            :stages="props.stages"
            :sizes="props.sizes"
            :assignments="props.assignments"
            :work-item-types="props.workItemTypes"
            @change="onColumnChange"
          />
        </div>
      </div>

      <!-- 編集モード保存ボタン -->
      <div v-if="editMode && canEdit" class="mb-6 flex justify-center">
        <button
          type="button"
          class="rounded bg-indigo-600 px-8 py-2 text-sm font-medium text-white hover:bg-indigo-700"
          @click="saveColumnConfig"
        >
          保存
        </button>
      </div>

    </div>

    <!-- ── 通常モード：進行管理表テーブル ──────────────── -->
    <div v-if="!editMode || !canEdit" class="mt-4">
      <div v-if="localColumnConfig.length === 0" class="rounded bg-white p-6 shadow py-8 text-center text-gray-400">
        列が定義されていません。編集モードで列を追加してください。
      </div>
      <div
        v-else
        ref="tableWrapRef"
        class="overflow-auto rounded bg-white shadow px-4 py-2"
        :style="{ height: tableHeight, minHeight: '200px', width: '100vw', marginLeft: 'calc(-50vw + 50%)' }"
      >
        <ProgressTable
          :rows="localRows"
          :column-config="localColumnConfig"
          :cells="localCells"
          :users="users"
          :subcontractors="props.subcontractors ?? []"
          :stages="props.stages"
          :sizes="props.sizes"
          :assignments="props.assignments"
          :work-item-types="props.workItemTypes"
          :can-edit="canEdit"
          :edit-mode="false"
          :auth-user-id="authUserId"
          @cell-update="onCellUpdate"
          @edit-row="onEditRow"
          @delete-row="deleteRow"
          @job-link-open="openJobLinkModal"
          @job-link-detail="openJobLinkDetail"
          @complete-assignment="onCompleteAssignmentFromCell"
          @proof-request-open="onProofRequestOpen"
          @proof-direct-complete="onProofDirectComplete"
        />
      </div>
    </div>

    <!-- ── ジョブリンク登録モーダル ──────────────────── -->
    <div
      v-if="jobLinkModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
      @click.self="jobLinkModal.open = false"
    >
      <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <h3 class="mb-4 text-lg font-semibold text-gray-800">
          {{ jobLinkModal.isSubcontractor ? '外注先ジョブとして登録' : (jobLinkModal.isSelfAssign ? 'MyJobとして登録' : 'ジョブ依頼として登録') }}
        </h3>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-gray-600">ジョブタイトル</label>
            <input
              v-model="jobLinkForm.title"
              type="text"
              class="mt-1 w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
            />
          </div>
          <!-- 外注先の場合：名前を読み取り表示 -->
          <div v-if="jobLinkModal.isSubcontractor">
            <label class="block text-xs font-medium text-gray-600">外注先</label>
            <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700">
              {{ subcontractors.find(s => s.id === jobLinkForm.assigneeSubcontractorId)?.name ?? '（外注先）' }}
            </div>
          </div>
          <!-- 通常担当者セレクト -->
          <div v-else>
            <label class="block text-xs font-medium text-gray-600">担当者</label>
            <select
              v-model="jobLinkForm.assigneeUserId"
              class="mt-1 w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
              @change="onAssigneeChange"
            >
              <option :value="authUserId">自分 (MyJob)</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600">期限</label>
            <input
              v-model="jobLinkForm.desiredEndDate"
              type="date"
              class="mt-1 w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600">メモ</label>
            <textarea
              v-model="jobLinkForm.detail"
              rows="2"
              class="mt-1 w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
            />
          </div>
          <p v-if="jobLinkModal.isSubcontractor" class="text-xs text-purple-600">
            ※ 外注先への依頼ジョブとして登録します。完了は進行管理が手動で行います。
          </p>
          <p v-else-if="!jobLinkModal.isSelfAssign" class="text-xs text-orange-600">
            ※ 自分以外を担当者に設定するとジョブ依頼（Coordinator割当）として登録されます。
          </p>
        </div>
        <div class="mt-5 flex justify-end gap-3">
          <button
            type="button"
            class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
            @click="jobLinkModal.open = false"
          >キャンセル</button>
          <button
            type="button"
            class="rounded px-4 py-1.5 text-sm font-medium text-white"
            :class="jobLinkModal.isSubcontractor ? 'bg-purple-600 hover:bg-purple-700' : (jobLinkModal.isSelfAssign ? 'bg-blue-600 hover:bg-blue-700' : 'bg-orange-500 hover:bg-orange-600')"
            :disabled="!jobLinkForm.title"
            @click="submitJobLink"
          >{{ jobLinkModal.isSubcontractor ? '外注先ジョブとして登録' : (jobLinkModal.isSelfAssign ? 'MyJobに登録' : 'ジョブ依頼として登録') }}</button>
        </div>
      </div>
    </div>

    <!-- ── ジョブリンク詳細モーダル ────────────────────── -->
    <div
      v-if="jobLinkDetailModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
      @click.self="jobLinkDetailModal.open = false"
    >
      <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
        <h3 class="mb-3 text-lg font-semibold text-gray-800">登録済みジョブ</h3>
        <dl class="space-y-2 text-sm">
          <div><dt class="text-xs font-medium text-gray-500">タイトル</dt><dd class="text-gray-800">{{ jobLinkDetailModal.title }}</dd></div>
          <div v-if="jobLinkDetailModal.assigneeName"><dt class="text-xs font-medium text-gray-500">担当者</dt><dd class="text-gray-800">{{ jobLinkDetailModal.assigneeName }}</dd></div>
          <div v-if="jobLinkDetailModal.endDate"><dt class="text-xs font-medium text-gray-500">期限</dt><dd class="text-gray-800">{{ jobLinkDetailModal.endDate }}</dd></div>
          <div><dt class="text-xs font-medium text-gray-500">状態</dt><dd><span :class="jobLinkDetailModal.completed ? 'text-yellow-700 font-semibold' : 'text-blue-700'">{{ jobLinkDetailModal.completed ? '✓ 完了' : '未完了' }}</span></dd></div>
        </dl>
        <div class="mt-5 flex flex-wrap justify-end gap-2">
          <button
            v-if="jobLinkDetailModal.assignmentId && !jobLinkDetailModal.isSubcontractor"
            type="button"
            class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
            @click="openAssignmentDetail(jobLinkDetailModal.assignmentId)"
          >ジョブ詳細を開く</button>
          <button
            v-if="canEdit && jobLinkDetailModal.assignmentId && !jobLinkDetailModal.completed"
            type="button"
            class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
            :disabled="jobLinkDetailModal.completing"
            @click="adminCompleteAssignment"
          >{{ jobLinkDetailModal.completing ? '処理中…' : '完了にする' }}</button>
          <button
            v-if="canEdit && jobLinkDetailModal.assignmentId && jobLinkDetailModal.completed"
            type="button"
            class="rounded bg-orange-500 px-4 py-1.5 text-sm font-medium text-white hover:bg-orange-600 disabled:opacity-60"
            :disabled="jobLinkDetailModal.completing"
            @click="adminUncompleteAssignment"
          >{{ jobLinkDetailModal.completing ? '処理中…' : '未完了に戻す' }}</button>
          <button
            v-if="canEdit"
            type="button"
            class="rounded bg-red-100 px-4 py-1.5 text-sm font-medium text-red-700 hover:bg-red-200 disabled:opacity-60"
            :disabled="jobLinkDetailModal.unlinking"
            @click="unlinkJobFromCell"
          >{{ jobLinkDetailModal.unlinking ? '処理中…' : '削除する' }}</button>
          <button
            type="button"
            class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
            @click="jobLinkDetailModal.open = false"
          >閉じる</button>
        </div>
      </div>
    </div>

    <!-- ── テンプレート登録モーダル ────────────────────── -->
    <div
      v-if="showRegisterModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
      @click.self="showRegisterModal = false"
    >
      <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
        <h3 class="mb-4 text-lg font-semibold text-gray-800">テンプレートとして登録</h3>
        <label class="block text-sm font-medium text-gray-700">テンプレート名</label>
        <input
          v-model="registerTemplateName"
          type="text"
          class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
          :placeholder="sheet.name + 'のテンプレート'"
        />
        <div class="mt-4 flex justify-end gap-3">
          <button
            type="button"
            class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
            @click="showRegisterModal = false"
          >
            キャンセル
          </button>
          <button
            type="button"
            class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
            @click="registerTemplate"
          >
            登録
          </button>
        </div>
      </div>
    </div>

    <!-- ── セット方式 初期化モーダル ────────────────────── -->
    <div
      v-if="showV2InitModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
      @click.self="showV2InitModal = false"
    >
      <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
        <h3 class="mb-2 text-lg font-semibold text-gray-800">セット方式で初期化</h3>
        <p class="mb-3 text-xs text-gray-500">
          「組版担当・登録欄 + 校正担当・登録欄」のペアが校ごとに生成されます。<br />
          <span class="font-medium text-orange-600">※ 既存の列定義は上書きされます。</span>
        </p>
        <div class="space-y-2">
          <div v-for="(round, idx) in v2InitRounds" :key="idx" class="flex items-center gap-2">
            <span class="w-12 flex-shrink-0 text-xs text-gray-500">第{{ idx + 1 }}校</span>
            <input
              v-model="round.label"
              type="text"
              class="flex-1 rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
              :placeholder="idx === 0 ? '初校' : idx === 1 ? '再校' : '三校'"
            />
            <button
              v-if="v2InitRounds.length > 1"
              type="button"
              class="text-xs text-red-400 hover:text-red-600"
              @click="v2InitRounds.splice(idx, 1)"
            >✕</button>
          </div>
          <button
            type="button"
            class="text-xs font-medium text-indigo-600 hover:underline"
            @click="v2InitRounds.push({ label: '' })"
          >＋ 校を追加</button>
        </div>
        <div class="mt-5 flex justify-end gap-3">
          <button
            type="button"
            class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
            @click="showV2InitModal = false"
          >キャンセル</button>
          <button
            type="button"
            class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
            @click="applyV2Init"
          >適用</button>
        </div>
      </div>
    </div>

    <ProofRequestModal
      :show="showProofModal"
      :initial-title="proofTargetAssignment?.title || projectJob?.title || ''"
      :project-job-assignment-id="proofTargetAssignment?.id || null"
      :project-job-id="projectJob?.id || null"
      :proof-cell-id="proofTargetCellId"
      @close="showProofModal = false; proofTargetAssignment = null; proofTargetCellId = null"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ProgressTable from '@/Components/ProgressTable.vue';
import ColumnTreeEditor from '@/Components/ColumnTreeEditor.vue';
import ProofRequestModal from '@/Components/ProofRequestModal.vue';

const props = defineProps({
  sheet: Object,
  rows: Array,
  cells: Array,
  users: Array,
  subcontractors: { type: Array, default: () => [] },
  stages: { type: Array, default: () => [] },
  sizes: { type: Array, default: () => [] },
  assignments: { type: Array, default: () => [] },
  workItemTypes: { type: Array, default: () => [] },
  projectJob: Object,
  canEdit: Boolean,
  templates: Array,
});

const authUserId = computed(() => usePage().props.auth?.user?.id ?? null);

// ── テーブルコンテナの動的高さ計算 ──────────────────────────
const tableWrapRef = ref(null);
const tableHeight = ref('calc(100vh - 350px)');

function calcTableHeight() {
  if (!tableWrapRef.value) return;
  const top = tableWrapRef.value.getBoundingClientRect().top;
  tableHeight.value = `${window.innerHeight - top - 4}px`;
}

onMounted(() => {
  calcTableHeight();
  window.addEventListener('resize', calcTableHeight);
  document.body.style.overflowX = 'hidden';
});

onUnmounted(() => {
  window.removeEventListener('resize', calcTableHeight);
  document.body.style.overflowX = '';
});

// 列が未定義の場合は自動で編集モードを開く
const editMode = ref(props.canEdit && (props.sheet.column_config?.length ?? 0) === 0);
const localSheetName = ref(props.sheet.name ?? '');
const showRegisterModal = ref(false);
const showV2InitModal = ref(false);
const v2InitRounds = ref([{ label: '初校' }]);
const showProofModal = ref(false);
const proofTargetAssignment = ref(null);
const proofTargetCellId = ref(null);
const registerTemplateName = ref('');
const newRowLabel = ref('');
const importText = ref('');
const addingChildTo = ref(null);
const newChildLabel = ref('');
const bulkChildLabel = ref('');

// ── ジョブリンク ──────────────────────────────────────
const jobLinkModal = ref({ open: false, isSelfAssign: true, isSubcontractor: false, rowId: null, colKey: null });
const jobLinkForm = ref({ title: '', detail: '', desiredEndDate: '', assigneeUserId: null, assigneeSubcontractorId: null });
const jobLinkDetailModal = ref({ open: false, title: '', assigneeName: '', endDate: '', completed: false, assignmentId: null, completing: false, unlinking: false, rowId: null, colKey: null, isSubcontractor: false });

/** 列ツリーをたどってキーまでのラベルパスを返す */
function findBreadcrumb(nodes, key, path = []) {
  for (const node of nodes) {
    const currentPath = [...path, node.label];
    if (node.key === key) return currentPath;
    if (node.children?.length) {
      const found = findBreadcrumb(node.children, key, currentPath);
      if (found) return found;
    }
  }
  return null;
}

/** colKey の直接の親グループを返す */
function findParentGroup(nodes, key) {
  for (const node of nodes) {
    if (node.children?.some((c) => c.key === key)) return node;
    if (node.children?.length) {
      const found = findParentGroup(node.children, key);
      if (found) return found;
    }
  }
  return null;
}

/** ノード配列のリーフを全収集 */
function collectLeaves(nodes) {
  const leaves = [];
  for (const node of nodes) {
    if (!node.children || node.children.length === 0) {
      leaves.push(node);
    } else {
      leaves.push(...collectLeaves(node.children));
    }
  }
  return leaves;
}

/** 同じ親グループ内の user 型セルに設定されているユーザーIDを返す */
function findSiblingUserValue(colKey, rowId) {
  const parent = findParentGroup(localColumnConfig.value, colKey);
  if (!parent?.children) return null;
  const userLeaves = parent.children.filter((c) => c.type === 'user' && !c.children?.length);
  for (const leaf of userLeaves) {
    const cell = localCells.value.find((c) => c.col_key === leaf.key && c.row_id === rowId);
    if (cell?.value_user_id) return cell.value_user_id;
  }
  return null;
}

/** 同じ親グループ内の user 型セルに設定されている外注先IDを返す */
function findSiblingSubcontractorValue(colKey, rowId) {
  const parent = findParentGroup(localColumnConfig.value, colKey);
  if (!parent?.children) return null;
  const userLeaves = parent.children.filter((c) => c.type === 'user' && !c.children?.length);
  for (const leaf of userLeaves) {
    const cell = localCells.value.find((c) => c.col_key === leaf.key && c.row_id === rowId);
    if (cell?.value_subcontractor_id) return cell.value_subcontractor_id;
  }
  return null;
}

/** 同じ行で指定タイプのセル値（value_text）を返す（グループ制限なし・行レベル全列検索） */
function findSiblingCellValue(colKey, rowId, type) {
  // まず同グループ内を優先検索、見つからなければ全列から検索
  const parent = findParentGroup(localColumnConfig.value, colKey);
  const primaryNodes = parent?.children ?? [];
  const allLeaves = collectLeaves(localColumnConfig.value);

  // 同グループ内を優先
  for (const node of collectLeaves(primaryNodes)) {
    if (node.type === type && node.key !== colKey) {
      const cell = localCells.value.find((c) => c.col_key === node.key && c.row_id === rowId);
      if (cell?.value_text) return cell.value_text;
    }
  }
  // 見つからなければ全列から
  for (const node of allLeaves) {
    if (node.type === type && node.key !== colKey) {
      const cell = localCells.value.find((c) => c.col_key === node.key && c.row_id === rowId);
      if (cell?.value_text) return cell.value_text;
    }
  }
  return null;
}

/**
 * colKey の祖先グループノードから指定型のIDを返す
 * 1. type 一致 + label 一致（本来の動作）
 * 2. type 不問で label がマスターリストに一致（グループノードが text 型でも stage 名のラベルを持つ場合）
 */
function findAncestorGroupId(colKey, type, masterList) {
  function collectAncestors(nodes, key, path = []) {
    for (const node of nodes) {
      const next = [...path, node];
      if (node.key === key) return path; // keyに達したら親リストを返す
      if (node.children?.length) {
        const found = collectAncestors(node.children, key, next);
        if (found !== null) return found;
      }
    }
    return null;
  }
  const ancestors = collectAncestors(localColumnConfig.value, colKey);
  if (!ancestors) return null;
  // 1. type 一致優先
  for (const ancestor of [...ancestors].reverse()) {
    if (ancestor.type === type && ancestor.label) {
      const found = masterList.find((item) => item.name === ancestor.label);
      if (found) return String(found.id);
    }
  }
  // 2. type 不問 ─ ラベル名がマスターリストに一致する祖先（text 型グループ見出しにステージ名がつく場合など）
  for (const ancestor of [...ancestors].reverse()) {
    if (ancestor.label) {
      const found = masterList.find((item) => item.name === ancestor.label);
      if (found) return String(found.id);
    }
  }
  return null;
}

/** ジョブタイトルを自動構築：「親行ラベルー縦軸ラベル_横軸中見出しー列ラベル」 */
function buildJobTitle(rowId, colKey) {
  const row = localRows.value.find((r) => r.id === rowId);
  const parentRow = row?.parent_id ? localRows.value.find((r) => r.id === row.parent_id) : null;
  const breadcrumb = findBreadcrumb(localColumnConfig.value, colKey); // [top, ..., leaf]
  const parentPath = breadcrumb ? breadcrumb.slice(0, -1) : []; // leafを除く親グループパス
  const rowPart = [parentRow?.label, row?.label].filter(Boolean).join('_');
  const colPart = parentPath.filter(Boolean).join('_');
  return [rowPart, colPart].filter(Boolean).join('_');
}

function onAssigneeChange() {
  jobLinkModal.value.isSelfAssign = (jobLinkForm.value.assigneeUserId === authUserId.value);
}

function openJobLinkModal({ rowId, colKey }) {
  const siblingUserId = findSiblingUserValue(colKey, rowId);
  const siblingSubcontractorId = findSiblingSubcontractorValue(colKey, rowId);
  const title = buildJobTitle(rowId, colKey);

  // 外注先が指定されている場合（ユーザーより優先）：モーダルで直接登録
  if (siblingSubcontractorId && !siblingUserId) {
    jobLinkForm.value = { title, detail: '', desiredEndDate: '', assigneeUserId: null, assigneeSubcontractorId: siblingSubcontractorId };
    jobLinkModal.value = { open: true, isSelfAssign: false, isSubcontractor: true, rowId, colKey };
    return;
  }

  const assigneeId = siblingUserId ?? authUserId.value;
  const isSelf = assigneeId === authUserId.value || !siblingUserId;

  // セル値優先、なければ大見出しグループ or projectJob フォールバック
  const sizeIdFromCell = findSiblingCellValue(colKey, rowId, 'size');
  const sizeId =
    sizeIdFromCell ??
    findAncestorGroupId(colKey, 'size', props.sizes) ??
    (props.projectJob.size_id ? String(props.projectJob.size_id) : null);

  const stageIdFromCell = findSiblingCellValue(colKey, rowId, 'stage');
  const stageId = stageIdFromCell ?? findAncestorGroupId(colKey, 'stage', props.stages);

  const workItemTypeIdFromCell = findSiblingCellValue(colKey, rowId, 'workItemType');
  const workItemTypeId =
    workItemTypeIdFromCell ?? findAncestorGroupId(colKey, 'workItemType', props.workItemTypes);

  const params = { title };
  if (sizeId) params.size_id = sizeId;
  if (stageId) params.stage_id = stageId;
  if (workItemTypeId) params.work_item_type_id = workItemTypeId;
  if (props.projectJob?.client_id) params.client_id = props.projectJob.client_id;
  params.project_job_id = props.projectJob.id;
  // 進行表セルリンクに必要な情報を渡す（セル登録後に assignment_id が紐付くようにする）
  params.progress_sheet_id = props.sheet.id;
  params.row_id = rowId;
  params.col_key = colKey;

  if (isSelf) {
    // 自己割当（MyJob）→ events/create-job へ遷移
    router.visit(route('events.create_job', params));
  } else {
    // 他者割当（ジョブ依頼）→ Coordinator割当作成ページへ遷移（担当者IDも渡す）
    if (assigneeId) params.user_id = assigneeId;
    router.visit(route('coordinator.project_jobs.assignments.create', { projectJob: props.projectJob.id, ...params }));
  }
}

function submitJobLink() {
  const payload = {
    row_id: jobLinkModal.value.rowId,
    col_key: jobLinkModal.value.colKey,
    title: jobLinkForm.value.title,
    detail: jobLinkForm.value.detail || null,
    desired_end_date: jobLinkForm.value.desiredEndDate || null,
  };
  if (jobLinkModal.value.isSubcontractor && jobLinkForm.value.assigneeSubcontractorId) {
    payload.assignee_subcontractor_id = jobLinkForm.value.assigneeSubcontractorId;
  } else {
    payload.assignee_user_id = jobLinkForm.value.assigneeUserId;
  }
  router.post(
    route('coordinator.progress_sheets.cells.link_job', { sheet: props.sheet.id }),
    payload,
    {
      preserveScroll: true,
      onSuccess: (page) => {
        jobLinkModal.value.open = false;
        // セルを再同期
        if (page.props.cells) {
          localCells.value = page.props.cells.map((c) => ({ ...c }));
        }
      },
    }
  );
}

function openAssignmentDetail(assignmentId) {
  if (!assignmentId || !props.projectJob?.id) return;
  router.visit(route('coordinator.project_jobs.assignments.show', { projectJob: props.projectJob.id, assignment: assignmentId }));
}

function openJobLinkDetail({ assignmentId, assignmentTitle, assigneeUserId, assigneeSubcontractorId, endDate, completed, rowId, colKey }) {
  let assigneeName = null;
  if (assigneeSubcontractorId) {
    const sub = props.subcontractors.find((s) => s.id === assigneeSubcontractorId);
    assigneeName = sub ? `[外注] ${sub.name}` : null;
  }
  if (!assigneeName) {
    const assignee = props.users.find((u) => u.id === assigneeUserId);
    assigneeName = assignee?.name ?? null;
  }
  jobLinkDetailModal.value = {
    open: true,
    title: assignmentTitle ?? '(タイトルなし)',
    assigneeName,
    isSubcontractor: !!assigneeSubcontractorId,
    endDate: endDate ?? null,
    completed: !!completed,
    assignmentId: assignmentId ?? null,
    completing: false,
    unlinking: false,
    rowId: rowId ?? null,
    colKey: colKey ?? null,
  };
}

// ── 管理者・担当者による完了管理 ──────────────────────────────────────────

function updateLocalCellCompleted(assignmentId, completedValue) {
  const idx = localCells.value.findIndex((c) => c.assignment_id === assignmentId);
  if (idx >= 0) {
    localCells.value.splice(idx, 1, { ...localCells.value[idx], assignment_completed: completedValue });
  }
}

async function callAssignmentApi(url) {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
  });
  if (!res.ok) throw new Error(await res.text());
  return res.json();
}

async function adminCompleteAssignment() {
  const id = jobLinkDetailModal.value.assignmentId;
  if (!id) return;
  jobLinkDetailModal.value.completing = true;
  try {
    await callAssignmentApi(route('coordinator.progress_sheets.assignments.complete', { assignment: id }));
    jobLinkDetailModal.value.completed = true;
    updateLocalCellCompleted(id, true);
  } catch { /* ignore */ }
  finally { jobLinkDetailModal.value.completing = false; }
}

async function adminUncompleteAssignment() {
  const id = jobLinkDetailModal.value.assignmentId;
  if (!id) return;
  jobLinkDetailModal.value.completing = true;
  try {
    await callAssignmentApi(route('coordinator.progress_sheets.assignments.uncomplete', { assignment: id }));
    jobLinkDetailModal.value.completed = false;
    updateLocalCellCompleted(id, false);
  } catch { /* ignore */ }
  finally { jobLinkDetailModal.value.completing = false; }
}

async function unlinkJobFromCell() {
  const modal = jobLinkDetailModal.value;
  if (!modal.rowId || !modal.colKey) return;

  const hasAssignment = !!modal.assignmentId;
  const msg = hasAssignment
    ? 'この登録情報を削除しますか？\n\n・カレンダーの予定がない場合はマイジョブも削除されます。\n・カレンダーの予定がある場合はマイジョブは管理シートと無関係なジョブとして残ります。'
    : 'この登録情報を削除しますか？（マイジョブのデータが見つからないため、セルのリンクのみクリアされます）';
  if (!confirm(msg)) return;

  modal.unlinking = true;
  try {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const res = await fetch(route('coordinator.progress_sheets.cells.unlink_job', { sheet: props.sheet.id }), {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
      },
      body: JSON.stringify({ row_id: modal.rowId, col_key: modal.colKey }),
    });
    if (res.ok) {
      // ローカルセルをクリア
      const idx = localCells.value.findIndex((c) => c.row_id === modal.rowId && c.col_key === modal.colKey);
      if (idx >= 0) {
        localCells.value.splice(idx, 1, {
          ...localCells.value[idx],
          assignment_id: null,
          assignment_title: null,
          assignment_completed: null,
          assignment_user_id: null,
          assignment_end_date: null,
        });
      }
      modal.open = false;
    } else {
      alert('削除に失敗しました。');
    }
  } catch {
    alert('削除に失敗しました。');
  } finally {
    modal.unlinking = false;
  }
}

async function onCompleteAssignmentFromCell({ assignmentId }) {
  if (!assignmentId) return;
  try {
    await callAssignmentApi(route('coordinator.progress_sheets.assignments.complete', { assignment: assignmentId }));
    updateLocalCellCompleted(assignmentId, true);
  } catch { /* ignore */ }
}

/** 進行表の proof_user セルから「校正管理へ依頼」選択時 */
function onProofRequestOpen({ rowId, colKey }) {
  // proof_user セルの id を特定して ProofRequestModal に渡す
  const cell = localCells.value.find((c) => c.row_id === rowId && c.col_key === colKey);
  proofTargetCellId.value = cell?.id ?? null;

  // colKey が "{prefix}_kosei_tanto" の場合、同じ行の "{prefix}_kumihan_toroku" セルから
  // pja_operator の id とタイトルを取得して ProofRequestModal に渡す
  const kumiTorokuKey = colKey.replace(/_kosei_tanto$/, '_kumihan_toroku');
  if (kumiTorokuKey !== colKey) {
    const kumiCell = localCells.value.find((c) => c.row_id === rowId && c.col_key === kumiTorokuKey);
    proofTargetAssignment.value = kumiCell?.assignment_id
      ? { id: kumiCell.assignment_id, title: kumiCell.assignment_title ?? '' }
      : null;
  } else {
    proofTargetAssignment.value = null;
  }

  showProofModal.value = true;
}

/** joblink セルの「校了にする」ボタン */
async function onProofDirectComplete({ assignmentId }) {
  if (!assignmentId) return;
  try {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const res = await fetch(route('coordinator.progress_sheets.assignments.proof_complete', { assignment: assignmentId }), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    });
    if (res.ok) {
      // ローカルセルの assignment_proof_completed を更新
      const idx = localCells.value.findIndex((c) => c.assignment_id === assignmentId);
      if (idx >= 0) {
        localCells.value.splice(idx, 1, { ...localCells.value[idx], assignment_proof_completed: true });
      }
    }
  } catch { /* ignore */ }
}

// ローカルコピー
const localColumnConfig = ref(JSON.parse(JSON.stringify(props.sheet.column_config ?? [])));
const localRows = ref(props.rows.map((r) => ({ ...r })));
const localCells = ref(props.cells.map((c) => ({ ...c })));

// セル pending（未保存の変更）
const pendingCells = ref([]);

// ── 行ツリー計算 ───────────────────────────────────────────
const topLevelRows = computed(() =>
  localRows.value.filter((r) => !r.parent_id)
);

const childrenOf = computed(() => {
  const map = {};
  for (const r of localRows.value) {
    if (r.parent_id) {
      (map[r.parent_id] ??= []).push(r);
    }
  }
  return map;
});

// 並び替え変更検出（トップレベルのみ）
let savedTopLevelIds = props.rows.filter((r) => !r.parent_id).map((r) => r.id);

const rowOrderChanged = computed(() =>
  JSON.stringify(topLevelRows.value.map((r) => r.id)) !== JSON.stringify(savedTopLevelIds)
);

// ── 列構成 ──
function onColumnChange(updated) {
  localColumnConfig.value = updated.slice();
}

function saveColumnConfig() {
  router.put(
    route('coordinator.progress_sheets.update', { sheet: props.sheet.id }),
    { name: localSheetName.value, column_config: localColumnConfig.value },
    {
      preserveScroll: true,
      onSuccess: () => { editMode.value = false; },
    }
  );
}

function generateV2ColumnConfig(rounds) {
  return rounds
    .filter((r) => r.label.trim())
    .map((round, idx) => {
      const key = 'round' + (idx + 1);
      return {
        key,
        label: round.label.trim(),
        type: 'text',
        children: [
          {
            key: key + '_kumihan',
            label: '組版',
            type: 'text',
            children: [
              { key: key + '_kumihan_tanto',  label: '担当',   type: 'user' },
              { key: key + '_kumihan_toroku', label: '登録欄', type: 'joblink' },
            ],
          },
          {
            key: key + '_kosei',
            label: '校正',
            type: 'text',
            children: [
              { key: key + '_kosei_tanto',  label: '担当',   type: 'proof_user' },
              { key: key + '_kosei_toroku', label: '登録欄', type: 'joblink' },
            ],
          },
        ],
      };
    });
}

function applyV2Init() {
  const config = generateV2ColumnConfig(v2InitRounds.value);
  if (config.length === 0) {
    alert('少なくとも1つの校を入力してください。');
    return;
  }
  localColumnConfig.value = config;
  showV2InitModal.value = false;
  editMode.value = false;
  router.put(
    route('coordinator.progress_sheets.update', { sheet: props.sheet.id }),
    { name: localSheetName.value, column_config: config },
    { preserveScroll: true }
  );
}

// ── 行管理 ──
function syncRowsFromPage(page) {
  if (page.props.rows) {
    localRows.value = page.props.rows.map((r) => ({ ...r }));
    savedTopLevelIds = localRows.value.filter((r) => !r.parent_id).map((r) => r.id);
  }
}

function addRow() {
  const label = newRowLabel.value.trim();
  if (!label) return;
  router.post(
    route('coordinator.progress_sheets.rows.store', { sheet: props.sheet.id }),
    { label },
    {
      preserveScroll: true,
      onSuccess: (page) => {
        syncRowsFromPage(page);
        newRowLabel.value = '';
      },
    }
  );
}

function importRows() {
  const labels = importText.value.split('\n').map((l) => l.trim()).filter(Boolean);
  if (labels.length === 0) return;
  router.post(
    route('coordinator.progress_sheets.rows.import', { sheet: props.sheet.id }),
    { labels },
    {
      preserveScroll: true,
      onSuccess: (page) => {
        syncRowsFromPage(page);
        importText.value = '';
      },
    }
  );
}

function deleteRow(row) {
  const hasChildren = childrenOf.value[row.id]?.length > 0;
  const msg = hasChildren
    ? `グループ「${row.label}」と子行をすべて削除しますか？セルデータも全て削除されます。`
    : `行「${row.label}」を削除しますか？セルデータも全て削除されます。`;
  if (!confirm(msg)) return;
  router.delete(
    route('coordinator.progress_sheets.rows.destroy', { sheet: props.sheet.id, row: row.id }),
    {
      preserveScroll: true,
      onSuccess: (page) => { syncRowsFromPage(page); },
    }
  );
}

function startAddChild(rowId) {
  addingChildTo.value = rowId;
  newChildLabel.value = '';
}

function confirmAddChild(row) {
  const label = newChildLabel.value.trim();
  if (!label) return;
  const hasChildren = childrenOf.value[row.id]?.length > 0;
  if (hasChildren) {
    // 既存グループに子行を追加
    router.post(
      route('coordinator.progress_sheets.rows.store', { sheet: props.sheet.id }),
      { label, parent_id: row.id },
      {
        preserveScroll: true,
        onSuccess: (page) => {
          syncRowsFromPage(page);
          addingChildTo.value = null;
          newChildLabel.value = '';
        },
      }
    );
  } else {
    // フラット行をグループ化（make-group）
    router.post(
      route('coordinator.progress_sheets.rows.make_group', { sheet: props.sheet.id, row: row.id }),
      { child_label: label },
      {
        preserveScroll: true,
        onSuccess: (page) => {
          syncRowsFromPage(page);
          addingChildTo.value = null;
          newChildLabel.value = '';
        },
      }
    );
  }
}

function addChildToAllGroups() {
  const label = bulkChildLabel.value.trim();
  if (!label) return;

  // すべてのトップレベル行を取得
  const allRows = topLevelRows.value;

  if (allRows.length === 0) {
    alert('行がありません。まず行を追加してください。');
    return;
  }

  // 順次処理で各行に子要素を追加
  let index = 0;

    function processNext() {
    if (index >= allRows.length) {
      // すべて完了したらページをリロード
      router.reload({ preserveScroll: true });
      bulkChildLabel.value = '';
      return;
    }

    const row = allRows[index];
    const hasChildren = childrenOf.value[row.id]?.length > 0;

    index++;
    // Optimistic update: まずローカルに仮の子行を追加して即時表示させ、
    // サーバ応答を受け取ったら正式な rows に同期する。
    const tempId = 'tmp-' + Date.now() + '-' + Math.random().toString(36).slice(2);
    // 仮行を追加（親子どちらでも同じ形で追加することで UI がグループ化を反映する）
    localRows.value.push({ id: tempId, sheet_id: props.sheet.id, label, parent_id: row.id, order: null, created_at: new Date().toISOString() });

    if (hasChildren) {
      // 既にグループ化されている場合：子要素を追加
      router.post(
        route('coordinator.progress_sheets.rows.store', { sheet: props.sheet.id }),
        { label, parent_id: row.id },
        {
          preserveScroll: true,
          onSuccess: (page) => {
            // サーバから返された rows をローカルに同期
            syncRowsFromPage(page);
            processNext();
          },
          onError: (errors) => {
            console.error('子要素追加エラー:', errors);
            // 仮行を削除
            const ti = localRows.value.findIndex((r) => r.id === tempId);
            if (ti >= 0) localRows.value.splice(ti, 1);
            alert(`「${row.label}」への子要素追加に失敗しました。`);
            processNext();
          }
        }
      );
    } else {
      // グループ化されていない場合：まずグループ化してから子要素を追加
      router.post(
        route('coordinator.progress_sheets.rows.make_group', { sheet: props.sheet.id, row: row.id }),
        { child_label: label },
        {
          preserveScroll: true,
          onSuccess: (page) => {
            // サーバから返された rows をローカルに同期
            syncRowsFromPage(page);
            processNext();
          },
          onError: (errors) => {
            console.error('グループ化エラー:', errors);
            // 仮行を削除
            const ti = localRows.value.findIndex((r) => r.id === tempId);
            if (ti >= 0) localRows.value.splice(ti, 1);
            alert(`「${row.label}」のグループ化に失敗しました。`);
            processNext();
          }
        }
      );
    }
  }

  // 最初の処理を開始
  processNext();
}

/** top-levelの行グループ（親+子）をフラット配列から取り出す */
function extractGroup(arr, parentRow) {
  const result = [parentRow];
  for (const r of arr) {
    if (r.parent_id === parentRow.id) result.push(r);
  }
  return result;
}

function moveTopRowUp(idx) {
  if (idx < 1) return;
  const topRows = localRows.value.filter((r) => !r.parent_id);
  const rowA = topRows[idx];     // 上に移動する行
  const rowB = topRows[idx - 1]; // 下に移動する行
  const groupA = extractGroup(localRows.value, rowA);
  const groupB = extractGroup(localRows.value, rowB);
  // 両グループを除いた残り
  const rest = localRows.value.filter((r) => !groupA.includes(r) && !groupB.includes(r));
  // groupBより前の行
  const before = rest.filter((r) => localRows.value.indexOf(r) < localRows.value.indexOf(rowB));
  const after  = rest.filter((r) => localRows.value.indexOf(r) > Math.max(...groupA.map((r) => localRows.value.indexOf(r))));
  localRows.value = [...before, ...groupA, ...groupB, ...after];
}

function moveTopRowDown(idx) {
  const topRows = localRows.value.filter((r) => !r.parent_id);
  if (idx >= topRows.length - 1) return;
  const rowA = topRows[idx];     // 下に移動する行
  const rowB = topRows[idx + 1]; // 上に移動する行
  const groupA = extractGroup(localRows.value, rowA);
  const groupB = extractGroup(localRows.value, rowB);
  const rest = localRows.value.filter((r) => !groupA.includes(r) && !groupB.includes(r));
  const before = rest.filter((r) => localRows.value.indexOf(r) < localRows.value.indexOf(rowA));
  const after  = rest.filter((r) => localRows.value.indexOf(r) > Math.max(...groupB.map((r) => localRows.value.indexOf(r))));
  localRows.value = [...before, ...groupB, ...groupA, ...after];
}

function saveRowOrder() {
  const ids = topLevelRows.value.map((r) => r.id);
  router.put(
    route('coordinator.progress_sheets.rows.reorder', { sheet: props.sheet.id }),
    { ids },
    {
      preserveScroll: true,
      onSuccess: (page) => { syncRowsFromPage(page); },
    }
  );
}

function onEditRow(row) {
  const label = prompt('行ラベルを編集', row.label);
  if (label === null || label.trim() === '' || label === row.label) return;
  router.put(
    route('coordinator.progress_sheets.rows.update', { sheet: props.sheet.id, row: row.id }),
    { label: label.trim() },
    { preserveScroll: true }
  );
}

// ── セル更新 ──
function onCellUpdate(payload) {
  // ローカルに即時反映
  const existing = localCells.value.find((c) => c.row_id === payload.row_id && c.col_key === payload.col_key);
  const fieldMap = { text: 'value_text', date: 'value_date', bool: 'value_bool', user: 'value_user_id', subcontractor: 'value_subcontractor_id' };
  const field = fieldMap[payload.value_type];
  if (existing) {
    existing[field] = payload.value;
    if (payload.value_type === 'user') {
      existing.value_user_name = props.users.find((u) => u.id === payload.value)?.name ?? null;
      existing.value_subcontractor_id = null;
      existing.value_subcontractor_name = null;
    } else if (payload.value_type === 'subcontractor') {
      existing.value_subcontractor_name = props.subcontractors.find((s) => s.id === payload.value)?.name ?? null;
      existing.value_user_id = null;
      existing.value_user_name = null;
    }
  } else {
    const cell = { row_id: payload.row_id, col_key: payload.col_key };
    cell[field] = payload.value;
    if (payload.value_type === 'user') {
      cell.value_user_name = props.users.find((u) => u.id === payload.value)?.name ?? null;
    } else if (payload.value_type === 'subcontractor') {
      cell.value_subcontractor_name = props.subcontractors.find((s) => s.id === payload.value)?.name ?? null;
    }
    localCells.value.push(cell);
  }

  // pending に追加（重複なら上書き）
  const idx = pendingCells.value.findIndex((c) => c.row_id === payload.row_id && c.col_key === payload.col_key);
  if (idx >= 0) {
    pendingCells.value[idx] = payload;
  } else {
    pendingCells.value.push(payload);
  }
}

function saveCells() {
  router.put(
    route('coordinator.progress_sheets.cells.update', { sheet: props.sheet.id }),
    { cells: pendingCells.value },
    {
      preserveScroll: true,
      onSuccess: () => { pendingCells.value = []; },
    }
  );
}

// ── テンプレート登録 ──
function registerTemplate() {
  const name = registerTemplateName.value.trim() || `${props.sheet.name}のテンプレート`;
  router.post(
    route('coordinator.progress_sheets.register_template', { sheet: props.sheet.id }),
    { name },
    {
      preserveScroll: true,
      onSuccess: () => { showRegisterModal.value = false; registerTemplateName.value = ''; },
    }
  );
}

// ── シート削除 ──
function confirmDelete() {
  if (!confirm(`進行管理表「${props.sheet.name}」を削除しますか？`)) return;
  router.delete(route('coordinator.progress_sheets.destroy', { sheet: props.sheet.id }));
}
</script>
