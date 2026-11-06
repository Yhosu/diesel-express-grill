# DIESEL EXPRESS GRILL — Documentación de Contexto, Arquitectura y Reglas de Negocio

> **Propósito de este documento:** Este archivo contiene **todo el contexto funcional, técnico, matemático y arquitectónico** del proyecto **Diesel Express Grill** (`d:\xampp8.2\htdocs\personal-projects\diesel-express-grill`). Fue diseñado para que cualquier desarrollador o asistente de IA que abra este proyecto tenga el 100% del contexto sin necesidad de revisar conversaciones previas.

---

## 1. Contexto de Negocio y Objetivo del Sistema

**Diesel Express Grill** es el sistema de control operativo, punto de venta móvil (POS), costeo por recetas (BOM), control de inventario físico y conciliación financiera por turno para las unidades móviles de **Diesel Nacional** (ej. unidad `DN-M01` operando en ubicaciones como *Sopocachi*, *San Miguel*, *Calacoto*, *Centro*, *Miraflores*, *Obrajes*).

### Documentos Fuente (Ubicados en `docs/`)
Toda la lógica del sistema proviene del documento funcional y los 6 libros de Excel maestros almacenados en la carpeta [`docs/`](./docs):
1. `docs/Sitema Venta Diesel EXPRESS GRIL_ENG_ESP.docx`: Especificación bilingüe del modelo de responsabilidad del operador, ecuaciones de control físico/financiero, códigos de excepción y gobernanza de turnos.
2. `docs/Diesel_Nacional_Control_10_Productos_con_Compras_Costos_ES.xlsx`: Libro maestro de 15 hojas con las fórmulas exactas de costos, recetas BOM de los 10 productos, compras de insumos, egresos operativos, despacho, ventas POS, mermas, conciliación y KPIs ejecutivos.
3. `docs/Diesel_Express_Grill_10_Product_Control_System_EN.xlsx` / `_ES.xlsx`: Libros base de operación de los 10 productos.
4. `docs/Diesel_Nacional_Bom_2025 (1).xlsx`: Recetario original y costeo de insumos.
5. `docs/flujo de proceso de venta (1).xlsx`: Flujo operativo desde despacho en cocina central hasta cierre y arqueo de caja.

---

## 2. Ecuaciones y Reglas de Negocio Innegociables

### 2.1. Ecuación de Balance Físico de Inventario (Por Producto / SKU en cada Turno)
Para cada uno de los **10 productos (`DN01` a `DN10`)** despachados a la unidad móvil en un turno:

$$\text{Disponible} = \text{Inventario Inicial (Opening)} + \text{Reposición (Replenish)}$$

$$\text{Diferencia Física (Unidades)} = \text{Disponible} - (\text{Vendido POS} + \text{Mermas} + \text{Cortesías} + \text{Comida Personal} + \text{Conteo Final})$$

$$\text{Valor Diferencia Inventario (Bs.)} = \text{Diferencia Física (Unidades)} \times \text{Precio de Venta Unitario}$$

* Si $\text{Diferencia Física} = 0$, el inventario cuadra exactamente.
* Cualquier unidad faltante no justificada con un código de excepción (`W01`, `W02`, `W03`, `C01`, `S01`) genera una diferencia valorizada a **precio de venta retail**.

### 2.2. Códigos Oficiales de Excepción (`App\Enums\ExceptionCode`)
* **`W01` — Caída / Accidente Operativo:** Suma a `waste_qty` (Merma).
* **`W02` — Error de Cocción / Quemado:** Suma a `waste_qty` (Merma).
* **`W03` — Producto Dañado / Empaque Roto:** Suma a `waste_qty` (Merma).
* **`C01` — Cortesía Autorizada Cliente:** Suma a `comp_qty` (Cortesía).
* **`S01` — Comida de Personal Autorizada:** Suma a `staff_qty` (Personal).

### 2.3. Ecuaciones de Conciliación Financiera (Efectivo y QR por Turno)
* **Efectivo Real Generado en el Turno:**
  $$\text{Efectivo Real} = \text{Efectivo Físico Contado al Cierre} - \text{Fondo Inicial (Float)}$$
* **Diferencia de Efectivo (`cash_variance`):**
  $$\text{Diferencia Efectivo} = \text{Efectivo Real} - \text{Ventas Efectivo Registradas en POS}$$
* **Diferencia de Pagos QR (`qr_variance`):**
  $$\text{Diferencia QR} = \text{Pagos QR Confirmados en Extracto Bancario} - \text{Ventas QR Registradas en POS}$$

