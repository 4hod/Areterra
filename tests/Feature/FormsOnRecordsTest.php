<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\FormDefinition;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormsOnRecordsTest extends TestCase
{
    use RefreshDatabase;

    private function form(?User $author = null): FormDefinition
    {
        $form = FormDefinition::create([
            'title' => 'Welfare concern',
            'slug' => 'welfare-concern',
            'is_active' => true,
            'created_by' => ($author ?? User::factory()->create())->id,
        ]);
        $form->fields()->create(['label' => 'What happened', 'type' => 'textarea', 'is_required' => true, 'sort_order' => 1]);

        return $form->load('fields');
    }

    public function test_a_report_saves_onto_the_animal_it_is_about(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $form = $this->form();
        $rico = Animal::create(['name' => 'Rico', 'species' => 'Macaw']);
        $field = $form->fields->first();

        $this->actingAs($user)->post("/forms/{$form->slug}/submissions", [
            "field_{$field->id}" => 'Feather plucking noticed',
            'about' => 'animal',
            'about_id' => $rico->id,
        ])->assertRedirect("/animals/{$rico->id}");

        $this->assertSame(1, $rico->formSubmissions()->count());
        $this->assertSame(
            'Feather plucking noticed',
            $rico->formSubmissions()->first()->data()->first()->value,
        );
    }

    public function test_a_report_saves_onto_the_member_it_is_about(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $form = $this->form();
        $amy = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $field = $form->fields->first();

        $this->actingAs($user)->post("/forms/{$form->slug}/submissions", [
            "field_{$field->id}" => 'Great session today',
            'about' => 'member',
            'about_id' => $amy->id,
        ])->assertRedirect("/members/{$amy->id}");

        $this->assertSame(1, $amy->formSubmissions()->count());
    }

    public function test_a_general_form_still_works_with_no_subject(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $form = $this->form();
        $field = $form->fields->first();

        $this->actingAs($user)->post("/forms/{$form->slug}/submissions", [
            "field_{$field->id}" => 'General note',
        ])->assertRedirect('/forms');

        $this->assertSame(1, $form->submissions()->count());
        $this->assertNull($form->submissions()->first()->subject_type);
    }

    public function test_the_form_page_names_the_record_it_is_about(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $form = $this->form();
        $rico = Animal::create(['name' => 'Rico', 'species' => 'Macaw']);

        $this->actingAs($user)
            ->get("/forms/{$form->slug}?about=animal&id={$rico->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('subject.name', 'Rico'));
    }
}
