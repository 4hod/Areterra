<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\EndOfDayRecord;
use App\Models\LegacyImportRow;
use App\Models\Member;
use App\Models\TransportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class LegacyJotformArchiveImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_import_accounts_for_every_row_and_is_idempotent(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->createMembers();

        $common = ['#', 'Full name', 'Submission Date', 'Submission Time', 'Date & Time - Date', 'Date & Time - Time'];
        $files = [
            'AB - End of shift report_11111111-1111-4111-8111-111111111111.xlsx' => $this->xlsx([
                [...$common, 'AB - Notes about the shift', 'Signature', 'Notes', 'Status', 'Status - Last status change'],
                [1, 'Legacy Author', '2026-09-01T14:00:00', '14:00', '2026-09-01T13:55:00', '13:55', 'First shift note', 'Image', '', 'None', ''],
                [2, 'Legacy Author', '2026-09-01T14:05:00', '14:05', '2026-09-01T13:58:00', '13:58', 'Second shift note', 'Image', '', 'None', ''],
            ]),
            'Register_22222222-2222-4222-8222-222222222222.xlsx' => $this->xlsx([
                [...$common, 'Who attended?', 'Signature', 'Notes', 'Status', 'Status - Last status change'],
                [1, 'Legacy Author', '2026-09-01T10:00:00', '10:00', 46266.413194444445, '09:55', 'AB, AT', 'Image', '', 'None', ''],
            ]),
            'Transport Register_33333333-3333-4333-8333-333333333333.xlsx' => $this->xlsx([
                [...$common, 'Who took Transport', 'Any Issues relating to members?', 'Signature', 'Notes', 'Status', 'Status - Last status change'],
                [1, 'Legacy Author', '2026-09-01T10:05:00', '10:05', '2026-09-01T10:00:00', '10:00', 'AB', 'Yes', 'Image', '', 'None', ''],
            ]),
        ];
        $archive = $this->archive($files);

        $response = $this->actingAs($admin)->post('/import/archive', [
            'file' => UploadedFile::fake()->createWithContent('archive.zip', $archive),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('archive_import_report.source_rows', 4);
        $response->assertSessionHas('archive_import_report.created.end_of_day', 1);
        $response->assertSessionHas('archive_import_report.merged.end_of_day', 1);
        $response->assertSessionHas('archive_import_report.created.attendance', 2);
        $response->assertSessionHas('archive_import_report.created.transport', 1);
        $response->assertSessionHas('archive_import_report.source_flags.transport_issues', 1);

        $this->assertDatabaseCount('legacy_import_rows', 4);
        $this->assertDatabaseCount('end_of_day_records', 1);
        $this->assertDatabaseCount('attendances', 2);
        $this->assertDatabaseCount('transport_runs', 1);
        $this->assertStringContainsString('First shift note', EndOfDayRecord::first()->notes);
        $this->assertStringContainsString('Second shift note', EndOfDayRecord::first()->notes);
        $this->assertSame(2, Attendance::where('status', 'present')->count());
        $this->assertSame('2026-09-01 09:55', Attendance::first()->checked_in_at->format('Y-m-d H:i'));
        $this->assertSame(1, TransportRun::where('phase', 'morning')->where('outcome', 'collected')->count());

        $encryptedPayload = DB::table('legacy_import_rows')->orderBy('id')->value('payload');
        $this->assertStringNotContainsString('First shift note', $encryptedPayload);
        $this->assertSame('First shift note', LegacyImportRow::first()->payload['AB - Notes about the shift']);

        $repeat = $this->actingAs($admin)->post('/import/archive', [
            'file' => UploadedFile::fake()->createWithContent('archive.zip', $archive),
        ]);

        $repeat->assertSessionHas('archive_import_report.already_imported', 4);
        $this->assertDatabaseCount('legacy_import_rows', 4);
        $this->assertDatabaseCount('end_of_day_records', 1);
        $this->assertDatabaseCount('attendances', 2);
        $this->assertDatabaseCount('transport_runs', 1);
    }

    private function createMembers(): void
    {
        foreach ([
            ['Amy', 'Buckle'], ['Andrew', 'Toomey'], ['Colin', 'Gibbons'], ['Gareth', 'Warner'],
            ['Matthew', 'England'], ['Michelle', 'Walker'], ['Nicholas', 'Thomas'], ['William', 'Garrett'],
        ] as [$firstName, $lastName]) {
            Member::create(['first_name' => $firstName, 'last_name' => $lastName, 'status' => 'active']);
        }
    }

    /** @param array<string, string> $files */
    private function archive(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'archive-test-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();
        $contents = file_get_contents($path);
        unlink($path);

        return $contents;
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function xlsx(array $rows): string
    {
        $xmlRows = [];
        foreach ($rows as $rowIndex => $row) {
            $cells = [];
            foreach ($row as $columnIndex => $value) {
                $reference = $this->columnName($columnIndex + 1).($rowIndex + 1);
                if (is_int($value) || is_float($value)) {
                    $cells[] = "<c r=\"{$reference}\"><v>{$value}</v></c>";
                } else {
                    $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $cells[] = "<c r=\"{$reference}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">{$escaped}</t></is></c>";
                }
            }
            $number = $rowIndex + 1;
            $xmlRows[] = "<row r=\"{$number}\">".implode('', $cells).'</row>';
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.implode('', $xmlRows).'</sheetData></worksheet>';

        $path = tempnam(sys_get_temp_dir(), 'xlsx-test-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        $contents = file_get_contents($path);
        unlink($path);

        return $contents;
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }
}
