<template>
  <AppLayout :title="sheet.name + ' - 進行管理表'">
    <template #header>
      <div class="flex flex-col gap-1">
        <div class="flex items-center gap-3">
          <Link
            :href="route('coordinator.project_jobs.show', { projectJob: projectJob.id }) + '?tab=progress'"
            class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
          >← 案件詳細に戻る</Link>
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

          <!-- 新形式に変換 -->
          <button
            v-if="!editMode && hasOldPairs"
            type="button"
            class="rounded border border-orange-300 bg-orange-50 px-3 py-1.5 text-sm font-medium text-orange-700 hover:bg-orange-100"
            @click="openConvertPreview"
          >
            新形式に変換
          </button>

          <!-- シート削除 -->
          <button
            type="button"
            class="rounded border border-red-200 bg-white px-3 py-1.5 text-sm text-red-500 hover:bg-red-50"
            @click="confirmDelete"
          >
            シート削除
          </button>

          <!-- 印刷 -->
          <button
            v-if="!editMode"
            type="button"
            class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
            @click="openPrint"
          >
            印刷
          </button>

          <!-- 共有リンク -->
          <template v-if="!editMode">
            <button
              v-if="!localShareToken"
              type="button"
              class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
              :disabled="shareLoading"
              @click="issueShare"
            >
              {{ shareLoading ? '発行中...' : '共有リンクを発行' }}
            </button>
            <template v-else>
              <button
                type="button"
                class="rounded border border-blue-300 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-100"
                @click="copyShareUrl"
              >
                URLをコピー
              </button>
              <button
                type="button"
                class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-50"
                :disabled="shareLoading"
                @click="revokeShare"
              >
                リンクを無効化
              </button>
            </template>
          </template>
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

        <!-- 全体完了率バッジ -->
        <span
          v-if="!editMode && sheetCompletion.total > 0"
          class="ml-auto rounded px-3 py-1 text-sm font-medium"
          :class="sheetCompletion.done === sheetCompletion.total
            ? 'bg-green-100 text-green-700'
            : 'bg-gray-100 text-gray-600'"
        >
          全体: {{ sheetCompletion.done }}/{{ sheetCompletion.total }} 完了
          ({{ Math.round(sheetCompletion.done / sheetCompletion.total * 100) }}%)
        </span>

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

        <!-- 左：行・ステージ構成 -->
        <div>
          <div class="mb-2 flex items-center justify-between">
            <h3 class="font-semibold text-gray-700">行・ステージ構成</h3>
            <div class="flex gap-2">
              <button
                type="button"
                class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-600 hover:bg-gray-50"
                @click="showBulkImportModal = true"
              >一括インポート</button>
              <button
                type="button"
                class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-600 hover:bg-gray-50"
                @click="showAddChildModal = true"
              >すべてに子要素を追加</button>
            </div>
          </div>

          <!-- 行一覧（ツリー表示） -->
          <div class="space-y-1">
              <template v-for="(row, idx) in topLevelRows" :key="row.id">
                <div>
                  <div class="rounded border border-gray-200 bg-white">
                    <!-- 行ヘッダー -->
                    <div class="flex items-center gap-2 px-3 py-2">
                      <span class="cursor-grab text-gray-400">⠿</span>
                      <input
                        v-model="row.label"
                        type="text"
                        class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none"
                        placeholder="例: P.1-4"
                        @change="updateRowLabel(row)"
                        @keydown.enter.prevent
                        @keyup.enter="updateRowLabel(row)"
                      />
                      <span
                        v-if="row.deadline"
                        :class="['rounded px-1.5 py-0.5 text-xs', deadlineStatus(row.deadline) === 'past' ? 'bg-gray-100 text-gray-400' : deadlineStatus(row.deadline) === 'soon' ? 'bg-yellow-100 text-yellow-700 font-semibold' : 'bg-blue-50 text-blue-600']"
                      >⏰ {{ row.deadline }}</span>
                      <span v-if="childrenOf[row.id]?.length > 0" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">グループ</span>
                      <button
                        v-if="!childrenOf[row.id]?.length"
                        type="button"
                        class="rounded bg-indigo-50 px-2 py-0.5 text-xs text-indigo-600 hover:bg-indigo-100"
                        @click="startAddChild(row.id)"
                      >＋グループ</button>
                      <button
                        v-else
                        type="button"
                        class="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-600 hover:bg-blue-100"
                        @click="startAddChild(row.id)"
                      >＋子行</button>
                      <button v-if="idx > 0" type="button" class="text-gray-400 hover:text-gray-600" @click="moveTopRowUp(idx)">↑</button>
                      <button v-if="idx < topLevelRows.length - 1" type="button" class="text-gray-400 hover:text-gray-600" @click="moveTopRowDown(idx)">↓</button>
                      <button type="button" class="rounded bg-orange-50 px-2 py-0.5 text-xs text-orange-600 hover:bg-orange-100" @click="duplicateRow(row)">複製</button>
                      <button type="button" class="rounded px-1.5 py-0.5 text-xs text-red-400 hover:bg-red-50 hover:text-red-600" @click="deleteRow(row)">✕</button>
                    </div>
                    <!-- 子行リスト（グループがある、または追加中） -->
                    <div v-if="childrenOf[row.id]?.length > 0 || addingChildTo === row.id" class="ml-6 border-l border-gray-200 pb-2 pl-2 pr-2">
                      <div class="space-y-1">
                        <div
                          v-for="(child, cidx) in childrenOf[row.id]"
                          :key="child.id"
                          class="flex items-center gap-2 rounded border border-gray-200 bg-white px-3 py-2"
                        >
                          <span class="cursor-grab text-gray-400">⠿</span>
                          <input
                            v-model="child.label"
                            type="text"
                            class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none"
                            placeholder="子行ラベル"
                            @change="updateRowLabel(child)"
                            @keydown.enter.prevent
                            @keyup.enter="updateRowLabel(child)"
                          />
                          <button v-if="cidx > 0" type="button" class="text-gray-400 hover:text-gray-600" @click="moveChildRowUp(row.id, cidx)">↑</button>
                          <button v-if="cidx < childrenOf[row.id].length - 1" type="button" class="text-gray-400 hover:text-gray-600" @click="moveChildRowDown(row.id, cidx)">↓</button>
                          <button type="button" class="rounded bg-orange-50 px-2 py-0.5 text-xs text-orange-600 hover:bg-orange-100" @click="duplicateRow(child)">複製</button>
                          <button type="button" class="rounded px-1.5 py-0.5 text-xs text-red-400 hover:bg-red-50 hover:text-red-600" @click="deleteRow(child)">✕</button>
                        </div>
                        <!-- 子行追加インライン入力 -->
                        <div v-if="addingChildTo === row.id" class="flex items-center gap-2 rounded border border-dashed border-gray-300 bg-white px-3 py-2">
                          <span class="select-none text-transparent">⠿</span>
                          <input
                            v-model="newChildLabel"
                            type="text"
                            class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none"
                            placeholder="子行ラベル"
                            @keydown.enter.prevent
                            @keyup.enter="confirmAddChild(row)"
                            @keydown.escape="newChildLabel = ''; addingChildTo = null"
                            @blur="confirmAddChild(row)"
                          />
                          <button type="button" class="rounded bg-blue-600 px-2 py-1 text-xs font-medium text-white hover:bg-blue-700" @click="confirmAddChild(row)">追加</button>
                          <button type="button" class="rounded px-1.5 py-0.5 text-xs text-red-400 hover:bg-red-50 hover:text-red-600" @mousedown.prevent @click="newChildLabel = ''; addingChildTo = null">✕</button>
                        </div>
                      </div>
                    </div>
                  </div>

              <!-- ここに行を挿入 -->
              <div v-if="pendingNewRow?.after_id === row.id" class="mt-1 rounded border border-dashed border-gray-300 bg-white">
                <div class="flex items-center gap-2 px-3 py-2">
                  <span class="select-none text-transparent">⠿</span>
                  <input
                    data-pending-row-input
                    v-model="pendingNewRow.label"
                    type="text"
                    class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none"
                    placeholder="ラベルを入力して Enter"
                    @keydown.enter.prevent
                    @keyup.enter="commitPendingRow"
                    @keydown.escape="cancelPendingRow"
                    @blur="commitPendingRow"
                  />
                  <button type="button" class="rounded px-1.5 py-0.5 text-xs text-red-400 hover:bg-red-50 hover:text-red-600" @mousedown.prevent @click="cancelPendingRow">✕</button>
                </div>
              </div>
              <button
                v-else
                type="button"
                class="mt-1 flex w-full items-center justify-center rounded border border-dashed border-gray-300 py-0.5 text-xs text-gray-400 hover:border-blue-400 hover:text-blue-500"
                @click="startPendingRow(row.id)"
              >＋ ここに行を挿入</button>

                </div>
              </template>

              <!-- 行を追加 -->
              <div v-if="pendingNewRow && pendingNewRow.after_id === null" class="mt-1 rounded border border-dashed border-gray-300 bg-white">
                <div class="flex items-center gap-2 px-3 py-2">
                  <span class="select-none text-transparent">⠿</span>
                  <input
                    data-pending-row-input
                    v-model="pendingNewRow.label"
                    type="text"
                    class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none"
                    placeholder="ラベルを入力して Enter"
                    @keydown.enter.prevent
                    @keyup.enter="commitPendingRow"
                    @keydown.escape="cancelPendingRow"
                    @blur="commitPendingRow"
                  />
                  <button type="button" class="rounded px-1.5 py-0.5 text-xs text-red-400 hover:bg-red-50 hover:text-red-600" @mousedown.prevent @click="cancelPendingRow">✕</button>
                </div>
              </div>
              <button
                v-else
                type="button"
                class="mt-1 flex w-full items-center justify-center rounded border border-dashed border-gray-300 py-1.5 text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-500"
                @click="startPendingRow(null)"
              >＋ 行を追加</button>
          </div>

          <!-- 項目から読み込む -->
          <button
            v-if="props.projectJob?.id"
            type="button"
            class="mt-2 flex w-full items-center justify-center rounded border border-dashed border-indigo-300 py-1.5 text-sm text-indigo-600 hover:border-indigo-500 hover:bg-indigo-50"
            @click="openLoadItemsModal"
          >＋ 項目から読み込む</button>

          <!-- 並び替え保存ボタン -->
          <button
            v-if="rowOrderChanged"
            type="button"
            class="mt-3 rounded bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700"
            @click="saveRowOrder"
          >
            並び順を保存
          </button>

          <!-- 一括インポート モーダル -->
          <div v-if="showBulkImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showBulkImportModal = false">
            <div class="w-96 rounded-lg bg-white p-6 shadow-xl">
              <h4 class="mb-3 font-semibold text-gray-700">一括インポート（改行区切り）</h4>
              <textarea
                v-model="importText"
                rows="8"
                class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
                placeholder="P.1-4&#10;P.5-8&#10;表紙"
              />
              <div class="mt-3 flex justify-end gap-2">
                <button type="button" class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50" @click="showBulkImportModal = false">キャンセル</button>
                <button type="button" class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700" @click="importRows(); showBulkImportModal = false">インポート</button>
              </div>
            </div>
          </div>

          <!-- すべてに子要素を追加 モーダル -->
          <div v-if="showAddChildModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showAddChildModal = false">
            <div class="w-80 rounded-lg bg-white p-6 shadow-xl">
              <h4 class="mb-3 font-semibold text-gray-700">すべてに子要素を追加</h4>
              <input
                v-model="bulkChildLabel"
                type="text"
                class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
                placeholder="例: 学部学科"
                @keydown.enter.prevent="addChildToAllGroups(); showAddChildModal = false"
              />
              <div class="mt-3 flex justify-end gap-2">
                <button type="button" class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50" @click="showAddChildModal = false">キャンセル</button>
                <button type="button" class="rounded bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700" @click="addChildToAllGroups(); showAddChildModal = false">追加</button>
              </div>
            </div>
          </div>
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
          :project-schedules="props.projectSchedules"
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
          @worker-complete="onWorkerComplete"
          @worker-job-register="onWorkerJobRegister"
          @worker-job-detail="onWorkerJobDetail"
          @schedlink-complete="onSchedlinkComplete"
          @note-save="onNoteSave"
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

    <!-- ── V2 変換プレビューモーダル ────────────────────────── -->
    <div
      v-if="showConvertModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
      @click.self="showConvertModal = false"
    >
      <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
        <h3 class="mb-3 text-lg font-semibold text-gray-800">新形式に変換 — プレビュー</h3>

        <!-- 読み込み中 -->
        <div v-if="convertPreviewLoading" class="py-8 text-center text-sm text-gray-400">読み込み中...</div>

        <!-- プレビュー内容 -->
        <template v-else-if="convertPreviewData">
          <p class="mb-2 text-sm font-medium text-gray-600">検出されたペア：</p>
          <ul class="mb-4 space-y-2">
            <li
              v-for="pair in convertPreviewData.pairs"
              :key="pair.user_col_key"
              class="rounded border border-gray-200 bg-gray-50 p-3 text-sm"
            >
              <div class="font-medium text-gray-700">
                ✅ {{ pair.parent_label }} — 担当列＋登録欄 → 担当＋ジョブ（worker型）
              </div>
              <div class="mt-1 text-xs text-gray-500">
                担当者設定: {{ pair.cells_with_user }}セル ／ ジョブ登録: {{ pair.cells_with_job }}セル
              </div>
              <div v-if="pair.cells_unmigratable > 0" class="mt-1 text-xs font-medium text-red-600">
                ❌ 引き継げないデータ: {{ pair.cells_unmigratable }}セル（変換後に空になります）
              </div>
            </li>
          </ul>

          <div
            v-if="convertPreviewData.total_unmigratable === 0"
            class="mb-4 rounded bg-green-50 p-3 text-sm text-green-700"
          >
            ✅ すべてのデータが引き継がれます（担当者設定・ジョブ登録を保持）
          </div>
          <div v-else class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">
            ⚠️ 引き継げないデータが {{ convertPreviewData.total_unmigratable }}件あります。
            変換後にこれらのセルデータは空になります。
          </div>

          <p class="mb-4 text-xs font-medium text-orange-600">⚠️ この操作は元に戻せません。</p>
        </template>

        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
            @click="showConvertModal = false"
          >キャンセル</button>
          <button
            v-if="convertPreviewData"
            type="button"
            class="rounded bg-orange-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-orange-700 disabled:opacity-60"
            :disabled="convertPreviewLoading"
            @click="executeConvert"
          >変換する（元に戻せません）</button>
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

    <!-- ── 項目から読み込むモーダル ── -->
    <div
      v-if="showLoadItemsModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
      @click.self="showLoadItemsModal = false"
    >
      <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <h3 class="mb-4 text-lg font-semibold text-gray-800">項目から行を追加</h3>

        <div v-if="loadItemsLoading" class="py-4 text-center text-sm text-gray-400">読み込み中...</div>
        <template v-else>
          <!-- 分類フィルター -->
          <div class="mb-3">
            <label class="block text-xs font-medium text-gray-600 mb-1">分類を選んで追加</label>
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                :class="['rounded px-3 py-1 text-xs font-medium border', !loadItemsCategory ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50']"
                @click="loadItemsCategory = null"
              >すべて</button>
              <button
                v-for="cat in loadItemsCategories"
                :key="cat"
                type="button"
                :class="['rounded px-3 py-1 text-xs font-medium border', loadItemsCategory === cat ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50']"
                @click="loadItemsCategory = cat"
              >{{ cat }}</button>
            </div>
          </div>

          <!-- プレビュー -->
          <div class="mb-4 max-h-48 overflow-y-auto rounded border border-gray-200 text-sm">
            <div v-if="loadItemsFiltered.length === 0" class="py-4 text-center text-gray-400">
              {{ loadItemsList.length === 0 ? '項目が登録されていません' : 'この分類に項目はありません' }}
            </div>
            <div
              v-for="item in loadItemsFiltered"
              :key="item.id"
              class="flex items-center justify-between border-b px-3 py-2 last:border-b-0"
            >
              <span class="font-medium text-gray-800">{{ item.name }}</span>
              <div class="flex items-center gap-2">
                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">{{ item.category }}</span>
                <span v-if="item.deadline" class="text-xs text-gray-400">〜{{ item.deadline }}</span>
              </div>
            </div>
          </div>

          <p class="mb-4 text-xs text-gray-500">※ すでに紐づいている項目はスキップされます</p>

          <div class="flex justify-end gap-2">
            <button type="button" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" @click="showLoadItemsModal = false">キャンセル</button>
            <button
              type="button"
              :disabled="loadItemsSubmitting || loadItemsFiltered.length === 0"
              class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
              @click="submitLoadItems"
            >{{ loadItemsSubmitting ? '追加中...' : `${loadItemsFiltered.length} 件を追加` }}</button>
          </div>
        </template>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ProgressTable from '@/Components/ProgressTable.vue';
