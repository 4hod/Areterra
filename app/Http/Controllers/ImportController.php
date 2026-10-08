<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\Member;
use App\Services\LegacyJotformArchiveImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;

// CSV import with preview-before-run (SPEC checklist: Import).
// Members: first_name,last_name[,preferred_name,status,dob,phone,email,postcode,address_line1,address_line2,town,support_needs,diagnoses,medication]
// Animals: name,species[,breed,sex,status,joined_date,care_requirements,feeding_notes]
class ImportController extends Controller
{
    public function index()
    {
        return Inertia::render('Import');
    }

    public function archive(Request $request, LegacyJotformArchiveImporter $importer)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        try {
            $report = $importer->import($request->file('file'), $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', 'Archive import stopped without changing any data: '.$exception->getMessage());
        }

        return back()->with([
            'success' => "Archive import complete. {$report['source_rows']} source rows were accounted for.",
            'archive_import_report' => $report,
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'kind' => ['required', 'in:members,animals'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        [$headers, $rows, $problems] = $this->parse($request);
        $token = (string) Str::uuid();
        Cache::put($this->cacheKey($request, $token), $request->file('file')->get(), now()->addHour());

        return back()->with([
            'import_preview' => [
                'kind' => $request->string('kind')->toString(),
                'headers' => $headers,
                'rows' => array_slice($rows, 0, 50),
                'total' => count($rows),
                'problems' => $problems,
                'token' => $token,
            ],
        ]);
    }

    public function commit(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:members,animals'],
            'token' => ['required', 'uuid'],
        ]);

        $content = Cache::pull($this->cacheKey($request, $data['token']));
        abort_unless(is_string($content), 410, 'This import preview has expired. Upload the CSV again.');

        $rows = $this->parseCsv($content);
        $problems = $this->problemsFor($data['kind'], $rows['rows']);
        if ($problems !== []) {
            throw ValidationException::withMessages(['file' => $problems]);
        }
        $created = 0;

        DB::transaction(function () use ($rows, $data, &$created) {
          foreach ($rows['rows'] as $row) {
            if ($data['kind'] === 'members') {
                $member = Member::firstOrCreate(
                    ['first_name' => $row['first_name'], 'last_name' => $row['last_name']],
                    collect($row)->only([
                        'preferred_name', 'status', 'dob', 'phone', 'email', 'postcode',
                        'address_line1', 'address_line2', 'town',
                        'support_needs', 'diagnoses', 'medication',
                    ])->filter()->all(),
                );
                if ($member->wasRecentlyCreated) {
                    $member->settings()->create(['attendance_days' => [1, 2, 4, 5]]);
                    $created++;
                }
            } else {
                $animal = Animal::firstOrCreate(
                    ['name' => $row['name'], 'species' => $row['species']],
                    collect($row)->only(['breed', 'sex', 'status', 'joined_date', 'care_requirements', 'feeding_notes'])
                        ->filter()->all(),
                );
                if ($animal->wasRecentlyCreated) {
                    $created++;
                }
            }
          }
        });

        return redirect()->route('import')->with('success', "Imported {$created} new {$data['kind']}. Existing records were skipped.");
    }

    private function parse(Request $request): array
    {
        $parsed = $this->parseCsv($request->file('file')->get());
        $problems = $this->problemsFor($request->string('kind')->toString(), $parsed['rows']);

        return [$parsed['headers'], $parsed['rows'], $problems];
    }

    private function parseCsv(string $content): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $content));
        rewind($stream);

        $headerRow = fgetcsv($stream);
        if ($headerRow === false) {
            fclose($stream);
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headerRow);
        $rows = [];
        while (($values = fgetcsv($stream)) !== false) {
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $rows[] = array_combine($headers, array_pad(array_slice($values, 0, count($headers)), count($headers), null));
        }
        fclose($stream);

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function problemsFor(string $kind, array $rows): array
    {
        $rules = $kind === 'members'
            ? [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'status' => ['nullable', 'in:active,inactive,on-leave,archived'],
                'dob' => ['nullable', 'date'],
                'email' => ['nullable', 'email'],
            ]
            : [
                'name' => ['required', 'string', 'max:100'],
                'species' => ['required', 'in:'.implode(',', Animal::SPECIES)],
                'status' => ['nullable', 'in:active,inactive'],
                'joined_date' => ['nullable', 'date'],
            ];

        $problems = [];
        foreach ($rows as $index => $row) {
            $validator = Validator::make($row, $rules);
            foreach ($validator->errors()->all() as $message) {
                $problems[] = 'Row '.($index + 2).': '.$message;
            }
        }

        return $problems;
    }

    private function cacheKey(Request $request, string $token): string
    {
        return 'csv-import-preview:'.$request->user()->id.':'.$token;
    }
}
