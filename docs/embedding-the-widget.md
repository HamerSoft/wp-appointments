# Embedding the Booking Widget

The booking widget can be added to any page on your site in two ways: via the **Divi Visual Builder** (recommended) or via a **shortcode** as a fallback.

---

## Option 1 — Divi Visual Builder

1. Open the page where you want the booking form in the Divi Visual Builder.
2. Add a new **Row** (or use an existing one).
3. Click the **+** icon to insert a module.
4. Search for **"Booking Widget"** in the module list and select it.
5. The widget will appear in the builder as a placeholder. No settings need to be configured.
6. Save and publish the page.

> **Note:** The Booking Widget module does not have any visual settings panel — the widget is fully self-contained and renders on the live page.

---

## Option 2 — Shortcode

If you are not using the Divi Visual Builder, or you want to embed the widget in a text/code block:

1. Edit the page where you want the booking form.
2. Add a **Code** module (Divi) or a standard **Shortcode** block (Gutenberg).
3. Paste the following shortcode:

   ```
   [wpappt_booking]
   ```

4. Save and publish the page.

---

## Recommendations

- **One widget per page.** The widget uses a fixed element ID (`wpappt-booking-widget`). Only embed it once per page.
- **Dedicated booking page.** Create a page called "Book a Session" (or similar) and embed the widget there. Link to it from your navigation menu.
- **Page width.** The widget has a maximum width of 640 px and centres itself. It works best in a single-column Divi row with no sidebar.

---

## Customising the appearance

The widget's colours are controlled by CSS custom properties defined on `#wpappt-booking-widget`. You can override them by adding custom CSS to your WordPress theme (**Divi → Theme Options → Custom CSS**, or **Appearance → Customize → Additional CSS**):

```css
#wpappt-booking-widget {
    --wpappt-primary:      #your-brand-colour;
    --wpappt-primary-dark: #your-brand-colour-darker;
    --wpappt-primary-bg:   #your-brand-colour-light-tint;
}
```

The default values are:

| Variable               | Default   | Used for                              |
|------------------------|-----------|---------------------------------------|
| `--wpappt-primary`     | `#2c6e49` | Buttons, selected states, progress bar |
| `--wpappt-primary-dark`| `#1e4d34` | Button hover state                    |
| `--wpappt-primary-bg`  | `#edf5f0` | Selected card backgrounds, calendar header |