import ColumnTreeEditor from '@/Components/ColumnTreeEditor.vue';
import ProofRequestModal from '@/Components/ProofRequestModal.vue';
import useToasts from '@/Composables/useToasts';
import axios from 'axios';
import { route } from 'ziggy-js';
const { showToast } = useToasts();

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
  projectSchedules: { type: Array, default: () => [] },
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
const showConvertModal      = ref(false);
const convertPreviewData    = ref(null);
const convertPreviewLoading = ref(false);
const localShareToken       = ref(props.sheet.share_token ?? null);
const shareLoading          = ref(false);
const showProofModal = ref(false);
const proofTargetAssignment = ref(null);
const proofTargetCellId = ref(null);
const registerTemplateName = ref('');
const newRowLabel = ref('');
const importText = ref('');
const addingChildTo = ref(null);
const newChildLabel = ref('');
const bulkChildLabel = ref('');
const showBulkImportModal = ref(false);
const showAddChildModal   = ref(false);
const pendingNewRow = ref(null); // null | { label: string, after_id: number | null }

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

// ── シート全体の完了率 ─────────────────────────────────────
function collectAllLeaves(nodes) {
  const result = [];
  for (const node of nodes) {
    if (!node.children || node.children.length === 0) result.push(node);
    else result.push(...collectAllLeaves(node.children));
  }
  return result;
}

