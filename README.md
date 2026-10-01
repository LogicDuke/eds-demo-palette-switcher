# EDS Demo Palette Switcher

Lets visitors to Elite Digital Solutions demo sites temporarily preview the color palettes that the active EDS theme provides.

**Scope: color palettes only.** The plugin never touches typography, layout, section order, animations, headers, button shapes, border radius, content, or images.

The theme owns the palettes. The plugin only receives what the theme registers, displays it, and applies the visitor's choice client-side through `localStorage`. There are no database writes, no Customizer or option changes, and no page reloads, so it stays safe behind full-page caches such as Cloudflare. The plugin ships no palettes of its own.

> Status: v0.1.0. The integration contract, the client-side preview and the responsive switcher control. Themes integrate themselves through the API below.

## Admin setting

There is one setting: **Settings → General → Demo Palette Switcher → Enable Demo Palette Switcher**.

- It is **off by default**.
- It is stored in the `eds_dps_enabled` option as `'1'` or `'0'`, and saved through the WordPress Settings API, which requires `manage_options`.
- Visitors never write to it.
- Uninstalling the plugin (Delete on the Plugins screen) removes this option. Nothing else is removed, because nothing else is stored; theme settings and palette definitions belong to the theme.

## When the frontend runs

The plugin prints nothing unless **all** of the following are true:

1. the setting is enabled;
2. `eds_dps_get_integration()` returns a valid integration;
3. that integration has at least one palette;
4. the request is not a Customizer preview (there, a stored visitor choice must not mask the palette being edited).

Otherwise the plugin is inert and the theme renders exactly as it would without it.

## How the preview works

### No-flash bootstrap

A tiny inline script runs at `wp_head` priority 1, before the theme's styles. It:

1. reads `localStorage['eds-dps:<integration_id>']`;
2. applies it as `<html data-eds-demo-palette="<id>">`, but only if the stored value is one of the registered palette IDs that PHP printed into the page.

If storage is unavailable or the stored value is unknown, it does nothing. It never throws and never waits for other scripts. The same inline script also exposes the page configuration as `window.edsDemoPalette`.

### Palette CSS

A `<style id="eds-dps-palettes">` block is printed late in `<head>` (`wp_head` priority 99), generated only from validated registration data. It contains one rule per palette:

```css
html[data-eds-demo-palette="dark"]{--example-bg:#000;--example-text:#fff;}
```

`html[attr]` is more specific than `:root`, so these rules win over the theme's defaults. **The theme must define its palette variables on `:root` or `html`** for the override to reach them. Visitors can never supply CSS.

### Persistence

Choosing a palette:

- sets the `html` attribute immediately;
- stores **only the palette ID** under `eds-dps:<integration_id>` in that visitor's `localStorage`.

The preview needs no reloads, AJAX requests, cookies, database writes, option changes or Customizer changes. If storage is blocked, the preview still applies on the current page but isn't remembered.

### Reset to Default

Reset removes the stored key and removes the `data-eds-demo-palette` attribute. The theme's own CSS then applies again, showing the palette WordPress is configured to use. Choosing the default palette by its button is different: it stores the choice explicitly, like any other palette.

### Cloudflare and page caching

Every response is identical for every visitor: the bootstrap, the CSS and the panel markup are the same, and the per-visitor choice lives only in the browser. The plugin sets no cookies or `Vary` headers, so pages are safe to cache at the edge.

## The switcher control

The switcher is a small **Try Colors** button that opens an **EDS Demo Colors** panel. It uses its own neutral EDS look, defined as `--eds-dps-ui-*` variables, so it looks identical under every palette and never takes the theme's colours. All of its styles are scoped to `.eds-dps`, it uses no fonts or images from outside the page, and it is hidden when printing.

The panel contains:

- the title and a one-line description;
- a close button;
- a **Now showing** line with the current palette, marked "(site default)" when there is no preview override;
- the palette list;
- **Reset to Default**.

Each palette row shows its swatches and name. The palette WordPress is configured to use is labelled "Site default", and the current palette is outlined and ticked.

### Desktop and tablet (768px and wider)

- The collapsed control is a slim tab fixed to the right edge, vertically centred, that stays visible while you scroll.
- The panel opens beside the tab, 300–344px wide, and is never taller than the viewport minus 48px.

### Mobile (narrower than 768px)

- A compact floating button sits at the bottom right.
- It opens a bottom sheet that is inset from the screen edges, at most 72% of the viewport height (and at most 520px wide).
- Both respect the device's safe areas (`env(safe-area-inset-*)`).
- Every control is at least 44px tall.

### Scrolling

The header and the Reset footer stay in place while the palette list scrolls on its own, using the browser's normal scrolling (`overflow-y: auto`). The list uses `overscroll-behavior: contain`, so reaching the end of the list doesn't start scrolling the page. The page itself is never locked.

### Opening and closing

- The tab or button toggles the panel, and its `aria-expanded` attribute reflects the state. It is linked to the panel with `aria-controls`.
- Opening moves focus to the current palette and scrolls it into view.
- Choosing a palette keeps the panel open so visitors can compare palettes.
- The panel collapses back to the tab when the visitor:
  - presses **Escape**;
  - clicks the close button;
  - clicks the tab or button again;
  - clicks anywhere outside the switcher.
- After Escape, the close button or the toggle, focus returns to the tab or button. An outside click leaves focus where the visitor clicked.
- The outside-click listener only observes clicks. It never stops or alters the theme's own clicks.
- Every page load starts collapsed. Collapsing is the only way to hide the switcher; nothing is hidden permanently.

### Accessibility

