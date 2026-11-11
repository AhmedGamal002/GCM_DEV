<?php

namespace Tests\Feature\PurchaseOrders\Pages;

use App\Models\Contractor;
use App\Models\User;
use Tests\Feature\PurchaseOrders\PurchaseOrderTestCase;

/**
 * Part 6.3 — the purchase-order pages (/app/po/*): who can open them and what the
 * Blade shells hand to the page scripts. The data itself comes from the API
 * (covered by the Api tests); here only the access rules and the shells, since
 * the FRD gives Data Entry, the client Project Auditor, contractor users and
 * drivers NO access to POs, not even to read them. One test = one actor.
 */
class PurchaseOrderPagesTest extends PurchaseOrderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The shells @vite their page scripts; the build isn't needed to test who may open them.
        $this->withoutVite();
    }

    private function as(string $role): static
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $this->actingAs($user, 'web');
    }

    private function asClientManager(): static
    {
        return $this->actingAs(User::factory()->client($this->company, 'client_project_manager', true)->create(), 'web');
    }

    public function test_the_system_admin_opens_every_page(): void
    {
        $this->actingAs($this->admin, 'web');

        $this->get('/app/po/list')->assertOk()->assertSee('datatables-purchase-orders', false);
        $this->get('/app/po/add')->assertOk()->assertSee('id="poForm"', false);
        $this->get('/app/po/view/5')->assertOk()->assertSee('window.poViewId = 5', false);
        $this->get('/app/po/edit/5')->assertOk()->assertSee('window.poEditId = 5', false);
    }

    public function test_the_list_shell_gives_the_admin_the_write_and_export_flags(): void
    {
        $this->actingAs($this->admin, 'web');

        $this->get('/app/po/list')->assertOk()
            ->assertSee('can_manage: true', false)->assertSee('can_export: true', false)
            ->assertSee('can_filter_lookups: true', false)
            ->assertSee('data-stat="cost_items_total"', false);
    }

    public function test_the_auditor_reads_and_exports_but_cannot_open_add_or_edit(): void
    {
        $this->as('auditor');

        $this->get('/app/po/list')->assertOk()
            ->assertSee('can_manage: false', false)->assertSee('can_export: true', false);
        $this->get('/app/po/view/5')->assertOk()->assertDontSee('pov-edit-link', false);
        $this->get('/app/po/add')->assertForbidden();
        $this->get('/app/po/edit/5')->assertForbidden();
    }

    public function test_a_client_project_manager_reads_but_has_no_company_lists_export_or_edit(): void
    {
        $this->asClientManager();

        $this->get('/app/po/list')->assertOk()
            ->assertSee('can_manage: false', false)->assertSee('can_export: false', false)
            // no access to the staff's company / project lists, so the filters for them aren't offered
            ->assertSee('can_filter_lookups: false', false);
        $this->get('/app/po/view/5')->assertOk()
            ->assertSee('can_open_project: false', false)->assertSee('can_open_company: false', false);
        $this->get('/app/po/add')->assertForbidden();
        $this->get('/app/po/edit/5')->assertForbidden();
    }

    public function test_data_entry_cannot_open_any_page_even_the_read_ones(): void
    {
        $this->as('data_entry');

        foreach (['/app/po/list', '/app/po/add', '/app/po/view/5', '/app/po/edit/5'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_a_client_project_auditor_cannot_open_any_page(): void
    {
        $this->actingAs(User::factory()->client($this->company, 'client_project_auditor', true)->create(), 'web');

        foreach (['/app/po/list', '/app/po/add', '/app/po/view/5', '/app/po/edit/5'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_a_contractor_user_cannot_open_any_page(): void
    {
        $this->actingAs(User::factory()->contractorUser(Contractor::factory()->create())->create(), 'web');

        foreach (['/app/po/list', '/app/po/add', '/app/po/view/5', '/app/po/edit/5'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_a_driver_cannot_open_any_page(): void
    {
        $this->as('driver');

        foreach (['/app/po/list', '/app/po/add', '/app/po/view/5', '/app/po/edit/5'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_a_guest_is_sent_to_the_login(): void
    {
        $this->get('/app/po/list')->assertRedirect();
        $this->get('/app/po/add')->assertRedirect();
    }

    public function test_a_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->admin, 'web');

        $this->get('/app/po/view/abc')->assertNotFound();
        $this->get('/app/po/edit/abc')->assertNotFound();
    }

    public function test_the_pages_have_the_breadcrumb_and_the_titles_of_the_frd(): void
    {
        $this->actingAs($this->admin, 'web');

        $this->get('/app/po/list')->assertSee('breadcrumb', false);
        $this->get('/app/po/add')->assertSee('Create New Purchase Order');
        $this->get('/app/po/edit/5')->assertSee('Edit Purchase Order Details');
        $this->get('/app/po/view/5')->assertSee('Purchase Order Details');
    }
}
