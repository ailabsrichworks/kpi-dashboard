<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\ApprovalActionService;

class SupabaseService
{
    protected string $url;

    protected string $key;

    // These reference tables are never written to by the app (managed
    // directly in Supabase) and change extremely rarely, so a short cache
    // avoids re-fetching them on every single page load — every controller
    // re-reads "departments" for the sidebar/switcher on every request.
    private const CACHEABLE_TABLES = ['companies', 'departments', 'kpi_permissions'];

    private const CACHE_TTL_SECONDS = 180;

    /**
     * REMOVED (2026-08-18) — this guard blocked `departments`/`kpis`/
     * `notifications`/`tasks` (and others) on the theory that every caller
     * reaching them was dead legacy code and the real, live tables lived in
     * a separate, RLS-protected "Platform" project instead. Investigating a
     * real production 500 (DashboardController::getUserDepartment(), the
     * very first screen after login) disproved that directly against the
     * actual live database (`mlggobjdsicuokblbsww`): `employees` and
     * `user_company_roles` — the tables that guard's own docblock claimed
     * "don't exist in production" — are real, active, and are what every
     * real login on this project goes through (see "Login system
     * correction" in CLAUDE.md). And there is no RLS to bypass here at
     * all: every table in this project, checked directly in the Supabase
     * table editor, shows RLS disabled / unrestricted. The guard was
     * correctly designed for a genuine multi-tenant Platform deployment —
     * it just isn't protecting one that exists on this Supabase project.
     * `departments`, `kpis`, `notifications`, and `tasks` are this
     * project's own real, single-tenant tables, actively read by
     * `DashboardController`, `KpiController`, the sidebar's unread-count
     * composer, and the Tasks feature — blocking them broke the app for
     * every real user immediately after login. If a genuine multi-tenant
     * Platform is ever deployed for real (its own project, real RLS,
     * confirmed live — not assumed), this class is the right place to
     * reintroduce a version of this guard scoped to that project's actual
     * tenant-owned tables.
     */

