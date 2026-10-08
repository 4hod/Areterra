<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Events\VetRecordCreated;
use Illuminate\Http\Request;

class VetRecordController extends Controller
{
    public function store(Request $request, Animal $animal)
    {
        $data = $request->validate([
            'visit_date' => ['required', 'date'],
            'next_due_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:200'],
            'treatment' => ['nullable', 'string'],
            'vet_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $record = $animal->vetRecords()->create($data);
        VetRecordCreated::dispatch($record);

        return back()->with('success', "Vet record added for {$animal->name}.");
    }
}
