<?php

namespace App\Http\Controllers;

use App\Services\AiService;
use App\Services\SupabaseService;
use Illuminate\Http\Request;

class AiController extends Controller
{
    protected AiService $ai;
    protected SupabaseService $supabase;

    public function __construct(AiService $ai, SupabaseService $supabase)
    {
        $this->ai       = $ai;
        $this->supabase = $supabase;
    }

    private function fetchJobDescription(): string
    {
        try {
            $employeeId = session('employee_uuid');
            if (!$employeeId) return '';

            $jd = $this->supabase->first('job_descriptions', [
                'employee_id' => 'eq.' . $employeeId,
                'select'      => 'summary,responsibilities,requirements,competencies',
            ]);

            if (!$jd) return '';

            $parts = [];
            if (!empty($jd['summary']))          $parts[] = 'Summary: '          . strip_tags($jd['summary']);
            if (!empty($jd['responsibilities'])) $parts[] = 'Responsibilities: ' . strip_tags($jd['responsibilities']);
            if (!empty($jd['requirements']))     $parts[] = 'Requirements: '      . strip_tags($jd['requirements']);
            if (!empty($jd['competencies']))     $parts[] = 'Competencies: '      . strip_tags($jd['competencies']);

            return implode("\n\n", $parts);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SUGGEST KPI DESCRIPTION
    |--------------------------------------------------------------------------
    */

    public function suggestDescription(Request $request)
    {
        $request->validate([
            'kpi_title' => 'required|string|max:255',
        ]);

        $employee   = session('employee', []);
        $department = $employee['department'] ?? '';
        $role       = $employee['role']       ?? '';

        try {

            $description = $this->ai->suggestKpiDescription(
                $request->kpi_title,
                $department,
                $role
            );

            return response()->json([
                'success'     => true,
                'description' => $description,
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'AI suggestion failed. Please try again.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CHAT BOT
    |--------------------------------------------------------------------------
    */

    public function chat(Request $request)
    {
        $request->validate([
            'messages'           => 'required|array|min:1|max:20',
            'messages.*.role'    => 'required|in:user,assistant',
            'messages.*.content' => 'required|string|max:8000',
        ]);

        $employee       = session('employee', []);
        $jobDescription = $this->fetchJobDescription();

        try {

            $reply = $this->ai->chat($request->messages, $employee, $jobDescription);

            return response()->json([
                'success' => true,
                'reply'   => $reply,
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Bot is unavailable. Please try again.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SCORE KPI DESCRIPTION
    |--------------------------------------------------------------------------
    */

    public function scoreDescription(Request $request)
    {
        $request->validate([
            'kpi_title'       => 'required|string|max:255',
            'kpi_description' => 'required|string',
            'base_target'     => 'nullable|numeric',
            'stretch_target'  => 'nullable|numeric',
            'unit'            => 'nullable|string|max:20',
            'weightage'       => 'nullable|numeric',
            'category'        => 'nullable|string|max:100',
            'sub_category'    => 'nullable|string|max:100',
            'quarter_targets' => 'nullable|array',
        ]);

        try {

            $result = $this->ai->scoreKpiDescription(
                $request->kpi_title,
                $request->kpi_description,
                $request->base_target,
                $request->stretch_target,
                $request->unit,
                $request->weightage,
                $request->category,
                $request->sub_category,
                $request->quarter_targets,
            );

            return response()->json([
                'success'  => true,
                'score'    => $result['score']    ?? 0,
                'feedback' => $result['feedback'] ?? '',
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Scoring failed. Please try again.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SCORE QUARTER PERFORMANCE (Section 2 "View" popup, appraiser side)
    |--------------------------------------------------------------------------
    */

    public function scoreQuarter(Request $request)
    {
        $request->validate([
            'kpi_title'      => 'required|string|max:255',
            'quarter'        => 'required|string|max:10',
            'subtitle'       => 'nullable|string|max:255',
            'actual'         => 'nullable|numeric',
            'target'         => 'nullable|numeric',
            'progress'       => 'nullable|numeric',
            'remark'         => 'nullable|string|max:2000',
            'has_attachment' => 'nullable|boolean',
        ]);

        try {

            $result = $this->ai->scoreQuarterPerformance(
                $request->kpi_title,
                $request->subtitle ?? '',
                $request->quarter,
                $request->actual,
                $request->target,
                (float) ($request->progress ?? 0),
                $request->remark ?? '',
                (bool) $request->boolean('has_attachment'),
            );

            return response()->json([
                'success' => true,
                'verdict' => $result['verdict'] ?? '',
                'points'  => $result['points']  ?? [],
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'ANIRA scoring failed. Please try again.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REPHRASE APPRAISER COMMENT
    |--------------------------------------------------------------------------
    */

    public function rephraseComment(Request $request)
    {
        $request->validate([
            'comment'   => 'required|string|max:2000',
            'kpi_title' => 'nullable|string|max:255',
            'quarter'   => 'nullable|string|max:10',
        ]);

        try {

            $rephrased = $this->ai->rephraseAppraiserComment(
                $request->comment,
                $request->kpi_title ?? '',
                $request->quarter ?? ''
            );

            return response()->json([
                'success'   => true,
                'rephrased' => $rephrased,
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'AI rephrase failed. Please try again.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SUGGEST QUARTERLY TARGETS
    |--------------------------------------------------------------------------
    */

    public function suggestTargets(Request $request)
    {
        $request->validate([
            'kpi_title'     => 'required|string|max:255',
            'annual_target' => 'required|numeric',
            'unit'          => 'nullable|string|max:50',
        ]);

        try {

            $targets = $this->ai->suggestQuarterlyTargets(
                $request->kpi_title,
                (float) $request->annual_target,
                $request->unit ?? ''
            );

            return response()->json([
                'success' => true,
                'targets' => $targets,
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'AI suggestion failed. Please try again.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SUGGEST COMPLETE KPI (used by Build My KPI button in ANIRA)
    |--------------------------------------------------------------------------
    */

    public function suggestKpi(Request $request)
    {
        $request->validate([
            'messages'           => 'required|array|min:1|max:40',
            'messages.*.role'    => 'required|in:user,assistant',
            'messages.*.content' => 'required|string|max:8000',
        ]);

        $employee       = session('employee', []);
        $jobDescription = $this->fetchJobDescription();

        try {

            $kpi = $this->ai->suggestKpi($request->messages, $employee, $jobDescription);

            return response()->json([
                'success' => true,
                'kpi'     => $kpi,
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Could not generate KPI suggestion. Please try again.',
            ], 500);
        }
    }
}
