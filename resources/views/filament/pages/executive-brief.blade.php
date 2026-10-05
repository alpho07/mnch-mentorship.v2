<x-filament-panels::page>
    {{--
        The brief is a print artefact, so it is shown here as the sheet it
        is: fixed A4 width on a neutral ground, scaled down on narrow
        screens rather than reflowed. The markup is the exact partial the
        PDF renders, so what is reviewed here is what is signed off.
    --}}
    <div class="exec-brief-stage">
        <div class="exec-brief-sheet">
            @include('reports.executive-brief', ['brief' => $brief])
        </div>
    </div> 

    <style>
        .exec-brief-stage {
            background: #e9eef0;
            padding: 24px 12px;
            overflow-x: auto;
            border-radius: 12px;
        }

        .exec-brief-sheet {
            width: 794px; /* A4 at 96dpi */
            min-height: 1123px;
            margin: 0 auto;
            padding: 29px 37px 24px 37px; /* matches the PDF's @page margin */
            background: #ffffff;
            box-shadow: 0 6px 28px rgba(15, 43, 56, 0.18);
        }

        /* The brief is a printed page: it stays light in dark mode rather
           than being recoloured into something the PDF will not match. */
        .dark .exec-brief-stage { background: #1c2a32; }

        @media (max-width: 900px) {
            .exec-brief-sheet {
                transform: scale(0.62);
                transform-origin: top left;
                margin: 0;
            }
            .exec-brief-stage { padding: 12px 0; }
        }

        @media print {
            .exec-brief-stage { background: none; padding: 0; overflow: visible; }
            .exec-brief-sheet { box-shadow: none; width: auto; padding: 0; }
        }
    </style>
</x-filament-panels::page>