const sheetCompletion = computed(() => {
  const config = props.sheet.column_config ?? [];
  const completableCols = collectAllLeaves(config).filter(
    (l) => ['worker', 'schedlink', 'joblink'].includes(l.type)
  );
  if (completableCols.length === 0) return { done: 0, total: 0 };
  const cellMap = {};
  for (const c of localCells.value) {
    cellMap[`${c.row_id}_${c.col_key}`] = c;
  }
  const dataRows = (props.rows ?? []).filter((r) => !r.parent_id || true); // 全行対象
  let total = 0;
  let done = 0;
  for (const row of dataRows) {
    for (const col of completableCols) {
      total++;
      const c = cellMap[`${row.id}_${col.key}`];
      if (!c) continue;
      if (col.type === 'joblink') { if (c.assignment_completed) done++; }
      else { if (c.completed_at || c.assignment_completed) done++; }
    }
  }
  return { done, total };
});

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

// 並び替え変更検出（トップレベル＋子行）
let savedTopLevelIds = props.rows.filter((r) => !r.parent_id).map((r) => r.id);
let savedAllRowIds   = props.rows.map((r) => r.id);

const rowOrderChanged = computed(() =>
  JSON.stringify(topLevelRows.value.map((r) => r.id)) !== JSON.stringify(savedTopLevelIds) ||
  JSON.stringify(localRows.value.map((r) => r.id))    !== JSON.stringify(savedAllRowIds)
);

