<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ManagesSalesReps;

class SalesRepController extends Controller
{
    use ManagesSalesReps;

    protected function salesRepsViewName(): string
    {
        return 'Coordinator/SalesReps/Index';
    }
}
