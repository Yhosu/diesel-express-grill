<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Shift;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExcelExportService
{
    /**
     * Genera un archivo Excel (.xlsx) profesional y multi-pestaña con el reporte integral de Diesel Express Grill.
     */
    public static function export(array $data, ?string $startDate, ?string $endDate, ?string $location): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Diesel Nacional — Express Grill')
            ->setTitle('Reporte General Consolidado')
            ->setSubject('Control Operativo y Financiero')
            ->setDescription('Auditoría Integral de Jornadas, Ventas POS, Costos BOM, Compras y Egresos.');

        $goldColor = 'C19D60';
        $darkHeader = '212121';

        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $darkHeader],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => $goldColor],
                ],
            ],
        ];

        $titleStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => $goldColor],
                'size' => 14,
            ],
        ];

        // ==========================================
        // HOJA 1: RESUMEN EJECUTIVO Y KPIS
        // ==========================================
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Resumen Ejecutivo');

        $sheet1->setCellValue('A1', 'DIESEL NACIONAL — EXPRESS GRILL');
        $sheet1->getStyle('A1')->applyFromArray($titleStyle);
        $sheet1->setCellValue('A2', 'REPORTE GENERAL CONSOLIDADO DE GESTIÓN Y RENTABILIDAD');
        $sheet1->getStyle('A2')->getFont()->setBold(true)->setSize(11);
        $sheet1->setCellValue('A3', 'Período: ' . ($startDate ?: 'Histórico') . ' al ' . ($endDate ?: date('Y-m-d')) . ' | Ubicación: ' . ($location ?: 'Todas'));
        $sheet1->getStyle('A3')->getFont()->setItalic(true);

        $kpis = $data['kpis'];
        $kpiRows = [
            ['MÉTRICA / INDICADOR CLAVE', 'VALOR CONSOLIDADO', 'DETALLE / OBSERVACIÓN'],
            ['1. Ventas POS Totales', $kpis['total_sales'], 'Efectivo: Bs. ' . number_format($kpis['cash_sales'], 2) . ' | QR: Bs. ' . number_format($kpis['qr_sales'], 2)],
            ['2. Costo Directo Receta BOM', $kpis['total_cogs'], 'Costo acumulado de ingredientes y empaques vendidos'],
            ['3. Contribución Bruta Generada', $kpis['total_contribution'], 'Ventas POS menos Costos BOM Directos'],
            ['4. Margen de Contribución Promedio', $kpis['total_sales'] > 0 ? round(($kpis['total_contribution'] / $kpis['total_sales']) * 100, 1) . '%' : '0%', 'Porcentaje de rentabilidad bruta sobre venta'],
            ['5. Egresos Operativos Totales', $kpis['total_expenses'], 'Combustible, permisos, alquiler, mantenimiento, marketing'],
            ['6. RESULTADO OPERATIVO NETO', $kpis['operating_profit'], 'Contribución Bruta menos Egresos Operativos'],
            ['7. Compras Totales de Insumos', $kpis['total_purchases'], 'Inversión en compras registradas'],
            ['8. Mermas y Excepciones (Valor Retail)', $kpis['total_exceptions_retail'], 'Pérdidas, cortesías y comidas de personal (precio venta)'],
            ['9. Total Jornadas Móviles', $kpis['shifts_count'], 'Turnos móviles operados en el período'],
            ['10. Total Transacciones POS', $kpis['sales_count'], 'Ventas emitidas en punto de venta'],
        ];

        $r = 5;
        foreach ($kpiRows as $idx => $row) {
            $sheet1->setCellValue("A{$r}", $row[0]);
            $sheet1->setCellValue("B{$r}", $row[1]);
            $sheet1->setCellValue("C{$r}", $row[2]);

            if ($idx === 0) {
                $sheet1->getStyle("A{$r}:C{$r}")->applyFromArray($headerStyle);
            } else {
                if (is_numeric($row[1])) {
                    $sheet1->getStyle("B{$r}")->getNumberFormat()->setFormatCode('"Bs. "#,##0.00');
                }
                if ($row[0] === '6. RESULTADO OPERATIVO NETO') {
                    $sheet1->getStyle("A{$r}:C{$r}")->getFont()->setBold(true)->setSize(11);
                    $sheet1->getStyle("B{$r}")->getFont()->getColor()->setRGB($kpis['operating_profit'] >= 0 ? '10B981' : 'EF4444');
                }
                $sheet1->getStyle("A{$r}:C{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E5E7EB');
            }
            $r++;
        }

        foreach (['A' => 38, 'B' => 24, 'C' => 50] as $col => $w) {
            $sheet1->getColumnDimension($col)->setWidth($w);
        }

        // ==========================================
        // HOJA 2: JORNADAS Y CONCILIACIÓN
        // ==========================================
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Jornadas y Conciliación');

        $shiftHeaders = [
            'ID Jornada',
            'Fecha',
            'Ubicación',
            'Unidad',
            'Operador',
            'Despacho Total',
            'Vendido POS',
            'Mermas',
            'Conteo Final',
            'Ventas Efectivo',
            'Ventas QR',
            'Ventas POS Total',
            'Fondo Inicial',
            'Arqueo Contado',
            'QR Confirmado',
            'Dif. Efectivo',
            'Dif. QR',
            'Dif. Inventario (Bs.)',
            'Semáforo',
            'Contribución (Bs.)',
        ];

        $sheet2->fromArray($shiftHeaders, null, 'A1');
        $sheet2->getStyle('A1:T1')->applyFromArray($headerStyle);

        $r = 2;
        foreach ($data['shifts'] as $s) {
            $semaphore = $s->total_semaphore instanceof \BackedEnum ? $s->total_semaphore->value : (string) $s->total_semaphore;
            $sheet2->fromArray([
                $s->shift_code,
                $s->shift_date?->format('d/m/Y'),
                $s->location,
                $s->unit_code,
                $s->operator_name,
                (int) $s->total_dispatched_qty,
                (int) $s->total_sold_qty,
                (int) $s->total_waste_qty,
                (int) $s->total_closing_qty,
                (float) $s->expected_cash_sales,
                (float) $s->expected_qr_sales,
                (float) $s->total_expected_sales,
                (float) $s->starting_float,
                (float) $s->counted_cash,
                (float) $s->confirmed_qr,
                (float) $s->cash_variance,
                (float) $s->qr_variance,
                (float) $s->inventory_variance_value,
                $semaphore,
                (float) $s->gross_contribution,
            ], null, "A{$r}");

            $sheet2->getStyle("J{$r}:R{$r}")->getNumberFormat()->setFormatCode('"Bs. "#,##0.00');
            $sheet2->getStyle("T{$r}")->getNumberFormat()->setFormatCode('"Bs. "#,##0.00');
            $sheet2->getStyle("A{$r}:T{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E5E7EB');
            $r++;
        }

        foreach (range('A', 'T') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }

        // ==========================================
        // HOJA 3: VENTAS POS DETALLADAS
        // ==========================================
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Ventas POS');

        $salesHeaders = [
            'ID Transacción',
            'ID Jornada',
            'Fecha',
            'Hora',
            'SKU',
            'Producto',
            'Cantidad',
            'Método Pago',
            'Precio Unitario',
            'Total Venta (Bs.)',
            'Costo BOM Unitario',
            'Contribución Bruta',
        ];

        $sheet3->fromArray($salesHeaders, null, 'A1');
        $sheet3->getStyle('A1:L1')->applyFromArray($headerStyle);

        $r = 2;
        foreach ($data['sales'] as $sale) {
            $sheet3->fromArray([
                $sale->transaction_code,
                $sale->shift?->shift_code ?? '-',
                $sale->sale_date?->format('d/m/Y'),
                substr((string) $sale->sale_time, 0, 5),
                $sale->product?->sku ?? '-',
                $sale->product?->name ?? '-',
                (int) $sale->qty,
                $sale->payment_method instanceof \BackedEnum ? $sale->payment_method->value : (string) $sale->payment_method,
                (float) $sale->unit_price,
                (float) $sale->sales_value,
                (float) $sale->unit_cost,
                (float) $sale->contribution_value,
            ], null, "A{$r}");

            $sheet3->getStyle("I{$r}:L{$r}")->getNumberFormat()->setFormatCode('"Bs. "#,##0.00');
            $sheet3->getStyle("A{$r}:L{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E5E7EB');
            $r++;
        }

        foreach (range('A', 'L') as $col) {
            $sheet3->getColumnDimension($col)->setAutoSize(true);
        }

        // ==========================================
        // HOJA 4: PRODUCTOS Y RECETAS BOM
        // ==========================================
        $sheet4 = $spreadsheet->createSheet();
        $sheet4->setTitle('Productos y Costos BOM');

        $prodHeaders = [
            'SKU',
            'Producto',
            'Precio Venta',
            'Ingredientes',
            'Empaques',
            'Costo Directo BOM',
            'Mano de Obra Directa',
            'Otros Costos Variables',
            'Costo Maestro Unitario',
            'Contribución Unitaria',
            'Margen %',
            'Componentes BOM',
            'Regla Porción',
        ];

        $sheet4->fromArray($prodHeaders, null, 'A1');
        $sheet4->getStyle('A1:M1')->applyFromArray($headerStyle);

        $r = 2;
        foreach ($data['products'] as $p) {
            $sheet4->fromArray([
                $p->sku,
                $p->name,
                (float) $p->selling_price,
                (float) $p->ingredients_cost,
                (float) $p->packaging_cost,
                (float) $p->direct_bom_cost,
                (float) $p->direct_labor_cost,
                (float) $p->other_variable_cost,
                (float) $p->master_unit_cost,
                (float) $p->contribution_amount,
                round((float) $p->contribution_margin_pct * 100, 1) . '%',
                (int) $p->boms_count,
                $p->portion_control ?: 'Estándar',
            ], null, "A{$r}");

            $sheet4->getStyle("C{$r}:J{$r}")->getNumberFormat()->setFormatCode('"Bs. "#,##0.00');
            $sheet4->getStyle("A{$r}:M{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E5E7EB');
            $r++;
        }

        foreach (range('A', 'M') as $col) {
            $sheet4->getColumnDimension($col)->setAutoSize(true);
        }

        // ==========================================
        // HOJA 5: COMPRAS Y EGRESOS
        // ==========================================
        $sheet5 = $spreadsheet->createSheet();
        $sheet5->setTitle('Compras y Egresos');

        $sheet5->setCellValue('A1', 'REGISTRO DE COMPRAS DE INSUMOS');
        $sheet5->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB($goldColor);

        $purchHeaders = ['ID Compra', 'Fecha', 'Proveedor', 'Factura', 'Insumo', 'Cantidad', 'Presentación', 'Precio Unit.', 'Costo Total (Bs.)', 'Método Pago'];
        $sheet5->fromArray($purchHeaders, null, 'A3');
        $sheet5->getStyle('A3:J3')->applyFromArray($headerStyle);

        $r = 4;
        foreach ($data['purchases'] as $purch) {
            $sheet5->fromArray([
                $purch->purchase_code,
                $purch->purchase_date?->format('d/m/Y'),
                $purch->supplier,
                $purch->invoice_number ?: '-',
                $purch->supply?->name ?? '-',
                (float) $purch->qty,
                $purch->purchase_unit,
                (float) $purch->unit_price,
                (float) $purch->total_cost,
                $purch->payment_method instanceof \BackedEnum ? $purch->payment_method->value : (string) $purch->payment_method,
            ], null, "A{$r}");

            $sheet5->getStyle("H{$r}:I{$r}")->getNumberFormat()->setFormatCode('"Bs. "#,##0.00');
            $sheet5->getStyle("A{$r}:J{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E5E7EB');
            $r++;
        }

        $r += 2;
        $sheet5->setCellValue("A{$r}", 'EGRESOS OPERATIVOS FUERA DE RECETA');
        $sheet5->getStyle("A{$r}")->getFont()->setBold(true)->setSize(12)->getColor()->setRGB($goldColor);
        $r++;

        $expHeaders = ['ID Egreso', 'Fecha', 'Categoría', 'Unidad Móvil', 'Ubicación', 'Proveedor / Detalle', 'Monto (Bs.)', 'Método Pago', 'Responsable'];
        $sheet5->fromArray($expHeaders, null, "A{$r}");
        $sheet5->getStyle("A{$r}:I{$r}")->applyFromArray($headerStyle);
        $r++;

        foreach ($data['expenses'] as $exp) {
            $sheet5->fromArray([
                $exp->expense_code,
                $exp->expense_date?->format('d/m/Y'),
                $exp->category instanceof \BackedEnum ? $exp->category->value : (string) $exp->category,
                $exp->unit_code ?: 'DN-M01',
                $exp->location ?: '-',
                $exp->description . ($exp->supplier ? " ({$exp->supplier})" : ''),
                (float) $exp->amount,
                $exp->payment_method instanceof \BackedEnum ? $exp->payment_method->value : (string) $exp->payment_method,
                $exp->responsible ?: 'Administrador',
            ], null, "A{$r}");

            $sheet5->getStyle("G{$r}")->getNumberFormat()->setFormatCode('"Bs. "#,##0.00');
            $sheet5->getStyle("A{$r}:I{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E5E7EB');
            $r++;
        }

        foreach (range('A', 'J') as $col) {
            $sheet5->getColumnDimension($col)->setAutoSize(true);
        }

        // Activamos la primera hoja por defecto
        $spreadsheet->setActiveSheetIndex(0);

        $fileName = 'Reporte_Consolidado_Diesel_Express_Grill_' . date('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}