// ── 列構成 ──
function onColumnChange(updated) {
  localColumnConfig.value = updated.slice();
}

function saveColumnConfig() {
  if (pendingNewRow.value?.label?.trim()) {
    showToast('未確定の行ラベルがあります。Enter で確定してから保存してください。', 'warning', 4000);
    return;
  }
  cancelPendingRow();
  router.put(
    route('coordinator.progress_sheets.update', { sheet: props.sheet.id }),
    { name: localSheetName.value, column_config: localColumnConfig.value },
    {
      preserveScroll: true,
      onSuccess: (page) => {
        syncRowsFromPage(page);
        editMode.value = false;
      },
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
            type: 'worker',
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

// ── V2 変換（既存 user+joblink ペア → worker 型） ──────────────────
function detectOldPairsInConfig(nodes) {
  for (const node of nodes) {
    const children = node.children ?? [];
    for (let j = 0; j < children.length - 1; j++) {
      if (children[j].type === 'user' && children[j + 1].type === 'joblink') return true;
    }
    if (children.length && detectOldPairsInConfig(children)) return true;
  }
  return false;
}
const hasOldPairs = computed(() => detectOldPairsInConfig(localColumnConfig.value));

// ── 印刷 ─────────────────────────────────────────────────────────────────────
function openPrint() {
  window.open(route('coordinator.progress_sheets.print', { sheet: props.sheet.id }), '_blank');
}

// ── 共有リンク ────────────────────────────────────────────────────────────────
async function issueShare() {
  shareLoading.value = true;
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const res = await fetch(route('coordinator.progress_sheets.share', { sheet: props.sheet.id }), {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
    });
    const data = await res.json();
    localShareToken.value = data.share_token;
  } finally {
    shareLoading.value = false;
  }
}

async function revokeShare() {
  if (!confirm('共有リンクを無効化しますか？現在のURLでのアクセスができなくなります。')) return;
  shareLoading.value = true;
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    await fetch(route('coordinator.progress_sheets.unshare', { sheet: props.sheet.id }), {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
    });
    localShareToken.value = null;
  } finally {
    shareLoading.value = false;
  }
}

function copyShareUrl() {
  const url = route('shared.progress_sheets.show', { token: localShareToken.value });
  navigator.clipboard.writeText(url).then(() => {
    alert('共有URLをコピーしました');
  });
}

async function openConvertPreview() {
  showConvertModal.value      = true;
  convertPreviewData.value    = null;
  convertPreviewLoading.value = true;
  try {
    const res = await axios.get(
      route('coordinator.progress_sheets.convert_preview', { sheet: props.sheet.id })
    );
    convertPreviewData.value = res.data;
  } catch (e) {
    showConvertModal.value = false;
    showToast('プレビューの取得に失敗しました。', 'error');
  } finally {
    convertPreviewLoading.value = false;
  }
}

async function executeConvert() {
  try {
    await axios.put(
      route('coordinator.progress_sheets.convert_to_v2', { sheet: props.sheet.id })
    );
    showConvertModal.value = false;
    router.reload();
  } catch (e) {
    showToast('変換に失敗しました。', 'error');
  }
}

// ── 行管理 ──
function syncRowsFromPage(page) {
  if (page.props.rows) {
    localRows.value = page.props.rows.map((r) => ({ ...r }));
    savedTopLevelIds = localRows.value.filter((r) => !r.parent_id).map((r) => r.id);
    savedAllRowIds   = localRows.value.map((r) => r.id);
  }
}

// ── 項目から読み込む ──────────────────────────────────────────────────────────
const showLoadItemsModal  = ref(false);
const loadItemsList       = ref([]);
const loadItemsLoading    = ref(false);
const loadItemsCategory   = ref(null); // null = すべて
const loadItemsSubmitting = ref(false);

const TODAY = (() => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
})();