    public function __construct()
    {
        $this->url = rtrim(
            env('SUPABASE_URL'),
            '/'
        );

        $this->key = env(
            'SUPABASE_SERVICE_ROLE_KEY'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BASE REQUEST
    |--------------------------------------------------------------------------
    */

    private function request()
    {
        return Http::timeout(15)->connectTimeout(5)->withHeaders([

            'apikey' => $this->key,

            'Authorization' => 'Bearer ' . $this->key,

            'Content-Type' => 'application/json',

            'Accept' => 'application/json',

            'Prefer' => 'return=representation',

        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ENDPOINT
    |--------------------------------------------------------------------------
    */

    private function endpoint(
        string $table
    ){
        return $this->url . '/rest/v1/' . $table;
    }

    /*
    |--------------------------------------------------------------------------
    | GET
    |--------------------------------------------------------------------------
    */

    public function get(
        string $table,
        array $query = []
    ){

        if (in_array($table, self::CACHEABLE_TABLES, true)) {
            $cacheKey = 'supabase:' . $table . ':' . md5(json_encode($query));

            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL_SECONDS,
                fn () => $this->fetch($table, $query)
            );
        }

        return $this->fetch($table, $query);
    }

    private function fetch(
        string $table,
        array $query
    ){
        return $this->request()

            ->get(
                $this->endpoint($table),
                $query
            )

            ->throw()

            ->json();
    }

    /*
    |--------------------------------------------------------------------------
    | GET MANY (concurrent)
    |--------------------------------------------------------------------------
    | Runs several independent GET requests over the wire in parallel instead
    | of one after another. Each Supabase REST call pays a full network
    | round-trip (commonly 300-700ms from this app to Supabase), so a
    | controller issuing N sequential calls pays N round-trips; this pays
    | roughly the cost of the single slowest one. Only use this for calls
    | that don't depend on each other's results — it does not change what
    | gets requested, just when the requests are sent.
    |
    | $requests: ['key' => ['table' => 'kpis', 'query' => [...]], ...]
    | Returns:   ['key' => <decoded json response>, ...] — same shape as
    |            calling get() for each entry one at a time.
    */

    public function getMany(array $requests): array
    {
        if (empty($requests)) {
            return [];
        }

        $headers = [
            'apikey'        => $this->key,
            'Authorization' => 'Bearer ' . $this->key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Prefer'        => 'return=representation',
        ];

        $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($requests, $headers) {
            $calls = [];
            foreach ($requests as $key => $req) {
                $calls[] = $pool->as($key)
                    ->timeout(15)
                    ->connectTimeout(5)
                    ->withHeaders($headers)
                    ->get($this->endpoint($req['table']), $req['query'] ?? []);
            }
            return $calls;
        });

        $results = [];
        foreach ($requests as $key => $req) {
            $response = $responses[$key];
            if ($response instanceof \Throwable) {
                throw $response;
            }
            $results[$key] = $response->throw()->json();
        }

        return $results;
    }

    /*
    |--------------------------------------------------------------------------
    | FIRST
    |--------------------------------------------------------------------------
    */

    public function first(
        string $table,
        array $query = []
    ){
        $query['limit'] = 1;

        $result = $this->get(
            $table,
            $query
        );

        return $result[0] ?? null;
    }

    /**
     * Same as `first()`, but retries briefly — for rows written by an async
     * trigger (e.g. the `auth.users` -> `public.users` sync) that may not be
     * visible yet the instant the triggering call returns.
     */
    public function firstEventually(string $table, array $query = [], int $attempts = 10, int $delayMicroseconds = 300_000)
    {
        for ($i = 0; $i < $attempts; $i++) {
            $row = $this->first($table, $query);

            if ($row) {
                return $row;
            }

            usleep($delayMicroseconds);
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | FIND BY ID
    |--------------------------------------------------------------------------
    */

    public function findById(
        string $table,
        string $id
    ){

        return $this->first(
            $table,
            [
                'id' => 'eq.' . $id
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | INSERT
    |--------------------------------------------------------------------------
    */

    public function insert(
        string $table,
        array $data
    ){

        return $this->request()

            ->post(
                $this->endpoint($table),
                $data
            )

            ->throw()

            ->json();
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(
        string $table,
        array $filters,
        array $data
    ){
        $query = http_build_query(
            $filters
        );

        return $this->request()

            ->patch(
                $this->endpoint($table) . '?' . $query,
                $data
            )

            ->throw()

            ->json();
    }

    /*
    |--------------------------------------------------------------------------
    | PATCH
    |--------------------------------------------------------------------------
    */

    public function patch(
        string $table,
        array $filters,
        array $data
    ){

        return $this->update(
            $table,
            $filters,
            $data
        );
    }

    /*
    |--------------------------------------------------------------------------
    | POST
    |--------------------------------------------------------------------------
    */

    public function post(
        string $table,
        array $data
    ){

        return $this->insert(
            $table,
            $data
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function delete(
        string $table,
        array $filters = []
    ){
        $url = $this->endpoint(
            $table
        );

        if(!empty($filters)){

            $url .= '?' . http_build_query(
                $filters
            );
        }

        return $this->request()

            ->delete($url)

            ->throw()

            ->json();
    }

        /*
    |--------------------------------------------------------------------------
    | UPLOAD TO STORAGE
    |--------------------------------------------------------------------------
    */

    public function uploadToStorage(string $bucket, string $path, string $contents, string $mimeType): string
    {
        $url = $this->url . '/storage/v1/object/' . $bucket . '/' . $path;

        $response = Http::timeout(30)->connectTimeout(10)->withHeaders([
            'apikey'        => $this->key,
            'Authorization' => 'Bearer ' . $this->key,
            'Content-Type'  => $mimeType,
            'x-upsert'      => 'true',
        ])->withBody($contents, $mimeType)->post($url);

        if (!$response->successful()) {
            throw new \RuntimeException('Supabase Storage upload failed: ' . $response->body());
        }

        return $this->url . '/storage/v1/object/public/' . $bucket . '/' . $path;
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE FROM STORAGE
    |--------------------------------------------------------------------------
    */

    public function deleteFromStorage(string $bucket, string $path): bool
    {
        $url = $this->url . '/storage/v1/object/' . $bucket . '/' . $path;

        $response = Http::timeout(30)->connectTimeout(10)->withHeaders([
            'apikey'        => $this->key,
            'Authorization' => 'Bearer ' . $this->key,
        ])->delete($url);

        return $response->successful();
    }

    /*
    |--------------------------------------------------------------------------
    | SAFE PATCH
    |--------------------------------------------------------------------------
    */

    public function safePatch(
        string $table,
        array $filters,
        array $data
    ): bool
    {
        try {

            $this->patch(
                $table,
                $filters,
                $data
            );

            return true;

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('safePatch failed', [
                'table' => $table, 'filters' => $filters, 'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SAFE INSERT
    |--------------------------------------------------------------------------
    */

    public function safeInsert(
        string $table,
        array $data
    ): bool
    {
        try {

            $this->insert(
                $table,
                $data
            );

            return true;

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('safeInsert failed', [
                'table' => $table, 'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPSERT (insert or update on conflict)
    |--------------------------------------------------------------------------
    */

    public function upsert(string $table, array $data, string $onConflict = 'id'): mixed
    {
        return Http::timeout(15)->connectTimeout(5)->withHeaders([
            'apikey'        => $this->key,
            'Authorization' => 'Bearer ' . $this->key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Prefer'        => 'resolution=merge-duplicates,return=representation',
        ])->post(
            $this->endpoint($table) . '?on_conflict=' . $onConflict,
            $data
        )->throw()->json();
    }

    /*
    |--------------------------------------------------------------------------
    | SAFE DELETE
    |--------------------------------------------------------------------------
    */

    public function safeDelete(
        string $table,
        array $filters
    ): bool
    {
        try {
            $this->delete(
                $table,
                $filters
            );
            return true;

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('safeDelete failed', [
                'table' => $table, 'filters' => $filters, 'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
