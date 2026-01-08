# Booking SaaS MVP - Design Phase PRD

**Created**: 2026-01-08
**Status**: Draft
**Owner**: Product Manager
**Target Release**: Q1 2026 - Design Phase
**Pricing Tier**: Multi-tier (Free/Basic/Premium)
**Document Type**: UI/UX Design Requirements

---

## 1. Goal

Define comprehensive UI/UX design requirements for a multi-tenant Booking/Reservation SaaS MVP targeting Slovak local services (hair salons, massage therapists, fitness trainers, beauty services). This PRD focuses **exclusively on the design phase**, providing multiple design concepts for each key screen to enable informed decision-making before implementation.

---

## 2. Target Audience

### Primary Users

#### Business Owners (Primary)
- **Profile**: Small business owners in Slovak local services sector
- **Age**: 25-55 years
- **Tech proficiency**: Low to moderate
- **Device preference**: Mobile-first (80% mobile, 20% desktop)
- **Key needs**: Simple setup, minimal learning curve, quick overview of daily schedule
- **Pain points**: Complex software, time-consuming administration, paper calendars

#### Staff Members (Secondary)
- **Profile**: Employees of small service businesses
- **Age**: 20-45 years
- **Tech proficiency**: Moderate
- **Device preference**: Mobile primarily
- **Key needs**: Quick appointment view, client info access, own schedule management

#### End Customers (External)
- **Profile**: Slovak consumers seeking local services
- **Age**: 18-65 years
- **Tech proficiency**: Varied (assume low to ensure accessibility)
- **Device preference**: 90% mobile
- **Key needs**: Quick booking, easy cancellation, appointment reminders
- **Pain points**: Phone calls during business hours, waiting for confirmation

### Market Context
- **Target market**: Slovakia (potential Czech expansion)
- **Competition**: Bookni.to, Calendly, Acuity Scheduling, SimplyBook.me
- **Differentiator**: Slovak-first UX, simplicity over features, local payment integration

---

## 3. Design Goals & Principles

### Core Design Principles

| Principle | Description | Measurement |
|-----------|-------------|-------------|
| **Mobile-First** | Design for mobile, adapt to desktop | 100% mobile-responsive |
| **3-Click Rule** | Any action completable in 3 clicks max | UX audit compliance |
| **Slovak-Native** | Language, dates, currency, customs | Native Slovak UX patterns |
| **Zero Learning Curve** | Intuitive without training | <5 min to first booking |
| **Speed** | Fast load, instant feedback | <2s page load target |

### Design Philosophy Options

#### Option A: Minimalist Professional
- Clean, white-space heavy design
- Black/white with single accent color
- Focus on content over decoration
- **Reference**: Calendly, Linear

#### Option B: Friendly & Approachable
- Rounded corners, soft shadows
- Warm color palette
- Illustrated empty states
- **Reference**: Notion, Slack

#### Option C: Bold & Modern
- Strong typography
- Gradient accents
- Card-based layout
- **Reference**: Stripe, Framer

**Recommendation**: Option B for Slovak market - approachability reduces friction for less tech-savvy users.

---

## 4. Brand Identity Options

### Brand Name Analysis

| Name | Pros | Cons | Domain Availability |
|------|------|------|---------------------|
| **Terminko** | Slovak-native, clear meaning, memorable | Diminutive may feel less professional | terminko.sk likely available |
| **Slotify** | Modern, international, tech-friendly | English-based, less relatable | slotify.sk to check |
| **Rezio** | Short, catchy, easy to spell | Less clear meaning | rezio.sk to check |
| **Volno** | Slovak word for "free time/vacancy" | May confuse with "vacancy" | volno.sk competitive |
| **Kedy** | Slovak for "when", direct meaning | Very short, may be taken | kedy.sk to check |

### Color Scheme Options

#### Option A: Trust & Professionalism (Terminko-aligned)
```
Primary: #2563EB (Blue)
Secondary: #10B981 (Green - success/available)
Accent: #F59E0B (Amber - attention)
Neutral: #6B7280 (Gray)
Background: #F9FAFB
Text: #111827
```
**Rationale**: Blue builds trust, green indicates availability, amber draws attention to CTAs

#### Option B: Energy & Warmth (Service Industry)
```
Primary: #EC4899 (Pink/Magenta)
Secondary: #8B5CF6 (Purple)
Accent: #14B8A6 (Teal)
Neutral: #64748B (Slate)
Background: #FAFAFB
Text: #0F172A
```
**Rationale**: Warm colors align with beauty/wellness industry, feels premium yet approachable