- All controls are real `<button>`s and work with the keyboard: Tab, Enter and Space.
- Every control shows a visible `:focus-visible` ring.
- Each palette button keeps `aria-pressed`.
- The **Now showing** line is announced politely (`aria-live="polite"`).
- The panel is a non-modal region labelled by its title.
- The close button has an accessible name.
- Swatches are decorative and hidden from assistive technology (`aria-hidden`).

### Motion

- **Opening:** the panel fades in and slides 12–14px into place in 280ms or less (a slide from the right on desktop, a rise from the bottom on mobile). This uses CSS `@starting-style`. Browsers without it simply show the panel without animating.
- **Closing:** the panel closes immediately.
- **With `prefers-reduced-motion: reduce`:** there is no slide, only a 120ms opacity change.

### Stacking

- The switcher uses `z-index: 9000`. That puts it above normal page content and sticky headers, but below consent banners that use higher values (EDS Consent uses 100000).
- Theme dialogs opened with `showModal()` render above it in the browser's top layer.
- The switcher stays `hidden` until its script runs, so visitors never see controls that don't work.

## Theme integration

Register your palettes on `after_setup_theme`. Guard the call so the theme keeps working when the plugin is inactive:

```php
add_action( 'after_setup_theme', function () {
	if ( function_exists( 'eds_dps_register_theme_palettes' ) ) {
		eds_dps_register_theme_palettes( [
			'integration_id'  => 'example-theme',
			'default_palette' => 'default', // The palette WordPress is currently configured to use.
			'palettes'        => [
				[
					'id'       => 'default',
					'name'     => 'Default',
					'swatches' => [ '#ffffff', '#111111' ],
					'vars'     => [
						'--example-background' => '#ffffff',
						'--example-text'       => '#111111',
					],
				],
			],
		] );
	}
} );
```

### Public API

The public contract is these two functions:

- `eds_dps_register_theme_palettes( array $config ): bool` returns `true` if the definition was stored and `false` if it was rejected.
- `eds_dps_get_integration(): ?array` returns the validated definition, or `null` if nothing valid is registered.

Functions that start with `_eds_dps_` are internal. They are not part of the contract and may change without notice.

### Registering more than once

- **The last valid registration wins.** Each successful call replaces the previous one completely.
- **A failed call changes nothing.** If a later registration is invalid, the previous valid registration stays in place.
- **Child themes:** WordPress loads a child theme's `functions.php` before the parent's. If both register at the same priority, the parent's call runs last and wins. A child theme that intentionally overrides its parent's palettes should register at a later priority:

  ```php
  add_action( 'after_setup_theme', 'my_child_register_palettes', 20 );
  ```

### Fields

| Key | Rules |
|---|---|
| `integration_id` | Required. A strict ID (see below). Identifies the theme integration and will namespace the visitor's stored choice. |
| `default_palette` | Required. A strict ID that must match the `id` of a palette that passes validation. Reset returns to this palette. |
| `palettes[].id` | Required. A strict ID. Must be unique: if two palettes share an ID, the first is kept and the rest are dropped. |
| `palettes[].name` | Required. Passed through `sanitize_text_field` and must not be empty afterwards. |
| `palettes[].swatches` | Required, non-empty. **Hex only in v0.1.0**: each entry must be a 3- or 6-digit hex color that `sanitize_hex_color` accepts (`#abc`, `#aabbcc`). 8-digit hex, named colors and color functions are rejected. If your `vars` use `oklch()` or similar, supply hex approximations here. |
| `palettes[].vars` | Required, non-empty. Maps CSS custom properties to color values (see below). |

### Strict IDs

An ID must already be a valid WordPress key: a non-empty string that `sanitize_key()` returns unchanged. In practice that means only lowercase `a–z`, `0–9`, `_` and `-`.

**IDs are rejected, never rewritten.** `Ocean`, `ocean blue` and `ocean!` are invalid; they are not converted to `ocean`. This prevents two different IDs from silently collapsing into one.

### Custom-property names

A name must match `^--[A-Za-z0-9_-]+` across the **entire** string, with nothing after it, not even a trailing newline.

### Custom-property values

A value must be at most 200 characters after trimming, and is built from:

- **Hex colors:** `#abc`, `#aabbcc`, `#aabbccdd`
- **Keywords:** CSS named colors, `transparent`, `currentColor`
- **Numbers and units:** `%`, `deg`, and the arithmetic operators `+ - * /`
- **Allowed functions only** (names are matched case-insensitively): `rgb` `rgba` `hsl` `hsla` `hwb` `lab` `lch` `oklab` `oklch` `color` `color-mix` `var` `calc` `min` `max` `clamp`

Relative color syntax works, for example `oklch(from var(--brand) calc(l * 0.9) c h)`.

**Any other function is rejected.** That includes `url()`, `src()`, `image()`, `image-set()`, `cross-fade()`, all gradients, `-moz-element()`, `expression()`, `attr()` and `env()`.

A value is also rejected if it:

- contains `;`, `{`, `}`, `<`, `>`, `:`, `@`, `!` (including `!important`), quotes or backslashes;
- contains a CSS comment (`/*` or `*/`);
- has unbalanced parentheses.

This leaves no way to break out of the declaration, inject selectors or raw CSS, or load URLs or images.

### Rejection rules

- If any field in a palette is invalid, the **whole palette** is dropped. A partial palette is never applied.
- The **whole integration** is rejected if `integration_id` is invalid, or if `default_palette` is invalid or doesn't match a valid palette. The plugin never guesses a fallback default.
- If nothing valid is registered, the plugin stays inert and the theme renders exactly as it would without it.

## Development

```sh
php tests/contract-check.php   # registration contract
php tests/frontend-check.php   # setting, eligibility, output; runs browser logic in Node (requires node)
```