### 2.4. Regla del Semáforo de Auditoría (`App\Enums\ReconciliationSemaphore`)
* **`VERDE` (`Exacto / Conciliado`):** $|\text{Diferencia Efectivo}| < 0.01$ **y** $|\text{Diferencia QR}| < 0.01$ **y** $|\text{Valor Diferencia Inventario}| < 0.01$.
* **`AMARILLO` (`Alerta Menor <= 5 Bs.`):** Sin descuadre de inventario ($0.00$), pero con diferencia financiera menor ($0.01 \le |\text{Dif. Financiera}| \le 5.00\text{ Bs.}$).
* **`ROJO` (`Descuadre / Auditar`):** Cualquier diferencia de inventario físico ($\ne 0$) o diferencia financiera $> 5.00\text{ Bs.}$.

### 2.5. Motor de Costeo de Insumos y Recetas BOM (`App\Services\CostingService`)
1. **Costo Base por Unidad de Receta del Insumo:**
   $$\text{base\_unit\_cost} = \frac{\text{package\_price}}{\text{net\_yield\_qty}}$$
2. **Costo Ajustado por Merma Estándar del Insumo:**
   $$\text{adjusted\_unit\_cost} = \frac{\text{base\_unit\_cost}}{1 - \text{std\_waste\_pct}}$$
3. **Propagación Automática Compras $\rightarrow$ Insumos $\rightarrow$ Recetas BOM $\rightarrow$ Producto:**
   * Cuando se registra una compra en `purchases` (o se edita el precio de un insumo en `supplies`), `CostingService::applyPurchaseToSupply()` actualiza el `last_purchase_price` y `package_price` del insumo, recalcula su `adjusted_unit_cost`, actualiza todas las filas de `product_boms` donde participa ese insumo (`line_cost = qty_per_unit * adjusted_unit_cost`), y recalcula automáticamente el **Costo Unitario Maestro (`master_unit_cost`)** y el **% Margen de Contribución (`contribution_margin_pct`)** de cada producto terminado.

---

## 3. Stack Tecnológico y Convenciones de Código (Laravel 12 + Filament v4)

* **Framework:** Laravel 12 (`PHP 8.2+`, `declare(strict_types=1);` en todos los archivos).
* **Panel Administrativo:** Filament v4 (`filament/filament: ^4.0`) configurado en `app/Providers/Filament/YhosuPanelProvider.php` en la ruta `/admin` con modo SPA (`->spa()`) y ancho completo (`->maxContentWidth('full')`).
* **Base de Datos:** MySQL (`DB_DATABASE=diesel_express_grill`, puerto `3306`).
* **Binario PHP en este entorno:** `d:\xampp8.2\php\php.exe`.

### Convenciones Clave de Filament v4 en este Proyecto
> **IMPORTANTE:** Filament v4 tiene diferencias de namespaces respecto a Filament v3. Respeta siempre estas importaciones cuando agregues o edites recursos:
* **Schemas en lugar de Forms:** El método `form()` de los Resources recibe y retorna `Filament\Schemas\Schema`:
  ```php
  use Filament\Schemas\Schema;
  public static function form(Schema $schema): Schema
  ```
* **Componentes de Layout (`Section`, `Grid`):** Viven en `Filament\Schemas\Components\Section` y `Filament\Schemas\Components\Grid`.
* **Utilidades Reactivas (`Get`, `Set`):** Viven en `Filament\Schemas\Components\Utilities\Get` y `Filament\Schemas\Components\Utilities\Set`.
* **Acciones de Tabla (`EditAction`, `DeleteAction`, `CreateAction`, `BulkActionGroup`, `DeleteBulkAction`):** Viven todas en `Filament\Actions\*` (no en `Filament\Tables\Actions\*`).
* **Propiedades Estáticas de Navegación en Resources:**
  * `protected static string|\BackedEnum|null $navigationIcon`
  * `protected static string|\UnitEnum|null $navigationGroup`
* **Filtros Globales de Dashboard:**
  * `App\Filament\Pages\Dashboard` usa `Filament\Pages\Dashboard\Concerns\HasFiltersForm` y define `public function filtersForm(Schema $schema): Schema`.
  * Los Widgets del Dashboard usan `Filament\Widgets\Concerns\InteractsWithPageFilters` y leen `$this->pageFilters['startDate']`, `$this->pageFilters['endDate']`, `$this->pageFilters['location']`, `$this->pageFilters['shift_id']`.

---

## 4. Estructura Detallada de Archivos Creados Paso a Paso

