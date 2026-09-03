---
name: Kinetic Campus Light
colors:
  surface: '#f7f9fb'
  surface-dim: '#d8dadc'
  surface-bright: '#f7f9fb'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f4f6'
  surface-container: '#eceef0'
  surface-container-high: '#e6e8ea'
  surface-container-highest: '#e0e3e5'
  on-surface: '#191c1e'
  on-surface-variant: '#57423d'
  inverse-surface: '#2d3133'
  inverse-on-surface: '#eff1f3'
  outline: '#8a726c'
  outline-variant: '#ddc0ba'
  surface-tint: '#a23e29'
  primary: '#9f3c27'
  on-primary: '#ffffff'
  primary-container: '#bf533c'
  on-primary-container: '#fffbff'
  inverse-primary: '#ffb4a4'
  secondary: '#6b38d4'
  on-secondary: '#ffffff'
  secondary-container: '#8455ef'
  on-secondary-container: '#fffbff'
  tertiary: '#585c65'
  on-tertiary: '#ffffff'
  tertiary-container: '#71747e'
  on-tertiary-container: '#fefcff'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#ffdad3'
  primary-fixed-dim: '#ffb4a4'
  on-primary-fixed: '#3e0500'
  on-primary-fixed-variant: '#822714'
  secondary-fixed: '#e9ddff'
  secondary-fixed-dim: '#d0bcff'
  on-secondary-fixed: '#23005c'
  on-secondary-fixed-variant: '#5516be'
  tertiary-fixed: '#dfe2ed'
  tertiary-fixed-dim: '#c3c6d1'
  on-tertiary-fixed: '#181c23'
  on-tertiary-fixed-variant: '#434750'
  background: '#f7f9fb'
  on-background: '#191c1e'
  surface-variant: '#e0e3e5'
typography:
  display:
    fontFamily: Geist
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Geist
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Geist
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-md:
    fontFamily: Geist
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  body-lg:
    fontFamily: Geist
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Geist
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: Geist
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Geist
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 8px
  xs: 4px
  sm: 12px
  md: 24px
  lg: 40px
  xl: 64px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 48px
---

## Brand & Style
The design system focuses on a high-energy, academic, and technical atmosphere tailored for modern learning environments. The brand personality is efficient, intellectually stimulating, and forward-thinking. It targets students, educators, and researchers who require a high-utility interface that remains visually engaging.

The design style is a hybrid of **Minimalism** and **Modern Corporate**, utilizing generous white space and high-contrast typography to ensure readability, while incorporating vibrant accent colors for an energetic, "kinetic" feel. The interface prioritizes clarity and functional density, ensuring that complex information remains digestible through clear visual hierarchies and subtle tactile feedback.

## Colors
The palette is centered on a clean, professional base with high-visibility accents.

- **Primary (Vibrant Terracotta):** Used for critical actions, alerts, and primary branding. On the light background, this hue is adjusted for optimal WCAG 2.1 compliance while maintaining its energetic warmth.
- **Secondary (Soft Electric Violet):** Reserved for active states, progress indicators, and navigational highlights. This adds a "kinetic" digital feel to the academic context.
- **Tertiary (Deep Slate):** The foundational color for all primary text, ensuring maximum legibility and a grounded, professional tone.
- **Neutral/Background:** An off-white (#F8FAFC) creates a soft canvas that reduces eye strain compared to pure white, while **Surface** (#FFFFFF) creates distinct physical layers for content cards.

## Typography
The design system utilizes **Geist** exclusively to convey a technical, developer-friendly, and precise aesthetic. 

- **Headlines:** Use Bold and SemiBold weights with tighter letter-spacing to create a strong visual anchor.
- **Body:** Standardized at 16px for optimal reading, utilizing the regular weight for maximum clarity against the light background.
- **Labels:** Use Medium and SemiBold weights. Small labels often utilize uppercase styling to distinguish meta-data from body content.
- **Scaling:** On mobile devices, display and large headline sizes scale down by approximately 25% to maintain composition integrity within narrower viewports.

## Layout & Spacing
This design system employs a **Fluid Grid** approach based on an 8px spatial rhythm. 

- **Desktop:** 12-column grid with 24px gutters and 48px outside margins. Content containers should typically max out at 1280px.
- **Tablet:** 8-column grid with 20px gutters and 32px margins.
- **Mobile:** 4-column grid with 16px gutters and 16px margins.

Spacing should be applied using the defined increments to maintain mathematical harmony. Components like cards and sections should use `md` (24px) for internal padding to ensure a breathable, "campus" feel.

## Elevation & Depth
Elevation is primarily achieved through **Low-Contrast Outlines** and subtle tonal layering rather than heavy shadows.

- **Level 0 (Background):** #F8FAFC. The lowest layer.
- **Level 1 (Cards/Surfaces):** #FFFFFF with a 1px solid border of #E2E8F0. No shadow is applied in the default state to maintain a clean, flat aesthetic.
- **Level 2 (Hover/Active):** When an element is interacted with, apply a very soft, diffused shadow: `0 4px 12px rgba(30, 34, 42, 0.05)` and increase the border contrast slightly.
- **Level 3 (Modals/Popovers):** Higher contrast borders and a more pronounced shadow to separate the element from the underlying UI: `0 12px 32px rgba(30, 34, 42, 0.1)`.

## Shapes
The shape language follows a "Round Eight" philosophy, which aligns with the 8px spacing system. 

- **Small Components (Buttons, Inputs, Tags):** 0.5rem (8px) radius.
- **Medium Components (Cards, Modals):** 1rem (16px) radius.
- **Large Components (Sections, Hero Containers):** 1.5rem (24px) radius.

This consistency in curvature softens the technical nature of the Geist typeface, making the academic environment feel more approachable and modern.

## Components
- **Buttons:** Primary buttons use a solid Terracotta (#E06C53) background with white text. Secondary buttons use a white background with a Slate (#1E222A) border. Tertiary/Ghost buttons use Slate text with no background.
- **Input Fields:** 1px border (#E2E8F0) that transitions to Electric Violet (#8B5CF6) on focus. Labels sit above the field in Label-MD styling.
- **Cards:** Pure white background, 1px border (#E2E8F0), and 24px padding. Titles within cards should use Headline-MD.
- **Chips/Tags:** Small, rounded-pill shapes with a light gray background (#F1F5F9) and Slate text. Active/Selected chips use a Violet tint (#EDE9FE) with Violet text (#8B5CF6).
- **Lists:** Clean rows separated by thin 1px lines (#E2E8F0). Hover states for list items should utilize a subtle background shift to #F8FAFC.
- **Checkboxes & Radios:** Use Electric Violet for the checked state to differentiate functional selection from primary "action" (Terracotta).