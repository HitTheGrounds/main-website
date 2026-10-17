{{-- Native page scrolling remains in charge; this is its visual control. --}}
<div class="page-scrollbar" data-page-scrollbar hidden tabindex="0" role="scrollbar"
    aria-label="Page scroll progress" aria-controls="public-page-content" aria-orientation="vertical"
    aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
    <div class="page-scrollbar__track" aria-hidden="true">
        <div class="page-scrollbar__progress" data-scroll-progress></div>
    </div>
    <div class="page-scrollbar__ball" data-scroll-thumb aria-hidden="true">
        <svg viewBox="0 0 16 16" fill="none">
            <circle cx="8" cy="8" r="6.5" fill="currentColor" />
            <path d="M5 2.5C9 5 7 11 11 13.5M3.5 3.5C7.5 6 5.5 12 9.5 14" stroke="var(--color-base-100)" stroke-width=".8" stroke-dasharray="1.2 1" />
        </svg>
    </div>
    <svg class="page-scrollbar__wicket" viewBox="0 0 16 32" fill="none" aria-hidden="true">
        <path class="page-scrollbar__stump page-scrollbar__stump--left" d="M3 29V9" />
        <path class="page-scrollbar__stump" d="M8 29V9" />
        <path class="page-scrollbar__stump page-scrollbar__stump--right" d="M13 29V9" />
        <path class="page-scrollbar__bail page-scrollbar__bail--left" d="M2 7H8" />
        <path class="page-scrollbar__bail page-scrollbar__bail--right" d="M8 7H14" />
        <path class="page-scrollbar__impact" d="M2 2L4 4M8 0V3M14 2L12 4" />
        <path class="page-scrollbar__crease" d="M1 31H15" />
    </svg>
</div>
