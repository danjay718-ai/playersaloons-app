export function settingsNavigation(sections) {
    return {
        section: sections[0],

        init() {
            this.restore();
        },

        restore() {
            const requested = new URL(window.location.href).searchParams.get('section');
            this.section = sections.includes(requested) ? requested : sections[0];
        },

        select(section) {
            if (!sections.includes(section) || section === this.section) return;
            this.section = section;
            const url = new URL(window.location.href);
            url.searchParams.set('section', section);
            window.history.pushState(null, '', url);
        },
    };
}