### Paso 1: Base de Datos y Migración Unificada
* [`database/migrations/2026_11_06_000001_create_express_grill_tables.php`](./database/migrations/2026_11_06_000001_create_express_grill_tables.php):
  Crea las 9 tablas del dominio con índices compuestos para reportes por fecha y llaves foráneas estrictas:
  1. `supplies`: 29 insumos y empaques (`code`, `name`, `category`, `purchase_unit`, `package_price`, `net_yield_qty`, `recipe_unit`, `base_unit_cost`, `std_waste_pct`, `adjusted_unit_cost`, `last_purchase_price`, `supplier`).
  2. `products`: 10 productos del menú (`sku`, `name`, `category`, `portion_size`, `unit`, `selling_price`, `manual_cost`, `bom_cost`, `master_unit_cost`, `contribution_margin_pct`, `par_stock`, `reorder_point`, `shelf_life_hours`, `prep_station`).
  3. `product_boms`: 66 líneas de receta BOM (`product_id`, `supply_id`, `component_type`, `qty_per_unit`, `recipe_unit`, `waste_pct`, `unit_cost_snapshot`, `line_cost`).
  4. `purchases`: Compras de insumos por fecha (`purchase_code`, `purchase_date`, `supplier`, `invoice_number`, `supply_id`, `qty_packages`, `package_price`, `total_cost`, `responsible`, `notes`).
  5. `shifts`: Turnos móviles diarios (`shift_code`, `shift_date`, `unit_code`, `location`, `operator_name`, `starting_float`, `counted_cash`, `confirmed_qr`, `expected_cash_sales`, `expected_qr_sales`, `actual_cash_sales`, `cash_variance`, `qr_variance`, `total_financial_variance`, `cash_semaphore`, `qr_semaphore`, `total_semaphore`, `total_dispatched_qty`, `total_sold_qty`, `total_waste_qty`, `inventory_variance_value`, `waste_pct`, `total_cogs`, `gross_contribution`, `status`).
  6. `shift_items`: Planilla física de los 10 productos por turno (`shift_id`, `product_id`, `unit_price`, `unit_cost`, `opening_qty`, `replenish_qty`, `available_qty`, `sales_potential`, `sold_qty`, `waste_qty`, `comp_qty`, `staff_qty`, `closing_qty`, `variance_qty`, `variance_value`).
  7. `sales`: Transacciones de venta POS (`transaction_code`, `shift_id`, `sale_date`, `sale_time`, `product_id`, `qty`, `unit_price`, `unit_cost`, `payment_method`, `sales_value`, `contribution_value`).
  8. `exceptions`: Registro de mermas, cortesías y comida de personal (`exception_code`, `shift_id`, `exception_date`, `exception_time`, `product_id`, `qty`, `code`, `reason`, `unit_price`, `retail_value`).
  9. `expenses`: Egresos operativos fuera de receta (`expense_code`, `expense_date`, `shift_id`, `unit_code`, `location`, `category`, `description`, `supplier`, `document_ref`, `amount`, `payment_method`, `responsible`).

### Paso 2: Enums del Dominio (`app/Enums/`)
* [`app/Enums/SupplyCategory.php`](./app/Enums/SupplyCategory.php): Categorías de insumos (`Carnes y Embutidos`, `Panadería`, `Quesos y Lácteos`, `Vegetales`, `Papas y Guarniciones`, `Salsas e Insumos`, `Aceites y Grasas`, `Empaques y Desechables`).
* [`app/Enums/PaymentMethod.php`](./app/Enums/PaymentMethod.php): Métodos de pago (`Efectivo`, `QR`, `Transferencia`). Incluye `PaymentMethod::posOptions()` para ventas POS (`Efectivo` y `QR`).
* [`app/Enums/ExceptionCode.php`](./app/Enums/ExceptionCode.php): Códigos `W01`, `W02`, `W03`, `C01`, `S01` con colores, iconos y método `isWaste()`.
* [`app/Enums/ReconciliationSemaphore.php`](./app/Enums/ReconciliationSemaphore.php): Estados `VERDE`, `AMARILLO`, `ROJO` con colores e iconos para auditoría visual inmediata.
* [`app/Enums/ShiftStatus.php`](./app/Enums/ShiftStatus.php): Estados `open` (`Abierto / En Operación`) y `closed` (`Cerrado y Conciliado`).
* [`app/Enums/ExpenseCategory.php`](./app/Enums/ExpenseCategory.php): Categorías de gastos operativos (`Combustible / GLP`, `Transporte y Logística`, `Hielo y Refrigeración`, `Limpieza y Sanitización`, `Jornales / Personal Extra`, `Permisos / Municipal`, `Mantenimiento`, `Otros Gastos`).

