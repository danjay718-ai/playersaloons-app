export function competitionPanel(wire = null) {
    return {
        activeTab: wire ? wire.entangle('activeTab') : 'overview',
        compactView: window.matchMedia('(max-width: 1023px)').matches,
        panelOpen: false,
        returnTab: null,
        panelTitle: '',
        returnFocus: null,
        returnScrollY: 0,

        openPanel(tab) {
            if (!this.compactView) return;
            if (!this.panelOpen) {
                this.returnTab = this.activeTab;
                this.returnFocus = document.activeElement;
                this.returnScrollY = window.scrollY;
            }
            this.panelTitle = this.$root.querySelector(`[data-competition-tab="${tab}"]`)?.textContent.trim() ?? '';
            this.panelOpen = true;
            this.$nextTick(() => {
                if (!this.panelOpen || !this.compactView) return;
                const dialog = this.$root.querySelector('.competition-panel');
                if (dialog.matches(':modal')) return;
                dialog.close();
                dialog.showModal();
            });
        },

        closePanel() {
            if (!this.panelOpen) return;
            this.panelOpen = false;
            this.activeTab = this.returnTab;
            this.$root.querySelector('.competition-panel').close();
            this.$root.querySelector('.competition-panel').show();
            this.$nextTick(() => {
                this.returnFocus?.focus({ preventScroll: true });
                window.scrollTo({ top: this.returnScrollY, behavior: 'instant' });
            });
        },

        resizePanel() {
            this.compactView = window.matchMedia('(max-width: 1023px)').matches;
            if (!this.compactView && this.panelOpen) {
                this.panelOpen = false;
                this.$root.querySelector('.competition-panel').close();
                this.$root.querySelector('.competition-panel').show();
            }
        },
    };
}