function deadlineStatus(deadline) {
  if (!deadline) return null;
  const today = new Date(TODAY);
  const dl = new Date(deadline);
  const diffMs = dl - today;
  const diffDays = Math.ceil(diffMs / 86400000);
  if (diffDays < 0)  return 'past';
  if (diffDays <= 3) return 'soon';
  return 'ok';
}

const ITEM_CATEGORY_COLORS = {
  '全体スケジュール': '#f97316',
  '組版':            '#8b5cf6',
  '校正':            '#10b981',
  '入稿':            '#06b6d4',
  '出力・納品':      '#ef4444',
  'サブ':            '#6b7280',
  'その他':          '#84cc16',
};

const loadItemsFiltered = computed(() => {
  if (!loadItemsCategory.value) return loadItemsList.value;
  return loadItemsList.value.filter((i) => i.category === loadItemsCategory.value);
});
const loadItemsCategories = computed(() => [...new Set(loadItemsList.value.map((i) => i.category).filter(Boolean))]);

async function openLoadItemsModal() {
  if (!props.projectJob?.id) return;
  showLoadItemsModal.value = true;
  loadItemsLoading.value = true;
  try {
    const res = await axios.get(route('coordinator.project_jobs.items.index', { projectJob: props.projectJob.id }));
    loadItemsList.value = res.data.items ?? [];
    loadItemsCategory.value = null;
  } catch (e) {
    showToast('項目の取得に失敗しました', 'error');
  } finally {
    loadItemsLoading.value = false;
  }
}

