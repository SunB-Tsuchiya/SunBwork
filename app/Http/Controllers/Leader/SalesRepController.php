<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ManagesSalesReps;

class SalesRepController extends Controller
{
    use ManagesSalesReps;

    protected function salesRepsViewName(): string
    {
        return 'Leader/SalesReps/Index';
    }
}
