# Product Requirements Document (PRD): Campus Parking Reservation System ("CampusPark")

**Author:** Product & Design Team  
**Design System:** Kinetic Campus / Shades of Unique (Dark Mode: `#0F131B` Slate | Light Mode: `#F7F9FB` / Frosted Glass | Accents: Terracotta `#E06C53`, Electric Violet `#8B5CF6`, Emerald `#10B981`)  
**Status:** Ready for Engineering & Figma Handoff  
**Target Form Factor:** Desktop-First Web App (Responsive Desktop Grid)

---

## 1. Executive Summary & Vision

Campus parking is historically plagued by circling congestion, unpredictable lot occupancy, compliance disputes, and inefficient manual permits. **CampusPark** is an end-to-end intelligent campus parking management and reservation platform tailored for university students, faculty, staff, and campus facility managers.

### Core Objectives
1. **Predictability:** Allow drivers to search, reserve, and pay for dedicated parking spots ahead of arrival across campus zones.
2. **Turnover & Compliance:** Enforce check-in/out policies via real-time active reservation sessions and a structured penalty system (3 late departures = 24hr temporary booking freeze).
3. **Operational Clarity:** Provide facility administrators with live slot management, maintenance toggles, zone capacity metrics, and exportable audit records.
4. **Seamless Experience:** Cohesive visual identity across dual themes (Light & Dark modes) with full interactive state consistency.

---

## 2. Target Personas & User Journeys

| Persona | Primary Needs & Pain Points | Key Screens & Touchpoints |
|---|---|---|
| **Undergraduate / Graduate Student (e.g., Alex Rivera)** | Arrives before class, needs rapid guaranteed spot in student lots (Zone B/C); requires clear penalty alerts and quick check-in from desktop/mobile. | Public Landing Page, User Dashboard (Map/List), Check-In / Check-Out Interface, Booking History |
| **Faculty & Staff Member** | Reserved priority lots (Zone A / Core Campus), EV charging availability, recurring or scheduled day bookings. | User Dashboard & Reservations, Registered Vehicles Management, Profile Settings |
| **Campus Parking Administrator** | Real-time lot oversight, manual spot state overrides (Maintenance, Out of Service), policy rule configuration, dispute auditing. | Slot Management (Grid + Quick Edit Drawer), Booking History & Audit Log, Notifications Center |

---

## 3. Product Architecture & Information Hierarchy

```
CampusPark Platform
│
├── Public Experience (Unauthenticated)
│   ├── Public Landing Page (Hero, How It Works, Interactive Campus Map, Rate Calculator, Social Proof)
│   ├── Login / Authentication Screen
│   └── Sign-Up & University ID Verification Screen
│
├── User Portal (Authenticated)
│   ├── User Dashboard & Spot Reservation (Interactive 3D/Vector Lot Map + High-Density List)
│   ├── Booking Confirmation Modal (Zone, Spot ID, Fee Breakdown, Grace Policy)
│   ├── Active Session / Check-In & Check-Out (Live Countdown Timer, Check-In / Out CTAs, Penalty Tracker)
│   ├── Booking History (Search, Filter by Status, Date Range, PDF Export)
│   ├── User Profile & Registered Vehicles (Multi-vehicle registration, Primary tags)
│   └── Notifications & Alerts Center (Confirmations, Reminders, Policy warnings)
│
└── Admin Console (Role-Based Access)
    ├── Slot Management Grid (Color-coded live status: Available, Occupied, Reserved, Maintenance)
    ├── Quick Edit Drawer (Zone reallocation, EV Charger toggle, Maintenance notes)
    └── System Audit & History (Global compliance reporting and exports)
```

---

## 4. Key Functional Modules & Specifications

### 4.1. Public Landing Page & Conversion Flow
- **Hero & Dynamic Visual:** High-impact value proposition ("Reserve Campus Parking In Seconds"), 3D isometric campus lot preview, stats counters (5,000+ Students, 98% On-Time rate).
- **Interactive Campus Overview:** High-contrast zone locator (Zone A Core, Zone B Outer, Zone C Perimeter) with live capacity badges.
- **Dynamic Cost Calculator:** Interactive widget estimating reservation fees based on Zone Tier and intended duration.
- **Global Header:** Brand wordmark, primary navigation links (Features, Pricing, Map), Theme Switcher (Dark/Light), and Authentication CTAs.

### 4.2. User Dashboard & Reservation Engine
- **Search & Filter Controls:** Date picker, arrival time slot picker (`09:00 - 11:00 AM`), vehicle type selector (Standard Car, Motorcycle, EV), and Campus Zone selector.
- **Dual View Layout:**
  - *Map View:* 3D isometric campus floor plan with interactive pins showing rates (`$2/hr`, `$4/hr`) and status.
  - *List View:* Side cards featuring slot tier tags (`PREMIUM`, `STANDARD`), walking distance estimates (`2 min walk to Hall`), and EV readiness.
