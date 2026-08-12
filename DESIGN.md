---
name: Modern Pastoral
colors:
  surface: '#fbf9f5'
  surface-dim: '#dbdad6'
  surface-bright: '#fbf9f5'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f5f3ef'
  surface-container: '#efeeea'
  surface-container-high: '#e9e8e4'
  surface-container-highest: '#e3e2de'
  on-surface: '#1b1c1a'
  on-surface-variant: '#434843'
  inverse-surface: '#30312e'
  inverse-on-surface: '#f2f1ed'
  outline: '#737973'
  outline-variant: '#c3c8c1'
  surface-tint: '#4d6453'
  primary: '#061b0e'
  on-primary: '#ffffff'
  primary-container: '#1b3022'
  on-primary-container: '#819986'
  inverse-primary: '#b4cdb8'
  secondary: '#5e5f5c'
  on-secondary: '#ffffff'
  secondary-container: '#e0e0dc'
  on-secondary-container: '#626360'
  tertiary: '#171718'
  on-tertiary: '#ffffff'
  tertiary-container: '#2c2c2c'
  on-tertiary-container: '#949393'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d0e9d4'
  primary-fixed-dim: '#b4cdb8'
  on-primary-fixed: '#0b2013'
  on-primary-fixed-variant: '#364c3c'
  secondary-fixed: '#e3e2df'
  secondary-fixed-dim: '#c7c7c3'
  on-secondary-fixed: '#1b1c1a'
  on-secondary-fixed-variant: '#464744'
  tertiary-fixed: '#e4e2e2'
  tertiary-fixed-dim: '#c8c6c6'
  on-tertiary-fixed: '#1b1c1c'
  on-tertiary-fixed-variant: '#474747'
  background: '#fbf9f5'
  on-background: '#1b1c1a'
  surface-variant: '#e3e2de'
typography:
  display-lg:
    fontFamily: Playfair Display
    fontSize: 48px
    fontWeight: '700'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Playfair Display
    fontSize: 32px
    fontWeight: '600'
    lineHeight: '1.3'
  headline-lg-mobile:
    fontFamily: Playfair Display
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.3'
  headline-md:
    fontFamily: Playfair Display
    fontSize: 24px
    fontWeight: '500'
    lineHeight: '1.4'
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.5'
  label-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: 0.05em
  data-mono:
    fontFamily: Inter
    fontSize: 15px
    fontWeight: '500'
    lineHeight: '1.2'
    letterSpacing: -0.01em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 8px
  container-margin: 40px
  gutter: 24px
  bento-gap: 16px
  section-padding: 64px
---

## Brand & Style
The design system embodies "Modern Pastoral"—a synthesis of heritage artisanal quality and precision agricultural technology. It is designed for a premium dairy management experience, prioritizing clarity, calm, and a sense of natural origin.

The visual style is **Minimalist** with a **Bento Grid** structural philosophy. It leverages heavy whitespace and a restricted, high-end color palette to reduce cognitive load in data-heavy environments. The aesthetic should feel like a high-end editorial magazine met a sophisticated SaaS dashboard: clean, spacious, and grounded.

## Colors
The palette is rooted in organic tones.
- **Primary (#1B3022):** "Deep Forest Green." Used for primary call-to-actions, active navigation states, and branding elements.
- **Secondary/Background (#F9F8F4):** "Creamy Off-White." This serves as the primary canvas color, providing a softer, more premium feel than pure white.
- **Tertiary/Text (#4A4A4A):** "Slate Grey." Used for body copy and secondary UI labels to maintain high legibility without the harshness of pure black.
- **Neutral (#E5E4E0):** Used for borders, dividers, and disabled states.

## Typography
The typography strategy creates a high-contrast hierarchy between "Heritage" and "Utility." 

**Playfair Display** is reserved for large titles, page headers, and significant metric summaries to evoke a sense of tradition and artisanal craftsmanship. 

**Inter** is the workhorse for all functional UI elements. Use "Data-Mono" (Inter with tabular figures enabled) for milk yields, livestock IDs, and financial figures to ensure vertical alignment in tables.

## Layout & Spacing
The system utilizes a **Bento Grid** modularity for dashboards. 
- **Grid Strategy:** A 12-column fluid grid for desktop with 24px gutters. Dashboard modules should snap to 3, 4, 6, or 12 column widths.
- **Modularity:** Each "Bento Box" (card) should have a consistent internal padding of 32px to maintain the spacious, premium feel.
- **Mobile:** Transition to a single-column stack with 16px side margins. 
- **Vertical Rhythm:** Use increments of 8px (the "Base" unit) for all element spacing to ensure mathematical harmony.

## Elevation & Depth
In alignment with the minimalist pastoral style, depth is achieved through **Tonal Layers** and **Ambient Shadows** rather than heavy gradients.
- **Surface:** The main background is `#F9F8F4`.
- **Level 1 (Cards):** Use a pure white background (`#FFFFFF`) to pop against the off-white base.
- **Shadows:** Use extremely soft, diffused shadows: `0px 4px 20px rgba(27, 48, 34, 0.04)`. The slight green tint in the shadow maintains the organic palette.
- **Borders:** Use a subtle 1px border of `#E5E4E0` on cards and inputs to define boundaries without adding visual noise.

## Shapes
Shapes are intentionally soft to reflect the organic nature of dairy farming. 
- **Cards/Bento Boxes:** Use `rounded-xl` (1.5rem / 24px) for the primary container corners.
- **Buttons & Inputs:** Use `rounded-lg` (1rem / 16px) to maintain a friendly but professional appearance.
- **Icons:** Should feature rounded terminals and a consistent 2px stroke weight.

## Components
- **Bento Cards:** The core component. Must contain a header with a serif title (Playfair Display) and a content area for Inter-based data.
- **Primary Buttons:** Solid Deep Forest Green with white text. High padding (12px 24px) and bold Inter labels.
- **Data Tables:** Use a "Ghost" style. No vertical lines; only horizontal separators in light neutral. Headers should be uppercase `label-sm`. Rows should have a subtle hover state using the off-white background.
- **Status Chips:** Used for livestock health or production status. Low-saturation backgrounds with high-saturation text (e.g., pale green background with deep green text).
- **Input Fields:** Large tap targets (48px height) with soft rounded corners. Focus states should use a 2px Deep Forest Green border.
- **Progress Bars:** Representing milk tank capacity or feed levels. Use a thick 8px track with rounded caps.