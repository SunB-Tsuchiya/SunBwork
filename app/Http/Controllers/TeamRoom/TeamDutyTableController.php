<?php

namespace App\Http\Controllers\TeamRoom;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\NormalizesCsvEncoding;
use App\Models\Team;
use App\Models\TeamDutyTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TeamDutyTableController extends Controller
{
    use NormalizesCsvEncoding;

    public function create(Team $team)
    {
        $this->assertMemberAccess($team);

        return Inertia::render('TeamRoom/DutyTable/Create', [
            'team'        => $team->only('id', 'name'),
            'previewHtml' => null,
            'formData'    => ['title' => '', 'description' => ''],
            'error'       => null,
        ]);
    }

    public function preview(Request $request, Team $team)
    {
        $this->assertMemberAccess($team);

        $request->validate([
            'file'        => 'required|file|max:10240',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ], [
            'file.required' => 'ファイルを選択してください',
            'title.required' => 'タイトルを入力してください',
        ]);

        $formData = [
            'title'       => $request->input('title'),
            'description' => $request->input('description', ''),
        ];

        try {
            $html = $this->fileToHtml($request->file('file'));
        } catch (\Throwable $e) {
            return Inertia::render('TeamRoom/DutyTable/Create', [
                'team'        => $team->only('id', 'name'),
                'previewHtml' => null,
                'formData'    => $formData,
                'error'       => 'ファイルを読み込めませんでした。Excelまたは CSV を確認して再アップロードしてください。（' . $e->getMessage() . '）',
            ]);
        }

        return Inertia::render('TeamRoom/DutyTable/Create', [
            'team'        => $team->only('id', 'name'),
            'previewHtml' => $html,
            'formData'    => $formData,
            'error'       => null,
        ]);
    }

    public function store(Request $request, Team $team)
    {
        $this->assertMemberAccess($team);

        $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string|max:2000',
            'html_content' => 'required|string',
        ]);

        TeamDutyTable::create([
            'team_id'      => $team->id,
            'user_id'      => Auth::id(),
            'title'        => $request->input('title'),
            'description'  => $request->input('description'),
            'html_content' => $request->input('html_content'),
        ]);

        return redirect()->route('team-rooms.show', ['team' => $team->id, 'tab' => 'duty'])
            ->with('success', '係・当番表を登録しました');
    }

    public function destroy(Team $team, TeamDutyTable $dutyTable)
    {
        $this->assertMemberAccess($team);

        if ($dutyTable->team_id !== $team->id) {
            abort(403);
        }

        $dutyTable->delete();

        return redirect()->route('team-rooms.show', ['team' => $team->id, 'tab' => 'duty'])
            ->with('success', '削除しました');
    }

    // ─── private helpers ────────────────────────────────────────

    private function assertMemberAccess(Team $team): void
    {
        $controller = app(TeamRoomController::class);
        $controller->assertMember($team);
    }

    private function fileToHtml(\Illuminate\Http\UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if (in_array($ext, ['csv', 'txt'])) {
            return $this->csvToHtml($file);
        }

        if (in_array($ext, ['xlsx', 'xls', 'xlsm', 'ods'])) {
            return $this->excelToHtml($file);
        }

        throw new \RuntimeException('対応形式: csv / xlsx / xls / ods');
    }

    private function csvToHtml(\Illuminate\Http\UploadedFile $file): string
    {
        $tmpPath = $this->normalizeCsvToTemp($file);
        try {
            $rows   = [];
            $handle = fopen($tmpPath, 'r');
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = $row;
            }
            fclose($handle);
        } finally {
            @unlink($tmpPath);
        }

        if (empty($rows)) {
            throw new \RuntimeException('データが空です');
        }

        return $this->rowsToHtml($rows);
    }

    private function excelToHtml(\Illuminate\Http\UploadedFile $file): string
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet       = $spreadsheet->getActiveSheet();

        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            $iter  = $row->getCellIterator();
            $iter->setIterateOnlyExistingCells(false);
            foreach ($iter as $cell) {
                $cells[] = $cell->getFormattedValue();
            }
            // 末尾の空セルを除去
            while (count($cells) > 0 && trim(end($cells)) === '') {
                array_pop($cells);
            }
            if (count($cells) > 0) {
                $rows[] = $cells;
            }
        }

        if (empty($rows)) {
            throw new \RuntimeException('データが空です');
        }

        return $this->rowsToHtml($rows);
    }

    private function rowsToHtml(array $rows): string
    {
        $html  = '<table>';
        $first = true;
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $val  = htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8');
                $html .= $first ? "<th>{$val}</th>" : "<td>{$val}</td>";
            }
            $html  .= '</tr>';
            $first  = false;
        }
        $html .= '</table>';

        return $html;
    }
}