#### Option C: Fresh & Modern (Slotify-aligned)
```
Primary: #06B6D4 (Cyan)
Secondary: #22C55E (Green)
Accent: #F97316 (Orange)
Neutral: #71717A (Zinc)
Background: #FFFFFF
Text: #18181B
```
**Rationale**: Fresh, tech-forward, appeals to younger business owners

---

## 5. Screen-by-Screen Design Requirements

### 5.1 Public Booking Widget (Customer-Facing)

**Purpose**: Allow end customers to book appointments without registration
**Critical Success Factor**: Conversion rate (visitor to booking)
**Device Split**: 90% mobile, 10% desktop

#### Design Approach A: Step-by-Step Wizard

```
Flow: Service Selection -> Staff Selection -> Date/Time -> Contact Info -> Confirmation

[STEP 1: SERVICE]
+------------------------------------------+
|  [Business Logo]                         |
|  Hair Salon Bella                        |
|                                          |
|  Vyberte sluzbu:                        |
|  +------------------------------------+  |
|  | Strihanie damske          45 min  |  |
|  | od 25 EUR                    [>]  |  |
|  +------------------------------------+  |
|  +------------------------------------+  |
|  | Farbenie                   90 min  |  |
|  | od 45 EUR                    [>]  |  |
|  +------------------------------------+  |
|  +------------------------------------+  |
|  | Styling                    30 min  |  |
|  | od 15 EUR                    [>]  |  |
|  +------------------------------------+  |
|                                          |
|  [Progress: o---o---o---o---o]          |
+------------------------------------------+
```

**Pros**:
- Clear progression, reduces cognitive load
- Easy to track progress
- Works well on mobile
- Prevents overwhelm

**Cons**:
- More clicks/screens
- Can feel slow for repeat customers
- Back navigation may lose context

#### Design Approach B: Single-Page with Sections

```
[ALL IN ONE VIEW]
+------------------------------------------+
|  [Business Logo]  Hair Salon Bella       |
|------------------------------------------|
|  1. SLUZBA                       [Edit]  |
|  Strihanie damske - 25 EUR, 45 min      |
|------------------------------------------|
|  2. TERMIN                       [Edit]  |
|  +----------------------------------+    |
|  |  <  Januar 2026  >              |    |
|  |  Po Ut St St Pi So Ne           |    |
|  |     1  2  3  4  5  6            |    |
|  |  7  8  9 [10] 11 12 13          |    |
|  +----------------------------------+    |
|  09:00  10:00  [11:00]  14:00  15:00   |
|------------------------------------------|
|  3. VASE UDAJE                           |
|  Meno: [________________]               |
|  Telefon: [________________]            |
|  Email: [________________]              |
|------------------------------------------|
|  [    REZERVOVAT TERMIN    ]            |
+------------------------------------------+
```

**Pros**:
- All information visible at once
- Faster for experienced users
- Easier to modify selections
- Better for desktop

**Cons**:
- Can be overwhelming on mobile
- Requires scrolling
- Complex validation states

#### Design Approach C: Chat-Style Conversational

```
[CONVERSATIONAL BOOKING]
+------------------------------------------+
|  [Business Logo]  Hair Salon Bella       |
|------------------------------------------|
|                                          |
|  Bot: Ahoj! Co by si chcel/a dnes       |
|       rezervovat?                        |
|                                          |
|  [Strihanie] [Farbenie] [Styling]       |
|                                          |
|  User: Strihanie                        |
|                                          |
|  Bot: Super! Kedy by ti vyhovovalo?     |
|                                          |
|  [Dnes] [Zajtra] [Tento tyzden]         |
|                                          |
|  [Calendar picker appears]               |
|                                          |
+------------------------------------------+
```

**Pros**:
- Feels personal and friendly
- Low cognitive load
- Natural flow
- Mobile-optimized

**Cons**:
- Takes more time
- Less control for users
- Harder to edit previous choices
- May feel gimmicky

**Recommendation**: Approach A (Step-by-Step Wizard) with optional "Express booking" for returning customers.

---

### 5.2 Business Dashboard (Owner View)

**Purpose**: Provide quick overview of business performance and daily operations
**Critical Success Factor**: Time to actionable insight
**Device Split**: 60% mobile, 40% desktop

#### Design Approach A: Metric Cards Layout