async function submitLoadItems() {
  if (loadItemsSubmitting.value) return;
  loadItemsSubmitting.value = true;
  try {
    const res = await axios.post(
      route('coordinator.project_jobs.items.load_into_sheet', { projectJob: props.projectJob.id, sheet: props.sheet.id }),
      { category: loadItemsCategory.value || null },
    );
    showLoadItemsModal.value = false;
    showToast(`${res.data.added} 件の行を追加しました`, 'success');
    router.reload({ only: ['rows'], onSuccess: (page) => { syncRowsFromPage(page); } });
  } catch (e) {
    showToast('読み込みに失敗しました', 'error');
  } finally {
    loadItemsSubmitting.value = false;
  }
}

// ── 行の締切ステータス色クラス ─────────────────────────────────────────────
function rowDeadlineClass(row) {
  const status = deadlineStatus(row.deadline);
  if (status === 'past') return 'text-gray-400';
  if (status === 'soon') return 'text-yellow-600 font-semibold';
  return 'text-gray-500';
}

function rowBgClass(row) {
  const status = deadlineStatus(row.deadline);
  if (status === 'past') return 'opacity-50';
  if (status === 'soon') return 'bg-yellow-50';
  return '';
}

function startPendingRow(afterId) {
  pendingNewRow.value = { label: '', after_id: afterId ?? null };
  nextTick(() => {
    const el = document.querySelector('[data-pending-row-input]');
    if (el) el.focus();
  });
}

function commitPendingRow() {
  const label = pendingNewRow.value?.label?.trim();
  if (!label) {
    pendingNewRow.value = null;
    return;
  }
  const afterId = pendingNewRow.value.after_id;
  pendingNewRow.value = null;
  router.post(
    route('coordinator.progress_sheets.rows.store', { sheet: props.sheet.id }),
    { label, ...(afterId !== null ? { after_id: afterId } : {}) },
    { preserveScroll: true, onSuccess: (page) => { syncRowsFromPage(page); } }
  );
}

function cancelPendingRow() {
  pendingNewRow.value = null;
}

function duplicateRow(row) {
  router.post(
    route('coordinator.progress_sheets.rows.duplicate', { sheet: props.sheet.id, row: row.id }),
    {},
    { preserveScroll: true, onSuccess: (page) => { syncRowsFromPage(page); } }
  );
}

function updateRowLabel(row) {
  const label = row.label.trim();
  if (!label) return;
  router.put(
    route('coordinator.progress_sheets.rows.update', { sheet: props.sheet.id, row: row.id }),
    { label },
    { preserveScroll: true, onSuccess: (page) => { syncRowsFromPage(page); } }
  );
}

