<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Maestro de Insumos, Empaques y Consumibles (Hoja 9)
        Schema::create('supplies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // INS001..CON003
            $table->string('name', 120);
            $table->string('category', 40)->index(); // Ingrediente, Bebida, Empaque, Consumible, Producción
            $table->string('base_unit', 30); // unidad, gramo, mililitro, rebanada
            $table->decimal('content_per_purchase', 12, 4)->default(1); // 12, 1000, 5000...
            $table->string('purchase_unit', 40); // paquete, kg, bidón 5 L...
            $table->decimal('last_purchase_price', 12, 4)->default(0); // Precio última compra Bs.
            $table->decimal('base_unit_cost', 14, 6)->default(0); // Precio / Contenido
            $table->decimal('standard_waste_pct', 6, 4)->default(0); // Ej: 0.05 = 5%
            $table->decimal('adjusted_unit_cost', 14, 6)->default(0); // base_unit_cost / (1 - merma)
            $table->string('main_supplier', 120)->nullable();
            $table->decimal('min_stock', 12, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 2. Maestro de Productos y Costos Calculados (Hojas 2 y 12)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 20)->unique(); // DN01..DN10
            $table->string('name', 120);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('ingredients_cost', 14, 6)->default(0);
            $table->decimal('packaging_cost', 14, 6)->default(0);
            $table->decimal('consumables_cost', 14, 6)->default(0);
            $table->decimal('production_var_cost', 14, 6)->default(0);
            $table->decimal('direct_bom_cost', 14, 6)->default(0);
            $table->decimal('direct_labor_cost', 12, 4)->default(1.50);
            $table->decimal('other_variable_cost', 12, 4)->default(0.50);
            $table->decimal('master_unit_cost', 14, 6)->default(0);
            $table->decimal('contribution_amount', 14, 6)->default(0);
            $table->decimal('contribution_margin_pct', 8, 6)->default(0); // 0.607905 = 60.79%
            $table->string('portion_control', 180)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 3. Recetas Estándar y Lista de Materiales BOM (Hoja 11)
        Schema::create('product_boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('supply_id')->constrained('supplies')->restrictOnDelete();
            $table->decimal('qty_per_unit', 12, 4)->default(1);
            $table->decimal('base_unit_cost_snapshot', 14, 6)->default(0);
            $table->decimal('component_cost', 14, 6)->default(0);
            $table->decimal('cost_share_pct', 8, 6)->default(0);
            $table->string('recipe_version', 20)->default('V1');
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'supply_id']);
        });

        // 4. Registro de Compras de Insumos (Hoja 10)
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_code', 30)->unique(); // C-0001
            $table->date('purchase_date')->index();
            $table->string('supplier', 120);
            $table->string('invoice_number', 60)->nullable();
            $table->foreignId('supply_id')->constrained('supplies')->restrictOnDelete();
            $table->decimal('qty', 12, 4)->default(1);
            $table->string('purchase_unit', 40)->nullable();
            $table->decimal('unit_price', 12, 4)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('freight', 12, 2)->default(0);
            $table->decimal('non_recoverable_tax', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0); // Subtotal - Descuento + Flete + Impuestos
            $table->string('payment_method', 40)->default('Efectivo');
            $table->string('responsible', 100)->default('Administrador');
            $table->boolean('update_supply_cost')->default(true);
            $table->timestamps();

            $table->index(['purchase_date', 'supply_id']);
        });

        // 5. Turnos Diarios: Despacho y Cierre/Conciliación (Hojas 3 y 6)
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('shift_code', 40)->unique(); // DN-M01-001
            $table->date('shift_date')->index();
            $table->string('unit_code', 30)->default('DN-M01')->index();
            $table->string('location', 100)->default('Sopocachi')->index();
            $table->string('operator_name', 120);
            $table->time('start_time')->nullable();
            $table->time('close_time')->nullable();
            $table->decimal('starting_float', 12, 2)->default(200.00);
            $table->boolean('operator_accepted_custody')->default(true);

            // Cierre físico de dinero (ingresado por operador)
            $table->decimal('counted_cash', 12, 2)->default(0);
            $table->decimal('confirmed_qr', 12, 2)->default(0);

            // Totales calculados por el motor de conciliación
            $table->decimal('sales_potential', 12, 2)->default(0);
            $table->decimal('expected_cash_sales', 12, 2)->default(0);
            $table->decimal('expected_qr_sales', 12, 2)->default(0);
            $table->decimal('actual_cash_sales', 12, 2)->default(0); // counted_cash - starting_float
            $table->decimal('total_expected_sales', 12, 2)->default(0);
            $table->decimal('total_actual_sales', 12, 2)->default(0);
            $table->decimal('cash_variance', 12, 2)->default(0);
            $table->decimal('qr_variance', 12, 2)->default(0);
            $table->decimal('total_financial_variance', 12, 2)->default(0);

            // Semáforos financieros
            $table->string('cash_semaphore', 20)->default('VERDE');
            $table->string('qr_semaphore', 20)->default('VERDE');
            $table->string('total_semaphore', 20)->default('VERDE')->index();

            // Indicadores físicos y de rentabilidad del turno
            $table->integer('total_dispatched_qty')->default(0);
            $table->integer('total_sold_qty')->default(0);
            $table->integer('total_waste_qty')->default(0);
            $table->integer('total_comp_qty')->default(0);
            $table->integer('total_staff_qty')->default(0);
            $table->integer('total_closing_qty')->default(0);
            $table->integer('total_inventory_variance_qty')->default(0);
            $table->decimal('inventory_variance_value', 12, 2)->default(0);
            $table->decimal('waste_pct', 8, 6)->default(0);
            $table->decimal('total_cogs', 12, 2)->default(0);
            $table->decimal('gross_contribution', 12, 2)->default(0);

            $table->string('status', 20)->default('open')->index(); // open | closed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shift_date', 'unit_code', 'location']);
        });

        // 6. Detalle de los 10 Productos por Turno (Despacho + Conciliación Física)
        Schema::create('shift_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('unit_cost', 14, 6)->default(0);
            $table->integer('opening_qty')->default(0);
            $table->integer('replenish_qty')->default(0);
            $table->integer('available_qty')->default(0); // opening + replenish
            $table->decimal('sales_potential', 12, 2)->default(0); // available * unit_price
            $table->integer('sold_qty')->default(0);
            $table->integer('waste_qty')->default(0);
            $table->integer('comp_qty')->default(0);
            $table->integer('staff_qty')->default(0);
            $table->integer('closing_qty')->default(0);
            $table->integer('variance_qty')->default(0); // Inicial + Repo - Vendido - Merma - Cortesía - Personal - Final
            $table->decimal('variance_value', 12, 2)->default(0); // variance_qty * unit_price
            $table->string('batch_notes', 150)->nullable();
            $table->timestamps();

            $table->unique(['shift_id', 'product_id']);
        });

        // 7. Registro de Ventas Transaccionales POS (Hoja 4)
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code', 30)->unique(); // T001
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->date('sale_date')->index();
            $table->time('sale_time');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('qty')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('unit_cost', 14, 6)->default(0);
            $table->string('payment_method', 30)->default('Efectivo')->index(); // Efectivo | QR
            $table->decimal('sales_value', 12, 2)->default(0); // qty * unit_price
            $table->decimal('contribution_value', 12, 2)->default(0); // qty * (unit_price - unit_cost)
            $table->timestamps();

            $table->index(['sale_date', 'payment_method']);
            $table->index(['shift_id', 'product_id']);
        });

        // 8. Registro de Excepciones: Mermas, Cortesías y Personal (Hoja 5)
        Schema::create('exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('exception_code', 30)->unique(); // E001
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->date('exception_date')->index();
            $table->time('exception_time');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('qty')->default(1);
            $table->string('code', 15)->index(); // W01, W02, W03, C01, S01
            $table->string('reason', 255);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('retail_value', 12, 2)->default(0); // qty * unit_price
            $table->timestamps();

            $table->index(['shift_id', 'product_id', 'code']);
        });

        // 9. Registro de Egresos Operativos fuera de Recetas (Hoja 13)
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_code', 30)->unique(); // EGR-001
            $table->date('expense_date')->index();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('unit_code', 30)->default('DN-M01');
            $table->string('location', 100)->nullable();
            $table->string('category', 80)->index();
            $table->string('description', 255);
            $table->string('supplier', 120)->nullable();
            $table->string('document_ref', 60)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment_method', 40)->default('Efectivo');
            $table->string('responsible', 100)->default('Administrador');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('exceptions');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('shift_items');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('product_boms');
        Schema::dropIfExists('products');
        Schema::dropIfExists('supplies');
    }
};

