---
title: Security
description: What Mason trusts, what it does not, and what bricks have to do themselves.
---

# Security

## The editor state comes from the browser

The field's state is entangled with the browser. Anyone who can edit a record can send any content for the field, not only what the editor's buttons produce: any brick id, any config, any attributes. Bricks therefore treat their config as untrusted input:

- Escape values in Blade with `{{ }}`. Output HTML from a rich editor or other free text only after sanitizing it, e.g. with `Str::sanitizeHtml()`.
- Check values that pick options (colours, sizes, layouts) against the allowed options instead of passing them on.
- Do not take markers or rendered HTML from the config. If a brick needs HTML that its caller computed (e.g. a container that receives HTML for an area), pass it as an object such as `HtmlString`, which cannot come from stored JSON, and ignore strings under that key.

`toUnsafeHtml()` outputs the bricks' HTML as it is. Use it only for bricks that sanitize their own output.

## Attributes from drop zones

Drop zones of container areas insert and move bricks with attributes (`dropZone(['slot' => 'a'])`). They come from the browser as well. `BrickAction` never lets them replace `id`, `config`, `uid`, `label`, `preview` or `outlineLabel`; other keys are stored as given. Containers compare these values, they should not output them unescaped.

## Locked structure

`lockedStructure()` hides the controls for adding, moving and removing bricks and refuses the matching commands on the server. Deleting and moving happen in the browser, though, so only `comparedTo` makes the lock binding: the structure is validated against it on save.

## Iframe context and data

The preview and entry render in an iframe fed by a separate request.

- The bricks, the layout and the preview data (`previewData()`) travel in a signed render context. A client can replay it, but cannot change it. It is signed, not encrypted: preview data is readable in the page, so it must hold nothing secret.
- The entry's render data (`MasonEntry::data()`) is encrypted, models are reduced to class and key.

## Known issue: messages between editor and iframe

The editor and its iframe talk via `postMessage` and do not check where a message comes from (`event.origin` / `event.source`). Another window that holds a reference to the admin window, e.g. a page opened from it without `rel="noopener"`, or a page that frames the admin, can send editor commands: delete, move or reorder bricks, change their attributes, open the brick picker. Nothing is saved until the user saves, and new content always goes through the brick's form.

Planned fix: the editor accepts messages only from its own iframe (`event.source === iframe.contentWindow`), the iframe only from its parent (`event.source === window.parent`). Until then, keep the admin from being framed (`X-Frame-Options` / `frame-ancestors`) and open external links with `rel="noopener"`.
