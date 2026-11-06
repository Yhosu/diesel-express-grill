<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ReconciliationSemaphore;
use App\Enums\UserRole;
use App\Services\CostingService;
use PHPUnit\Framework\TestCase;

class RoleAndCostingUnitTest extends TestCase
{
    public function test_roles_permissions_matrix_and_spanish_labels(): void
    {
        $superAdmin = UserRole::SUPER_ADMIN;
        $subAdmin = UserRole::SUB_ADMIN;

        $this->assertSame('Super Administrador', $superAdmin->getLabel());
        $this->assertSame('Subadministrador', $subAdmin->getLabel());

        // Super Administrador tiene acceso total
        $this->assertTrue($superAdmin->canEditRecords());
        $this->assertTrue($superAdmin->canDeleteRecords());
        $this->assertTrue($superAdmin->canAccessAuditLogs());
        $this->assertTrue($superAdmin->canManageUsers());
        $this->assertTrue($superAdmin->canAccessReports());

        // Subadministrador solo registra y ve reportes (no edita ni accede a tabla de auditoría)
        $this->assertFalse($subAdmin->canEditRecords());
        $this->assertFalse($subAdmin->canDeleteRecords());
        $this->assertFalse($subAdmin->canAccessAuditLogs());
        $this->assertFalse($subAdmin->canManageUsers());
        $this->assertTrue($subAdmin->canAccessReports());
    }

    public function test_costing_service_computes_base_and_waste_adjusted_unit_costs(): void
    {
        // Insumo: Pan de hamburguesa (Bs. 24 por paquete de 12 unidades, 2% merma)
        $costs = CostingService::computeSupplyCosts(24.00, 12.0, 0.02);

        $this->assertEqualsWithDelta(2.0, $costs['base_unit_cost'], 0.0001);
        $this->assertEqualsWithDelta(2.040816, $costs['adjusted_unit_cost'], 0.0001);
    }

    public function test_reconciliation_semaphore_thresholds(): void
    {
        $this->assertSame(ReconciliationSemaphore::VERDE, ReconciliationSemaphore::evaluate(0.0));
        $this->assertSame(ReconciliationSemaphore::AMARILLO, ReconciliationSemaphore::evaluate(3.50));
        $this->assertSame(ReconciliationSemaphore::AMARILLO, ReconciliationSemaphore::evaluate(-5.00));
        $this->assertSame(ReconciliationSemaphore::ROJO, ReconciliationSemaphore::evaluate(5.01));
        $this->assertSame(ReconciliationSemaphore::ROJO, ReconciliationSemaphore::evaluate(-12.00));
    }
}