function addRow(label) {
  if (!label) return;
  router.post(
    route('coordinator.progress_sheets.rows.store', { sheet: props.sheet.id }),
    { label },
    { preserveScroll: true, onSuccess: (page) => { syncRowsFromPage(page); } }
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

// startInsertAfter / confirmInsertAfter → startPendingRow / commitPendingRow に統合

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

function moveChildRowUp(parentId, cidx) {
  if (cidx < 1) return;
  const children = childrenOf.value[parentId];
  const rowA = children[cidx];
  const rowB = children[cidx - 1];
  const iA = localRows.value.indexOf(rowA);
  const iB = localRows.value.indexOf(rowB);
  const copy = [...localRows.value];
  [copy[iA], copy[iB]] = [copy[iB], copy[iA]];
  localRows.value = copy;
}

function moveChildRowDown(parentId, cidx) {
  const children = childrenOf.value[parentId];
  if (cidx >= children.length - 1) return;
  const rowA = children[cidx];
  const rowB = children[cidx + 1];
  const iA = localRows.value.indexOf(rowA);
  const iB = localRows.value.indexOf(rowB);
  const copy = [...localRows.value];
  [copy[iA], copy[iB]] = [copy[iB], copy[iA]];
  localRows.value = copy;
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
  const ids = localRows.value.map((r) => r.id);
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

  if (payload.value_type === 'schedlink') {
    // schedlink型: value=schedule_id
    const target = existing ?? (() => { const c = { row_id: payload.row_id, col_key: payload.col_key }; localCells.value.push(c); return c; })();
    target.schedule_id = payload.value ?? null;
    target.schedule_name = payload.value
      ? (props.projectSchedules?.find((s) => s.id === payload.value)?.name ?? null)
      : null;
    target.schedule_end_date = payload.value
      ? (props.projectSchedules?.find((s) => s.id === payload.value)?.end_date ?? null)
      : null;
    const sidx = pendingCells.value.findIndex((c) => c.row_id === payload.row_id && c.col_key === payload.col_key);
    const schedlinkPayload = { row_id: payload.row_id, col_key: payload.col_key, value_type: 'schedlink', value: payload.value };
    if (sidx >= 0) pendingCells.value[sidx] = schedlinkPayload;
    else pendingCells.value.push(schedlinkPayload);
    return;
  }

  if (payload.value_type === 'worker') {
    // worker型: value=user_id, subcontractor_id は payload.subcontractor_id で来る
    const target = existing ?? (() => { const c = { row_id: payload.row_id, col_key: payload.col_key }; localCells.value.push(c); return c; })();
    if (payload.subcontractor_id) {
      target.value_subcontractor_id = payload.subcontractor_id;
      target.value_subcontractor_name = props.subcontractors?.find((s) => s.id === payload.subcontractor_id)?.name ?? null;
      target.value_user_id = null;
      target.value_user_name = null;
    } else {
      target.value_user_id = payload.value;
      target.value_user_name = props.users.find((u) => u.id === payload.value)?.name ?? null;
      target.value_subcontractor_id = null;
      target.value_subcontractor_name = null;
    }
    // pendingに追加（worker型として保存）
    const widx = pendingCells.value.findIndex((c) => c.row_id === payload.row_id && c.col_key === payload.col_key);
    const workerPayload = {
      row_id: payload.row_id,
      col_key: payload.col_key,
      value_type: 'worker',
      value: payload.subcontractor_id ? null : payload.value,
      subcontractor_id: payload.subcontractor_id ?? null,
    };
    if (widx >= 0) pendingCells.value[widx] = workerPayload;
    else pendingCells.value.push(workerPayload);
    return;
  }

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
      onSuccess: (page) => {
        pendingCells.value = [];
        if (page.props.cells) {
          localCells.value = page.props.cells.map((c) => ({ ...c }));
        }
      },
    }
  );
}

// ── worker型セルハンドラ ──────────────────────────────────────────────────

/** workerセルの「完了にする」 */
async function onWorkerComplete({ cellId, assignmentId, rowId, colKey }) {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  try {
    if (assignmentId) {
      // ジョブ紐づきあり: 既存の assignments.complete を使う（JobBoxController側でcompleted_atも更新）
      await callAssignmentApi(route('coordinator.progress_sheets.assignments.complete', { assignment: assignmentId }));
    } else if (cellId) {
      // ジョブなし: セル単体の complete API
      const res = await fetch(route('coordinator.progress_cells.complete', { cell: cellId }), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      });
      if (!res.ok) throw new Error(await res.text());
    }
    // ローカルセルを更新
    const idx = localCells.value.findIndex((c) => c.row_id === rowId && c.col_key === colKey);
    if (idx >= 0) {
      localCells.value.splice(idx, 1, {
        ...localCells.value[idx],
        completed_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
        assignment_completed: assignmentId ? true : localCells.value[idx].assignment_completed,
      });
    }
  } catch { /* ignore */ }
}

/** schedlinkセルの「完了にする」 */
async function onSchedlinkComplete({ cellId, rowId, colKey }) {
  if (!cellId) {
    console.warn('[schedlink-complete] cellId が未定義です。先にセルを保存してください。', { rowId, colKey });
    return;
  }
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  try {
    const res = await fetch(route('coordinator.progress_cells.complete', { cell: cellId }), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    });
    if (!res.ok) {
      const body = await res.text();
      console.error('[schedlink-complete] API エラー', res.status, body);
      return;
    }
    const json = await res.json();
    const idx = localCells.value.findIndex((c) => c.row_id === rowId && c.col_key === colKey);
    if (idx >= 0) {
      localCells.value.splice(idx, 1, {
        ...localCells.value[idx],
        completed_at: json.completed_at ?? new Date().toISOString().slice(0, 19).replace('T', ' '),
      });
    }
  } catch (e) {
    console.error('[schedlink-complete] 例外', e);
  }
}

