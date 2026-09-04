---
name: Kinetic Campus System
light_colors:
  surface: '#f7f9fb'
  surface-dim: '#cbd5e1'
  surface-bright: '#f7f9fb'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f1f5f9'
  surface-container: '#e2e8f0'
  surface-container-high: '#cbd5e1'
  surface-container-highest: '#94a3b8'
  on-surface: '#0f172a'
  on-surface-variant: '#475569'
  inverse-surface: '#0f131b'
  inverse-on-surface: '#f8fafc'
  outline: '#64748b'
  outline-variant: '#cbd5e1'
  surface-tint: '#9f3c27'
  primary: '#9f3c27'
  on-primary: '#ffffff'
  primary-container: '#822714'
  on-primary-container: '#ffffff'
  inverse-primary: '#ff8a75'
  secondary: '#6d28d9'
  on-secondary: '#ffffff'
  secondary-container: '#5b21b6'
  on-secondary-container: '#ffffff'
  tertiary: '#475569'
  on-tertiary: '#ffffff'
  tertiary-container: '#334155'
  on-tertiary-container: '#ffffff'
  error: '#b91c1c'
  on-error: '#ffffff'
  error-container: '#fef2f2'
  on-error-container: '#991b1b'
  background: '#f7f9fb'
  on-background: '#0f172a'
  surface-variant: '#e2e8f0'
dark_colors:
  background: '#0f131b'
  on-background: '#f8fafc'
  surface: '#181c25'
  surface-dim: '#0f131b'
  surface-bright: '#1e222a'
  surface-container-lowest: '#080a0f'
  surface-container-low: '#141822'
  surface-container: '#181c25'
  surface-container-high: '#1e222a'
  surface-container-highest: '#282e3d'
  on-surface: '#f8fafc'
  on-surface-variant: '#cbd5e1'
  inverse-surface: '#f8fafc'
  inverse-on-surface: '#0f172a'
  outline: '#94a3b8'
  outline-variant: 'rgba(255, 255, 255, 0.15)'
  surface-tint: '#ff8a75'
  primary: '#ff8a75'
  on-primary: '#0f131b'
  primary-container: '#d8583b'
  on-primary-container: '#ffffff'
  inverse-primary: '#9f3c27'
  secondary: '#c4b5fd'
  on-secondary: '#0f131b'
  secondary-container: '#8b5cf6'
  on-secondary-container: '#ffffff'
  tertiary: '#cbd5e1'
  on-tertiary: '#0f131b'
  tertiary-container: '#475569'
  on-tertiary-container: '#f8fafc'
  error: '#fca5a5'
  on-error: '#450a0a'
  error-container: '#450a0a'
  on-error-container: '#fecaca'
  surface-variant: '#1e222a'
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
The design system focuses on a high-energy, academic, and technical atmosphere tailored for modern learning environments. The brand personality is efficient, intellectually stimulating, and forward-thinking. It targets students, educators, and researchers who require a high-utility interface that remains visually engaging across both Light and Dark themes.

The design style is a hybrid of **Minimalism** and **Modern Corporate**, utilizing generous white space and high-contrast typography to ensure readability, while incorporating vibrant accent colors for an energetic, "kinetic" feel. The interface prioritizes clarity and functional density, ensuring that complex information remains digestible through clear visual hierarchies and subtle tactile feedback.

## Colors
The palette is centered on a clean, professional base with high-visibility accents, fully specified for both **Kinetic Campus Light** and **Kinetic Campus Dark** themes.

- **Primary (Vibrant Terracotta - #E06C53 / #9F3C27):** Used for critical actions, alerts, and primary branding. Maintains energetic warmth while meeting WCAG 2.1 contrast standards.
- **Secondary (Soft Electric Violet - #8B5CF6 / #D0BCFF):** Reserved for active states, progress indicators, focused fields, and navigational highlights.
- **Tertiary (Deep Slate / Crisp Light Slate):** The foundational color for secondary text and structural markers.
- **Neutral / Background:** 
  - *Light Theme:* An off-white (#F7F9FB) canvas with crisp white cards (#FFFFFF).
  - *Dark Theme:* A Deep Obsidian Slate (#0F131B) canvas with dark glass surface containers (#141822 / #181C25 / #1E222A) and subtle glowing accents.

## Typography
The design system utilizes **Geist** exclusively to convey a technical, developer-friendly, and precise aesthetic. 

- **Headlines:** Use Bold and SemiBold weights with tighter letter-spacing to create a strong visual anchor.
- **Body:** Standardized at 16px for optimal reading, utilizing high contrast against both light (#191C1E) and dark (#E0E3E5) backgrounds.
- **Labels:** Use Medium and SemiBold weights. Small labels often utilize uppercase styling to distinguish meta-data from body content.
- **Scaling:** On mobile devices, display and large headline sizes scale down by approximately 25% to maintain composition integrity within narrower viewports.

## Layout & Spacing
This design system employs a **Fluid Grid** approach based on an 8px spatial rhythm. 

- **Desktop:** 12-column grid with 24px gutters and 48px outside margins. Content containers should typically max out at 1280px.
- **Tablet:** 8-column grid with 20px gutters and 32px margins.
- **Mobile:** 4-column grid with 16px gutters and 16px margins.

Spacing should be applied using the defined increments to maintain mathematical harmony. Components like cards and sections should use `md` (24px) for internal padding to ensure a breathable, "campus" feel.

## Elevation & Depth
Elevation is achieved through **Low-Contrast Outlines** and subtle tonal layering rather than heavy shadows.

- **Level 0 (Background):** #F7F9FB (Light) / #0F131B (Dark). The lowest layer.
- **Level 1 (Cards/Surfaces):** #FFFFFF (Light) / #181C25 (Dark) with a 1px solid border (#E2E8F0 in light, `rgba(255, 255, 255, 0.08)` in dark).
- **Level 2 (Hover/Active):** Soft diffused shadow (`0 4px 12px rgba(30, 34, 42, 0.05)` in light, `0 4px 16px rgba(0, 0, 0, 0.4)` in dark) with subtle accent border glow.
- **Level 3 (Modals/Popovers):** Higher contrast borders and pronounced separation (`0 12px 32px rgba(30, 34, 42, 0.1)` in light, `0 12px 32px rgba(0, 0, 0, 0.6)` in dark).

## Shapes
The shape language follows a "Round Eight" philosophy, which aligns with the 8px spacing system. 

- **Small Components (Buttons, Inputs, Tags):** 0.5rem (8px) radius.
- **Medium Components (Cards, Modals):** 1rem (16px) radius.
- **Large Components (Sections, Hero Containers):** 1.5rem (24px) radius.

## Components
- **Buttons:** Primary buttons use solid Terracotta (#E06C53) background with white text. Secondary buttons use transparent/white background with Electric Violet (#8B5CF6) border. Ghost buttons use subtle hover shifts.
- **Input Fields:** 1px border (#E2E8F0 in light, `rgba(255, 255, 255, 0.12)` in dark) transitioning to Electric Violet (#8B5CF6) on focus.
- **Cards:** Crisp surface layer, 1px border, and 24px padding. Titles use Headline-MD.
- **Chips/Tags & Badges:** Rounded pill shapes with distinct status color fills in light mode and deep ambient fills in dark mode.
- **Lists & Tables:** Clean rows separated by thin 1px dividers with subtle hover highlights (`#F8FAFC` in light, `#1E222A` in dark).
- **Checkboxes & Radios:** Use Electric Violet for checked state to differentiate selection from primary action.