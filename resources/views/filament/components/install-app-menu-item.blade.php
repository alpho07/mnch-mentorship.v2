<div
    x-data="{
        deferredPrompt: null,
        show: false,
        isIos: false,
        isStandalone: false,
        init() {
            this.isStandalone = window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;

            if (this.isStandalone) {
                return;
            }

            this.isIos = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());

            window.addEventListener('beforeinstallprompt', (event) => {
                event.preventDefault();
                this.deferredPrompt = event;
                this.show = true;
            });

            if (this.isIos) {
                this.show = true;
            }
        },
        async install() {
            if (this.isIos || !this.deferredPrompt) {
                return;
            }

            this.deferredPrompt.prompt();
            await this.deferredPrompt.userChoice;
            this.deferredPrompt = null;
            this.show = false;
        },
    }"
    x-show="show"
    x-cloak
    style="padding: 4px 8px;"
>
    <button
        type="button"
        x-on:click="install()"
        x-bind:title="isIos ? 'Tap the Share icon, then \'Add to Home Screen\'' : 'Install this app'"
        style="
            display:flex;align-items:center;gap:10px;width:100%;
            padding:8px 10px;border-radius:0.5rem;border:none;background:transparent;
            font-size:13px;font-weight:600;color:inherit;cursor:pointer;text-align:left;
        "
        onmouseover="this.style.background='rgba(28,58,138,0.08)'"
        onmouseout="this.style.background='transparent'"
    >
        <span aria-hidden="true">📲</span>
        <span x-text="isIos ? 'Add to Home Screen' : 'Install App'"></span>
    </button>
</div>
