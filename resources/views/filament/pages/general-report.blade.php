@php
    $data = $reportData ?? ($this ? $this->getReportData() : []);
    $kpis = $data['kpis'] ?? [];
@endphp

<x-filament-panels::page>
    <style>
        .dn-report-wrap {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            color: #f3f4f6;
            font-family: inherit;
        }
        .dn-card {
            background-color: #212121;
            border: 1px solid rgba(193, 157, 96, 0.35);
            border-radius: 0.75rem;
            padding: 1.25rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.4), 0 2px 4px -2px rgba(0, 0, 0, 0.4);
        }
        .dn-card-sub {
            background-color: #292929;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 0.75rem;
            padding: 1.25rem;
        }
        .dn-header {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            justify-content: space-between;
        }
        @media (min-width: 768px) {
            .dn-header {
                flex-direction: row;
                align-items: center;
            }
        }
        .dn-title-gold {
            color: #C19D60;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 700;
        }
        .dn-btn-gold {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background-color: #C19D60;
            color: #121212;
            font-weight: 700;
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid #C19D60;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .dn-btn-gold:hover {
            background-color: #ffffff;
            border-color: #ffffff;
            color: #121212;
        }
        .dn-btn-dark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #303030;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.15);
            cursor: pointer;
            width: 100%;
            height: 38px;
            transition: all 0.2s;
        }
        .dn-btn-dark:hover {
            border-color: #C19D60;
            color: #C19D60;
        }
        .dn-grid-filters {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        @media (min-width: 768px) {
            .dn-grid-filters {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                align-items: flex-end;
            }
        }
        .dn-input {
            width: 100%;
            margin-top: 0.25rem;
            padding: 0.5rem 0.75rem;
            background-color: #292929;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 0.5rem;
            color: #ffffff;
            font-size: 0.875rem;
            outline: none;
        }
        .dn-input:focus {
            border-color: #C19D60;
            box-shadow: 0 0 0 1px #C19D60;
        }
        .dn-grid-kpis {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 640px) {
            .dn-grid-kpis {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (min-width: 1024px) {
            .dn-grid-kpis {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
        .dn-kpi-card {
            background-color: #292929;
            border: 1px solid rgba(193, 157, 96, 0.25);
            border-radius: 0.75rem;
            padding: 1.15rem;
        }
        .dn-kpi-val {
            font-size: 1.5rem;
            font-weight: 800;
            color: #ffffff;
            margin-top: 0.35rem;
            line-height: 1.2;
        }
        .dn-kpi-val.gold { color: #C19D60; }
        .dn-kpi-val.green { color: #10b981; }
        .dn-kpi-val.red { color: #f43f5e; }
        .dn-kpi-sub {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 0.35rem;
        }
        .dn-row-item {
            background-color: #212121;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            overflow: hidden;
            transition: border-color 0.15s;
        }
        .dn-row-item:hover {
            border-color: rgba(193, 157, 96, 0.4);
        }
        .dn-row-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            text-align: left;
            background: transparent;
            border: none;
            cursor: pointer;
            color: inherit;
        }
        .dn-row-grid-shifts {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
            flex: 1;
        }
        @media (min-width: 768px) {
            .dn-row-grid-shifts {
                grid-template-columns: 1.2fr 1.2fr 1.4fr 1.4fr 1fr;
                align-items: center;
            }
        }
        .dn-row-grid-products {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.5rem;
            flex: 1;
        }
        @media (min-width: 768px) {
            .dn-row-grid-products {
                grid-template-columns: 1.5fr 1fr 1fr 1fr;
                align-items: center;
            }
        }
        .dn-badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            background-color: rgba(193, 157, 96, 0.18);
            color: #C19D60;
            border: 1px solid rgba(193, 157, 96, 0.35);
        }
        .dn-detail-panel {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            background-color: #1a1a1a;
            padding: 0.85rem 1.25rem;
            font-size: 0.785rem;
            color: #d1d5db;
        }
        .dn-detail-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .dn-detail-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        .dn-two-col {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        @media (min-width: 1024px) {
            .dn-two-col {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media print {
            .dn-btn-gold, .dn-grid-filters, .fi-topbar, .fi-sidebar { display: none !important; }
            body, .fi-main { background: #fff !important; color: #000 !important; }
            .dn-card, .dn-card-sub, .dn-kpi-card, .dn-row-item { background: #fff !important; color: #000 !important; border: 1px solid #ccc !important; }
            .dn-kpi-val, .dn-title-gold { color: #000 !important; }
        }
    </style>

    <div class="dn-report-wrap">
        {{-- Cabecera con Identidad Diesel Nacional --}}
        <div class="dn-card">
            <div class="dn-header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <img src="{{ asset('images/logo-diesel-gold.svg') }}" alt="Diesel Nacional" style="height: 42px; width: auto;" />
                    <div>
                        <span class="dn-title-gold">Sistema de Control Operativo y Financiero</span>
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: #ffffff; margin: 0.2rem 0 0 0;">
                            Reporte General Consolidado (Jornadas, Ventas POS, BOM, Compras y Mermas)
                        </h2>
                    </div>
                </div>
                <div>
                    <button type="button" wire:click="exportExcel" wire:loading.attr="disabled" class="dn-btn-gold">
                        <svg wire:loading.remove wire:target="exportExcel" style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span wire:loading wire:target="exportExcel" style="font-size: 0.85rem;">Generando Excel...</span>
                        <span wire:loading.remove wire:target="exportExcel">Exportar a Excel (.xlsx)</span>
                    </button>
                </div>
            </div>

            {{-- Filtros de Período y Ubicación --}}
            <div class="dn-grid-filters">
                <div>
                    <label class="dn-title-gold">Fecha Desde</label>
                    <input type="date" wire:model.live="startDate" class="dn-input" />
                </div>
                <div>
                    <label class="dn-title-gold">Fecha Hasta</label>
                    <input type="date" wire:model.live="endDate" class="dn-input" />
                </div>
                <div>
                    <label class="dn-title-gold">Ubicación Móvil</label>
                    <select wire:model.live="location" class="dn-input">
                        <option value="">Todas las ubicaciones</option>
                        @foreach($data['locations'] as $loc)
                            <option value="{{ $loc }}">{{ $loc }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="button" wire:click="resetFilters" class="dn-btn-dark">
                        Limpiar Filtros
                    </button>
                </div>
            </div>
        </div>

        {{-- Tarjetas Ejecutivas de Resumen (KPIs) --}}
        <div class="dn-grid-kpis">
            <div class="dn-kpi-card">
                <span class="dn-title-gold">1. Ventas POS Totales</span>
                <div class="dn-kpi-val">Bs. {{ number_format($kpis['total_sales'], 2) }}</div>
                <div class="dn-kpi-sub">Efectivo: Bs. {{ number_format($kpis['cash_sales'], 2) }} | QR: Bs. {{ number_format($kpis['qr_sales'], 2) }}</div>
            </div>

            <div class="dn-kpi-card">
                <span class="dn-title-gold">2. Contribución Bruta</span>
                <div class="dn-kpi-val green">Bs. {{ number_format($kpis['total_contribution'], 2) }}</div>
                <div class="dn-kpi-sub">Costo Directo Recetas: Bs. {{ number_format($kpis['total_cogs'], 2) }}</div>
            </div>

            <div class="dn-kpi-card">
                <span class="dn-title-gold">3. Resultado Operativo Neto</span>
                <div class="dn-kpi-val {{ $kpis['operating_profit'] >= 0 ? 'gold' : 'red' }}">
                    Bs. {{ number_format($kpis['operating_profit'], 2) }}
                </div>
                <div class="dn-kpi-sub">Egresos Operativos: Bs. {{ number_format($kpis['total_expenses'], 2) }}</div>
            </div>

            <div class="dn-kpi-card">
                <span class="dn-title-gold">4. Compras y Mermas</span>
                <div class="dn-kpi-val">Bs. {{ number_format($kpis['total_purchases'], 2) }}</div>
                <div class="dn-kpi-sub">Mermas / Excepciones: Bs. {{ number_format($kpis['total_exceptions_retail'], 2) }}</div>
            </div>
        </div>

        {{-- Sección I: Conciliación de Jornadas Móviles con Filas Expandibles --}}
        <div class="dn-card-sub">
            <h3 style="color: #C19D60; font-size: 0.95rem; font-weight: 800; text-transform: uppercase; margin-bottom: 0.75rem; letter-spacing: 0.05em;">
                I. Reporte de Jornadas Diarias y Conciliación (Haga clic en cada fila para ver el desglose)
            </h3>
            <div>
                @forelse($data['shifts'] as $shift)
                    <div x-data="{ open: false }" class="dn-row-item">
                        <button type="button" @click="open = !open" class="dn-row-btn">
                            <div class="dn-row-grid-shifts">
                                <div>
                                    <span style="font-weight: 800; color: #C19D60;">{{ $shift->shift_code }}</span>
                                    <div style="font-size: 0.75rem; color: #9ca3af;">{{ $shift->shift_date?->format('d/m/Y') }}</div>
                                </div>
                                <div>
                                    <span style="font-weight: 700; color: #ffffff;">{{ $shift->location }}</span>
                                    <div style="font-size: 0.75rem; color: #9ca3af;">Unidad: {{ $shift->unit_code }}</div>
                                </div>
                                <div>
                                    <span style="color: #e5e7eb;">{{ $shift->operator_name }}</span>
                                    <div style="font-size: 0.75rem; color: #9ca3af;">Vendidas: {{ $shift->total_sold_qty }} uds.</div>
                                </div>
                                <div>
                                    <span style="font-weight: 800; color: #10b981;">Bs. {{ number_format((float) $shift->total_expected_sales, 2) }}</span>
                                    <div style="font-size: 0.75rem; color: #C19D60;">Contrib.: Bs. {{ number_format((float) $shift->gross_contribution, 2) }}</div>
                                </div>
                                <div>
                                    <span class="dn-badge">
                                        {{ $shift->total_semaphore instanceof \BackedEnum ? $shift->total_semaphore->value : $shift->total_semaphore }}
                                    </span>
                                </div>
                            </div>
                            <span style="color: #C19D60; font-size: 0.75rem; font-weight: 700; margin-left: 0.5rem;" x-text="open ? '▲ Ocultar' : '▼ Detalle'"></span>
                        </button>

                        <div x-show="open" x-collapse class="dn-detail-panel">
                            <div class="dn-detail-grid">
                                <div>
                                    <p><strong style="color: #fff;">Despacho Total:</strong> {{ $shift->total_dispatched_qty }} uds.</p>
                                    <p><strong style="color: #fff;">Unidades Vendidas:</strong> {{ $shift->total_sold_qty }} uds.</p>
                                    <p><strong style="color: #fff;">Mermas Registradas:</strong> {{ $shift->total_waste_qty }} uds.</p>
                                </div>
                                <div>
                                    <p><strong style="color: #fff;">Ventas Efectivo POS:</strong> Bs. {{ number_format((float) $shift->expected_cash_sales, 2) }}</p>
                                    <p><strong style="color: #fff;">Ventas QR POS:</strong> Bs. {{ number_format((float) $shift->expected_qr_sales, 2) }}</p>
                                    <p><strong style="color: #fff;">Fondo Inicial:</strong> Bs. {{ number_format((float) $shift->starting_float, 2) }}</p>
                                </div>
                                <div>
                                    <p><strong style="color: #fff;">Diferencia Efectivo:</strong> Bs. {{ number_format((float) $shift->cash_variance, 2) }}</p>
                                    <p><strong style="color: #fff;">Diferencia QR:</strong> Bs. {{ number_format((float) $shift->qr_variance, 2) }}</p>
                                    <p><strong style="color: #fff;">Diferencia Inventario:</strong> Bs. {{ number_format((float) $shift->inventory_variance_value, 2) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div style="padding: 1.5rem; text-align: center; color: #9ca3af; font-size: 0.875rem;">
                        No existen jornadas registradas en el período seleccionado.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Sección II: Catálogo de Productos y Márgenes BOM --}}
        <div class="dn-card-sub">
            <h3 style="color: #C19D60; font-size: 0.95rem; font-weight: 800; text-transform: uppercase; margin-bottom: 0.75rem; letter-spacing: 0.05em;">
                II. Maestro de Productos, Costos BOM y Margen de Contribución (10 SKUs)
            </h3>
            <div>
                @foreach($data['products'] as $product)
                    <div x-data="{ open: false }" class="dn-row-item">
                        <button type="button" @click="open = !open" class="dn-row-btn">
                            <div class="dn-row-grid-products">
                                <div>
                                    <span style="font-weight: 800; color: #C19D60;">{{ $product->sku }}</span> —
                                    <span style="font-weight: 700; color: #ffffff;">{{ $product->name }}</span>
                                </div>
                                <div>
                                    <span style="color: #9ca3af;">Precio:</span>
                                    <span style="font-weight: 700; color: #ffffff;">Bs. {{ number_format((float) $product->selling_price, 2) }}</span>
                                </div>
                                <div>
                                    <span style="color: #9ca3af;">Costo Maestro:</span>
                                    <span style="font-weight: 700; color: #fbbf24;">Bs. {{ number_format((float) $product->master_unit_cost, 2) }}</span>
                                </div>
                                <div>
                                    <span style="font-weight: 800; color: #10b981;">
                                        Margen: {{ number_format((float) $product->contribution_margin_pct * 100, 1) }}%
                                    </span>
                                </div>
                            </div>
                            <span style="color: #C19D60; font-size: 0.75rem; font-weight: 700; margin-left: 0.5rem;" x-text="open ? '▲' : '▼'"></span>
                        </button>

                        <div x-show="open" x-collapse class="dn-detail-panel">
                            <div style="display: flex; flex-wrap: wrap; gap: 1.5rem;">
                                <div><strong style="color: #fff;">Costo Directo BOM:</strong> Bs. {{ number_format((float) $product->direct_bom_cost, 2) }}</div>
                                <div><strong style="color: #fff;">Contribución Unitaria:</strong> Bs. {{ number_format((float) $product->contribution_amount, 2) }}</div>
                                <div><strong style="color: #fff;">Insumos en Receta:</strong> {{ $product->boms_count }} componentes ({{ $product->portion_control ?: 'Estándar' }})</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Sección III y IV: Compras y Egresos Operativos --}}
        <div class="dn-two-col">
            <div class="dn-card-sub">
                <h3 style="color: #C19D60; font-size: 0.95rem; font-weight: 800; text-transform: uppercase; margin-bottom: 0.75rem; letter-spacing: 0.05em;">
                    III. Registro de Compras de Insumos
                </h3>
                <div>
                    @forelse($data['purchases'] as $purchase)
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0.85rem; background: #212121; border-radius: 0.5rem; margin-bottom: 0.4rem; border: 1px solid rgba(255,255,255,0.06);">
                            <div>
                                <span style="font-weight: 800; color: #ffffff;">{{ $purchase->purchase_code }}</span> —
                                <span style="color: #e5e7eb;">{{ $purchase->supply?->name }}</span>
                                <div style="font-size: 0.75rem; color: #9ca3af;">{{ $purchase->purchase_date?->format('d/m/Y') }} | {{ $purchase->supplier }}</div>
                            </div>
                            <span style="font-weight: 800; color: #C19D60;">Bs. {{ number_format((float) $purchase->total_cost, 2) }}</span>
                        </div>
                    @empty
                        <div style="padding: 1rem; text-align: center; color: #9ca3af; font-size: 0.875rem;">Sin compras en el período.</div>
                    @endforelse
                </div>
            </div>

            <div class="dn-card-sub">
                <h3 style="color: #C19D60; font-size: 0.95rem; font-weight: 800; text-transform: uppercase; margin-bottom: 0.75rem; letter-spacing: 0.05em;">
                    IV. Egresos Operativos
                </h3>
                <div>
                    @forelse($data['expenses'] as $expense)
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0.85rem; background: #212121; border-radius: 0.5rem; margin-bottom: 0.4rem; border: 1px solid rgba(255,255,255,0.06);">
                            <div>
                                <span style="font-weight: 800; color: #ffffff;">{{ $expense->expense_code }}</span> —
                                <span style="color: #e5e7eb;">{{ $expense->description }}</span>
                                <div style="font-size: 0.75rem; color: #9ca3af;">{{ $expense->expense_date?->format('d/m/Y') }} | {{ $expense->location }}</div>
                            </div>
                            <span style="font-weight: 800; color: #f43f5e;">Bs. {{ number_format((float) $expense->amount, 2) }}</span>
                        </div>
                    @empty
                        <div style="padding: 1rem; text-align: center; color: #9ca3af; font-size: 0.875rem;">Sin egresos en el período.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
