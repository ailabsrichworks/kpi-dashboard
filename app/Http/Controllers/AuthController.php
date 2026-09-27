<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\PasswordResetMail;
use App\Services\SupabaseService;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    public function submitLogin(Request $request, SupabaseService $supabase)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $users = $supabase->get('users', [
            'email' => 'eq.' . $request->email,
            'is_active' => 'eq.true',
            'select' => '*',
        ]);

        if (empty($users)) {
            return back()
                ->withInput()
                ->with('error', 'Email tidak dijumpai atau akaun tidak aktif.');
        }

        $user = $users[0];

        $inputPassword = $request->password;
        $defaultPassword = 'Richworks';

        $usingDefaultPassword = false;

        if (empty($user['password_hash'])) {
            if ($inputPassword !== $defaultPassword) {
                return back()
                    ->withInput()
                    ->with('error', 'Password tidak betul.');
            }

            $usingDefaultPassword = true;
        } else {
            if (!Hash::check($inputPassword, $user['password_hash'])) {
                return back()
                    ->withInput()
                    ->with('error', 'Password tidak betul.');
            }
        }

        session([
            'user_uuid' => $user['id'],
            'user_name' => $user['name'] ?? 'User',
            'user_email' => $user['email'],
            'using_default_password' => $usingDefaultPassword,
        ]);

        $dashboards = $this->getUserDashboards(
            $supabase,
            $user['id']
        );

        if (empty($dashboards)) {
            session()->flush();

            return back()
                ->withInput()
                ->with('error', 'Akaun ini belum diberi akses kepada mana-mana dashboard.');
        }

        $this->updateLastLogin(
            $supabase,
            $user['id']
        );

        // Auto-select only when there is exactly one dashboard.
        // With 2+ companies always show the chooser so the user can pick.
        if (count($dashboards) === 1) {
            $this->setDashboardSession($dashboards[0]);
            $this->setSubordinateSession($supabase, $dashboards[0]);
            return redirect()->route('dashboard');
        }

        session(['available_dashboards' => $dashboards]);
        return redirect()->route('dashboard.choose');
    }

    public function showChooseDashboard()
    {
        if (!session()->has('user_uuid')) {
            return redirect()
                ->route('login')
                ->with('error', 'Sila login terlebih dahulu.');
        }

        $dashboards = session('available_dashboards', []);

        if (empty($dashboards)) {
            return redirect()
                ->route('login')
                ->with('error', 'Tiada dashboard tersedia untuk akaun ini.');
        }

        return Inertia::render('Auth/ChooseDashboard', [
            'dashboards' => $dashboards,
            'userName' => session('user_name'),
        ]);
    }

    public function selectDashboard(Request $request, SupabaseService $supabase)
    {
        $request->validate([
            'employee_uuid' => 'required|string',
            'company_code' => 'required|string',
        ]);

        $dashboards = session('available_dashboards', []);

        if (empty($dashboards)) {
            return redirect()
                ->route('login')
                ->with('error', 'Session dashboard tidak dijumpai. Sila login semula.');
        }

        $selectedDashboard = collect($dashboards)->first(function ($dashboard) use ($request) {
            return $dashboard['employee_uuid'] === $request->employee_uuid
                && $dashboard['company_code'] === $request->company_code;
        });

        if (!$selectedDashboard) {
            return redirect()
                ->route('dashboard.choose')
                ->with('error', 'Dashboard yang dipilih tidak sah.');
        }

        $this->setDashboardSession($selectedDashboard);
        $this->setSubordinateSession($supabase, $selectedDashboard);

        // Clear bulky available_dashboards to keep session cookie small
        session()->forget('available_dashboards');
        session()->save();

        return redirect()->route('dashboard');
    }

    public function logout()
    {
        session()->flush();

        return redirect()
            ->route('login')
            ->with('success', 'Anda telah logout.');
    }

    public function showForgotPassword()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetLink(Request $request, SupabaseService $supabase)
    {
        $request->validate(['email' => 'required|email']);

        $user = $supabase->first('users', [
            'email'     => 'eq.' . $request->email,
            'is_active' => 'eq.true',
            'select'    => 'id,name,email',
        ]);

        // Always show the same message whether or not the email exists,
        // so this form can't be used to probe which emails have accounts.
        if ($user) {
            $token = Str::random(64);

            $supabase->safePatch('users', ['id' => 'eq.' . $user['id']], [
                'password_reset_token'      => Hash::make($token),
                'password_reset_expires_at' => now()->addMinutes(30)->toIso8601String(),
            ]);

            $resetUrl = route('password.reset', ['token' => $token]) . '?email=' . urlencode($user['email']);

            try {
                Mail::to($user['email'])->send(new PasswordResetMail($resetUrl, $user['name'] ?? 'there'));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Password reset email failed to send', ['error' => $e->getMessage()]);
            }
        }

        return back()->with('success', 'If that email is registered, a reset link has been sent to it.');
    }

    public function showResetPassword(string $token, Request $request)
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function submitResetPassword(Request $request, SupabaseService $supabase)
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $supabase->first('users', [
            'email'     => 'eq.' . $request->email,
            'is_active' => 'eq.true',
            'select'    => '*',
        ]);

        $expired = empty($user['password_reset_expires_at'])
            || now()->greaterThan(\Carbon\Carbon::parse($user['password_reset_expires_at']));

        $tokenValid = $user
            && !empty($user['password_reset_token'])
            && !$expired
            && Hash::check($request->token, $user['password_reset_token']);

        if (!$tokenValid) {
            return back()
                ->withInput()
                ->with('error', 'This reset link is invalid or has expired. Please request a new one.');
        }

        $supabase->update('users', ['id' => 'eq.' . $user['id']], [
            'password_hash'             => Hash::make($request->password),
            'password_reset_token'      => null,
            'password_reset_expires_at' => null,
        ]);

        return redirect()
            ->route('login')
            ->with('success', 'Your password has been reset. Please log in with your new password.');
    }

    private function getUserDashboards(SupabaseService $supabase, string $userId): array
    {
        $roles = $supabase->get('user_company_roles', [
            'user_id' => 'eq.' . $userId,
            'is_active' => 'eq.true',
            'select' => '*',
        ]);

        if (empty($roles)) {
            return [];
        }

        $dashboards = [];

        foreach ($roles as $roleAccess) {
            $employees = $supabase->get('employees', [
                'id' => 'eq.' . $roleAccess['employee_id'],
                'is_active' => 'eq.true',
                'select' => '*',
            ]);

            if (empty($employees)) {
                continue;
            }

            $employee = $employees[0];

            $companies = $supabase->get('companies', [
                'code' => 'eq.' . $roleAccess['company_code'],
                'select' => '*',
            ]);

            $company = $companies[0] ?? null;

            $dashboards[] = [
                'user_uuid' => $userId,

                'employee_uuid' => $employee['id'],
                'employee_id' => $employee['employee_id'],
                'short_name' => $employee['short_name'] ?? null,
                'full_name' => $employee['full_name'] ?? null,
                'salutation' => $employee['salutation'] ?? null,

                'role' => $employee['role'],
                'hr_access' => (bool)($employee['hr_access'] ?? false),
                'position' => $employee['position'] ?? null,
                'department_code' => $employee['department_code'],

                'company_code' => $roleAccess['company_code'],
                'company_name' => $company['name'] ?? $roleAccess['company_code'],

                'company_display_name' => $company['display_name'] ?? ($company['name'] ?? $roleAccess['company_code']),
                // companies.logo_url wins when set; otherwise falls back to
                // whichever public/images/{code}-Logo(.png|-black.png) file
                // actually shows up against this card's light background.
                'logo_url' => \App\Services\CompanyLogoService::resolve(
                    $roleAccess['company_code'],
                    $company['logo_url'] ?? null,
                    '#F8FAFC'
                ),

                'manager_code' => $employee['manager_code'] ?? null,
                'vp_code' => $employee['vp_code'] ?? null,
                'reports_to' => $employee['reports_to'] ?? null,

                'is_default' => $roleAccess['is_default'] ?? false,
            ];
        }

        return $dashboards;
    }

    private function setDashboardSession(array $dashboard): void
    {
        session([
            'employee_uuid'        => $dashboard['employee_uuid'],
            'employee_id'          => $dashboard['employee_id'],
            'employee_name'        => $dashboard['short_name'] ?? $dashboard['full_name'] ?? 'User',
            'short_name'           => $dashboard['short_name'] ?? null,
            'full_name'            => $dashboard['full_name'] ?? null,
            'salutation'           => $dashboard['salutation'] ?? null,
            'role'                 => $dashboard['role'],
            'hr_access'            => $dashboard['hr_access'] ?? false,
            'position'             => $dashboard['position'] ?? null,
            'department_code'      => $dashboard['department_code'],
            'company_code'         => $dashboard['company_code'],
            'company_name'         => $dashboard['company_name'],
            'company_display_name' => $dashboard['company_display_name'],
        ]);
    }

    private function setSubordinateSession(SupabaseService $supabase, array $dashboard): void
    {
        try {
            $subs = $supabase->get('employees', [
                'reports_to_id' => 'eq.' . $dashboard['employee_uuid'],
                'company_code'  => 'eq.' . $dashboard['company_code'],
                'select'        => 'id',
                'limit'         => '1',
            ]) ?? [];
            session(['has_subordinates' => !empty($subs)]);
        } catch (\Throwable) {
            session(['has_subordinates' => false]);
        }
    }

    private function updateLastLogin(SupabaseService $supabase, string $userId): void
    {
        try {
            $supabase->update('users', [
                'id' => 'eq.' . $userId,
            ], [
                'last_login_at' => now()->timezone('Asia/Kuala_Lumpur')->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            // Login jangan gagal hanya sebab last_login_at gagal update.
        }
    }
}