```
[DASHBOARD - METRIC CARDS]
+------------------------------------------+
|  [=] Terminko        Salon Bella   [@]  |
|------------------------------------------|
|  Dnes: Streda, 8. januar 2026           |
|------------------------------------------|
|  +----------------+  +----------------+  |
|  | DNESNE         |  | TENTO TYZDEN   |  |
|  | REZERVACIE     |  | TRZBY          |  |
|  |     12         |  |   1,240 EUR    |  |
|  | +3 vs vcera    |  |   +15%         |  |
|  +----------------+  +----------------+  |
|                                          |
|  +----------------+  +----------------+  |
|  | VOLNE SLOTY    |  | NOVE           |  |
|  | DNES           |  | KLIENTI        |  |
|  |     5          |  |     3          |  |
|  | 14:00-18:00    |  | tento tyzden   |  |
|  +----------------+  +----------------+  |
|------------------------------------------|
|  DNESNY PROGRAM                          |
|  +------------------------------------+  |
|  | 09:00 | Jana K. | Strihanie  [>]  |  |
|  | 10:00 | Maria S.| Farbenie   [>]  |  |
|  | 11:30 | ----VOLNE----              |  |
|  | 14:00 | Peter M.| Strihanie  [>]  |  |
|  +------------------------------------+  |
|                                          |
|  [+] Nova rezervacia                     |
+------------------------------------------+
```

**Pros**:
- Quick metrics at a glance
- Clear hierarchy of information
- Easy to scan
- Good for goal tracking

**Cons**:
- May require scrolling on mobile
- Metrics can be overwhelming
- Less focus on immediate actions

#### Design Approach B: Timeline-Centric

```
[DASHBOARD - TIMELINE FOCUS]
+------------------------------------------+
|  [=] Terminko        Salon Bella   [@]  |
|------------------------------------------|
|  Streda, 8. januar             [<] [>]  |
|------------------------------------------|
|                                          |
|  TERAZ: 10:45                           |
|  +------------------------------------+  |
|  |  Maria Svetlova                    |  |
|  |  Farbenie - este 45 min           |  |
|  |  [Ukoncit] [Predlzit]             |  |
|  +------------------------------------+  |
|                                          |
|  DALSIE:                                |
|  +------------------------------------+  |
|  | 11:30 | Volne - 2 hodiny          |  |
|  | 14:00 | Peter M. - Strihanie      |  |
|  | 15:00 | Anna B. - Styling         |  |
|  +------------------------------------+  |
|                                          |
|  +------------------------------------+  |
|  |  Dnesne statistiky: 8/12 hotovo   |  |
|  |  [===----------] 67%              |  |
|  +------------------------------------+  |
|                                          |
|  [+ Pridat rezervaciu] [Otvorit kalendar]|
+------------------------------------------+
```

**Pros**:
- Focus on "now" and next actions
- Perfect for day-to-day operations
- Less cognitive load
- Action-oriented

**Cons**:
- Less strategic overview
- Metrics hidden
- May miss longer-term view

#### Design Approach C: Kanban-Style Status Board

```
[DASHBOARD - KANBAN]
+------------------------------------------+
|  [=] Terminko        Salon Bella   [@]  |
|------------------------------------------|
|  +----------+ +----------+ +----------+  |
|  | CAKAJUCE | | PREBIEHA | | HOTOVE   |  |
|  +----------+ +----------+ +----------+  |
|  |          | |          | |          |  |
|  | 09:00    | | 10:00    | | 08:00    |  |
|  | Jana K.  | | Maria S. | | Tom L.   |  |
|  | Strihanie| | Farbenie | | Strihanie|  |
|  |          | |          | |          |  |
|  | 14:00    | |          | | 09:30    |  |
|  | Peter M. | |          | | Eva H.   |  |
|  | Strihanie| |          | | Styling  |  |
|  |          | |          | |          |  |
|  | 15:00    | |          | |          |  |
|  | Anna B.  | |          | |          |  |
|  +----------+ +----------+ +----------+  |
|                                          |
|  [+ Nova rezervacia]                     |
+------------------------------------------+
```

**Pros**:
- Visual status at a glance
- Satisfying to move items
- Clear workflow
- Popular pattern

**Cons**:
- Requires horizontal space (poor on mobile)
- May be unfamiliar to older users
- Overkill for simple workflows

**Recommendation**: Approach B (Timeline-Centric) as primary with metric cards accessible via swipe/tab.

---

### 5.3 Calendar View (Appointments Management)

**Purpose**: View and manage all appointments in calendar format
**Critical Success Factor**: Speed of finding/adding appointments
**Device Split**: 50% mobile, 50% desktop

#### Design Approach A: Traditional Calendar Grid

```
[CALENDAR - TRADITIONAL]
+------------------------------------------+
|  [<] Januar 2026 [>]    [Den][Tyzden][Mesiac]|
|------------------------------------------|
|       Po    Ut    St    St    Pi         |
|------------------------------------------|
| 08:00                                    |
|------------------------------------------|
| 09:00 [Jana] [    ] [Tom ] [    ] [Eva ] |
|       Strih        Strih        Strih    |
|------------------------------------------|
| 10:00 [    ] [Maria] [    ] [Anna] [    ]|
|              Farben        Farben        |
|------------------------------------------|
| 11:00 [    ] [    ] [    ] [    ] [    ] |
|------------------------------------------|
| 12:00            OBED                    |
|------------------------------------------|
| 14:00 [Peter] [    ] [    ] [    ] [Juro]|
|------------------------------------------|
```

