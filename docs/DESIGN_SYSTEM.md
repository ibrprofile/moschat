# MosChat Design System

Product feel: calm, trustworthy, professional SMB SaaS — not flashy AI dashboards.

## Brand

- Name: **MosChat**
- Domain: moschat.online
- Primary accent: restrained blue for actions/active states only
- Surfaces: white / slate-tinted gray background
- Font: Inter (fallback system-ui)

## Tokens (CSS variables)

```css
:root {
  --color-primary: #2563EB;
  --color-primary-hover: #1D4ED8;
  --color-primary-soft: #EFF6FF;
  --color-dark: #111827;
  --color-text: #1F2937;
  --color-text-secondary: #6B7280;
  --color-muted: #9CA3AF;
  --color-bg: #F8FAFC;
  --color-surface: #FFFFFF;
  --color-border: #E5E7EB;
  --color-border-strong: #D1D5DB;
  --color-success: #16A34A;
  --color-warning: #D97706;
  --color-danger: #DC2626;
  --color-info: #0284C7;

  --radius-sm: 6px;
  --radius-md: 8px;
  --radius-lg: 12px;

  --shadow-sm: 0 1px 2px rgba(17, 24, 39, 0.05);
  --shadow-md: 0 4px 12px rgba(17, 24, 39, 0.08);

  --sidebar-width: 232px;
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 20px;
  --space-6: 24px;
  --space-8: 32px;

  --font: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
  --text-page: 1.125rem;   /* 18px page titles — compact */
  --text-section: 0.9375rem;
  --text-body: 0.875rem;   /* 14px */
  --text-meta: 0.75rem;    /* 12px */
  --line: 1.45;

  --motion: 160ms ease;
}
```

## Layout

### App shell
- Left sidebar ~232px: nav + company/user footer
- Main: full remaining width, light bg
- Inbox: 3 panes — list (~320px) | thread (flex) | client (~280px)
- Mobile: single pane navigation stack

### Sidebar nav
Inbox, CRM, Visitors, Analytics, Employees, Departments, Settings, API & Docs  
Active: soft primary bg + primary text, no loud indicators

## Components

Reuse small primitives (vanilla JS + CSS classes), not a framework:

- `.btn` `.btn-primary` `.btn-ghost` `.btn-danger`
- `.input` `.select` `.textarea`
- `.avatar` `.badge` `.tag`
- `.dropdown` `.modal` `.toast`
- `.table` `.pagination`
- `.empty-state` `.skeleton`
- `.tabs` `.tooltip`

Icons: Lucide (inline SVG sprite or lucide CDN subset).

Charts: Chart.js only on Analytics.

## Density

Operator tools are dense: 14px body, tight list rows (~56–64px), compact headers. Avoid oversized hero titles inside `/app`.

## Empty states

Always: what / why / next action (e.g. Inbox → “Install chat”).

## Motion

120–200ms opacity/transform only. Honor `prefers-reduced-motion`.

## Accessibility

Visible focus rings, aria-labels on icon buttons, keyboard: Enter send, Shift+Enter newline, Esc close.

## Anti-patterns (forbidden)

- Purple gradients / glassmorphism / neon glow
- Card-wrapping every field
- Emoji as UI icons
- Fake placeholder screens for MVP features
