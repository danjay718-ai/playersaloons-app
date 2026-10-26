document.addEventListener('alpine:init', () => {
    window.Alpine.data('guestTournamentSlider', () => ({
        index: 0, count: 0, now: Date.now(), timer: null, labels: {},
        pointer: null, origin: 0, scrollOrigin: 0, dragged: false,
        init() {
            this.count = this.$el.querySelectorAll('[data-tournament-slide]').length;
            this.labels = { ...this.$el.dataset };
            this.timer = setInterval(() => { this.now = Date.now(); }, 1000);
        },
        remaining(date) {
            const seconds = Math.max(0, Math.ceil((Date.parse(date) - this.now) / 1000));
            if (!seconds) return this.labels.started;
            const { days, hours, minutes, seconds: unit } = this.labels;
            return `${Math.floor(seconds / 86400)}${days} ${String(Math.floor(seconds / 3600) % 24).padStart(2, '0')}${hours} ${String(Math.floor(seconds / 60) % 60).padStart(2, '0')}${minutes} ${String(seconds % 60).padStart(2, '0')}${unit}`;
        },
        go(index) {
            const track = this.$refs.track;
            const slide = track?.children[Math.max(0, Math.min(index, this.count - 1))];
            if (slide) track.scrollTo({ left: slide.offsetLeft - track.children[0].offsetLeft, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
        },
        sync() {
            const track = this.$refs.track;
            if (track?.children.length > 1) this.index = Math.round(track.scrollLeft / (track.children[1].offsetLeft - track.children[0].offsetLeft));
        },
        startDrag(event) {
            if (event.pointerType !== 'mouse' || event.button !== 0 || event.target.closest('a, button')) return;
            this.pointer = event.pointerId;
            this.origin = event.clientX;
            this.scrollOrigin = this.$refs.track.scrollLeft;
            this.dragged = false;
        },
        drag(event) {
            if (event.pointerId !== this.pointer) return;
            const distance = event.clientX - this.origin;
            if (!this.dragged && Math.abs(distance) < 6) return;
            this.dragged = true;
            this.$refs.track.classList.add('is-dragging');
            this.$refs.track.setPointerCapture(event.pointerId);
            this.$refs.track.scrollLeft = this.scrollOrigin - distance;
            event.preventDefault();
        },
        endDrag(event) {
            if (event.pointerId !== this.pointer) return;
            this.pointer = null;
            this.$refs.track.classList.remove('is-dragging');
            this.sync();
            if (this.dragged) this.go(this.index);
        },
        destroy() { clearInterval(this.timer); },
    }));
    window.Alpine.data('guestHome', () => ({
        observer: null,
        init() {
            if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            this.observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('is-entering');
                    this.observer.unobserve(entry.target);
                });
            }, { threshold: 0.12 });
            this.$el.querySelectorAll('.guest-reveal').forEach((element, index) => {
                element.style.animationDelay = `${(index % 4) * 70}ms`;
                this.observer.observe(element);
            });
        },
        destroy() { this.observer?.disconnect(); },
    }));
});