**Pros**:
- Familiar pattern
- Good overview of week
- Easy to spot gaps
- Works well on desktop

**Cons**:
- Poor mobile experience
- Requires horizontal scroll
- Dense information

#### Design Approach B: Day-Focus with Day Switcher

```
[CALENDAR - DAY FOCUS]
+------------------------------------------+
|  [=] Kalendar                      [+]  |
|------------------------------------------|
|  [< Po ] [ Ut ] [ St ] [ St ] [ Pi >]   |
|    6      7      8*     9      10        |
|------------------------------------------|
|  Streda, 8. januar                       |
|------------------------------------------|
|  08:00  --------------------------------  |
|                                          |
|  09:00  +----------------------------+   |
|         | Jana Kovacova              |   |
|         | Strihanie damske - 45min   |   |
|         | Tel: +421 900 123 456      |   |
|         +----------------------------+   |
|                                          |
|  10:00  +----------------------------+   |
|         | Maria Svetlova             |   |
|         | Farbenie - 90min           |   |
|         +----------------------------+   |
|  11:00  |                            |   |
|         +----------------------------+   |
|                                          |
|  11:30  -----------VOLNE--------------   |
|                                          |
|  12:00  --------------------------------  |
|                                          |
|  14:00  +----------------------------+   |
|         | Peter Malik               |    |
|         +----------------------------+   |
+------------------------------------------+
```

**Pros**:
- Excellent mobile experience
- Clear day overview
- Easy to read appointments
- Swipe between days

**Cons**:
- No week overview
- Requires more navigation
- Harder to compare days

#### Design Approach C: Agenda List View

```
[CALENDAR - AGENDA]
+------------------------------------------+
|  [=] Kalendar        [Filter] [Mesiac]  |
|------------------------------------------|
|  DNES - Streda, 8. januar               |
|  +------------------------------------+  |
|  | 09:00 | Jana K.   | Strihanie [>] |  |
|  | 10:00 | Maria S.  | Farbenie  [>] |  |
|  | 14:00 | Peter M.  | Strihanie [>] |  |
|  | 15:00 | Anna B.   | Styling   [>] |  |
|  +------------------------------------+  |
|                                          |
|  ZAJTRA - Stvrtok, 9. januar            |
|  +------------------------------------+  |
|  | 09:30 | Eva H.    | Strihanie [>] |  |
|  | 11:00 | Lucia D.  | Farbenie  [>] |  |
|  | 16:00 | Miro K.   | Strihanie [>] |  |
|  +------------------------------------+  |
|                                          |
|  PIATOK, 10. januar                     |
|  +------------------------------------+  |
|  | 08:00 | Tom L.    | Strihanie [>] |  |
|  +------------------------------------+  |
|                                          |
|  [+] Nova rezervacia                     |
+------------------------------------------+
```

**Pros**:
- Best for mobile
- Shows upcoming chronologically
- Quick scanning
- Great for "what's next" mindset

**Cons**:
- No visual time gaps
- Hard to spot availability
- Less spatial awareness

**Recommendation**: Approach B (Day-Focus) for mobile, with Approach A (Traditional) available for desktop users.

---

### 5.4 Services Management

**Purpose**: Define and manage offered services
**Critical Success Factor**: Time to set up new service
**Device Split**: 40% mobile, 60% desktop (setup task)

#### Design Approach A: Table/List with Inline Editing

```
[SERVICES - TABLE]
+------------------------------------------+
|  [=] Sluzby                        [+]  |
|------------------------------------------|
|  AKTIVNE SLUZBY (5)                      |
|------------------------------------------|
| Nazov          | Cena   | Cas   | Stav  |
|----------------|--------|-------|-------|
| Strihanie D.   | 25 EUR | 45min | [x]   |
| Strihanie P.   | 18 EUR | 30min | [x]   |
| Farbenie       | 45 EUR | 90min | [x]   |
| Styling        | 15 EUR | 30min | [x]   |
| Umytie         | 5 EUR  | 15min | [ ]   |
|------------------------------------------|
|  [Pridat sluzbu]                         |
|                                          |
|  KATEGORIE                               |
|  [Strihanie] [Farbenie] [Specialne]     |
+------------------------------------------+
```

**Pros**:
- Compact, all info visible
- Quick comparison
- Bulk actions possible
- Familiar table pattern