### Paso 3: Servicios de Negocio (`app/Services/`)
* [`app/Services/CostingService.php`](./app/Services/CostingService.php):
  * `calculateSupplyUnitCosts()`: Calcula `base_unit_cost` y `adjusted_unit_cost`.
  * `syncSupplyToBoms()`: Actualiza todas las líneas BOM cuando cambia el costo de un insumo.
  * `recalculateProductCost()`: Suma las líneas BOM de un producto y actualiza `bom_cost`, `master_unit_cost` y `contribution_margin_pct`.
  * `applyPurchaseToSupply()`: Propaga el precio de compra desde `Purchase` hacia `Supply` y hacia todos los productos.
* [`app/Services/ShiftReconciliationService.php`](./app/Services/ShiftReconciliationService.php):
  * `ensureShiftItemsInitialized()`: Crea automáticamente las 10 filas de `shift_items` (`DN01`..`DN10`) al crear un turno (usando `ShiftItem::withoutEvents` para evitar recursión).
  * `reconcile()`: Recalcula en una sola pasada todas las ventas, mermas, conteos físicos, diferencias de efectivo/QR y semáforos del turno.

### Paso 4: Modelos Eloquent (`app/Models/`)
* [`app/Models/Supply.php`](./app/Models/Supply.php)
* [`app/Models/Product.php`](./app/Models/Product.php)
* [`app/Models/ProductBom.php`](./app/Models/ProductBom.php)
* [`app/Models/Purchase.php`](./app/Models/Purchase.php)
* [`app/Models/Shift.php`](./app/Models/Shift.php)
* [`app/Models/ShiftItem.php`](./app/Models/ShiftItem.php)
* [`app/Models/Sale.php`](./app/Models/Sale.php)
* [`app/Models/ShiftException.php`](./app/Models/ShiftException.php)
* [`app/Models/Expense.php`](./app/Models/Expense.php)

### Paso 5: Seeder con Toda la Data Inicial del Excel (`database/seeders/DatabaseSeeder.php`)
* [`database/seeders/DatabaseSeeder.php`](./database/seeders/DatabaseSeeder.php):
  * Crea el usuario administrador (`admin@dieselexpress.bo` / `password`).
  * Carga los **29 insumos** (`INS001` a `CON003`) con sus rendimientos netos y mermas estándar.
  * Carga los **10 productos** (`DN01` a `DN10`) con sus precios de venta, par stock, puntos de reorden y estaciones de preparación.
  * Carga las **66 líneas de receta BOM** vinculando cada producto con sus insumos y empaques.
  * Carga las **3 compras iniciales** (`C-0001`, `C-0002`, `C-0003` por **Bs. 445.00**).
  * Carga los **2 egresos operativos** (`EGR-001` GLP y `EGR-002` Transporte por **Bs. 100.00**).
  * Carga **2 turnos completos y conciliados (`VERDE`)**:
    * **`DN-M01-001`** (`2026-11-01`, *Sopocachi*, Operador: *Carlos Méndez*): 5 transacciones POS (`T001`..`T005`) por **Bs. 106.00** (`Bs. 36.00` Efectivo + `Bs. 70.00` QR), 2 excepciones (`E001` merma `W02` de `DN03`, `E002` comida personal `S01` de `DN10`), Contribución Bruta **Bs. 55.19**.
    * **`DN-M01-002`** (`2026-11-06`, *San Miguel*, Operador: *Rodrigo Vargas*): Despacho completo de 165 unidades, 4 transacciones consolidadas POS (`T006`..`T009`) por **Bs. 3,436.00** (`Bs. 1,786.00` Efectivo + `Bs. 1,650.00` QR), Contribución Bruta **Bs. 1,743.82**.

