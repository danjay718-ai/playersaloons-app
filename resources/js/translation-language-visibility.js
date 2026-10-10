export function translationLanguageVisibility(adminId, supported, storage = globalThis.localStorage) {
    const key = `translation-hidden-languages-${adminId}`;
    let hidden = [];
    try { hidden = JSON.parse(storage.getItem(key) || '[]'); } catch { /* Use default visibility. */ }
    return {
        supported,
        hiddenLanguages: Array.isArray(hidden) ? hidden.filter(locale => locale !== 'en' && supported.includes(locale)) : [],
        persist() {
            try { storage.setItem(key, JSON.stringify(this.hiddenLanguages)); } catch { /* Session-only visibility still works. */ }
        },
        toggleLanguage(locale) {
            if (locale === 'en' || !this.supported.includes(locale)) return;
            this.hiddenLanguages = this.hiddenLanguages.includes(locale)
                ? this.hiddenLanguages.filter(value => value !== locale)
                : [...this.hiddenLanguages, locale];
            this.persist();
        },
        showAllLanguages() { this.hiddenLanguages = []; this.persist(); },
    };
}