**Cons**:
- Hard to edit on mobile
- Limited space for descriptions
- Dense interface

#### Design Approach B: Card Grid with Modal Editing

```
[SERVICES - CARDS]
+------------------------------------------+
|  [=] Sluzby                        [+]  |
|------------------------------------------|
|  [Vsetky] [Strihanie] [Farbenie]        |
|------------------------------------------|
|  +----------------+  +----------------+  |
|  | STRIHANIE      |  | STRIHANIE      |  |
|  | DAMSKE         |  | PANSKE         |  |
|  | [=====]        |  | [=====]        |  |
|  | 25 EUR         |  | 18 EUR         |  |
|  | 45 minut       |  | 30 minut       |  |
|  | [Aktivne]      |  | [Aktivne]      |  |
|  | [...] [Edit]   |  | [...] [Edit]   |  |
|  +----------------+  +----------------+  |
|                                          |
|  +----------------+  +----------------+  |
|  | FARBENIE       |  | STYLING        |  |
|  | [=====]        |  | [=====]        |  |
|  | 45 EUR         |  | 15 EUR         |  |
|  | 90 minut       |  | 30 minut       |  |
|  | [Aktivne]      |  | [Aktivne]      |  |
|  | [...] [Edit]   |  | [...] [Edit]   |  |
|  +----------------+  +----------------+  |
+------------------------------------------+
```

**Pros**:
- Visual and scannable
- Works on mobile
- Room for images/icons
- Drag-drop reordering

**Cons**:
- Uses more space
- Slower to compare
- Requires modal for editing

#### Design Approach C: Accordion/Expandable List

```
[SERVICES - ACCORDION]
+------------------------------------------+
|  [=] Sluzby                        [+]  |
|------------------------------------------|
|  +------------------------------------+  |
|  | v  Strihanie damske        25 EUR |  |
|  |------------------------------------|  |
|  | Popis: Umytie, strihanie, fenovanie |
|  | Trvanie: 45 minut                   |
|  | Kategoria: Strihanie                |
|  | Online rezervacia: Ano              |
|  |                                     |
|  | [Upravit] [Duplikovat] [Vymazat]   |
|  +------------------------------------+  |
|                                          |
|  +------------------------------------+  |
|  | >  Strihanie panske         18 EUR |  |
|  +------------------------------------+  |
|                                          |
|  +------------------------------------+  |
|  | >  Farbenie                 45 EUR |  |
|  +------------------------------------+  |
|                                          |
|  +------------------------------------+  |
|  | >  Styling                  15 EUR |  |
|  +------------------------------------+  |
|                                          |
|  [+ Pridat novu sluzbu]                 |
+------------------------------------------+
```

**Pros**:
- Mobile-friendly
- Expandable details
- Clean initial view
- Easy keyboard navigation

**Cons**:
- Can only see one expanded at a time
- More clicks to compare
- Animation can feel slow

**Recommendation**: Approach C (Accordion) for mobile-first with card preview. Desktop can show Approach B (Cards) in wider grid.

---

### 5.5 Client Management

**Purpose**: Store and manage client information and history
**Critical Success Factor**: Speed of finding client and their history
**Device Split**: 50% mobile, 50% desktop

#### Design Approach A: Search-First with Client Cards

```
[CLIENTS - SEARCH FIRST]
+------------------------------------------+
|  [=] Klienti                       [+]  |
|------------------------------------------|
|  [Search: Meno, tel., email...      Q]  |
|------------------------------------------|
|  NEDAVNI KLIENTI                         |
|------------------------------------------|
|  +------------------------------------+  |
|  | JK  Jana Kovacova            [>]  |  |
|  |     +421 900 123 456              |  |
|  |     Posledna navsteva: 8.1.2026   |  |
|  +------------------------------------+  |
|  +------------------------------------+  |
|  | MS  Maria Svetlova           [>]  |  |
|  |     +421 900 234 567              |  |
|  |     Posledna navsteva: 8.1.2026   |  |
|  +------------------------------------+  |
|  +------------------------------------+  |
|  | PM  Peter Malik              [>]  |  |
|  |     +421 900 345 678              |  |
|  |     Posledna navsteva: 5.1.2026   |  |
|  +------------------------------------+  |
|                                          |
|  [Zobrazit vsetkych (247)]              |
+------------------------------------------+
```

**Pros**:
- Quick access to search
- Recent clients prominent
- Mobile-optimized
- Minimal scrolling for common tasks

**Cons**:
- No alphabetical browsing
- Limited filtering options
- Relies on search accuracy

#### Design Approach B: Alphabetical with Filters

