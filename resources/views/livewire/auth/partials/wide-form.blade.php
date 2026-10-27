{{-- Register page only: slim hero, wide card, form laid out as a grid. Desktop only; the stacked mobile layout from styles.blade.php stays as is. --}}
<style>
    @media (min-width:1101px){
        :root{--hero-w:clamp(280px,25%,360px)}
        .auth-hero{flex:0 0 var(--hero-w)}
        .hero-bg,.hero-gradient{width:var(--hero-w)}
        .hero-content{padding:2rem 1.6rem 1.4rem}
        .hero-title{font-size:clamp(1.6rem,2.2vw,2.1rem)}
        .hero-desc{font-size:.84rem}
        .hero-programmes,.hero-supported{display:none}
        .impact-grid{grid-template-columns:1fr 1fr}
        .impact-card{min-height:70px}
        .impact-card div span{white-space:normal}
        .auth-right{justify-content:flex-start;align-items:center;padding:2rem clamp(1.25rem,3vw,3rem)}
        .auth-box{width:100%;max-width:1040px;margin:0 auto;padding:2rem 2.25rem 1.5rem}
        .auth-box .secure-strip{margin-inline:-2.25rem}
    }
    /* Sections of the form: lighter card inside the card, tight spacing, no squashed fields */
    .auth-box .fi-section{border:1px solid #e3eaf3!important;border-radius:14px!important;background:#fbfdff!important;box-shadow:none!important;margin-bottom:.9rem}
    .auth-box .fi-section-header{padding:.7rem 1rem .1rem!important;background:none!important;border:0!important}
    .auth-box .fi-section-header-heading{font-size:.8rem!important;font-weight:800!important;letter-spacing:.04em;text-transform:uppercase;color:#1769c2!important}
    .auth-box .fi-section-content-ctn{padding:.8rem 1rem 1rem!important}
    .auth-box .fi-fo-field-wrp-label label span{font-size:.78rem}
    .auth-box .fi-fo-component-ctn{gap:1rem}
</style>
