<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Inertia\Inertia;

class HelpController extends Controller
{
    public function index(SupabaseService $supabase)
    {
        $employees = $supabase->get('employees', [
            'id'        => 'eq.' . session('employee_uuid'),
            'is_active' => 'eq.true',
            'select'    => '*',
        ]);

        if (empty($employees)) {
            session()->flush();
            abort(403, 'Employee not found.');
        }

        return Inertia::render('Help', ['user' => $employees[0]]);
    }
}
