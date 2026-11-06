<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\Portfolio\PortfolioProfile;
use App\Services\Portfolio\PortfolioAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** アクセント色・表示名・肩書き */
class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'accent_color' => ['required', Rule::in(PortfolioProfile::ACCENT_PALETTE)],
            'display_name' => 'nullable|string|max:100',
            'headline' => 'nullable|string|max:150',
        ]);

        PortfolioProfile::updateOrCreate(['user_id' => PortfolioAccess::editOwnerId($request)], $data);

        return response()->json(['ok' => true]);
    }
}
