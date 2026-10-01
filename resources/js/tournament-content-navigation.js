function scrollToPanel(findPanel) {
    // Wait for Alpine visibility changes and Livewire's new match component to paint.
    requestAnimationFrame(() => requestAnimationFrame(() => {
        const target = findPanel();
        if (!target || !target.isConnected || target.getClientRects().length === 0) return;
        const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
    }));
}

export function scrollToTournamentContent(root, tab, bracketView = 'bracket', focus = null) {
    const section = tab === 'matches' ? `matches-${bracketView}` : tab;
    scrollToPanel(() => {
        const panel = root.querySelector(`[data-tournament-content="${section}"]`);
        if (focus === 'dispute') return panel?.querySelector('[data-match-content="dispute"]') ?? panel;
        return tab === 'submit-results' ? panel?.querySelector('[data-match-panel="submit"]') ?? panel : panel;
    });
}

export function scrollToMatchContent(root, tab) {
    scrollToPanel(() => root.querySelector(`[data-match-panel="${tab}"]`));
}