### Paso 6: Recursos y Páginas de Filament v4 (`app/Filament/`)
1. **Grupo `1. Operación Diaria Móvil`:**
   * [`ShiftResource.php`](./app/Filament/Resources/Shifts/ShiftResource.php): Gestión integral de turnos diarios, despacho, conteo físico de los 10 productos y conciliación financiera.
     * [`EditShift.php`](./app/Filament/Resources/Shifts/Pages/EditShift.php): Incluye 3 botones de acción rápida en cabecera: **Registrar Venta POS**, **Registrar Merma / Cortesía** y **Recalcular Conciliación**.
     * [`SalesRelationManager.php`](./app/Filament/Resources/Shifts/RelationManagers/SalesRelationManager.php): Pestaña de ventas POS dentro del turno.
     * [`ExceptionsRelationManager.php`](./app/Filament/Resources/Shifts/RelationManagers/ExceptionsRelationManager.php): Pestaña de mermas y excepciones dentro del turno.
   * [`SaleResource.php`](./app/Filament/Resources/Sales/SaleResource.php): Histórico global de ventas POS con filtro por rango de fechas (`Desde` / `Hasta`), turno, producto y método de pago.
   * [`ExceptionResource.php`](./app/Filament/Resources/Exceptions/ExceptionResource.php): Histórico global de mermas, cortesías y comida de personal con filtro por rango de fechas y código (`W01..S01`).
2. **Grupo `2. Costos, Recetas BOM y Compras`:**
   * [`ProductResource.php`](./app/Filament/Resources/Products/ProductResource.php): Catálogo de los 10 productos con `Repeater` interactivo de su Receta BOM y cálculo automático de Costo Maestro y Margen de Contribución.
   * [`SupplyResource.php`](./app/Filament/Resources/Supplies/SupplyResource.php): Catálogo de los 29 insumos y empaques con cálculo en vivo del costo ajustado por merma.
   * [`PurchaseResource.php`](./app/Filament/Resources/Purchases/PurchaseResource.php): Registro de compras de insumos con filtro por fechas y actualización automática de costos BOM.
   * [`ExpenseResource.php`](./app/Filament/Resources/Expenses/ExpenseResource.php): Registro de egresos operativos fuera de receta con filtro por fechas y categoría.
3. **Dashboard y Reportes por Fecha (`app/Filament/Pages/` y `app/Filament/Widgets/`):**
   * [`Dashboard.php`](./app/Filament/Pages/Dashboard.php): Filtros globales reactivos (`Fecha Desde`, `Fecha Hasta`, `Ubicación / Punto Móvil`, `Turno Específico`).
   * [`ExpressGrillStatsOverview.php`](./app/Filament/Widgets/ExpressGrillStatsOverview.php): 8 tarjetas ejecutivas (Ventas POS Efectivo/QR y Ticket Promedio, Costo de Ventas BOM y Sell-Through, Contribución Bruta y `% Margen`, Resultado Operativo Neto y Punto de Equilibrio, Compras de Insumos, Conciliación Caja/QR, Mermas vs Meta `< 3%`, y Semáforo de Auditoría).
   * [`ProductPerformanceTableWidget.php`](./app/Filament/Widgets/ProductPerformanceTableWidget.php): Tabla comparativa de los 10 productos (`DN01`..`DN10`) en el período filtrado.
   * [`ShiftAuditTableWidget.php`](./app/Filament/Widgets/ShiftAuditTableWidget.php): Tabla de auditoría de turnos y semáforos en el período filtrado.

---

## 5. Cómo Ejecutar y Operar el Proyecto

### Credenciales de Acceso (2 Usuarios por Defecto — 1 de cada Rol)
* **URL Local (XAMPP):** `http://localhost/personal-projects/diesel-express-grill/public/admin`
* **1. Rol Super Administrador (`super_admin`):**
  * **Email:** `admin@dieselnacional.com`
  * **Contraseña:** `password`
  * **Permisos:** Acceso total (ver, crear, editar y eliminar en todos los módulos), acceso a todos los reportes y Dashboard, gestión de Usuarios/Roles y acceso exclusivo a la **Tabla de Auditoría (`audit_logs`)**.
* **2. Rol Subadministrador (`sub_admin`):**
  * **Email:** `subadmin@dieselnacional.com`
  * **Contraseña:** `password`
  * **Permisos:** Solo registra y consulta operaciones (creación y visualización; **sin permiso de edición ni eliminación**), y tiene acceso completo a los **Reportes y Dashboard** (sin acceso a la Tabla de Auditoría ni Gestión de Usuarios).

### Comandos Útiles (Ejecutar en `d:\xampp8.2\htdocs\personal-projects\diesel-express-grill`)
```powershell
# Limpiar cachés de Laravel y Filament
d:\xampp8.2\php\php.exe artisan optimize:clear

# Recrear base de datos y recargar toda la data inicial de los Excel
d:\xampp8.2\php\php.exe artisan migrate:fresh --seed

# Levantar servidor de desarrollo rápido (opcional si no se usa Apache de XAMPP)
d:\xampp8.2\php\php.exe artisan serve --port=8000
```

