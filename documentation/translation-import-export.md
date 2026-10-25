# English translation catalog

Translation Manager keeps English as its source catalog. Sync English reads `lang/en.json` and discovers admin, player, and guest interface copy from Blade templates, component labels, attributes, PHP labels, country names, and browser UI messages. This includes dashboard headings, buttons, cards, tooltips, placeholders, and states. It inspects source literals without rendering pages or collecting player names, chat messages, or other user data. Sync Database adds public CMS and competition phrases. Neither operation creates empty rows for every other language. Existing translated values are retained. The migration removes only active, empty, non-English placeholder rows.

Changing labels that contain counts or names use stable placeholders, such as `Welcome, :name`, so one translation works for every player and number. Browser-generated controls receive localized labels before Alpine or JavaScript changes their text. Sync English discovers admin, player, and guest interface copy. Date-format arguments are excluded. Identical English text is listed and exported once, even when legacy keys refer to the same phrase. Imported and manually saved translations update those equivalent keys.

To translate a language:

1. Sync any new English phrases, then click **Export English**.
2. Edit only each entry's `translation` value in the downloaded JSON. Keep `key`, `source`, the file version, and source fingerprint unchanged.
3. Choose the target language, upload the JSON, and click **Validate and import**.

```json
{
  "version": 1,
  "source_locale": "en",
  "source_hash": "<fingerprint from the downloaded file>",
  "entries": [
    {
      "key": "Hello :name.",
      "source": "Hello :name.",
      "translation": "Bonjour :name."
    }
  ]
}
```

The import validates the exported snapshot fingerprint, unique keys, unchanged existing English sources, non-empty translations, and matching named/printf placeholders. Entry order may change. New phrases added after export do not invalidate a file; they remain untranslated. Changed or removed source phrases require a fresh export. All database batches share one transaction: a mismatch or database failure rolls back every write, including earlier batches. Rejected imports leave the previously published translations available.

**Add a language** accepts a unique language code (for example `fil` or `zh-hans`), its English name, and its native name. The language appears in Translation Manager and the site language selector. Adding it creates no phrase placeholders. Untranslated phrases continue to use existing file translations or English fallback.

The import panel guides the admin through language selection and file upload, with drag-and-drop support, filename feedback, and upload progress. Add Language opens a modal with quick-fill choices; the native name is optional and defaults to the language name. A country flag picker includes a preview and saves the chosen flag for the language selector and admin language tabs. Quick-fill choices also select an appropriate flag. A neutral flag is available when no country flag is desired.

Language creation checks that both its registry and flag column are installed before validating uniqueness or writing records. An incomplete setup produces a validation message instead of a server error. Existing custom languages receive a neutral flag when the flag migration runs.

**Reset translations** requires the separate `translations.reset` permission. Super Admin always has access. Other roles do not receive it by default; it can be granted or revoked in Roles & Permissions, and that choice survives deployment seeding. Reset can clear one language or all languages except English. The warning dialog requires typing the uppercase language code or `RESET ALL`. It permanently removes active and deleted translation rows for that scope, records an audit entry, and invalidates translation caches. English source phrases and language definitions remain untouched.

Reset also suppresses the image-bundled JSON fallback for the affected languages, so old translations cannot reappear after the database is cleared. Those languages use English until new manual translations or a validated import are added. A migration creates `translation_language_resets` to persist this behavior across restarts. No live reset runs during deployment.

Show/hide language checkboxes change tab visibility in the browser without contacting Livewire. Preferences are stored locally per admin. Hiding a tab does not disable the language for players or change the language currently being edited.

Available to players is a separate global control requiring `translations.manage`. Changes save to system settings through Livewire without reloading the page. Disabled languages disappear from site language pickers and cannot be selected through a direct language-update request. Existing sessions and accounts using a disabled locale fall back to the enabled default (English if the configured default is disabled). Translation rows remain editable in the manager. English is always available. Existing browser-only hidden-tab preferences are not converted into global settings.

Imports and individual saves update the database and invalidate shared translation caches. Runtime translations therefore work across app, worker, and scheduler containers without rewriting all language JSON files on each action. The existing files remain a fallback. Summary counts use a cached aggregate, and table pagination queries only the selected language. Startup writes remain limited to 100 rows per batch.

Deployment requires the new migrations (`translation_languages`, placeholder cleanup, and expanding `users.locale` to 10 characters). Standard production startup already runs migrations. The image permits 12 MB PHP uploads and 16 MB request bodies; the import itself is limited to 10 MB. No changes to Docker memory limits are needed.
