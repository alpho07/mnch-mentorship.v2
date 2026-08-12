@php
    $statePath = $getStatePath();
@endphp
<div
    x-data="{
        state: $wire.entangle('{{ $statePath }}'),
        drawing: false,
        ctx: null,
        init() {
            const canvas = this.$refs.canvas;
            this.ctx = canvas.getContext('2d');
            this.ctx.strokeStyle = '#111827';
            this.ctx.lineWidth = 2;
            if (this.state) {
                const img = new Image();
                img.onload = () => this.ctx.drawImage(img, 0, 0);
                img.src = this.state;
            }
        },
        start(e) {
            this.drawing = true;
            const rect = this.$refs.canvas.getBoundingClientRect();
            this.ctx.beginPath();
            this.ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
        },
        move(e) {
            if (!this.drawing) return;
            const rect = this.$refs.canvas.getBoundingClientRect();
            this.ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
            this.ctx.stroke();
        },
        stop() {
            if (!this.drawing) return;
            this.drawing = false;
            this.state = this.$refs.canvas.toDataURL('image/png');
        },
        clear() {
            this.ctx.clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height);
            this.state = null;
        },
    }"
    class="fi-signature-pad"
>
    <canvas
        x-ref="canvas"
        width="400"
        height="150"
        style="border:1px solid #d1d5db;border-radius:0.5rem;touch-action:none;background:#fff;max-width:100%;"
        @mousedown="start" @mousemove="move" @mouseup="stop" @mouseleave="stop"
    ></canvas>
    <button type="button" @click="clear" class="fi-btn fi-btn-size-sm" style="margin-top:0.5rem;">Clear signature</button>
</div>
