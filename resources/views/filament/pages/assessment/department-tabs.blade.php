<div class="aqs-dept-tabs">
    @foreach ($departments as $dept)
        <a
            href="{{ $baseUrl }}?dept={{ $dept->slug }}"
            class="aqs-dept-tab {{ $dept->id === $activeDepartmentId ? 'is-active' : '' }}"
        >
            {{ $dept->name }}
        </a>
    @endforeach
</div>

<style>
    .aqs-dept-tabs{display:flex;flex-wrap:wrap;gap:.5rem;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:.75rem;margin-bottom:1.25rem;box-shadow:0 1px 3px rgba(15,23,42,.05)}
    .aqs-dept-tab{
        display:inline-flex;align-items:center;
        padding:.55rem 1.1rem;border-radius:10px;
        border:1.75px solid #e2e8f0;background:#fff;
        font-size:.85rem;font-weight:700;color:#475569;
        text-decoration:none;cursor:pointer;
        transition:border-color .15s,background .15s,color .15s;
    }
    .aqs-dept-tab:hover{border-color:#93c5fd;background:#f8fafc}
    .aqs-dept-tab.is-active{border-color:#2563eb;background:#eff6ff;color:#1d4ed8;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
    @media(max-width:480px){.aqs-dept-tabs{flex-direction:column}.aqs-dept-tab{width:100%;justify-content:center}}
</style>
