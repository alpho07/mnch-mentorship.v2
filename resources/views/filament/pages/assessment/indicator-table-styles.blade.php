{{--
    Table chrome for the indicators section (see IndicatorTableRenderer).
    Scoped to .aqs-ind-table so it can't leak into the other question_group
    sections, which keep the stacked layout. Same inline-<style> approach as
    section-chrome.blade.php and department-tabs.blade.php.
--}}
<style>
    .aqs-ind-table .fi-section-content{padding:0!important}
    .aqs-ind-table .fi-section-content-ctn{border-radius:12px;overflow:hidden;border:1px solid #e5e7eb}

    /* Each row is a 12-col grid; the gap is removed so cell borders meet. */
    .aqs-ind-table .aqs-ind-row{gap:0!important;border-bottom:1px solid #eef2f7;align-items:center}
    .aqs-ind-table .aqs-ind-row:last-child{border-bottom:none}
    .aqs-ind-table .aqs-ind-row > *{padding:.6rem .9rem}
    .aqs-ind-table .aqs-ind-row > * + *{border-left:1px solid #eef2f7}

    /* Rows carry their own separators; the per-field wrapper padding and
       divider the .aqs section style adds would double them up. */
    .aqs-ind-table .aqs-ind-row .fi-fo-field-wrp{padding:0;border-bottom:none}

    /* Explanatory note above the header row. */
    .aqs-ind-table .aqs-ind-note{padding:.85rem 1rem;background:#fffbeb;border-bottom:1px solid #fde68a}
    .aqs-ind-table .aqs-ind-note .fi-fo-placeholder{font-size:.8rem;color:#78350f;line-height:1.55}
    .aqs-ind-table .aqs-ind-note strong{font-weight:800;color:#92400e}

    .aqs-ind-table .aqs-ind-head{background:#f8fafc;border-bottom:1.5px solid #e2e8f0;position:sticky;top:0;z-index:2}
    .aqs-ind-table .aqs-ind-head .fi-fo-placeholder{font-size:.72rem;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.05em}

    .aqs-ind-table .aqs-ind-band{background:#eff6ff;border-top:1px solid #dbeafe;border-bottom:1px solid #dbeafe}
    .aqs-ind-table .aqs-ind-band .fi-fo-placeholder{font-size:.9rem;font-weight:800;color:#1d4ed8;line-height:1.4}

    .aqs-ind-table .aqs-ind-row:not(.aqs-ind-head):not(.aqs-ind-band):nth-of-type(even){background:#fcfdfe}
    .aqs-ind-table .aqs-ind-row:not(.aqs-ind-head):not(.aqs-ind-band):hover{background:#f8fafc}
    .aqs-ind-table .aqs-ind-row .fi-fo-placeholder{font-size:.86rem;color:#1e293b;line-height:1.45}

    .aqs-ind-table input[type="number"]{text-align:center;font-weight:700}
    .aqs-ind-table .fi-fo-checkbox{justify-content:center}
    .aqs-ind-table .fi-checkbox-input{width:18px;height:18px}

    /* A ticked row reads as struck off rather than merely empty. */
    .aqs-ind-table .aqs-ind-row:has(.fi-checkbox-input:checked){background:#fff7ed}
    .aqs-ind-table .aqs-ind-row:has(.fi-checkbox-input:checked) .fi-fo-placeholder{color:#9a3412}

    /* Stacks to label / value+checkbox on a phone, where a 3-column table
       with 36 long question texts is unusable. */
    @media(max-width:640px){
        .aqs-ind-table .aqs-ind-head{display:none}
        .aqs-ind-table .aqs-ind-row{display:block}
        .aqs-ind-table .aqs-ind-row > * + *{border-left:none;padding-top:0}
    }
</style>
