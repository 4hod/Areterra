<?php

namespace App\Support;

use App\Models\Animal;
use App\Models\ComplianceItem;
use App\Models\Document;
use App\Models\Grant;
use App\Models\Member;
use App\Models\MemberInvoice;

/**
 * Point 13 — one search across everything.
 *
 * Search "Amy Buckle" and get her member record and authorised linked modules.
 * Encrypted free-text sources such as incidents and tasks are deliberately not
 * database-searched because doing so would require storing a plaintext index.
 *
 * Results are capability-filtered: a volunteer searching for a member will not
 * see safeguarding or finance hits, because each source declares the capability
 * required to read it. Nothing here bypasses that check.
 */
final class GlobalSearch
{
    private const PER_TYPE = 5;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function for(?string $term, ?object $user = null): array
    {
        $term = trim((string) $term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $user ??= auth()->user();
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $groups = [];

        foreach (self::sources() as $source) {
            if ($source['capability'] && ! $user?->hasCapability($source['capability'])) {
                continue;
            }

            $rows = ($source['query'])($like);

            if ($rows === []) {
                continue;
            }

            $groups[] = [
                'type' => $source['label'],
                'results' => $rows,
            ];
        }

        return $groups;
    }

    /** @return array<int, array<string, mixed>> */
    private static function sources(): array
    {
        return [
            [
                'label' => 'Members',
                'capability' => 'view_members',
                'query' => fn (string $like) => Member::query()
                    ->where('name', 'like', $like)
                    ->limit(self::PER_TYPE)->get()
                    ->map(fn ($m) => self::hit($m->id, $m->name, null, 'members.show'))->all(),
            ],
            [
                'label' => 'Animals',
                'capability' => 'view_animals',
                'query' => fn (string $like) => Animal::query()
                    ->where('name', 'like', $like)
                    ->limit(self::PER_TYPE)->get()
                    ->map(fn ($a) => self::hit($a->id, $a->name, $a->species ?? null, 'animals.show'))->all(),
            ],
            [
                'label' => 'Grants',
                'capability' => 'manage_finance',
                'query' => fn (string $like) => Grant::query()
                    ->where('title', 'like', $like)->orWhere('funder', 'like', $like)
                    ->limit(self::PER_TYPE)->get()
                    ->map(fn ($g) => self::hit($g->id, $g->title, $g->funder, null))->all(),
            ],
            [
                'label' => 'Invoices',
                'capability' => 'manage_finance',
                'query' => fn (string $like) => MemberInvoice::query()
                    ->where('qb_reference', 'like', $like)
                    ->limit(self::PER_TYPE)->get()
                    ->map(fn ($i) => self::hit($i->id, $i->qb_reference ?? "Invoice #{$i->id}", $i->status ?? null, null))->all(),
            ],
            [
                'label' => 'Documents',
                'capability' => 'view_documents',
                'query' => fn (string $like) => Document::query()
                    ->where('title', 'like', $like)->orWhere('original_name', 'like', $like)
                    ->limit(self::PER_TYPE)->get()
                    ->map(fn ($d) => self::hit($d->id, $d->title, $d->category, null))->all(),
            ],
            [
                'label' => 'Compliance',
                'capability' => 'view_all_compliance',
                'query' => fn (string $like) => ComplianceItem::query()
                    ->where('title', 'like', $like)
                    ->limit(self::PER_TYPE)->get()
                    ->map(fn ($c) => self::hit($c->id, $c->title, $c->status ?? null, null))->all(),
            ],
        ];
    }

    private static function hit(int $id, ?string $title, ?string $meta, ?string $route): array
    {
        return [
            'id' => $id,
            'title' => $title ?? '(untitled)',
            'meta' => $meta,
            'url' => $route && \Illuminate\Support\Facades\Route::has($route) ? route($route, $id) : null,
        ];
    }
}