- **Policy Warning Banner:** Fixed dismissible alert: *"Note: 3 late departures will temporarily disable your booking privileges for 24 hours."*
- **Reservation Modal:** Spot review with location breadcrumbs, pricing formula, permit details, and direct checkout.

### 4.3. Check-In / Check-Out Interface
- **Active Session Card:** Displays active Booking ID (`#CP-8492`), allocated Zone, and exact spot number.
- **Live Countdown Timer:** Large prominent digital clock display (e.g. `01:45:22 Remaining`) with color change upon entering the 15-minute grace window.
- **Action Buttons:** Direct "Check-In" trigger (active ±15 mins from start) and immediate "Check-Out" trigger to release the slot.
- **Late Penalty Tracker:** Progress indicator (`1 / 3 Warnings`) explaining the 24-hour booking restriction threshold.

### 4.4. Admin Slot Management & Operations
- **Interactive Grid System:** Categorized by Zone/Deck (e.g., Zone A: Faculty & Staff, Capacity 45/50).
- **Status Indicators:**
  - *Available:* Emerald Green (`#10B981`)
  - *Occupied:* Vibrant Terracotta (`#E06C53`)
  - *Reserved:* Electric Violet (`#8B5CF6`)
  - *Maintenance:* Neutral Slate / Charcoal (`#4B5563`)
- **Quick-Edit Side Drawer:** Allows real-time modification of spot properties, assignment to specialized categories (EV Level 2 capable, Handicap, Compact), and custom maintenance logging.

### 4.5. Booking History & Reporting
- **Data Filtering:** Free-text search by Booking ID or Spot ID, date range picker, and status chips (`All`, `Active`, `Completed`, `Cancelled`, `Late/Overdue`).
- **Reporting:** One-click "Export Report as PDF" for expense reimbursements or administrative compliance reviews.

### 4.6. Profile, Vehicles, & Notification Center
- **Vehicle Vault:** Registration of up to 5 campus-approved vehicles with license plate, make, model, and active primary vehicle toggle.
- **Categorized Inbox:** Real-time filter tabs (`All`, `Alerts`, `System`) providing audit notifications for bookings, check-in windows, and grace period reminders.

---

## 5. Visual System & UI Tokens

The design follows the **Kinetic Campus** system with complete dual-mode parity:

| Design Token | Dark Theme (`Kinetic Campus`) | Light Theme (`Kinetic Campus Light`) | Usage Context |
|---|---|---|---|
| **Base Surface** | `#0F131B` (Deep Obsidian Slate) | `#F7F9FB` (Crisp Light Slate) | Background canvas & body |
| **Container / Card** | `#181C23` / `#1E222A` | `#FFFFFF` / `rgba(255,255,255,0.85)` | Glass cards, content panels |
| **Primary Action** | `#E06C53` (Vibrant Terracotta) | `#E06C53` (Vibrant Terracotta) | "Reserve Now", "Save Changes", Primary CTA |
| **Secondary Accent**| `#8B5CF6` (Electric Violet) | `#8B5CF6` (Electric Violet) | Badges, active nav states, highlighted pins |
| **Success / Open** | `#10B981` (Emerald Green) | `#10B981` (Emerald Green) | "Available" spot status, confirmed badges |
| **Borders & Dividers**| `rgba(255,255,255,0.08)` | `rgba(0,0,0,0.08)` / `#E2E8F0` | Subtle structural division |
| **Typography** | Geist Sans / Display Bold | Geist Sans / Display Bold | High legibility across tables & forms |

---

## 6. Success Metrics & KPIs

1. **Space Utilization:** Increase campus parking lot occupancy efficiency by 25% during peak hours (9:00 AM - 1:00 PM).
2. **Search Time Reduction:** Reduce average time spent circling for a parking spot from 18 minutes to under 3 minutes.
3. **Turnover & Overstay Compliance:** Reduce unauthorized overstays by 40% within the first academic term via active countdown reminders and penalty enforcement.
4. **Administrative Efficiency:** Enable instant spot status reassignments in < 10 seconds via the Admin Quick-Edit Drawer.

---

## 7. Implementation Roadmap

- **Phase 1 (MVP Prototypes):** Complete clickable light/dark interactive prototypes for User Dashboard, Slot Management, Active Session, and Booking History. *(Delivered)*
- **Phase 2 (Design System & Figma Handoff):** Auto-layout components, token variables, and responsive layout specifications. *(Delivered)*
- **Phase 3 (Backend & IoT Integration):** In-ground sensor / camera LPR (License Plate Recognition) API sync with the live slot grid.
- **Phase 4 (Mobile Native Companion):** iOS / Android push notification extensions with Apple Wallet / Google Pay digital campus parking passes.