/** セルメモ保存 */
async function onNoteSave({ cellId, rowId, colKey, note }) {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  if (!cellId) {
    // セルが DB 未作成の場合: 位置指定エンドポイントで upsert + note 保存
    try {
      const res = await fetch(route('coordinator.progress_sheets.cell_note', { sheet: props.sheet.id }), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({ row_id: rowId, col_key: colKey, cell_note: note }),
      });
      if (!res.ok) {
        const body = await res.text();
        console.error('[note-save] API エラー', res.status, body);
        return;
      }
      const data = await res.json();
      const authUser = usePage().props.auth?.user;
      const existing = localCells.value.find((c) => c.row_id === rowId && c.col_key === colKey);
      if (existing) {
        const idx = localCells.value.indexOf(existing);
        localCells.value.splice(idx, 1, {
          ...existing,
          id: data.cell_id,
          cell_note: note,
          cell_note_user_name: note ? (authUser?.name ?? null) : null,
          cell_note_user_role: note ? (authUser?.user_role ?? null) : null,
        });
      } else {
        localCells.value.push({
          row_id: rowId,
          col_key: colKey,
          id: data.cell_id,
          cell_note: note,
          cell_note_user_name: note ? (authUser?.name ?? null) : null,
          cell_note_user_role: note ? (authUser?.user_role ?? null) : null,
        });
      }
    } catch (e) {
      console.error('[note-save] 例外', e);
    }
    return;
  }

  try {
    const res = await fetch(route('coordinator.progress_cells.note', { cell: cellId }), {
      method: 'PATCH',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({ cell_note: note }),
    });
    if (!res.ok) {
      const body = await res.text();
      console.error('[note-save] API エラー', res.status, body);
      return;
    }
    // ローカルのセルデータを即時更新
    const idx = localCells.value.findIndex((c) => c.id === cellId);
    if (idx >= 0) {
      const authUser = usePage().props.auth?.user;
      localCells.value.splice(idx, 1, {
        ...localCells.value[idx],
        cell_note: note,
        cell_note_user_name: note ? (authUser?.name ?? null) : null,
        cell_note_user_role: note ? (authUser?.user_role ?? null) : null,
      });
    }
  } catch (e) {
    console.error('[note-save] 例外', e);
  }
}

/** workerセルの「＋ 登録」→ 既存のjob登録フローへ */
function onWorkerJobRegister({ rowId, colKey, userId, subcontractorId }) {
  // workerセル自身が担当者を持っているので、jobLinkModalと同様の処理
  const title = buildJobTitle(rowId, colKey);
  if (subcontractorId && !userId) {
    jobLinkForm.value = { title, detail: '', desiredEndDate: '', assigneeUserId: null, assigneeSubcontractorId: subcontractorId };
    jobLinkModal.value = { open: true, isSelfAssign: false, isSubcontractor: true, rowId, colKey };
    return;
  }
  const assigneeId = userId ?? authUserId.value;
  const isSelf = !userId || assigneeId === authUserId.value;
  const sizeId = findSiblingCellValue(colKey, rowId, 'size') ?? findAncestorGroupId(colKey, 'size', props.sizes) ?? (props.projectJob.size_id ? String(props.projectJob.size_id) : null);
  const stageId = findSiblingCellValue(colKey, rowId, 'stage') ?? findAncestorGroupId(colKey, 'stage', props.stages);
  const workItemTypeId = findSiblingCellValue(colKey, rowId, 'workItemType') ?? findAncestorGroupId(colKey, 'workItemType', props.workItemTypes);
  const params = { title };
  if (sizeId) params.size_id = sizeId;
  if (stageId) params.stage_id = stageId;
  if (workItemTypeId) params.work_item_type_id = workItemTypeId;
  if (props.projectJob?.client_id) params.client_id = props.projectJob.client_id;
  params.project_job_id = props.projectJob.id;
  params.progress_sheet_id = props.sheet.id;
  params.row_id = rowId;
  params.col_key = colKey;
  if (isSelf) {
    router.visit(route('events.create_job', params));
  } else {
    if (assigneeId) params.user_id = assigneeId;
    router.visit(route('coordinator.project_jobs.assignments.create', { projectJob: props.projectJob.id, ...params }));
  }
}

/** workerセルの「詳細」→ 既存のjob詳細モーダルを流用 */
function onWorkerJobDetail({ assignmentId, rowId, colKey }) {
  const cell = localCells.value.find((c) => c.row_id === rowId && c.col_key === colKey);
  openJobLinkDetail({
    assignmentId,
    assignmentTitle: cell?.assignment_title ?? null,
    assigneeUserId: cell?.assignment_user_id ?? cell?.value_user_id ?? null,
    assigneeSubcontractorId: cell?.assignment_subcontractor_id ?? cell?.value_subcontractor_id ?? null,
    endDate: cell?.assignment_end_date ?? cell?.cell_deadline ?? null,
    completed: !!(cell?.completed_at || cell?.assignment_completed),
    rowId,
    colKey,
  });
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
