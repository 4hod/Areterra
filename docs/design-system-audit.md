# Areterra Hub design-system audit

## Purpose

This audit records the shared decisions behind the application-wide interface refinement. It is deliberately free of member data and operationally sensitive content.

## Research translated into product decisions

- Mature care systems prioritise quick recording, clear warnings, person-centred timelines and contextual information over decorative presentation.
- Staff should be able to record with large targets, predictable forms, pre-filled controls and minimal interruption to the workflow.
- Operational dashboards work best when they summarise exceptions and allow a direct drill-down, rather than presenting a large marketing-style hero.
- A single icon specification and reusable components reduce cognitive load and visual inconsistency.
- Responsive behaviour must be intentional. A tablet is not treated as a compressed desktop.

Reference material reviewed: Nourish Better Care, Better Care at Home, the Nourish Design Kit case study and Nourish handover workflow material.

## Audit findings

1. The prior visual layer had accumulated multiple successive refresh passes with conflicting breakpoint, hero, card and typography rules.
2. Desktop navigation began at 768px, causing the full sidebar to compete with content on portrait tablets and smaller laptops.
3. Routine modules inherited decorative gradients, circles and oversized headings that displaced operational content.
4. Body text and metadata frequently fell below a comfortable working size.
5. Cards, filters, tabs and dialogs had multiple radii, shadows and spacing scales.
6. Profile headers gave actions and decoration similar visual weight to the person or animal identity.
7. Desktop and mobile dialogs did not share a consistent sizing model.

## Authoritative design decisions

- Brand blue `#009DE6`, navy `#00345C`, yellow `#FFD900`, workspace `#F5F7FA` and white are the core palette.
- Green, amber and red are reserved for status and safety meaning.
- Inter remains the single application typeface. Body content is 14–16px; metadata never falls below 12px.
- Spacing follows 4, 8, 12, 16, 24 and 32px increments.
- Standard control radius is 10px; cards and dialogs use 12–14px.
- Desktop navigation starts at 1024px. Below it, the drawer and bottom navigation are used and the sidebar is absent.
- Only the main dashboard may use a distinctive welcome surface. Routine headers are compact white work surfaces.
- Profiles use compact identity headers, a clear primary action and grouped secondary actions.
- Desktop uses centred dialogs with bounded width; mobile uses bottom sheets with safe-area clearance.
- Existing routes, permissions, submit handlers and application behaviour remain unchanged.

## Implementation structure

`app.css` retains Tailwind and legacy feature-level styles. `reference-refresh.css` retains specialised module layouts. `design-system.css` is the single application-wide contract for tokens, shell, hierarchy, shared surfaces and responsive behaviour. New visual work should modify the design-system file or a feature's own component styles, not add another global override pass.
