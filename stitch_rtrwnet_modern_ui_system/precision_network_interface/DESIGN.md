---
name: Precision Network Interface
colors:
  surface: '#faf8ff'
  surface-dim: '#d9d9e5'
  surface-bright: '#faf8ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f3f3fe'
  surface-container: '#ededf9'
  surface-container-high: '#e7e7f3'
  surface-container-highest: '#e1e2ed'
  on-surface: '#191b23'
  on-surface-variant: '#434655'
  inverse-surface: '#2e3039'
  inverse-on-surface: '#f0f0fb'
  outline: '#737686'
  outline-variant: '#c3c6d7'
  surface-tint: '#0053db'
  primary: '#004ac6'
  on-primary: '#ffffff'
  primary-container: '#2563eb'
  on-primary-container: '#eeefff'
  inverse-primary: '#b4c5ff'
  secondary: '#505f76'
  on-secondary: '#ffffff'
  secondary-container: '#d0e1fb'
  on-secondary-container: '#54647a'
  tertiary: '#943700'
  on-tertiary: '#ffffff'
  tertiary-container: '#bc4800'
  on-tertiary-container: '#ffede6'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dbe1ff'
  primary-fixed-dim: '#b4c5ff'
  on-primary-fixed: '#00174b'
  on-primary-fixed-variant: '#003ea8'
  secondary-fixed: '#d3e4fe'
  secondary-fixed-dim: '#b7c8e1'
  on-secondary-fixed: '#0b1c30'
  on-secondary-fixed-variant: '#38485d'
  tertiary-fixed: '#ffdbcd'
  tertiary-fixed-dim: '#ffb596'
  on-tertiary-fixed: '#360f00'
  on-tertiary-fixed-variant: '#7d2d00'
  background: '#faf8ff'
  on-background: '#191b23'
  surface-variant: '#e1e2ed'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 60px
    letterSpacing: -0.02em
  display-lg-mobile:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-sm:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.05em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  unit: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  container-margin-mobile: 16px
  container-margin-desktop: 32px
  gutter: 16px
---

## Brand & Style

The design system is engineered for the high-utility environment of ISP management. The brand personality is **Professional, Systematic, and Resilient**. It prioritizes clarity over decoration, ensuring that network administrators can diagnose issues and manage subscribers with zero cognitive friction.

The visual style is **Corporate / Modern**, leaning into a highly structured, systematic approach. It utilizes a "Utility-First" aesthetic: a clean white-label feel that emphasizes data density and functional hierarchy. The emotional response should be one of absolute control and reliability, achieved through generous whitespace, consistent alignment, and a technical color palette.

## Colors

The palette is anchored by a high-trust **Signal Blue** (#2563EB) for primary actions and brand presence. The neutral scale is heavily weighted toward cool slates to maintain a technical, "SaaS-like" atmosphere.

- **Primary:** Used for core CTAs, active navigation states, and primary data highlights.
- **Secondary:** Used for auxiliary actions, icon fills, and subtle UI elements.
- **Semantic (Success/Warning/Error):** Strictly reserved for status indicators (e.g., "Active", "Late Payment", "Fiber Cut").
- **Surface & Background:** A subtle distinction between `#F8FAFC` (Canvas) and `#FFFFFF` (Component) provides depth without the need for heavy shadows.

## Typography

The design system utilizes **Inter** exclusively to leverage its exceptional legibility in data-heavy interfaces. 

- **Hierarchy:** Display styles are used for dashboard overviews (e.g., Total Revenue). Headlines are for page titles and card sections.
- **Readability:** Body-md is the default for all content. For dense tables or sidebars, body-sm is preferred.
- **Systematic Labels:** Labels use a medium or semi-bold weight and slight tracking to distinguish them from interactive text.
- **Mobile Scaling:** Large display types scale down significantly on mobile to maintain vertical rhythm.

## Layout & Spacing

The system follows a **4px baseline grid**. All margins and paddings must be multiples of this unit.

- **Desktop:** A 12-column fluid grid is used for the main content area with a fixed 280px sidebar. 
- **Mobile:** A single-column layout with 16px side margins. Sidebars transition to a bottom-sheet or hamburger drawer.
- **Progressive Disclosure:** Large data sets use "Show More" patterns or paginated tables to prevent "information overwhelm" on mobile devices.
- **Rhythm:** Use `lg` (24px) for spacing between major cards and `md` (16px) for internal card padding.

## Elevation & Depth

This design system uses **Tonal Layering** combined with **Ambient Shadows**. Depth is used to signify interactivity and priority:

- **Level 0 (Background):** `#F8FAFC` - The canvas.
- **Level 1 (Cards/Surface):** `#FFFFFF` with a 1px border of `#E2E8F0`. No shadow. Used for static content.
- **Level 2 (Interactive):** Low-opacity, diffused shadow (0px 4px 6px -1px rgba(0,0,0,0.1)). Used for hover states on buttons and clickable cards.
- **Level 3 (Overlay):** Stronger shadow (0px 10px 15px -3px rgba(0,0,0,0.1)). Used for modals, dropdowns, and mobile navigation menus.

## Shapes

The design system uses a **Rounded** approach to soften the technical nature of the software, making it more approachable for daily use.

- **Base Radius:** 8px (0.5rem) for primary components like buttons, input fields, and small cards.
- **Large Radius:** 16px (1rem) for main dashboard containers or empty state illustrations.
- **Pill:** Fully rounded (999px) for status badges and tags only.

## Components

### Buttons
- **Primary:** Solid Blue (#2563EB), White text, 8px radius. High-emphasis actions.
- **Secondary:** Bordered (#E2E8F0), Dark Slate text. For auxiliary actions.
- **Ghost:** No background/border. Used for table actions or navigation.

### Cards
Cards are the primary container. They must feature a 1px border (#E2E8F0) and 16px - 24px padding. Headers within cards should have a subtle bottom border to separate titles from content.

### Tables
Tables are the heart of the ISP manager. Use a "Zebra" striping pattern for rows on hover. Column headers should use `label-sm` in Secondary color (#64748B). Rows should have a minimum height of 48px for touch targets.

### Badges (Status)
Small, pill-shaped indicators.
- **Active:** Light green background with dark green text.
- **Inactive:** Light slate background with dark slate text.
- **Overdue:** Light red background with dark red text.

### Input Fields
Inputs use an 8px radius and a 1px border. On focus, the border transitions to Primary Blue with a subtle 2px outer glow (ring). Labels must always be visible (no placeholder-only labels) to meet accessibility standards.

### Navigation
Vertical sidebar for desktop with icons and text. On mobile, transition to a bottom navigation bar for the most used features: Dashboard, Subscribers, Billing, and Support.