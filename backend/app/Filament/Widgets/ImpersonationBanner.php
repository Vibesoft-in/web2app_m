<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Tenant;
use App\Services\Security\ImpersonationService;
use Filament\Widgets\Widget;

class ImpersonationBanner extends Widget
{
    protected static string $view = 'filament.widgets.impersonation-banner';
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return app(ImpersonationService::class)->isImpersonating();
    }

    public function getImpersonatedTenant(): ?Tenant
    {
        $tenantId = app(ImpersonationService::class)->getImpersonatedTenantId();
        return $tenantId ? Tenant::find($tenantId) : null;
    }

    public function stopImpersonation(): void
    {
        app(ImpersonationService::class)->stopImpersonation();
        $this->redirect('/admin');
    }
}
