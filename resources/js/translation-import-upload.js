export function translationImportUpload() {
    return {
        dragging: false,
        uploading: false,
        progress: 0,
        dropFile(event, input) {
            this.dragging = false;
            const file = event.dataTransfer?.files?.[0];
            if (!file || !input) return;
            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        },
    };
}