```
[CLIENTS - ALPHABETICAL]
+------------------------------------------+
|  [=] Klienti                    [+] [Q] |
|------------------------------------------|
|  [Filter: Vsetci v] [Zoradit: Meno v]   |
|------------------------------------------|
|  A (3)                                   |
|  +------------------------------------+  |
|  | Anna Babicova      | 12 navstev   |  |
|  | Anton Cerny        | 5 navstev    |  |
|  | Alena Dlha         | 8 navstev    |  |
|  +------------------------------------+  |
|                                          |
|  B (2)                                   |
|  +------------------------------------+  |
|  | Boris Elias        | 3 navstevy   |  |
|  | Beata Fikova       | 15 navstev   |  |
|  +------------------------------------+  |
|                                          |
|  [A][B][C][D][E][F][G][H][I][J][K]...   |
+------------------------------------------+
```

**Pros**:
- Easy to browse
- Familiar pattern
- Quick jump to letter
- Good for large lists

**Cons**:
- Slower for known clients
- More scrolling
- Less useful without full names

#### Design Approach C: Segmented Client View

```
[CLIENTS - SEGMENTED]
+------------------------------------------+
|  [=] Klienti                       [+]  |
|------------------------------------------|
|  [VIP] [Aktivni] [Neaktivni] [Vsetci]   |
|------------------------------------------|
|  [Search...                          Q]  |
|------------------------------------------|
|  VIP KLIENTI (15)                        |
|  +------------------------------------+  |
|  | * Jana Kovacova                   |  |
|  |   24 navstev | 1,240 EUR celkovo  |  |
|  |   Poznamka: Preferuje rano        |  |
|  +------------------------------------+  |
|  +------------------------------------+  |
|  | * Maria Svetlova                  |  |
|  |   18 navstev | 980 EUR celkovo    |  |
|  |   Poznamka: Alergia na latex      |  |
|  +------------------------------------+  |
|                                          |
|  AKTIVNI KLIENTI (89)                    |
|  +------------------------------------+  |
|  | Peter Malik                        |  |
|  | Eva Horvathova                     |  |
|  | ...                               |  |
|  +------------------------------------+  |
+------------------------------------------+
```

**Pros**:
- Business-value focused
- Highlights important clients
- Built-in CRM thinking
- Encourages notes/tracking

**Cons**:
- Requires client categorization
- More complex setup
- May overwhelm simple users

**Recommendation**: Approach A (Search-First) with Approach C (Segmented) available as optional filter view for power users.

---

### 5.6 Settings & Onboarding

**Purpose**: Configure business settings and guide new users through setup
**Critical Success Factor**: Completion rate of onboarding (target: >80%)
**Device Split**: 30% mobile, 70% desktop (initial setup)

#### Onboarding Approach A: Linear Step-by-Step Wizard

```
[ONBOARDING - LINEAR WIZARD]
+------------------------------------------+
|            VITAJTE V TERMINKO            |
|------------------------------------------|
|  Krok 2 z 5: Vase podnikanie            |
|  [====--------------------]              |
|------------------------------------------|
|                                          |
|  Nazov vasho podnikania:                 |
|  [Salon Bella_________________]          |
|                                          |
|  Typ sluzby:                            |
|  ( ) Kadernictvo                        |
|  (o) Kozmetika                          |
|  ( ) Masaze                             |
|  ( ) Fitness                            |
|  ( ) Ine: [__________]                  |
|                                          |
|  Adresa prevadzky:                       |
|  [Hlavna 15, Bratislava________]         |
|                                          |
|------------------------------------------|
|  [Spat]              [Pokracovat ->]    |
+------------------------------------------+
```

**Pros**:
- Clear progress tracking
- Low cognitive load per step
- Hard to skip important fields
- Good completion rates

**Cons**:
- Can feel long
- No flexibility to skip ahead
- Must complete sequentially

#### Onboarding Approach B: Checklist Dashboard

```
[ONBOARDING - CHECKLIST]
+------------------------------------------+
|  VITAJTE V TERMINKO!                     |
|  Dokonci nastavenie svojho uctu         |
|------------------------------------------|
|  Tvoj pokrok: 40% dokoncene             |
|  [========-----------------]             |
|------------------------------------------|
|  [x] Vytvor ucet                        |
|  [x] Potvrd email                       |
|  [ ] Pridaj informacie o podnikaní  [>] |
|  [ ] Nastav otvaracie hodiny        [>] |
|  [ ] Pridaj prvu sluzbu             [>] |
|  [ ] Vyskusaj rezervaciu            [>] |
|------------------------------------------|
|                                          |
|  TIP: Mozes zacat pouzivat Terminko     |
|  ihned, zvysok dokoncis neskor.         |
|                                          |
|  [Preskocit nastavenie]                 |
|  [Pokracovat v nastaveni]               |
+------------------------------------------+
```

