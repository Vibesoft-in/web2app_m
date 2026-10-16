@if ($tenant = $this->getImpersonatedTenant())
    <div class="p-4 mb-4 rounded-xl bg-amber-500 text-white font-semibold flex items-center justify-between shadow-lg">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>
                ACTIVE IMPERSONATION: You are acting as <strong>{{ $tenant->name }}</strong> (Tenant #{{ $tenant->id }}). All actions are recorded in the audit trail.
            </span>
        </div>
        <button
            wire:click="stopImpersonation"
            class="px-4 py-1.5 bg-black hover:bg-slate-900 text-white text-xs uppercase tracking-wider rounded-lg font-bold transition">
            Exit Impersonation
        </button>
    </div>
@endif
