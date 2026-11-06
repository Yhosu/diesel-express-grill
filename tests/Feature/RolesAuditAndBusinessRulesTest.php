<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Filament\Pages\GeneralReportPage;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\OperationalLocations\OperationalLocationResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Resources\Shifts\ShiftResource;
use App\Filament\Resources\ShiftTemplates\ShiftTemplateResource;
use App\Filament\Resources\Supplies\SupplyResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\OperationalLocation;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use App\Models\Supply;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAuditAndBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_application_locale_is_spanish_and_default_two_users_exist(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', \Illuminate\Support\Facades\DB::connection()->getDriverName());
        $this->assertSame('es', app()->getLocale());
        $this->assertTrue(\Filament\Actions\Action::make('test_slideover')->isModalSlideOver());

        // Verificar que ningún recurso redirige a páginas /create, /edit o /{record}, forzando SlideOver en todo el panel
        $resources = [
            ShiftResource::class,
            SaleResource::class,
            \App\Filament\Resources\Exceptions\ExceptionResource::class,
            ProductResource::class,
            SupplyResource::class,
            PurchaseResource::class,
            ExpenseResource::class,
            ShiftTemplateResource::class,
            OperationalLocationResource::class,
            UserResource::class,
            AuditLogResource::class,
        ];

        foreach ($resources as $resourceClass) {
            $pages = array_keys($resourceClass::getPages());
            $this->assertSame(['index'], $pages, "El recurso {$resourceClass} debe usar únicamente SlideOver en index.");
        }

        $users = User::query()->orderBy('id')->get();
        $this->assertCount(2, $users);

        $superAdmin = User::query()->where('email', 'admin@dieselnacional.com')->first();
        $subAdmin = User::query()->where('email', 'subadmin@dieselnacional.com')->first();

        $this->assertNotNull($superAdmin);
        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertSame(UserRole::SUPER_ADMIN, $superAdmin->role);

        $this->assertNotNull($subAdmin);
        $this->assertTrue($subAdmin->isSubAdmin());
        $this->assertSame(UserRole::SUB_ADMIN, $subAdmin->role);
    }

    public function test_super_admin_has_full_edit_delete_reports_and_audit_access(): void
    {
        $superAdmin = User::query()->where('email', 'admin@dieselnacional.com')->firstOrFail();
        $this->actingAs($superAdmin);

        $shift = Shift::query()->firstOrFail();
        $sale = Sale::query()->firstOrFail();
        $product = Product::query()->firstOrFail();
        $supply = Supply::query()->firstOrFail();
        $purchase = Purchase::query()->firstOrFail();
        $expense = Expense::query()->firstOrFail();

        // Puede crear y editar en todos los recursos
        $this->assertTrue(ShiftResource::canCreate());
        $this->assertTrue(ShiftResource::canEdit($shift));
        $this->assertTrue(ShiftResource::canDelete($shift));

        $this->assertTrue(SaleResource::canCreate());
        $this->assertTrue(SaleResource::canEdit($sale));
        $this->assertTrue(SaleResource::canDelete($sale));

        $this->assertTrue(ProductResource::canEdit($product));
        $this->assertTrue(SupplyResource::canEdit($supply));
        $this->assertTrue(PurchaseResource::canEdit($purchase));
        $this->assertTrue(ExpenseResource::canEdit($expense));

        // Tiene acceso a Reportes, Tabla de Auditoría y Configuración (Turnos, Ubicaciones, Usuarios)
        $this->assertTrue(GeneralReportPage::canAccess());
        $this->assertTrue(AuditLogResource::canViewAny());
        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(ShiftTemplateResource::canViewAny());
        $this->assertTrue(OperationalLocationResource::canViewAny());
    }

    public function test_shift_templates_and_locations_are_linked_and_sale_date_is_locked_for_sub_admin(): void
    {
        $shift = Shift::query()->with(['shiftTemplate', 'operationalLocation'])->firstOrFail();
        $this->assertNotNull($shift->shiftTemplate);
        $this->assertInstanceOf(ShiftTemplate::class, $shift->shiftTemplate);
        $this->assertNotNull($shift->operationalLocation);
        $this->assertInstanceOf(OperationalLocation::class, $shift->operationalLocation);

        $product = Product::query()->where('sku', 'DN01')->firstOrFail();

        // Subadministrador intenta registrar una venta con fecha pasada: el sistema fuerza el día actual
        $subAdmin = User::query()->where('email', 'subadmin@dieselnacional.com')->firstOrFail();
        $this->actingAs($subAdmin);
        $this->assertFalse(ShiftTemplateResource::canViewAny());
        $this->assertFalse(OperationalLocationResource::canViewAny());

        $subAdminSale = Sale::query()->create([
            'transaction_code' => 'T801',
            'shift_id' => $shift->id,
            'sale_date' => '2020-01-15',
            'sale_time' => '08:00:00',
            'product_id' => $product->id,
            'qty' => 1,
            'unit_price' => 35.00,
            'payment_method' => PaymentMethod::EFECTIVO->value,
        ]);

        $this->assertSame(now()->toDateString(), $subAdminSale->sale_date->format('Y-m-d'));

        // Super Administrador sí puede elegir una fecha específica en el registro de venta POS
        $superAdmin = User::query()->where('email', 'admin@dieselnacional.com')->firstOrFail();
        $this->actingAs($superAdmin);

        $adminSale = Sale::query()->create([
            'transaction_code' => 'T802',
            'shift_id' => $shift->id,
            'sale_date' => '2026-10-25',
            'sale_time' => '13:15:00',
            'product_id' => $product->id,
            'qty' => 2,
            'unit_price' => 35.00,
            'payment_method' => PaymentMethod::QR->value,
        ]);

        $this->assertSame('2026-10-25', $adminSale->sale_date->format('Y-m-d'));
    }

    public function test_sub_admin_can_register_and_view_reports_but_cannot_edit_delete_or_access_audit(): void
    {
        $subAdmin = User::query()->where('email', 'subadmin@dieselnacional.com')->firstOrFail();
        $this->actingAs($subAdmin);

        $shift = Shift::query()->firstOrFail();
        $sale = Sale::query()->firstOrFail();
        $product = Product::query()->firstOrFail();
        $supply = Supply::query()->firstOrFail();
        $purchase = Purchase::query()->firstOrFail();
        $expense = Expense::query()->firstOrFail();

        // Puede visualizar y registrar (crear)
        $this->assertTrue(ShiftResource::canViewAny());
        $this->assertTrue(ShiftResource::canCreate());
        $this->assertTrue(SaleResource::canViewAny());
        $this->assertTrue(SaleResource::canCreate());
        $this->assertTrue(PurchaseResource::canCreate());
        $this->assertTrue(ExpenseResource::canCreate());

        // NO puede editar ni eliminar ningún registro
        $this->assertFalse(ShiftResource::canEdit($shift));
        $this->assertFalse(ShiftResource::canDelete($shift));
        $this->assertFalse(SaleResource::canEdit($sale));
        $this->assertFalse(SaleResource::canDelete($sale));
        $this->assertFalse(ProductResource::canEdit($product));
        $this->assertFalse(SupplyResource::canEdit($supply));
        $this->assertFalse(PurchaseResource::canEdit($purchase));
        $this->assertFalse(ExpenseResource::canEdit($expense));

        // SÍ tiene acceso a reportes, pero NO a Tabla de Auditoría ni Gestión de Usuarios
        $this->assertTrue(GeneralReportPage::canAccess());
        $this->assertFalse(AuditLogResource::canViewAny());
        $this->assertFalse(UserResource::canViewAny());

        // Verificar que el Dashboard con el widget de auditoría y la página de Reporte General cargan sin RouteNotFoundException
        $dashboardResponse = $this->get('/admin');
        $dashboardResponse->assertSuccessful();

        \Livewire\Livewire::test(\App\Filament\Widgets\ShiftAuditTableWidget::class)
            ->assertSuccessful()
            ->assertSee('Auditoría de Turnos');

        \Livewire\Livewire::test(GeneralReportPage::class)
            ->assertSuccessful()
            ->assertSee('Reporte General Consolidado')
            ->assertSee('.dn-report-wrap', escape: false)
            ->assertSee('#C19D60', escape: false)
            ->assertSee('Exportar a Excel (.xlsx)')
            ->call('exportExcel')
            ->assertFileDownloaded();
    }

    public function test_audit_log_records_when_who_and_how_changes_are_made(): void
    {
        $subAdmin = User::query()->where('email', 'subadmin@dieselnacional.com')->firstOrFail();
        $this->actingAs($subAdmin);

        $shift = Shift::query()->firstOrFail();
        $product = Product::query()->where('sku', 'DN01')->firstOrFail();

        // Subadministrador registra una nueva venta POS
        $sale = Sale::query()->create([
            'transaction_code' => 'T999',
            'shift_id' => $shift->id,
            'sale_date' => $shift->shift_date,
            'sale_time' => '16:00:00',
            'product_id' => $product->id,
            'qty' => 2,
            'unit_price' => 35.00,
            'payment_method' => PaymentMethod::QR->value,
        ]);

        $createLog = AuditLog::query()
            ->where('auditable_type', Sale::class)
            ->where('auditable_id', $sale->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($createLog);
        $this->assertSame($subAdmin->id, $createLog->user_id);
        $this->assertSame('Subadministrador Operativo', $createLog->user_name);
        $this->assertSame('Subadministrador', $createLog->user_role);
        $this->assertSame('Venta POS', $createLog->module);
        $this->assertSame('T999', $createLog->record_label);

        // Super Administrador edita el precio de un insumo y se registra el cambio exacto (antes y después)
        $superAdmin = User::query()->where('email', 'admin@dieselnacional.com')->firstOrFail();
        $this->actingAs($superAdmin);

        $supply = Supply::query()->where('code', 'INS001')->firstOrFail();
        $supply->update([
            'last_purchase_price' => 26.00,
        ]);

        $updateLog = AuditLog::query()
            ->where('auditable_type', Supply::class)
            ->where('auditable_id', $supply->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($updateLog);
        $this->assertSame($superAdmin->id, $updateLog->user_id);
        $this->assertSame('Super Administrador', $updateLog->user_role);
        $this->assertSame('Insumo / Empaque', $updateLog->module);
        $this->assertStringContainsString('last_purchase_price', $updateLog->summary);
        $this->assertArrayHasKey('last_purchase_price', $updateLog->old_values);
        $this->assertArrayHasKey('last_purchase_price', $updateLog->new_values);
    }
}

