// Guest landing v4 ("Neon Arena"). Alpine behaviours only; no extra dependencies.
const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('grTypewriter', (words) => ({
        words: Array.isArray(words) && words.length ? words : ['CASH OUT.'],
        wordIndex: 0,
        charIndex: 0,
        deleting: false,
        display: '',
        timer: null,
        init() {
            this.display = this.words[0];
            this.charIndex = this.display.length;
            if (reduced() || this.words.length < 2) return;
            this.schedule(2200);
        },
        schedule(ms) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.tick(), ms);
        },
        tick() {
            const current = this.words[this.wordIndex] || '';
            if (!this.deleting) {
                this.charIndex = Math.min(current.length, this.charIndex + 1);
                this.display = current.slice(0, this.charIndex);
                if (this.charIndex >= current.length) {
                    this.deleting = true;
                    this.schedule(2000);
                } else {
                    this.schedule(85);
                }
            } else {
                this.charIndex = Math.max(0, this.charIndex - 1);
                this.display = current.slice(0, this.charIndex);
                if (this.charIndex === 0) {
                    this.deleting = false;
                    this.wordIndex = (this.wordIndex + 1) % this.words.length;
                    this.schedule(260);
                } else {
                    this.schedule(42);
                }
            }
        },
        destroy() { clearTimeout(this.timer); },
    }));

    window.Alpine.data('grCountdown', (iso, labels) => ({
        now: Date.now(), timer: null,
        init() { this.timer = setInterval(() => { this.now = Date.now(); }, 1000); },
        destroy() { clearInterval(this.timer); },
        get seconds() { return Math.max(0, Math.ceil((Date.parse(iso) - this.now) / 1000)); },
        get parts() {
            const s = this.seconds;
            const pad = (n) => String(n).padStart(2, '0');
            return [
                { label: labels.d, value: pad(Math.floor(s / 86400)) },
                { label: labels.h, value: pad(Math.floor(s / 3600) % 24) },
                { label: labels.m, value: pad(Math.floor(s / 60) % 60) },
                { label: labels.s, value: pad(s % 60) },
            ];
        },
        get text() {
            if (!this.seconds) return labels.started;
            const [d, h, m, s] = this.parts;
            return `${Number(d.value)}${labels.d} ${h.value}${labels.h} ${m.value}${labels.m} ${s.value}${labels.s}`;
        },
    }));

    window.Alpine.data('grTable', () => ({
        open: false,
        move(event) {
            if (event.pointerType !== 'mouse' || reduced()) return;
            const box = this.$el.getBoundingClientRect();
            this.$el.style.setProperty('--ry', `${((event.clientX - box.left) / box.width - 0.5) * 8}deg`);
            this.$el.style.setProperty('--rx', `${(0.5 - (event.clientY - box.top) / box.height) * 8}deg`);
        },
        leave() { this.$el.style.setProperty('--rx', '0deg'); this.$el.style.setProperty('--ry', '0deg'); },
    }));

    window.Alpine.data('grHome', () => ({
        pastHero: false, observers: [], onScroll: null,
        init() {
            const root = this.$el;
            const hero = root.querySelector('[data-gr-hero]');
            if (hero) {
                const io = new IntersectionObserver(([entry]) => { this.pastHero = !entry.isIntersecting; });
                io.observe(hero);
                this.observers.push(io);
                if (!reduced()) hero.addEventListener('pointermove', (e) => {
                    const box = hero.getBoundingClientRect();
                    hero.style.setProperty('--mx', `${e.clientX - box.left}px`);
                    hero.style.setProperty('--my', `${e.clientY - box.top}px`);
                });
            }
            this.revealAndCount(root);
            this.stepProgress(root);
        },
        revealAndCount(root) {
            const items = root.querySelectorAll('.gr-reveal');
            if (reduced()) { items.forEach((el) => el.classList.add('is-in')); return; }
            const io = new IntersectionObserver((entries) => entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-in');
                const count = entry.target.querySelector('[data-count]');
                if (count) this.countUp(count);
                io.unobserve(entry.target);
            }), { threshold: 0.15 });
            items.forEach((el, i) => { el.style.transitionDelay = `${(i % 4) * 70}ms`; io.observe(el); });
            this.observers.push(io);
        },
        countUp(el) {
            const match = el.dataset.count.match(/^(\D*)([\d,]+(?:\.\d+)?)(.*)$/);
            if (!match) return;
            const [, prefix, number, suffix] = match;
            const decimals = (number.split('.')[1] || '').length;
            const target = parseFloat(number.replace(/,/g, ''));
            const start = performance.now();
            const tick = (now) => {
                const t = Math.min(1, (now - start) / 1100);
                const value = target * (1 - Math.pow(1 - t, 3));
                el.textContent = prefix + value.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + suffix;
                if (t < 1) requestAnimationFrame(tick); else el.textContent = el.dataset.count;
            };
            requestAnimationFrame(tick);
        },
        stepProgress(root) {
            const steps = root.querySelector('[data-gr-steps]');
            if (!steps) return;
            const update = () => {
                const box = steps.getBoundingClientRect();
                const progress = Math.min(1, Math.max(0, (innerHeight * 0.75 - box.top) / box.height));
                steps.style.setProperty('--p', reduced() ? 1 : progress.toFixed(3));
            };
            this.onScroll = update;
            addEventListener('scroll', update, { passive: true });
            update();
        },
        destroy() {
            this.observers.forEach((o) => o.disconnect());
            if (this.onScroll) removeEventListener('scroll', this.onScroll);
        },
    }));
});
