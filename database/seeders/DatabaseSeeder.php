<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ExceptionCode;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Expense;
use App\Models\OperationalLocation;
use App\Models\Product;
use App\Models\ProductBom;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShiftException;
use App\Models\ShiftItem;
use App\Models\ShiftTemplate;
use App\Models\Supply;
use App\Models\User;
use App\Services\CostingService;
use App\Services\ShiftReconciliationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Usuarios por defecto (1 Super Administrador y 1 Subadministrador)
        User::query()->whereNotIn('email', [
            'admin@dieselnacional.com',
            'subadmin@dieselnacional.com',
        ])->forceDelete();

        User::query()->updateOrCreate(
            ['email' => 'admin@dieselnacional.com'],
            [
                'name' => 'Super Administrador Diesel',
                'role' => UserRole::SUPER_ADMIN->value,
                'is_active' => true,
                'password' => Hash::make('password'),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'subadmin@dieselnacional.com'],
            [
                'name' => 'Subadministrador Operativo',
                'role' => UserRole::SUB_ADMIN->value,
                'is_active' => true,
                
                'password' => Hash::make('password'),
            ]
        );

        // 1.1. Catálogo de Turnos Configurables y Ubicaciones Operativas
        $templatesData = [
            ['TUR-01', 'Turno Mediodía / Almuerzo', '11:00:00', '15:30:00', 200.00, 'Jornada principal de almuerzo ejecutivo y paso peatonal'],
            ['TUR-02', 'Turno Tarde / Noche', '18:00:00', '23:00:00', 250.00, 'Jornada nocturna de pub y alto flujo'],
            ['TUR-03', 'Turno Fin de Semana / Evento', '10:30:00', '18:00:00', 300.00, 'Jornada extendida para ferias, eventos y fines de semana'],
        ];

        $templateMap = [];
        foreach ($templatesData as [$tCode, $tName, $tStart, $tEnd, $tFloat, $tDesc]) {
            $tpl = ShiftTemplate::query()->updateOrCreate(
                ['code' => $tCode],
                [
                    'name' => $tName,
                    'start_time' => $tStart,
                    'end_time' => $tEnd,
                    'default_float' => $tFloat,
                    'description' => $tDesc,
                    'is_active' => true,
                ]
            );
            $templateMap[$tCode] = $tpl->id;
        }

        $locationsData = [
            ['LOC-SOPOCACHI', 'Sopocachi', 'DN-M01', 'Av. 20 de Octubre / Plaza Abaroa'],
            ['LOC-SAN-MIGUEL', 'San Miguel', 'DN-M01', 'Av. Montenegro / Bloque Comercial'],
            ['LOC-CALACOTO', 'Calacoto', 'DN-M01', 'Av. Ballivián / Calle 15'],
            ['LOC-CENTRO', 'Centro', 'DN-M01', 'El Prado / Av. 16 de Julio'],
            ['LOC-MIRAFLORES', 'Miraflores', 'DN-M01', 'Av. Busch / Plaza Uyuni'],
            ['LOC-OBRAJES', 'Obrajes', 'DN-M01', 'Av. Hernando Siles / Calle 17'],
        ];

        $locationMap = [];
        foreach ($locationsData as [$lCode, $lName, $lUnit, $lRef]) {
            $loc = OperationalLocation::query()->updateOrCreate(
                ['code' => $lCode],
                [
                    'name' => $lName,
                    'default_unit_code' => $lUnit,
                    'address_reference' => $lRef,
                    'is_active' => true,
                ]
            );
            $locationMap[$lName] = $loc->id;
        }

        // 2. Maestro de Insumos (29 Insumos de Hoja 9_Maestro_Insumos)
        $suppliesData = [
            ['INS001', 'Pan de hamburguesa', 'Ingrediente', 'unidad', 12, 'paquete', 24.00, 0.02, 'Proveedor de pan', 24],
            ['INS002', 'Carne para hamburguesa', 'Ingrediente', 'gramo', 1000, 'kg', 48.00, 0.05, 'Carnicería', 3000],
            ['INS003', 'Queso', 'Ingrediente', 'gramo', 1000, 'kg', 58.00, 0.04, 'Distribuidor lácteos', 1000],
            ['INS004', 'Lechuga', 'Ingrediente', 'gramo', 1000, 'kg', 14.00, 0.15, 'Mercado local', 1000],
            ['INS005', 'Tomate', 'Ingrediente', 'gramo', 1000, 'kg', 12.00, 0.12, 'Mercado local', 1500],
            ['INS006', 'Salsas mixtas', 'Ingrediente', 'gramo', 1000, 'kg', 35.00, 0.03, 'Distribuidor alimentos', 1000],
            ['INS007', 'Pan de sándwich', 'Ingrediente', 'rebanada', 20, 'bolsa', 18.00, 0.03, 'Proveedor de pan', 40],
            ['INS008', 'Jamón', 'Ingrediente', 'gramo', 1000, 'kg', 52.00, 0.03, 'Distribuidor cárnico', 1000],
            ['INS009', 'Salchicha', 'Ingrediente', 'unidad', 20, 'paquete', 38.00, 0.03, 'Distribuidor cárnico', 40],
            ['INS010', 'Papa', 'Ingrediente', 'gramo', 1000, 'kg', 9.00, 0.15, 'Mercado local', 5000],
            ['INS011', 'Aceite de fritura', 'Ingrediente', 'mililitro', 5000, 'bidón 5 L', 82.00, 0.08, 'Distribuidor alimentos', 5000],
            ['INS012', 'Masa de empanada', 'Ingrediente', 'unidad', 20, 'paquete', 28.00, 0.02, 'Proveedor de masas', 40],
            ['INS013', 'Relleno empanada', 'Ingrediente', 'gramo', 1000, 'kg', 42.00, 0.05, 'Cocina central', 2000],
            ['INS014', 'Gaseosa', 'Bebida', 'unidad', 12, 'caja', 72.00, 0.01, 'Distribuidor bebidas', 24],
            ['INS015', 'Tortilla wrap', 'Ingrediente', 'unidad', 12, 'paquete', 30.00, 0.02, 'Distribuidor alimentos', 24],
            ['INS016', 'Pollo', 'Ingrediente', 'gramo', 1000, 'kg', 34.00, 0.06, 'Proveedor avícola', 3000],
            ['INS017', 'Pan hot dog', 'Ingrediente', 'unidad', 12, 'paquete', 22.00, 0.02, 'Proveedor de pan', 24],
            ['INS018', 'Agua embotellada', 'Bebida', 'unidad', 12, 'caja', 42.00, 0.01, 'Distribuidor bebidas', 24],
            ['INS019', 'Postre individual', 'Ingrediente', 'unidad', 12, 'bandeja', 60.00, 0.03, 'Proveedor de postres', 12],
            ['EMP001', 'Caja hamburguesa', 'Empaque', 'unidad', 100, 'paquete', 55.00, 0.00, 'Proveedor de empaques', 100],
            ['EMP002', 'Papel antigrasa', 'Empaque', 'unidad', 500, 'paquete', 65.00, 0.00, 'Proveedor de empaques', 500],
            ['EMP003', 'Bolsa de papel', 'Empaque', 'unidad', 100, 'paquete', 42.00, 0.00, 'Proveedor de empaques', 100],
            ['EMP004', 'Envase para papas', 'Empaque', 'unidad', 100, 'paquete', 48.00, 0.00, 'Proveedor de empaques', 100],
            ['EMP005', 'Servilleta', 'Consumible', 'unidad', 500, 'paquete', 30.00, 0.00, 'Proveedor de limpieza', 500],
            ['EMP006', 'Vaso/tapa', 'Empaque', 'unidad', 100, 'paquete', 70.00, 0.00, 'Proveedor de empaques', 100],
            ['EMP007', 'Caja empanada', 'Empaque', 'unidad', 100, 'paquete', 45.00, 0.00, 'Proveedor de empaques', 100],
            ['CON001', 'Guante desechable', 'Consumible', 'unidad', 100, 'caja', 45.00, 0.00, 'Proveedor de limpieza', 100],
            ['CON002', 'Gas de cocina', 'Producción', 'gramo', 10000, 'garrafa equivalente', 30.00, 0.00, 'Proveedor de gas', 2000],
            ['CON003', 'Producto de limpieza', 'Producción', 'mililitro', 5000, 'bidón 5 L', 55.00, 0.00, 'Proveedor de limpieza', 1000],
        ];

        $supplyMap = [];
        foreach ($suppliesData as [$code, $name, $cat, $baseUnit, $content, $purchUnit, $price, $waste, $supplier, $minStock]) {
            $supply = Supply::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'category' => $cat,
                    'base_unit' => $baseUnit,
                    'content_per_purchase' => $content,
                    'purchase_unit' => $purchUnit,
                    'last_purchase_price' => $price,
                    'standard_waste_pct' => $waste,
                    'main_supplier' => $supplier,
                    'min_stock' => $minStock,
                    'is_active' => true,
                ]
            );
            $supplyMap[$code] = $supply->id;
        }

        // 3. Maestro de los 10 Productos (Hoja 2_Maestro_Productos y 12_Costos_Productos)
        $productsData = [
            ['DN01', 'Hamburguesa', 35.00, 1.50, 0.50, 'Balanza para carne (120g) y dosificador para salsa (20g)'],
            ['DN02', 'Sándwich', 25.00, 1.50, 0.50, 'Balanza para jamón (60g) y queso (20g)'],
            ['DN03', 'Salchipapa', 25.00, 1.50, 0.50, 'Canastilla medidora para papa (250g) y 1.5 salchichas'],
            ['DN04', 'Empanada', 12.00, 1.50, 0.50, 'Cuchara medidora para relleno (80g)'],
            ['DN05', 'Gaseosa', 10.00, 1.50, 0.50, 'Conteo individual cerrado'],
            ['DN06', 'Wrap de pollo', 30.00, 1.50, 0.50, 'Balanza para pollo (120g) y dosificador de salsa'],
            ['DN07', 'Hot dog', 20.00, 1.50, 0.50, '1 salchicha por pan y dosificador de salsa (20g)'],
            ['DN08', 'Papas fritas', 18.00, 1.50, 0.50, 'Canastilla o recipiente medidor (250g)'],
            ['DN09', 'Agua embotellada', 8.00, 1.50, 0.50, 'Conteo individual cerrado'],
            ['DN10', 'Postre', 15.00, 1.50, 0.50, 'Porción individual cerrada'],
        ];

        $productMap = [];
        foreach ($productsData as [$sku, $name, $price, $labor, $otherVar, $portionControl]) {
            $product = Product::query()->updateOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name,
                    'selling_price' => $price,
                    'direct_labor_cost' => $labor,
                    'other_variable_cost' => $otherVar,
                    'portion_control' => $portionControl,
                    'is_active' => true,
                ]
            );
            $productMap[$sku] = $product->id;
        }

        // 4. Recetas BOM completas (66 líneas exactas de Hoja 11_Recetas_BOM)
        $bomsData = [
            // DN01 Hamburguesa
            ['DN01', 'INS001', 1],
            ['DN01', 'INS002', 120],
            ['DN01', 'INS003', 25],
            ['DN01', 'INS004', 15],
            ['DN01', 'INS005', 20],
            ['DN01', 'INS006', 20],
            ['DN01', 'EMP001', 1],
            ['DN01', 'EMP002', 1],
            ['DN01', 'EMP005', 2],
            ['DN01', 'CON001', 0.05],
            ['DN01', 'CON002', 15],
            // DN02 Sándwich
            ['DN02', 'INS007', 2],
            ['DN02', 'INS008', 60],
            ['DN02', 'INS003', 20],
            ['DN02', 'INS004', 15],
            ['DN02', 'INS005', 20],
            ['DN02', 'INS006', 15],
            ['DN02', 'EMP003', 1],
            ['DN02', 'EMP002', 1],
            ['DN02', 'EMP005', 2],
            ['DN02', 'CON001', 0.04],
            // DN03 Salchipapa
            ['DN03', 'INS009', 1.5],
            ['DN03', 'INS010', 250],
            ['DN03', 'INS011', 30],
            ['DN03', 'INS006', 20],
            ['DN03', 'EMP004', 1],
            ['DN03', 'EMP005', 2],
            ['DN03', 'CON001', 0.04],
            ['DN03', 'CON002', 20],
            // DN04 Empanada
            ['DN04', 'INS012', 1],
            ['DN04', 'INS013', 80],
            ['DN04', 'INS011', 15],
            ['DN04', 'EMP007', 1],
            ['DN04', 'EMP005', 1],
            ['DN04', 'CON001', 0.03],
            ['DN04', 'CON002', 10],
            // DN05 Gaseosa
            ['DN05', 'INS014', 1],
            ['DN05', 'EMP005', 1],
            // DN06 Wrap de pollo
            ['DN06', 'INS015', 1],
            ['DN06', 'INS016', 120],
            ['DN06', 'INS004', 20],
            ['DN06', 'INS005', 20],
            ['DN06', 'INS006', 20],
            ['DN06', 'EMP003', 1],
            ['DN06', 'EMP002', 1],
            ['DN06', 'EMP005', 2],
            ['DN06', 'CON001', 0.05],
            ['DN06', 'CON002', 15],
            // DN07 Hot dog
            ['DN07', 'INS017', 1],
            ['DN07', 'INS009', 1],
            ['DN07', 'INS006', 20],
            ['DN07', 'EMP003', 1],
            ['DN07', 'EMP002', 1],
            ['DN07', 'EMP005', 2],
            ['DN07', 'CON001', 0.03],
            ['DN07', 'CON002', 10],
            // DN08 Papas fritas
            ['DN08', 'INS010', 250],
            ['DN08', 'INS011', 30],
            ['DN08', 'EMP004', 1],
            ['DN08', 'EMP005', 2],
            ['DN08', 'CON001', 0.03],
            ['DN08', 'CON002', 15],
            // DN09 Agua embotellada
            ['DN09', 'INS018', 1],
            ['DN09', 'EMP005', 1],
            // DN10 Postre
            ['DN10', 'INS019', 1],
            ['DN10', 'EMP005', 1],
        ];

        foreach ($bomsData as [$sku, $supplyCode, $qty]) {
            ProductBom::query()->updateOrCreate(
                [
                    'product_id' => $productMap[$sku],
                    'supply_id' => $supplyMap[$supplyCode],
                ],
                [
                    'qty_per_unit' => $qty,
                    'recipe_version' => 'V1',
                ]
            );
        }

        // Recalcular costos maestros de los 10 productos
        CostingService::recalculateAllProducts();

        // 5. Registro de Compras iniciales (Hoja 10_Registro_Compras)
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $purchasesData = [
            ['C-0001', $yesterday, 'Proveedor de pan', 'F-001', 'INS001', 3, 'paquete', 24.00, 0, 5.00, 0, PaymentMethod::TRANSFERENCIA->value, 'Administrador'],
            ['C-0002', $yesterday, 'Carnicería', 'F-002', 'INS002', 5, 'kg', 48.00, 0, 8.00, 0, PaymentMethod::EFECTIVO->value, 'Administrador'],
            ['C-0003', $yesterday, 'Proveedor de empaques', 'F-003', 'EMP001', 2, 'paquete', 55.00, 0, 10.00, 0, PaymentMethod::QR->value, 'Administrador'],
        ];

        foreach ($purchasesData as [$pCode, $pDate, $supplier, $inv, $sCode, $qty, $pUnit, $uPrice, $disc, $freight, $tax, $payMethod, $resp]) {
            Purchase::query()->updateOrCreate(
                ['purchase_code' => $pCode],
                [
                    'purchase_date' => $pDate,
                    'supplier' => $supplier,
                    'invoice_number' => $inv,
                    'supply_id' => $supplyMap[$sCode],
                    'qty' => $qty,
                    'purchase_unit' => $pUnit,
                    'unit_price' => $uPrice,
                    'discount' => $disc,
                    'freight' => $freight,
                    'non_recoverable_tax' => $tax,
                    'payment_method' => $payMethod,
                    'responsible' => $resp,
                    'update_supply_cost' => true,
                ]
            );
        }

        // 6. Turno 1 (Día anterior - Ejemplo conciliado perfecto en VERDE en Sopocachi)
        $shift1 = Shift::query()->updateOrCreate(
            ['shift_code' => 'DN-M01-001'],
            [
                'shift_template_id' => $templateMap['TUR-01'],
                'operational_location_id' => $locationMap['Sopocachi'],
                'shift_date' => $yesterday,
                'unit_code' => 'DN-M01',
                'location' => 'Sopocachi',
                'operator_name' => 'Carlos Mendoza',
                'start_time' => '11:00:00',
                'close_time' => '15:30:00',
                'starting_float' => 200.00,
                'operator_accepted_custody' => true,
                'counted_cash' => 236.00,
                'confirmed_qr' => 70.00,
                'status' => ShiftStatus::CLOSED->value,
                'notes' => 'Jornada inicial conciliada en Sopocachi.',
            ]
        );

        ShiftReconciliationService::ensureShiftItemsInitialized($shift1);

        // Cantidades de despacho del Excel Hoja 3_Despacho_Diario (Total 390 uds, Potencial Bs. 7,195)
        // Con conteo final exacto para conciliación física = 0 (37 Hamburguesas restantes, 47 Empanadas restantes)
        $dispatchShift1 = [
            'DN01' => ['open' => 40, 'rep' => 0, 'close' => 37], // 40 - 2 vendidas - 1 merma = 37
            'DN02' => ['open' => 30, 'rep' => 0, 'close' => 30],
            'DN03' => ['open' => 30, 'rep' => 0, 'close' => 30],
            'DN04' => ['open' => 50, 'rep' => 0, 'close' => 47], // 50 - 3 vendidas = 47
            'DN05' => ['open' => 60, 'rep' => 0, 'close' => 60],
            'DN06' => ['open' => 30, 'rep' => 0, 'close' => 30],
            'DN07' => ['open' => 35, 'rep' => 0, 'close' => 35],
            'DN08' => ['open' => 40, 'rep' => 0, 'close' => 40],
            'DN09' => ['open' => 50, 'rep' => 0, 'close' => 50],
            'DN10' => ['open' => 25, 'rep' => 0, 'close' => 25],
        ];

        foreach ($dispatchShift1 as $sku => $counts) {
            ShiftItem::query()
                ->where('shift_id', $shift1->id)
                ->where('product_id', $productMap[$sku])
                ->update([
                    'opening_qty' => $counts['open'],
                    'replenish_qty' => $counts['rep'],
                    'closing_qty' => $counts['close'],
                ]);
        }

        // Ventas de ejemplo del Excel Hoja 4_Registro_Ventas (T001 y T002)
        Sale::query()->updateOrCreate(
            ['transaction_code' => 'T001'],
            [
                'shift_id' => $shift1->id,
                'sale_date' => $yesterday,
                'sale_time' => '11:35:00',
                'product_id' => $productMap['DN01'],
                'qty' => 2,
                'unit_price' => 35.00,
                'payment_method' => PaymentMethod::QR->value,
            ]
        );

        Sale::query()->updateOrCreate(
            ['transaction_code' => 'T002'],
            [
                'shift_id' => $shift1->id,
                'sale_date' => $yesterday,
                'sale_time' => '11:42:00',
                'product_id' => $productMap['DN04'],
                'qty' => 3,
                'unit_price' => 12.00,
                'payment_method' => PaymentMethod::EFECTIVO->value,
            ]
        );

        // Excepción de ejemplo del Excel Hoja 5_Registro_Excepciones (E001)
        ShiftException::query()->updateOrCreate(
            ['exception_code' => 'E001'],
            [
                'shift_id' => $shift1->id,
                'exception_date' => $yesterday,
                'exception_time' => '13:00:00',
                'product_id' => $productMap['DN01'],
                'qty' => 1,
                'code' => ExceptionCode::W02->value,
                'reason' => 'Error de preparación',
                'unit_price' => 35.00,
            ]
        );

        ShiftReconciliationService::reconcile($shift1);

        // 7. Turno 2 (Hoy - Ejemplo con alto volumen de ventas)
        $shift2 = Shift::query()->updateOrCreate(
            ['shift_code' => 'DN-M01-002'],
            [
                'shift_template_id' => $templateMap['TUR-01'],
                'operational_location_id' => $locationMap['San Miguel'],
                'shift_date' => $today,
                'unit_code' => 'DN-M01',
                'location' => 'San Miguel',
                'operator_name' => 'Carlos Mendoza',
                'start_time' => '11:30:00',
                'close_time' => '15:30:00',
                'starting_float' => 200.00,
                'operator_accepted_custody' => true,
                'counted_cash' => 1480.00, // 1280 ventas efectivo + 200 fondo
                'confirmed_qr' => 1650.00, // 1650 ventas QR
                'status' => ShiftStatus::CLOSED->value,
                'notes' => 'Jornada completa en San Miguel conciliada.',
            ]
        );

        ShiftReconciliationService::ensureShiftItemsInitialized($shift2);

        $dispatchShift2 = [
            'DN01' => ['open' => 40, 'rep' => 0, 'close' => 6],  // 31 vendidos + 2 merma + 1 staff + 6 cierre = 40
            'DN02' => ['open' => 30, 'rep' => 0, 'close' => 3],  // 26 vendidos + 1 merma + 3 cierre = 30
            'DN03' => ['open' => 30, 'rep' => 0, 'close' => 2],  // 27 vendidos + 1 merma + 2 cierre = 30
            'DN04' => ['open' => 50, 'rep' => 0, 'close' => 5],  // 43 vendidos + 2 cortesía + 5 cierre = 50
            'DN05' => ['open' => 60, 'rep' => 0, 'close' => 8],  // 51 vendidos + 1 staff + 8 cierre = 60
            'DN06' => ['open' => 20, 'rep' => 0, 'close' => 20],
            'DN07' => ['open' => 20, 'rep' => 0, 'close' => 20],
            'DN08' => ['open' => 20, 'rep' => 0, 'close' => 20],
            'DN09' => ['open' => 30, 'rep' => 0, 'close' => 30],
            'DN10' => ['open' => 15, 'rep' => 0, 'close' => 15],
        ];

        foreach ($dispatchShift2 as $sku => $counts) {
            ShiftItem::query()
                ->where('shift_id', $shift2->id)
                ->where('product_id', $productMap[$sku])
                ->update([
                    'opening_qty' => $counts['open'],
                    'replenish_qty' => $counts['rep'],
                    'closing_qty' => $counts['close'],
                ]);
        }

        // Ventas de Turno 2 que suman exactamente Bs. 1,650 QR y Bs. 1,280 Efectivo (= Bs. 2,930)
        // DN01: 31 * 35 = 1085 (30 QR = 1050, 1 Efectivo = 35)
        // DN02: 26 * 25 = 650 (24 QR = 600, 2 Efectivo = 50) -> QR Total = 1050 + 600 = 1650!
        // DN03: 27 * 25 = 675 (Efectivo = 675)
        // DN04: 43 * 12 = 516 (Efectivo: 510? Wait: 35 + 50 + 675 + 516 = 1276, + DN05 51 * 10 = 510 -> Total = 3436)
        // Ajustemos métodos de pago para que coincida 100% con el efectivo y QR contado del turno 2:
        $salesShift2 = [
            ['T003', '12:05:00', 'DN01', 20, PaymentMethod::QR->value],       // 700 QR
            ['T004', '12:25:00', 'DN01', 11, PaymentMethod::EFECTIVO->value], // 385 Efectivo
            ['T005', '12:40:00', 'DN02', 26, PaymentMethod::QR->value],       // 650 QR
            ['T006', '13:10:00', 'DN03', 12, PaymentMethod::QR->value],       // 300 QR -> Total QR = 1,650 Bs.
            ['T007', '13:30:00', 'DN03', 15, PaymentMethod::EFECTIVO->value], // 375 Efectivo
            ['T008', '13:55:00', 'DN04', 43, PaymentMethod::EFECTIVO->value], // 516 Efectivo
            ['T009', '14:20:00', 'DN05', 51, PaymentMethod::EFECTIVO->value], // 510 Efectivo -> Total Efectivo = 1,786 Bs.
        ];

        foreach ($salesShift2 as [$tCode, $time, $sku, $qty, $method]) {
            Sale::query()->updateOrCreate(
                ['transaction_code' => $tCode],
                [
                    'shift_id' => $shift2->id,
                    'sale_date' => $today,
                    'sale_time' => $time,
                    'product_id' => $productMap[$sku],
                    'qty' => $qty,
                    'payment_method' => $method,
                ]
            );
        }

        // Actualizamos el efectivo contado del Turno 2 a 1986 (1786 ventas efectivo + 200 fondo inicial) para que concilie en VERDE
        $shift2->update([
            'counted_cash' => 1986.00,
            'confirmed_qr' => 1650.00,
        ]);

        // Excepciones del Turno 2
        $exceptionsShift2 = [
            ['E002', '12:50:00', 'DN01', 2, ExceptionCode::W02->value, 'Error de cocción en hora pico'],
            ['E003', '14:30:00', 'DN01', 1, ExceptionCode::S01->value, 'Almuerzo operador'],
            ['E004', '13:15:00', 'DN02', 1, ExceptionCode::W03->value, 'Reposición a cliente'],
            ['E005', '13:40:00', 'DN03', 1, ExceptionCode::W01->value, 'Porción caída'],
            ['E006', '14:00:00', 'DN04', 2, ExceptionCode::C01->value, 'Degustación autorizada gerencia'],
            ['E007', '14:35:00', 'DN05', 1, ExceptionCode::S01->value, 'Bebida personal turno'],
        ];

        foreach ($exceptionsShift2 as [$eCode, $time, $sku, $qty, $code, $reason]) {
            ShiftException::query()->updateOrCreate(
                ['exception_code' => $eCode],
                [
                    'shift_id' => $shift2->id,
                    'exception_date' => $today,
                    'exception_time' => $time,
                    'product_id' => $productMap[$sku],
                    'qty' => $qty,
                    'code' => $code,
                    'reason' => $reason,
                ]
            );
        }

        ShiftReconciliationService::reconcile($shift2);

        // 8. Egresos Operativos de ejemplo (Hoja 13_Egresos_Operativos)
        $expensesData = [
            ['EGR-001', $yesterday, $shift1->id, 'DN-M01', 'Sopocachi', ExpenseCategory::COMBUSTIBLE->value, 'Carga de combustible generador y traslado', 'Estación de Servicio Volcán', 'FAC-8821', 65.00, PaymentMethod::EFECTIVO->value],
            ['EGR-002', $today, $shift2->id, 'DN-M01', 'San Miguel', ExpenseCategory::PERMISOS->value, 'Tasa municipal diaria de parada móvil', 'GAMLP', 'REC-104', 35.00, PaymentMethod::QR->value],
        ];

        foreach ($expensesData as [$code, $date, $sId, $unit, $loc, $cat, $desc, $sup, $doc, $amt, $pay]) {
            Expense::query()->updateOrCreate(
                ['expense_code' => $code],
                [
                    'expense_date' => $date,
                    'shift_id' => $sId,
                    'unit_code' => $unit,
                    'location' => $loc,
                    'category' => $cat,
                    'description' => $desc,
                    'supplier' => $sup,
                    'document_ref' => $doc,
                    'amount' => $amt,
                    'payment_method' => $pay,
                    'responsible' => 'Administrador',
                ]
            );
        }
    }
}
