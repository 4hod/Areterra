<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\Member;
use Illuminate\Http\Request;
use Inertia\Inertia;

// CSV import with preview-before-run (SPEC checklist: Import).
// Members: first_name,last_name[,preferred_name,status,dob,phone,postcode]
// Animals: name,species[,breed,sex,status]
class ImportController extends Controller
{
    public function index()
    {
        return Inertia::render('Import');
    }

    public function preview(Request $request)
    {
        $request->validate([
            'kind' => ['required', 'in:members,animals'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        [$headers, $rows, $problems] = $this->parse($request);

        return back()->with([
            'import_preview' => [
                'kind' => $request->string('kind')->toString(),
                'headers' => $headers,
                'rows' => array_slice($rows, 0, 50),
                'total' => count($rows),
                'problems' => $problems,
                'csv' => base64_encode($request->file('file')->get()),
            ],
        ]);
    }

    public function commit(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:members,animals'],
            'csv' => ['required', 'string'],
        ]);

        $rows = $this->parseCsv(base64_decode($data['csv']));
        $created = 0;

        foreach ($rows['rows'] as $row) {
            if ($data['kind'] === 'members') {
                if (empty($row['first_name']) || empty($row['last_name'])) {
                    continue;
                }
                $member = Member::firstOrCreate(
                    ['first_name' => $row['first_name'], 'last_name' => $row['last_name']],
                    collect($row)->only(['preferred_name', 'status', 'dob', 'phone', 'postcode'])
                        ->filter()->all(),
                );
                if ($member->wasRecentlyCreated) {
                    $member->settings()->create(['attendance_days' => [1, 2, 4, 5]]);
                    $created++;
                }
            } else {
                if (empty($row['name']) || empty($row['species']) || ! in_array($row['species'], Animal::SPECIES, true)) {
                    continue;
                }
                $animal = Animal::firstOrCreate(
                    ['name' => $row['name'], 'species' => $row['species']],
                    collect($row)->only(['breed', 'sex', 'status'])->filter()->all(),
                );
                if ($animal->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return redirect()->route('import')->with('success', "Imported {$created} new {$data['kind']}. Existing records were skipped.");
    }

    private function parse(Request $request): array
    {
        $parsed = $this->parseCsv($request->file('file')->get());
        $problems = [];

        foreach ($parsed['rows'] as $i => $row) {
            if ($request->string('kind')->toString() === 'members' && (empty($row['first_name']) || empty($row['last_name']))) {
                $problems[] = 'Row '.($i + 2).': missing first_name or last_name — will be skipped';
            }
            if ($request->string('kind')->toString() === 'animals') {
                if (empty($row['name']) || empty($row['species'])) {
                    $problems[] = 'Row '.($i + 2).': missing name or species — will be skipped';
                } elseif (! in_array($row['species'], Animal::SPECIES, true)) {
                    $problems[] = 'Row '.($i + 2).": unknown species \"{$row['species']}\" — will be skipped";
                }
            }
        }

        return [$parsed['headers'], $parsed['rows'], $problems];
    }

    private function parseCsv(string $content): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $content)), fn ($l) => $l !== ''));
        if ($lines === []) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_map(fn ($h) => strtolower(trim($h)), str_getcsv(array_shift($lines)));
        $rows = [];
        foreach ($lines as $line) {
            $values = str_getcsv($line);
            $rows[] = array_combine($headers, array_pad(array_slice($values, 0, count($headers)), count($headers), null));
        }

        return ['headers' => $headers, 'rows' => $rows];
    }
}
