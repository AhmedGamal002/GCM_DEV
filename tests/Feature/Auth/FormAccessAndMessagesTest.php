<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The create / edit form pages are for whoever may create — not the view-only auditor — and validation
 * messages come in the user's language, naming the field the way the form labels it.
 */
class FormAccessAndMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);

        app()->instance('tenant', Tenant::create(['name' => 'GCM', 'slug' => 'gcm', 'status' => 'active']));
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public static function formPages(): array
    {
        return array_map(fn ($p) => [$p], [
            '/app/user/add', '/app/client-user/add', '/app/driver/add', '/app/vehicle/add', '/app/vehicle-category/add',
            '/app/asset/add', '/app/asset/insert-into-project', '/app/asset-category/add', '/app/facility/add',
            '/app/company/add', '/app/project/add', '/app/main-service/add', '/app/sub-service/add',
            '/app/user/edit/1', '/app/client-user/edit/1', '/app/driver/edit/1', '/app/vehicle/edit/1', '/app/vehicle-category/edit/1',
            '/app/main-service/edit/1', '/app/sub-service/edit/1', '/app/asset/edit/1', '/app/asset-category/edit/1', '/app/facility/edit/1', '/app/company/edit/1', '/app/project/edit/1',
        ]);
    }

    #[DataProvider('formPages')]
    public function test_the_auditor_cannot_open_a_create_or_edit_form(string $page): void
    {
        $this->actingAs($this->user('auditor'), 'web')->get($page)->assertForbidden();
    }

    #[DataProvider('formPages')]
    public function test_the_driver_cannot_open_a_create_or_edit_form(string $page): void
    {
        $this->actingAs($this->user('driver'), 'web')->get($page)->assertForbidden();
    }

    public function test_data_entry_still_opens_the_forms_it_may_use(): void
    {
        $dataEntry = $this->user('data_entry');

        foreach (['/app/user/add', '/app/driver/add', '/app/vehicle/add', '/app/asset/add', '/app/company/add', '/app/project/add', '/app/facility/add'] as $page) {
            $this->actingAs($dataEntry, 'web')->get($page)->assertOk();
        }
    }

    public function test_the_system_admin_opens_the_category_forms(): void
    {
        $this->actingAs($this->user('system_admin'), 'web')->get('/app/vehicle-category/add')->assertOk();
    }

    public function test_the_auditor_gets_the_view_only_marker_and_others_do_not(): void
    {
        $this->actingAs($this->user('auditor'), 'web')->get('/app/user/list')->assertOk()->assertSee('data-gcm-view-only', false);
    }

    public function test_data_entry_does_not_get_the_view_only_marker(): void
    {
        $this->actingAs($this->user('data_entry'), 'web')->get('/app/user/list')->assertOk()->assertDontSee('data-gcm-view-only', false);
    }

    public function test_the_wording_for_clear_error_messages_is_on_every_page(): void
    {
        $this->actingAs($this->user('data_entry'), 'web')->get('/app/user/list')->assertOk()->assertSee('gcmI18n', false)->assertSee('required_hint', false);
    }

    public function test_validation_messages_are_in_arabic_and_name_the_field(): void
    {
        app()->setLocale('ar');

        $errors = Validator::make(['phone' => '', 'company_id' => ''], ['phone' => 'required', 'company_id' => 'required'])->errors();

        $this->assertSame('رقم الموبايل إجبارية.', $errors->first('phone'));
        $this->assertSame('الشركة إجبارية.', $errors->first('company_id'));
    }

    public function test_english_messages_use_the_form_label_not_the_column_name(): void
    {
        app()->setLocale('en');

        $this->assertSame('The company field is required.', Validator::make(['company_id' => ''], ['company_id' => 'required'])->errors()->first('company_id'));
        $this->assertSame('The mobile number field is required.', Validator::make(['phone' => ''], ['phone' => 'required'])->errors()->first('phone'));
    }
}