**Pros**:
- Flexible order
- Can skip and return
- Shows progress without forcing
- Can use app during setup

**Cons**:
- May leave setup incomplete
- Less guided experience
- Can be overwhelming

#### Onboarding Approach C: Conversational Setup

```
[ONBOARDING - CONVERSATIONAL]
+------------------------------------------+
|  [Terminko Logo]                         |
|------------------------------------------|
|                                          |
|  Terminko: Ahoj! Som tu aby som ti      |
|  pomohol nastavit tvoj ucet.            |
|                                          |
|  Ako sa vola tvoje podnikanie?          |
|                                          |
|  [Salon Bella_________________] [->]     |
|                                          |
|  Terminko: Super! Salon Bella znie      |
|  skvelo. A co robite?                   |
|                                          |
|  [Kadernictvo] [Kozmetika] [Masaze]     |
|  [Fitness] [Ine...]                     |
|                                          |
+------------------------------------------+
```

**Pros**:
- Friendly and approachable
- Reduces intimidation
- Natural language
- Good for non-tech users

**Cons**:
- Takes longer
- Can feel patronizing
- Hard to go back/edit

**Recommendation**: Approach B (Checklist) with elements of Approach A (Wizard) for critical steps (business info, first service).

#### Settings Structure

```
[SETTINGS - ORGANIZED]
+------------------------------------------+
|  [=] Nastavenia                          |
|------------------------------------------|
|  PODNIKANIE                              |
|  +------------------------------------+  |
|  | Zakladne informacie           [>] |  |
|  | Otvaracie hodiny              [>] |  |
|  | Miesta / Prevadzky            [>] |  |
|  +------------------------------------+  |
|                                          |
|  REZERVACIE                              |
|  +------------------------------------+  |
|  | Online booking                [>] |  |
|  | Notifikacie                   [>] |  |
|  | Storno podmienky             [>] |  |
|  +------------------------------------+  |
|                                          |
|  TIM                                     |
|  +------------------------------------+  |
|  | Clenovia timu                 [>] |  |
|  | Prava a role                  [>] |  |
|  +------------------------------------+  |
|                                          |
|  INTEGRACIE                              |
|  +------------------------------------+  |
|  | Google Kalendar               [>] |  |
|  | Platobna brana               [>] |  |
|  +------------------------------------+  |
+------------------------------------------+
```

---

## 6. Mobile vs Desktop Considerations

### Mobile-Specific Requirements

| Element | Mobile Implementation |
|---------|----------------------|
| Navigation | Bottom tab bar (max 5 items) |
| Forms | Stacked vertical layout, large touch targets (min 44px) |
| Calendar | Day view default, swipe for navigation |
| Tables | Transform to cards or accordion |
| Actions | Floating action button (FAB) for primary action |
| Modals | Full-screen sheets sliding from bottom |

### Desktop-Specific Features

| Element | Desktop Implementation |
|---------|------------------------|
| Navigation | Sidebar with expanded labels |
| Forms | 2-column layout where appropriate |
| Calendar | Week view default with day detail panel |
| Tables | Full table with sorting/filtering |
| Actions | Button in header, keyboard shortcuts |
| Modals | Centered modal dialogs |

### Responsive Breakpoints

```
Mobile: 0 - 640px (Tailwind: sm)
Tablet: 641px - 1024px (Tailwind: md, lg)
Desktop: 1025px+ (Tailwind: xl, 2xl)
```

---

## 7. Wireframe/Mockup Requirements

### Deliverables per Screen

| Screen | Wireframes | Mockups | Prototype |
|--------|------------|---------|-----------|
| Booking Widget | 3 approaches | 1 selected | Click-through |
| Dashboard | 3 approaches | 1 selected | - |
| Calendar | 3 approaches | 1 selected (mobile + desktop) | Click-through |
| Services | 3 approaches | 1 selected | - |
| Clients | 3 approaches | 1 selected | - |
| Settings/Onboarding | 2-3 approaches | 1 selected | Click-through |

### Design Tool Requirements

- **Wireframes**: Low-fidelity, grayscale (Figma/Excalidraw)
- **Mockups**: High-fidelity with colors and typography (Figma)
- **Prototypes**: Interactive flows for key user journeys (Figma)

### User Journey Prototypes Required

1. **Customer Booking Flow**: Landing -> Select Service -> Select Time -> Confirm
2. **Business Owner Daily Flow**: Login -> Dashboard -> View Appointment -> Mark Complete
3. **New User Onboarding**: Signup -> Setup Wizard -> First Service -> Preview Widget

---

## 8. Competitor Analysis for Design Reference

