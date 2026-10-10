# Proposed changes to Mason

We use Mason for a CMS with shared content blocks and translations. Along the way we added a few features to Mason itself. They are split into branches on top of the current `3.x`, one commit each, with tests and docs in `docs/`, so you can pick what fits.

## Branches

| Branch | What it does | Based on |
|---|---|---|
| `split/format-mason-js` | Runs Prettier over `resources/js/mason.js` (9 lines), so the feature diffs below contain no formatting. | `3.x` |
| `split/iframe-message-source` | The editor handles only messages from its own preview iframe, and the iframe only those from the window around it (`event.source`). Hardening; 5 lines. | format |
| `split/docs-security` | New docs page "Security": the editor state comes from the browser, so bricks sanitize their output; what the render context and entry data protect. | `3.x` |
| `split/preview-data` | `Mason::previewData()`: data for the bricks in the editor preview, passed as `toHtml()`'s `$data` (null without, as before). Travels in the signed render context. | `3.x` |
| `split/nested-bricks` | Container bricks (`ContainsBricks`): bricks that hold other bricks under `content`, e.g. columns. Paths like `"2.0"` in commands and the editor, drop zones, allowed children, nested outline. Docs: `bricks/containers`. | format |
| `split/brick-uids` | Every brick gets a lasting `attrs.uid`: kept on edit and move, new for inserted bricks and copies, duplicates fixed on load and save. Useful for translations or per-page overrides. | nested-bricks |
| `split/locked-structure` | `Mason::lockedStructure()`: bricks stay editable, but cannot be added, moved or removed; with `comparedTo`, a changed structure fails validation on save. | brick-uids |
| `split/container-areas` | A container can have several areas (e.g. slots): `BrickChildren::where()`, `dropZone($attrs)` and `dropTarget($attrs)`; bricks dropped there get the attributes. | nested-bricks |

## Dependencies

```
3.x ─┬─ format-mason-js ─┬─ iframe-message-source
     │                   └─ nested-bricks ─┬─ brick-uids ── locked-structure
     │                                     └─ container-areas
     ├─ preview-data
     └─ docs-security
```

## Good to know

- All branches pass `composer test` on their own, and together (270 tests).
- Merging `locked-structure` and `container-areas`, or `preview-data` and `nested-bricks`, gives small conflicts: both add parameters or lines at the same function heads (`handleInsertBlock`, `moveBlock` in `mason.js`; `getBlockHtml` in `IframeRenderer`).
- The workbench CSS in `nested-bricks`, `locked-structure` and `container-areas` was rebuilt with Filament 5.10.1, as there is no `composer.lock`. The diff in `workbench/resources/dist/*.css` therefore also contains Filament's own changes; `npm run build:workbench` with your setup gives a clean one.
- Existing behaviour changes in two places only: `updateBrick` keeps attributes the new brick does not set, and a container's bricks (`nested-bricks`); and every brick gets an `attrs.uid` (`brick-uids`). Everything else is opt-in. Index arguments to `BrickCommand` keep working.
