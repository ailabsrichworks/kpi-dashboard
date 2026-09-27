<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SupabaseService;

class KpiTemplateController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function currentUser(SupabaseService $supabase): array
    {
        $employeeUuid = session('employee_uuid');

        if (!$employeeUuid) {
            abort(403, 'Employee not logged in.');
        }

        $employees = $supabase->get('employees', [
            'id'        => 'eq.' . $employeeUuid,
            'is_active' => 'eq.true',
            'select'    => '*',
        ]);

        if (empty($employees)) {
            session()->flush();
            abort(403, 'Employee not found.');
        }

        return $employees[0];
    }

    private function currentFY(): string
    {
        return 'FY' . now()->year;
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX — list templates for user's dept + current FY
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $supabase  = app(SupabaseService::class);
        $user      = $this->currentUser($supabase);
        $fy        = $this->currentFY();

        $templates = $supabase->get('kpi_templates', [
            'department_code' => 'eq.' . $user['department_code'],
            'financial_year'  => 'eq.' . $fy,
            'select'          => '*',
            'order'           => 'sort_order.asc',
        ]) ?? [];

        return response()->json($templates);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE — insert a new template
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $request->validate([
            'category'        => 'required|string|max:100',
            'sub_category'    => 'nullable|string|max:100',
            'kpi_title'       => 'required|string|max:255',
            'kpi_description' => 'nullable|string',
            'unit'            => 'required|in:percentage,currency,number',
        ]);

        $supabase = app(SupabaseService::class);
        $user     = $this->currentUser($supabase);
        $fy       = $this->currentFY();

        // Determine next sort_order
        $existing   = $supabase->get('kpi_templates', [
            'department_code' => 'eq.' . $user['department_code'],
            'financial_year'  => 'eq.' . $fy,
            'select'          => 'id',
        ]) ?? [];
        $sortOrder = count($existing) + 1;

        $inserted = $supabase->insert('kpi_templates', [
            'department_code' => $user['department_code'],
            'financial_year'  => $fy,
            'category'        => $request->input('category'),
            'sub_category'    => $request->input('sub_category') ?: null,
            'kpi_title'       => $request->input('kpi_title'),
            'kpi_description' => $request->input('kpi_description') ?: null,
            'unit'            => $request->input('unit'),
            'sort_order'      => $sortOrder,
        ]);

        return response()->json([
            'success'  => true,
            'template' => $inserted[0],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — delete a template by id
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $supabase = app(SupabaseService::class);
        $this->currentUser($supabase); // session guard

        $supabase->delete('kpi_templates', [
            'id' => 'eq.' . $id,
        ]);

        return response()->json(['success' => true]);
    }
}