### Bookni.to (Slovak)
- **Strengths**: Local market knowledge, Slovak language
- **Design**: Basic, functional but dated
- **Learn from**: Local service industry expectations

### Calendly
- **Strengths**: Excellent UX, clean design
- **Design**: Minimalist, professional, polished
- **Learn from**: Booking flow simplicity, email integration

### Acuity Scheduling
- **Strengths**: Feature-rich, flexible
- **Design**: Functional but busy
- **Learn from**: Customization options, payment integration

### SimplyBook.me
- **Strengths**: European market, many integrations
- **Design**: Modern but complex
- **Learn from**: Widget customization, marketing features

### Design Differentiation Strategy

| Competitor Gap | Our Opportunity |
|----------------|-----------------|
| Complex setup | 5-minute onboarding |
| English-first | Slovak-native UX |
| Desktop-focused | Mobile-first design |
| Feature bloat | Focused MVP |
| Generic branding | Local service personality |

---

## 9. Accessibility Requirements

### WCAG 2.1 AA Compliance

- Color contrast: Minimum 4.5:1 for text
- Touch targets: Minimum 44x44px
- Focus indicators: Visible keyboard focus states
- Screen readers: Proper ARIA labels
- Motion: Respect prefers-reduced-motion

### Slovak-Specific Accessibility

- Diacritics support (á, č, ď, é, í, ĺ, ľ, ň, ó, ô, ŕ, š, ť, ú, ý, ž)
- Date format: DD.MM.YYYY
- Time format: 24-hour (09:00, 14:30)
- Currency: EUR with proper formatting (25,00 EUR)
- Phone format: +421 XXX XXX XXX

---

## 10. Success Metrics for Design Phase

### Quantitative Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Onboarding completion | >80% | Analytics tracking |
| Time to first booking (business) | <5 minutes | User testing |
| Time to book (customer) | <2 minutes | User testing |
| Mobile usability score | >90/100 | Lighthouse/PageSpeed |
| Task completion rate | >95% | User testing |

### Qualitative Metrics

- User satisfaction with visual design (survey)
- Perceived simplicity vs. competitors (interviews)
- Brand perception alignment (focus groups)

---

## 11. Implementation Handoff

### Design Phase Deliverables

1. **Brand Guidelines**
   - Logo usage
   - Color palette (primary, secondary, semantic)
   - Typography scale
   - Component library

2. **Wireframes**
   - All 6 key screens
   - Mobile and desktop variants
   - All 2-3 approaches per screen

3. **High-Fidelity Mockups**
   - Selected approach for each screen
   - Mobile and desktop versions
   - Light mode (dark mode future phase)

4. **Interactive Prototypes**
   - Customer booking flow
   - Business owner daily flow
   - Onboarding flow

5. **Design System Documentation**
   - Component specifications
   - Spacing and layout grid
   - Interaction patterns
   - Animation guidelines

### Handoff to Development

- **Task folder**: `tasks/2026-01-booking-saas-design/`
- **Related PRDs**: This is the foundational design PRD
- **Next phase**: Implementation PRD (separate document)

---

## 12. Agent Session Log

### Session 2026-01-08
- **Status**: Initial PRD creation
- **Pending questions**:
  - Brand name final selection (recommend user testing with target audience)
  - Color scheme preference (recommend Option A for trust-building)
  - Priority of prototype screens for user testing
- **Working notes**:
  - Created comprehensive design requirements covering all 6 key screens
  - Provided 2-3 design approaches per screen with pros/cons
  - Focused on Slovak market needs and mobile-first approach
  - Referenced competitors for differentiation strategy
- **Next steps**:
  - User validation of design approaches
  - Brand name testing with target audience
  - Proceed to wireframe creation after approach selection
- **Decisions**:
  - Mobile-first design principle confirmed
  - Slovak-native UX as key differentiator
  - Timeline-centric dashboard recommended
  - Step-by-step wizard for booking recommended

### Design Approach Recommendations Summary

| Screen | Recommended Approach | Rationale |
|--------|---------------------|-----------|
| Booking Widget | A: Step-by-Step Wizard | Best conversion, mobile-friendly |
| Dashboard | B: Timeline-Centric | Action-focused, daily operations |
| Calendar | B: Day-Focus (mobile) + A: Traditional (desktop) | Responsive optimization |
| Services | C: Accordion/Expandable | Mobile-friendly, clean |
| Clients | A: Search-First | Quick access to common tasks |
| Onboarding | B: Checklist + A: Wizard elements | Flexible yet guided |

---

*This PRD focuses exclusively on the Design Phase. Implementation details, API specifications, and technical architecture will be covered in subsequent PRDs.*
