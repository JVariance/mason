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

## Locked bricks in templates

Locks of bricks a container comes with (`HasBrickTemplate`) follow the template in the code. A lock stored with the content is ignored, and a locked brick that is missing is added again when the content is saved. `attrs.hidden` comes from the browser like everything else: it only decides whether a brick is rendered.

## Locked structure

`lockedStructure()` hides the controls for adding, moving and removing bricks and refuses the matching commands on the server. Deleting and moving happen in the browser, though, so only `comparedTo` makes the lock binding: the structure is validated against it on save.

## Iframe context and data

The preview and entry render in an iframe fed by a separate request.

- The bricks, the layout and the preview data (`previewData()`) travel in a signed render context. A client can replay it, but cannot change it. It is signed, not encrypted: preview data is readable in the page, so it must hold nothing secret.
- The entry's render data (`MasonEntry::data()`) is encrypted, models are reduced to class and key.

## Messages between editor and iframe

The editor and its iframe talk via `postMessage`. The editor handles only messages from its own iframe (`event.source === iframe.contentWindow`), the iframe only those from the window around it (`event.source === window.parent`), so other windows that can reach the admin, e.g. a page opened from it, cannot send editor commands.
