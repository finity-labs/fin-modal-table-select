# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.4] - 2026-10-06

### Fixed
- Opening the picker from a record action on an array-backed table threw `Cannot assign array to property TableSelectLivewireComponent::$record of type ?Model` the moment either select trigger was clicked. On an array-backed table the action's schema record is the row itself as a plain array, and the embedded mount forwarded it into a property typed `?Model`. The mount now passes the record only when it's an Eloquent model — standalone mode never used it, since it only scopes relationship-based tables. Reported on PR #1 against fin-codex's coverage attach modal

## [1.1.3] - 2026-10-04

### Changed
- Filament requirement raised from `^4.0` to `^4.12`. `standaloneRecords()` builds on the embedded-view `TableSelect` that Filament shipped in 4.12.0 (`toEmbeddedHtml()`, `Field::wrapEmbeddedHtml()`); on earlier 4.x the field fell back to the stock Blade view and mounted the relationship-based table, so the array-backed mode never ran. Composer now says so at install time instead of the field breaking quietly at runtime

## [1.1.2] - 2026-09-09

### Fixed
- Wrapped stacked-list lines rendered a blank line above and below the content: white-space: pre-line preserved the Blade template's own formatting newlines inside the `<p>`. The wrapped tags now hug their content, and string values are trimmed so a trailing newline in the data cannot add a bottom blank line either — newlines inside the value still render as line breaks

## [1.1.1] - 2026-09-09

### Added
- `stackedListPrimaryWrapped()` / `stackedListSecondaryWrapped()`: let the stacked list's text lines wrap instead of truncating on narrow widths; newlines in the value render as line breaks (white-space: pre-line), and the per-item remove button stays pinned to the top of a grown row
- `stackedListSecondary()` closures may return an `HtmlString` to render markup — Htmlable values pass through unescaped, plain strings stay escaped

### Fixed
- The picker threw "Typed property Component::$container must not be accessed before initialization" when placed inside a Repeater row or an action's modal schema. Filament clones a field into every Repeater item and into a modal schema, and the select, remove and collapse actions were registered through closures bound to the original field rather than the clone; they now receive the component Filament injects, so a picker in a Repeater row builds its actions — and evaluates `fn (Get $get)` records closures — on the row's own field

## [1.1.0] - 2026-09-09

### Added
- `standaloneRecords()`: array-backed mode — run the picker over plain arrays (named routes, page classes, API data) with no Eloquent involved. The modal table searches the searchable columns and sorts the sortable ones through Filament's records() data source; the selected keys land in the field state as strings, single or multiple()
- The records closure is evaluated with Filament's closure dependency injection on the field, so `fn (Get $get) => ...` can scope the list by sibling form state, including inside Repeater rows
- Stale keys degrade gracefully: a saved key the records closure no longer returns renders as its raw key instead of being dropped
- `getRecordKey()`: the record's identity across both worlds — model key or the array key attribute

### Changed
- Every display mode and both fills accept array records alongside models: display and fill closures receive `Model|array`, and `getSelectedRecords()` now returns a base `Collection` (still Eloquent collections for the model modes)

## [1.0.0] - 2026-08-12

### Added
- Translations for 58 locales, matching the locale set of the other fin packages
- `FilledEntry`: a fillsFields() display target pairing a Hidden field with a live TextEntry — bare infolist entries are not stateful and cannot receive fills
- `hydrateSelectionFromRepeater()`: rebuild the picker selection from saved repeater rows on edit pages
- `listStyle()`: comma, dot, bullet, or line-break text lists instead of badges
- `badgeColorFromRecord()` / `badgeIconFromRecord()`: per-record badge colors and icons
- `cardGrid()`: card grid display with image, title, description, and remove buttons
- `thumbnails()`: compact image strip with tooltips, initials fallback, optional remove
- `itemView()`: render each selected record with a custom Blade view (receives $record, $field, $removeAction)
- `emptyStateSelectButton()`: "Select…" link in the empty state that opens the modal
- `standalone()`: use the picker without an Eloquent relationship — records come from a model query, selected IDs live in the field state (JSON column)
- `stackedList()`: stacked-list display with primary/secondary lines, thumbnail, and per-item remove buttons that update state server-side
- `displayLimit()`: collapse badges and stacked-list rows behind a client-side "+N more" toggle
- `SelectedItemsRepeater`: Repeater preset paired to a picker via `for()` — rows only enter through the modal, and deleting a row deselects its record
- `displayAsTable()`: table display for selected records with columns inherited from the modal's `tableConfiguration()` class
- `fillsFields()`: prefill sibling form fields from the selected record (attribute paths, dot notation, or closures); targets clear on deselection
- `fillsRepeater()`: sync a multiple selection into a sibling Repeater — new records append, user-edited rows survive, deselected rows are removed
- Livewire feature tests covering rendering, prefill, and repeater merge

### Changed
- `selectionSummary()` now renders the count badge on the label line (after the field label) and works in every display mode, not just selection-only
- The inherited table display derived from `tableConfiguration()` is memoized per request
- Table and infolist rendering moved out of Blade into PHP schema builders; rows and cards are bound to the record models, so entry formatting, casts, and dot-notation relationships resolve natively
- `getSelectedRecords()` (was `getRecords()`) uses the parent's relationship query strategy, honors `modifyRelationshipQueryUsing()`, keeps selection order, and memoizes per state
- The parent's `getSelectedRecord()` pipeline and cache are used as-is instead of being overridden

### Removed
- Form display mode (`formSchema()`, `formColumns()`, `formEagerLoad()`): the rendered fields could never round-trip user input. Use `fillsFields()` with real form fields instead.
