# EDS Demo Palette Switcher

Lets visitors to Elite Digital Solutions demo sites temporarily preview the color palettes that the active EDS theme provides.

**Scope: color palettes only.** The plugin never touches typography, layout, section order, animations, headers, button shapes, border radius, content, or images.

The theme owns the palettes. The plugin only receives what the theme registers, displays it, and (in a later version) applies the visitor's choice client-side through `localStorage`. There are no database writes, no Customizer or option changes, and no page reloads, so it stays safe behind full-page caches such as Cloudflare. The plugin ships no palettes of its own.

> Status: v0.1.0 contains the integration contract only. It has no frontend output yet.

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
php tests/contract-check.php
```
