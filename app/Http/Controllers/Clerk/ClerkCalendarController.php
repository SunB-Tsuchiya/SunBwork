<?php

namespace App\Http\Controllers\Clerk;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class ClerkCalendarController extends Controller
{
    public function index()
    {
        return Inertia::render('Clerk/Calendar/Index');
    }
}
