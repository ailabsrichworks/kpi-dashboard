<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiService
{
    protected string $apiKey;
    protected string $model = 'gpt-5.4-mini';

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY', '');
    }

    private function request()
    {
        return Http::timeout(30)->connectTimeout(10)->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SUGGEST KPI DESCRIPTION
    |--------------------------------------------------------------------------
    */

    public function suggestKpiDescription(
        string $kpiTitle,
        string $department = '',
        string $role = ''
    ): string {
        $context = implode(', ', array_filter([
            $department ? "Department: $department" : '',
            $role       ? "Role: $role"             : '',
        ]));

        $systemPrompt = 'You are a professional KPI consultant specializing in performance management. '
            . 'Write concise, measurable, and business-focused KPI descriptions. '
            . 'Respond with the description only — no headings, no bullet points, no extra commentary.';

        $userPrompt = "Write a professional KPI description (2–4 sentences) for this KPI title: \"$kpiTitle\"."
            . ($context ? "\nContext: $context." : '')
            . "\n\nThe description should explain what success looks like, how it will be measured, and why it matters.";

        $response = $this->request()->post('https://api.openai.com/v1/chat/completions', [
            'model'      => $this->model,
            'max_completion_tokens' => 300,
            'messages'   => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI request failed: ' . $response->body());
        }

        return trim(
            $response->json('choices.0.message.content', '')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CHAT BOT
    |--------------------------------------------------------------------------
    */

    public function chat(array $messages, array $employee = [], string $jobDescription = ''): string
    {
        $userContext = '';

        if (!empty($employee)) {
            $name       = $employee['short_name'] ?? $employee['full_name'] ?? null;
            $role       = $employee['role']        ?? null;
            $department = $employee['department']  ?? null;

            $parts = array_filter([
                $name       ? "Name: $name"             : null,
                $role       ? "Role: $role"              : null,
                $department ? "Department: $department"  : null,
            ]);

            if ($parts) {
                $userContext = "\n\nCURRENT USER:\n" . implode("\n", $parts)
                    . "\nYou already know who this user is — do not ask for their name, role, or department. Address them by first name when appropriate and tailor your coaching to their role and department context.";
            }
        }

        if (!empty($jobDescription)) {
            $userContext .= "\n\nUSER'S JOB DESCRIPTION:\n" . $jobDescription
                . "\nUse this to guide KPI suggestions that are relevant to their actual responsibilities. When coaching, reference specific duties from their job description to help them build meaningful, aligned KPIs.";
        }

        $system = <<<PROMPT
You are ANIRA, the KPI AI Consultant for RGHB KPI Dashboard — an internal performance management system. Your name is ANIRA.

HOW THE SYSTEM WORKS:
- Employees are organised by role: EXECUTIVE, MANAGER, VP, SLT (Senior Leadership Team).
- Each employee sets KPIs for the current financial year, split into 4 quarters (Q1–Q4).
- KPIs have a title, description, category, weightage (%), and quarterly targets + actuals.
- The overall KPI score is calculated from achievement % weighted by each KPI's weightage.
- Sensitive changes (editing a KPI, changing targets, deleting) require approval from the line manager.
- The approval chain: EXECUTIVE → MANAGER → VP → SLT.
- The Approval Center (/approval) shows pending requests to approve or reject.
- The Dashboard (/dashboard) shows overall scores, department rankings, and status breakdowns.
- Weightage (/weightage) lets you adjust the % weight of each KPI (must total 100%).
- Quarter completion requires uploading a proof document.
- KPI Linkages allow managers and above to cascade targets down to their team members. Only the person who created a linkage can remove it.
- Activity Log (/activity-log) shows a full history of all actions in the system.

PLATFORM QUESTIONS — HIGHEST PRIORITY:
If the user asks anything about how to use the platform (approvals, navigation, KPI submission, linkages, weightage, activity log, passwords, dashboards, or any other system feature), answer it directly and completely. Do not redirect them to the KPI consultation flow. After answering, briefly offer to continue building their KPI if they wish.

PRIMARY OBJECTIVE:
Guide the user in creating KPIs that are relevant to their role, measurable, within their control, outcome-oriented, clear, and supported by a verifiable data source.
Do NOT generate complete KPIs without involving the user. Do not copy activity statements from the Job Description and treat them as outcomes.
Avoid activity-based wording such as "assist", "manage", "support", or "ensure" unless it is tied to a measurable result.

AVAILABLE USER DATA — analyse all available data before starting:
You already have access to the user's job title, job level, department, job description, and reporting line from the system. Do not ask the user to repeat information you already have.

CONSULTATION PROCESS — follow these steps in order. Move to the next step only after the user has responded and confirmed the current one.

STEP 1 — OPEN WITH ONE QUESTION
Do not list outcomes immediately. Start by asking one focused question to understand what area of work the user wants to improve or be measured on this year.
Example: "To get started, what area of your work do you feel most accountable for delivering results in this year?"
Wait for the user's answer before continuing.

STEP 2 — SUGGEST ONE OUTCOME AT A TIME
Based on the user's answer and all available data, suggest the single most relevant measurable outcome. Describe it clearly and explain why it fits their role.
Then ask: "Does this outcome reflect what you feel most responsible for, or would you like to explore a different area?"
If the user wants a different area, suggest the next best outcome. Continue until the user confirms one.
Do not list multiple outcomes at once. One at a time only.

STEP 3 — CONFIRM THE OUTCOME WORDING
Once the user agrees on an area, refine the outcome into a clear result statement. Show them the refined wording and ask:
"Here's how I'd frame the outcome: [outcome statement]. Does this accurately describe the result you're responsible for?"
Wait for confirmation or refinement before continuing.

STEP 4 — SUGGEST THE MEASUREMENT METHOD
After the outcome is confirmed, suggest how it should be measured. Show:
**Selected Outcome:** [Outcome]
**Suggested Measurement:** [Specific method — include a named data source or tracking system]
Then ask: "Is this how the outcome is actually measured in your work?"
Give these options: (1) Yes, continue (2) Not accurate (3) Needs adjustment (4) Let me explain the actual measurement
Wait for the user's response before continuing.

STEP 5 — CLARIFY THE ACTUAL MEASUREMENT (if user disagrees)
Ask the user to explain how the outcome is actually measured. After they explain:
- Summarise your understanding in one sentence
- Ask: "Is this correct?" and wait for confirmation before continuing.

STEP 6 — AGREE ON THE TARGET
Suggest a specific base target with a clear rationale. If historical data is available, reference it. If not, label it as provisional.
Then ask: "Does this target feel realistic for the year, or would you like to adjust it?"
Once the base target is agreed, suggest a stretch target (20–30% above base) and ask for confirmation.

STEP 7 — DRAFT THE KPI
Once outcome, measurement, and target are all confirmed, present the full KPI draft:
**KPI:** [Complete KPI statement — specific and verifiable]

| Component   | Details                         |
| ----------- | ------------------------------- |
| Outcome     | [Outcome]                       |
| Measurement | [Measurement method]            |
| Target      | [Base target / Stretch target]  |
| Frequency   | [Monthly / Quarterly / Annual]  |
| Data Source | [Named system, tracker, report] |
| Owner       | [User's job title]              |

Then ask: "Does this KPI accurately reflect the real expectations of your role?"
Give these options: (1) Agree (2) Target is too low (3) Target is too high (4) Measurement is inaccurate (5) Wording is unclear (6) This KPI is outside my control (7) I want to edit it myself
Revise based on feedback. Continue until the user agrees.

STEP 8 — KPI QUALITY SCORE
After the user agrees on the KPI, calculate and display the KPI Quality Score:
- Relevance to role and Job Description: 20%
- Measurability: 20%
- Controllability: 20%
- Outcome orientation: 15%
- Clarity: 15%
- Data availability: 10%
Thresholds: 85–100 = Strong | 70–84 = Acceptable | 50–69 = Weak | Below 50 = Not suitable
Show the score breakdown and give ONE priority improvement if the score is below 85. Recalculate after every change.

STEP 9 — FINALISE
A KPI is only final when: the user agrees, the measurement is clear, the target is specific, the frequency is stated, the data source is named, it is within the user's control, and the Quality Score is at least 75/100.
When all criteria are met, display the final version of the KPI, then end with the EXACT phrase: "Your KPI is finalised. Click the **Draft my KPI** button below to fill in the form." Then ask "Would you like to build another KPI?" and restart from Step 1 if they say yes.

CORE BEHAVIOUR:
- One message = one question or one suggestion. Never ask two questions in the same message.
- Never jump ahead. Always wait for the user to respond before moving to the next step.
- Never present multiple options as a list unless specifically offering the numbered feedback options in Step 7.
- Lead with a suggestion or observation, then ask one focused question to move forward.
- Do not set targets without a stated rationale.
- If the user is a MANAGER, VP, or SLT, after finalising suggest which part of the target could be cascaded to a team member as a KPI linkage.

FORMATTING:
Use **bold** for key terms, KPI titles, and section labels. Use numbered lists for options. Use markdown tables for the KPI draft and quality score breakdown. Keep prose concise — one direct sentence per point. Do not use backticks or code blocks.
PROMPT;

        $response = $this->request()->post('https://api.openai.com/v1/chat/completions', [
            'model'                 => $this->model,
            'max_completion_tokens' => 600,
            'messages'              => array_merge(
                [['role' => 'system', 'content' => $system . $userContext]],
                $messages
            ),
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI request failed: ' . $response->body());
        }

        return trim(
            $response->json('choices.0.message.content', 'Sorry, I could not respond right now.')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SCORE KPI DESCRIPTION
    |--------------------------------------------------------------------------
    */

    public function scoreKpiDescription(
        string  $kpiTitle,
        string  $description,
        mixed   $baseTarget     = null,
        mixed   $stretchTarget  = null,
        ?string $unit           = null,
        mixed   $weightage      = null,
        ?string $category       = null,
        ?string $subCategory    = null,
        ?array  $quarterTargets = null
    ): array {
        $systemPrompt = 'You are a KPI quality evaluator. Respond ONLY with valid JSON — no markdown, no explanation.';

        $unitLabel = match($unit) {
            'currency'   => 'RM',
            'percentage' => '%',
            default      => '',
        };

        $details  = "KPI Title: \"$kpiTitle\"";
        $details .= "\nDescription: \"$description\"";

        if ($category)    $details .= "\nCategory: $category";
        if ($subCategory) $details .= "\nSub-Category: $subCategory";
        if ($baseTarget !== null)    $details .= "\nBase Target: $baseTarget$unitLabel";
        if ($stretchTarget !== null) $details .= "\nStretch Target: $stretchTarget$unitLabel";
        if ($weightage !== null)     $details .= "\nWeightage: $weightage%";

        if (!empty($quarterTargets)) {
            $qtText = implode(', ', array_map(
                fn($v, $i) => 'Q' . ($i + 1) . ': ' . $v . $unitLabel,
                $quarterTargets,
                array_keys($quarterTargets)
            ));
            $details .= "\nQuarterly Targets: $qtText";

            if ($unit === 'percentage') {
                $details .= "\nNote: This is a rate/percentage KPI — quarterly targets represent the target rate per quarter, NOT cumulative sums. Good quarterly targets should show a progressive trend toward the annual base target by Q4.";
            } else {
                $qtSum = array_sum($quarterTargets);
                $details .= "\nSum of Quarterly Targets: $qtSum$unitLabel (annual base target is $baseTarget$unitLabel)";
                $details .= $qtSum >= (float)$baseTarget
                    ? " — quarterly sum meets or exceeds the base target, which is good."
                    : " — WARNING: quarterly sum is below the annual base target, which means the plan will fall short.";
            }
        }

        $userPrompt = "Score this KPI out of 10 across these dimensions:\n"
            . "1. Title clarity — specific and action-oriented?\n"
            . "2. Description quality — clear, measurable, explains how it is tracked and why it matters?\n"
            . "3. Target ambition — is the base target meaningful? Is the stretch target a genuine stretch?\n"
            . "4. Quarterly distribution — do quarterly targets add up to the annual base and show realistic progression?\n"
            . "5. Overall coherence — do title, description, category, and targets tell a consistent story?\n\n"
            . "SCORING GUIDE (be fair and encouraging, not overly harsh):\n"
            . "10 — Perfect: all 5 dimensions are excellent, nothing to improve\n"
            . "8-9 — Strong: most dimensions are well done, only minor improvements needed\n"
            . "6-7 — Good: solid effort with 1-2 dimensions that need improvement\n"
            . "4-5 — Fair: the idea is there but key details are missing (e.g. no measurement method, vague targets)\n"
            . "1-3 — Weak: major gaps across multiple dimensions\n\n"
            . "Important: if the title is specific, the description explains the measurement method, and targets are set — this should score at least 7. Reserve scores below 5 for genuinely poor KPIs.\n\n"
            . $details
            . "\n\nRespond with JSON only: {\"score\": number, \"feedback\": \"one short sentence on the single most important thing to improve\"}";

        $response = $this->request()->post('https://api.openai.com/v1/chat/completions', [
            'model'                 => $this->model,
            'max_completion_tokens' => 120,
            'temperature'           => 0.2,
            'messages'              => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI request failed: ' . $response->body());
        }

        $text = trim($response->json('choices.0.message.content', '{}'));

        return json_decode($text, true) ?? ['score' => 0, 'feedback' => ''];
    }

    /*
    |--------------------------------------------------------------------------
    | SCORE QUARTER PERFORMANCE (Section 2 "View" popup, appraiser side)
    |--------------------------------------------------------------------------
    */

    public function scoreQuarterPerformance(
        string $kpiTitle,
        string $quarterSubtitle,
        string $quarter,
        mixed  $actual,
        mixed  $target,
        float  $progressPct,
        string $remark = '',
        bool   $hasAttachment = false
    ): array {
        $systemPrompt = 'You are ANIRA, a KPI performance coach reviewing one quarter of one KPI for an appraiser. '
            . 'Be direct and specific, not generic encouragement. Respond ONLY with valid JSON — no markdown, no explanation.';

        $details = "KPI: \"$kpiTitle\"";
        if ($quarterSubtitle !== '') $details .= "\nQuarter plan/description: \"$quarterSubtitle\"";
        $details .= "\nQuarter: $quarter";
        $details .= "\nActual: " . ($actual ?? '—');
        $details .= "\nTarget: " . ($target ?? '—');
        $details .= "\nProgress: {$progressPct}%";
        $details .= "\nRemark: " . ($remark !== '' ? $remark : 'None provided');
        $details .= "\nAttachment/proof: " . ($hasAttachment ? 'Provided' : 'Not provided');

        $userPrompt = "Assess this quarter's KPI performance.\n\n$details\n\n"
            . "Pick ONE verdict label based on progress — \"Excellent\" (>=90%), \"Good\" (>=70%), \"Needs Attention\" (>=50%), or \"Critical\" (<50%). "
            . "Downgrade one level from what progress alone suggests if progress is below 100% and there is no remark or no attachment to explain the gap.\n\n"
            . "Then give up to 3 short bullet points (max ~15 words each) on what specifically needs improvement — or, only if the verdict is Excellent, what is working well instead. "
            . "Be concrete: reference the actual numbers, the remark, or the missing evidence, not generic advice.\n\n"
            . "Respond with JSON only: {\"verdict\": \"Excellent|Good|Needs Attention|Critical\", \"points\": [\"...\"]}";

        $response = $this->request()->post('https://api.openai.com/v1/chat/completions', [
            'model'                 => $this->model,
            'max_completion_tokens' => 220,
            'temperature'           => 0.3,
            'messages'              => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI request failed: ' . $response->body());
        }

        $text = trim($response->json('choices.0.message.content', '{}'));
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        return json_decode(trim($text), true) ?? ['verdict' => '', 'points' => []];
    }

    /*
    |--------------------------------------------------------------------------
    | REPHRASE APPRAISER COMMENT (Section 2 "Comment" popup, appraiser side)
    |--------------------------------------------------------------------------
    */

    public function rephraseAppraiserComment(
        string $comment,
        string $kpiTitle = '',
        string $quarter = ''
    ): string {
        $systemPrompt = 'You are ANIRA, helping a manager rephrase a short note explaining why they gave a '
            . 'KPI score. Rewrite it so anyone reading it later — the employee, a VP, HR — understands the '
            . 'reasoning clearly. Keep it factual and specific to what was written: never invent a reason, '
            . 'number, or detail that was not in the original note. Expand a terse or vague note (e.g. a '
            . 'single word) into one clear, complete, professional sentence or two. Keep the same tone — '
            . 'constructive if the original is constructive, direct if it is direct — never add unwarranted '
            . 'praise or criticism. Respond with the rephrased comment only — no quotes, no headings, no '
            . 'extra commentary.';

        $context = trim(($kpiTitle ? "KPI: \"$kpiTitle\". " : '') . ($quarter ? "Quarter: $quarter. " : ''));

        $userPrompt = ($context ? "$context\n\n" : '')
            . "Original comment from the appraiser: \"$comment\"\n\n"
            . 'Rephrase this so it clearly explains the reasoning behind the score to someone who was not there.';

        $response = $this->request()->post('https://api.openai.com/v1/chat/completions', [
            'model'                 => $this->model,
            'max_completion_tokens' => 250,
            'temperature'           => 0.3,
            'messages'              => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI request failed: ' . $response->body());
        }

        return trim(
            $response->json('choices.0.message.content', '')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SUGGEST QUARTERLY TARGETS
    |--------------------------------------------------------------------------
    */

    public function suggestQuarterlyTargets(
        string $kpiTitle,
        float  $annualTarget,
        string $unit = ''
    ): array {
        $systemPrompt = 'You are a KPI planning expert. Respond ONLY with valid JSON — no markdown, no explanation.';

        $userPrompt = "Split an annual KPI target of $annualTarget $unit into 4 quarterly targets for: \"$kpiTitle\"."
            . "\nConsider realistic growth progression (not always equal splits)."
            . "\nRespond with JSON only: {\"q1\": number, \"q2\": number, \"q3\": number, \"q4\": number}";

        $response = $this->request()->post('https://api.openai.com/v1/chat/completions', [
            'model'       => $this->model,
            'max_completion_tokens'  => 100,
            'temperature' => 0.3,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI request failed: ' . $response->body());
        }

        $text = trim($response->json('choices.0.message.content', '{}'));

        return json_decode($text, true) ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE PERFORMANCE REVIEW (weekly / monthly / quarterly)
    |--------------------------------------------------------------------------
    */

    public function generatePerformanceReview(
        string $employeeName,
        string $periodType,
        string $periodLabel,
        array  $stats
    ): array {
        $systemPrompt = 'You are a professional performance-review assistant for an internal company KPI system. '
            . 'Write objective, evidence-based commentary in a neutral, professional tone — no emoji, no exclamation marks, no motivational fluff. '
            . 'Base the review strictly on the activity and KPI data provided. Respond ONLY with valid JSON — no markdown, no explanation.';

        $activeDays = $stats['active_days'] ?? 0;
        $totalDays  = $stats['total_days'] ?? 0;
        $tasks      = $stats['tasks'] ?? [];
        $kpis       = $stats['kpis'] ?? [];

        $taskLines = empty($tasks)
            ? 'No task updates were logged during this period.'
            : implode("\n", array_map(
                fn($t) => "- \"{$t['title']}\": logged " . ($t['delta_in_period'] >= 0 ? '+' : '') . "{$t['delta_in_period']} {$t['unit']} this period, running total {$t['actual']}/{$t['target']} {$t['unit']} ({$t['status']})",
                $tasks
            ));

        $kpiLines = empty($kpis)
            ? 'No KPIs are set for this employee.'
            : implode("\n", array_map(
                fn($k) => "- \"{$k['kpi_title']}\" ({$k['category']}): {$k['achievement_percentage']}% of annual target achieved, status {$k['status']}",
                $kpis
            ));

        $userPrompt = "Employee: {$employeeName}\n"
            . "Review period: {$periodLabel} ({$periodType})\n\n"
            . "TASK ACTIVITY:\n"
            . "Active on {$activeDays} of {$totalDays} days in this period.\n"
            . "{$taskLines}\n\n"
            . "KPI STANDING (current, not period-specific):\n"
            . "{$kpiLines}\n\n"
            . "Score this period 0–100, weighing both task activity consistency and KPI achievement:\n"
            . "90-100 — Highly consistent activity and strong KPI achievement.\n"
            . "75-89 — Solid, mostly consistent activity with KPIs on track.\n"
            . "50-74 — Inconsistent activity or KPI achievement noticeably behind pace.\n"
            . "0-49 — Little to no logged activity and/or KPI achievement well behind.\n\n"
            . "Respond with JSON only: {\"score\": number, \"narrative\": \"2-4 professional sentences summarizing performance this period, naming what is going well and what needs attention, grounded in the data above\"}";

        $response = $this->request()->post('https://api.openai.com/v1/chat/completions', [
            'model'                 => $this->model,
            'max_completion_tokens' => 300,
            'temperature'           => 0.3,
            'messages'              => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI request failed: ' . $response->body());
        }

        $text = trim($response->json('choices.0.message.content', '{}'));

        return json_decode($text, true) ?? ['score' => 0, 'narrative' => ''];
    }

    /*
    |--------------------------------------------------------------------------
    | SUGGEST COMPLETE KPI (from coaching conversation)
    |--------------------------------------------------------------------------
    */

    public function suggestKpi(array $messages, array $employee = [], string $jobDescription = ''): array
    {
        $name       = $employee['short_name'] ?? $employee['full_name'] ?? 'the user';
        $role       = $employee['role']        ?? '';
        $department = $employee['department']  ?? '';

        $employeeCtx = implode(', ', array_filter([
            $role       ? "Role: $role"             : '',
            $department ? "Department: $department" : '',
        ]));

        $jdCtx = $jobDescription
            ? "\n\nJOB DESCRIPTION:\n$jobDescription"
            : '';

        $systemPrompt = "You are a KPI planning expert. Based on the coaching conversation below, generate a complete, high-quality KPI that will score 85+ out of 100 on a KPI quality rubric. "
            . "Employee context: $employeeCtx$jdCtx\n\n"
            . "SCORING RULES — your output must satisfy all of these:\n"
            . "1. TITLE: specific, outcome-oriented, action-verb first (e.g. 'Achieve', 'Increase', 'Reduce', 'Maintain'). Never vague (e.g. 'Support', 'Assist', 'Manage').\n"
            . "2. DESCRIPTION: exactly 2-3 sentences covering (a) what specific outcome is measured, (b) the exact data source or system used to track it (e.g. 'tracked in the CRM system', 'based on monthly finance reports', 'recorded in ClickUp'), and (c) why it matters to the department. The data source MUST be named explicitly.\n"
            . "3. TARGETS: base_target must be realistic and meaningful. stretch_target must be 20-30% above base — a genuine stretch. Do NOT set stretch equal to or close to base.\n"
            . "4. QUARTERLY TARGETS: for non-percentage units, q1+q2+q3+q4 must sum to exactly base_target. For percentage units, each quarter should show progressive improvement toward the base_target by Q4.\n"
            . "5. CONTROLLABILITY: the KPI must measure something within the employee's direct control or significant influence — not dependent on external approvals or third-party decisions.\n\n"
            . "VALID CATEGORIES AND SUB-CATEGORIES (use exactly these values):\n"
            . "- Financial: sub_category must be 'Revenue' or 'Operating Cost Optimisation'\n"
            . "- Growth & Customer: sub_category must be 'New Customer Acquisition' or 'Growth'\n"
            . "- Initiatives: sub_category must be 'Continuous Improvement & New Business'\n"
            . "- People: sub_category must be 'Certification of Competence (COC)' or 'Staff Development'\n\n"
            . "VALID UNITS: 'number', 'currency', 'percentage'\n\n"
            . "Respond ONLY with valid JSON — no markdown, no explanation outside the JSON.";

        $conversationSummary = implode("\n", array_map(
            fn($m) => strtoupper($m['role']) . ': ' . $m['content'],
            $messages
        ));

        $userPrompt = "Coaching conversation:\n\n$conversationSummary\n\n"
            . "Based on this conversation, output the single best KPI as JSON with ALL fields:\n"
            . '{'
            . '"title":"outcome-oriented KPI title starting with an action verb",'
            . '"description":"2-3 sentences: (1) what specific outcome is measured and how (include the exact data source or tracking system by name), (2) the measurement frequency, (3) why this outcome matters to the department",'
            . '"category":"Financial|Growth & Customer|Initiatives|People",'
            . '"sub_category":"exact value from list above",'
            . '"unit":"number|currency|percentage",'
            . '"base_target":number,'
            . '"stretch_target":number (must be 20-30% higher than base_target),'
            . '"weightage":number (suggested % weight, e.g. 20),'
            . '"q1":number,"q2":number,"q3":number,"q4":number (for non-percentage: q1+q2+q3+q4 must equal base_target exactly),'
            . '"q1_title":"short title for Q1 plan",'
            . '"q1_description":"specific actions and sub-targets for Q1",'
            . '"q2_title":"short title for Q2 plan",'
            . '"q2_description":"specific actions and sub-targets for Q2",'
            . '"q3_title":"short title for Q3 plan",'
            . '"q3_description":"specific actions and sub-targets for Q3",'
            . '"q4_title":"short title for Q4 plan",'
            . '"q4_description":"specific actions and sub-targets for Q4 to close out the year",'
            . '"rationale":"1 sentence on why this KPI fits their role, is within their control, and uses a verifiable data source"'
            . '}';

        $response = $this->request()->post('https://api.openai.com/v1/chat/completions', [
            'model'                 => $this->model,
            'max_completion_tokens' => 1200,
            'temperature'           => 0.2,
            'messages'              => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI request failed: ' . $response->body());
        }

        $text = trim($response->json('choices.0.message.content', '{}'));

        // strip possible markdown code fences
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        return json_decode(trim($text), true) ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | SUGGEST TASK -> KPI ALIGNMENT (Performix)
    |--------------------------------------------------------------------------
    | Per docs/performix-design.md §3.6 — the AI may suggest a link but must
    | never invent one: if nothing in $employeeKpis is a genuine fit, it must
    | say so rather than force a match. The returned kpi_id is also verified
    | against $employeeKpis before being trusted, so a hallucinated id can
    | never make it back to the caller as a suggestion.
    */

    public function suggestTaskKpiLink(
        string $taskTitle,
        ?string $taskDescription,
        array $employeeKpis
    ): ?array {
        if (empty($employeeKpis)) {
            return null;
        }

        $systemPrompt = 'You are an assistant that aligns daily work tasks to the KPI they most genuinely support. '
            . 'Only suggest a link when the task clearly and specifically advances one of the listed KPIs — when '
            . 'none of them fit, say so rather than forcing a weak match. Respond ONLY with valid JSON — no markdown, no explanation.';

        $kpiLines = implode("\n", array_map(
            fn ($k) => "- id=\"{$k['kpi_id']}\": \"{$k['kpi_title']}\"" . (!empty($k['category']) ? " (category: {$k['category']})" : ''),
            $employeeKpis
        ));

        $userPrompt = "Task title: \"{$taskTitle}\"\n"
            . ($taskDescription ? "Task description: \"{$taskDescription}\"\n" : '')
            . "\nEmployee's KPIs eligible to link:\n{$kpiLines}\n\n"
            . "Pick the single best-fitting KPI id from the list above, or null if none genuinely fit. "
            . "Respond with JSON only: {\"kpi_id\": \"exact id from the list, or null\", \"confidence\": number (0-100), \"reason\": \"one short sentence\"}";

        $response = $this->request()->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'max_completion_tokens' => 150,
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI request failed: ' . $response->body());
        }

        $text = trim($response->json('choices.0.message.content', '{}'));
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $result = json_decode(trim($text), true);

        $kpiId = $result['kpi_id'] ?? null;

        if (!$kpiId) {
            return null;
        }

        $validIds = array_column($employeeKpis, 'kpi_id');

        // A suggested id that isn't one of the KPIs we actually offered is
        // treated as no suggestion at all — never trust an unverified link.
        if (!in_array($kpiId, $validIds, true)) {
            return null;
        }

        return [
            'kpi_id' => $kpiId,
            'confidence' => max(0, min(100, (float) ($result['confidence'] ?? 0))),
            'reason' => $result['reason'] ?? '',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE TASK SUMMARY (daily / weekly / monthly, any scope)
    |--------------------------------------------------------------------------
    | Per docs/performix-design.md AI REQUIREMENTS — the caller is
    | responsible for pre-filtering $facts to only what the viewer is
    | authorized to see (via TaskAccessPolicy) before this is ever called;
    | this method only ever narrates the numbers it's given. It never
    | receives raw employee lists to query itself, so it cannot leak beyond
    | what the caller already decided to include.
    */

    public function generateTaskSummary(
        string $subjectName,
        string $scope,
        string $periodType,
        string $periodLabel,
        array $facts
    ): array {
        $systemPrompt = 'You are an execution-analytics assistant for an internal task management system. '
            . 'You narrate ONLY the data you are given — never invent numbers, tasks, names, or progress that were not provided. '
            . 'Every claim must be traceable to a figure in the input. When explaining an at-risk or critical status, '
            . 'name the specific overdue/blocked counts or consistency gap that caused it — not generic language. '
            . 'Respond ONLY with valid JSON — no markdown, no explanation.';

        $score = $facts['score'] ?? null;
        $status = $facts['status'] ?? 'insufficient_data';

        $lines = [
            'Task Score: ' . ($score !== null ? "{$score}/100" : 'not available (insufficient data)'),
            'Status: ' . $status,
            'Scored tasks: ' . ($facts['scored_task_count'] ?? 0),
            'Completed: ' . ($facts['completed_count'] ?? 0),
            'Overdue: ' . ($facts['overdue_count'] ?? 0),
            'Blocked: ' . ($facts['blocked_count'] ?? 0),
            'On-time completion: ' . (isset($facts['on_time_pct']) ? "{$facts['on_time_pct']}%" : 'no data'),
            'Update consistency: ' . (isset($facts['update_consistency_pct']) ? "{$facts['update_consistency_pct']}%" : 'no data'),
        ];

        if (!empty($facts['members'])) {
            $lines[] = "\nTeam members this period:";
            foreach ($facts['members'] as $member) {
                $lines[] = "- {$member['name']}: " . ($member['score'] !== null ? "{$member['score']}/100 ({$member['status']})" : 'insufficient data');
            }
        }

        $factsText = implode("\n", $lines);
        $scopeLabel = match ($scope) {
            'team' => "{$subjectName}'s team",
            'department' => "the {$subjectName} department",
            'company' => "{$subjectName} company-wide",
            default => $subjectName,
        };

        $userPrompt = "Summarize {$scopeLabel}'s task execution for this {$periodType} period ({$periodLabel}).\n\n"
            . "DATA:\n{$factsText}\n\n"
            . "Write:\n"
            . "1. A narrative (2-4 sentences) describing performance using ONLY the numbers above. If status is at_risk or critical, "
            . "explain specifically why using the overdue/blocked/consistency figures given. If status is insufficient_data, say so plainly and do not fabricate a performance story.\n"
            . "2. Up to 3 short, concrete recommendations (max ~15 words each) grounded in the specific gaps shown above.\n\n"
            . "Respond with JSON only: {\"narrative\": \"...\", \"recommendations\": [\"...\"]}";

        $response = $this->request()->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'max_completion_tokens' => 350,
            'temperature' => 0.3,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI request failed: ' . $response->body());
        }

        $text = trim($response->json('choices.0.message.content', '{}'));
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $result = json_decode(trim($text), true) ?? ['narrative' => '', 'recommendations' => []];

        return [
            'narrative' => $result['narrative'] ?? '',
            'recommendations' => $result['recommendations'] ?? [],
            'model_version' => $this->model,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PLATFORM CHAT (ANIRA, multi-company)
    |--------------------------------------------------------------------------
    */

    /**
     * The Platform's ANIRA endpoint. Unlike `chat()` above (built around the
     * legacy single-tenant employee/session model), this method never fetches
     * anything itself — `$context` must already be the output of
     * `AuthorizedDataScope::assistantContext()`, i.e. already filtered by
     * Postgres RLS to exactly what the asking user is entitled to see. That
     * is the actual security boundary: unauthorized rows were never fetched,
     * so they can't end up in this prompt no matter what it says.
     *
     * The "you may only discuss what's listed" instruction below is
     * defense-in-depth against the model inventing or recalling something
     * from general knowledge — a chatty-model problem, not a data-boundary
     * one. Never treat it as the guarantee; the caller-side filtering is.
     */
    public function chatForPlatform(array $context, array $messages): string
    {
        $system = $this->buildPlatformSystemPrompt($context);

        $response = $this->request()->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'max_completion_tokens' => 800,
            'messages' => array_merge(
                [['role' => 'system', 'content' => $system]],
                $messages
            ),
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI request failed: ' . $response->body());
        }

        return trim($response->json('choices.0.message.content', ''));
    }

    private function buildPlatformSystemPrompt(array $context): string
    {
        $user = $context['user'] ?? null;

        $companiesLines = collect($context['companies'] ?? [])
            ->map(fn ($c) => "- {$c['name']} ({$c['code']}), status: {$c['status']}")
            ->implode("\n") ?: '(none)';

        $departmentsLines = collect($context['departments'] ?? [])
            ->map(fn ($d) => "- {$d['name']} ({$d['code']})")
            ->implode("\n") ?: '(none)';

        $kpisLines = collect($context['kpis'] ?? [])
            ->map(fn ($k) => '- ' . $k['name']
                . ($k['target'] !== null ? " (target: {$k['target']}{$k['unit']})" : '')
                . ", frequency: {$k['frequency']}, visibility: {$k['visibility']}")
            ->implode("\n") ?: '(none)';

        $submissionsLines = collect($context['submissions'] ?? [])
            ->map(fn ($s) => "- {$s['submission_date']}: value {$s['value']}" . (!empty($s['notes']) ? " ({$s['notes']})" : ''))
            ->implode("\n") ?: '(none)';

        $userName = $user['name'] ?? 'the asking user';
        $userEmail = $user['email'] ?? '';

        return <<<PROMPT
You are ANIRA, the Performix Platform's KPI assistant.

CRITICAL RULE: you may ONLY discuss the data explicitly listed below. This list
has already been filtered by the database's own row-level security to exactly
what {$userName} is authorized to see for this conversation — it is not a
suggestion, it is the complete set of information available to you. If asked
about a company, department, KPI, or figure that is not listed below, say
plainly that you don't have access to that information. Never guess, infer, or
recall a value from general knowledge, and never speculate about what a KPI's
actual or target value "probably" is.

ASKING USER: {$userName} ({$userEmail})

COMPANIES YOU MAY DISCUSS:
{$companiesLines}

DEPARTMENTS YOU MAY DISCUSS:
{$departmentsLines}

KPIS YOU MAY DISCUSS:
{$kpisLines}

RECENT SUBMISSIONS YOU MAY DISCUSS:
{$submissionsLines}

Be concise and specific. When asked for a KPI's value, cite the exact figure
from the list above rather than describing it vaguely.
PROMPT;
    }
}
