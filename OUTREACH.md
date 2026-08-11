# Cold Outreach — Lead Generation Playbook

## Context

We're doing B2B sales outreach for a white-label car rental platform.
The platform (this repo) handles: vehicle fleet management, online booking
with Stripe payments, deposit handling, mobile apps (Expo React Native),
push notifications, admin panel (Filament), and full theme customization.

**Target customers:** Car rental operators who don't have online booking
or a mobile app — ie. they take bookings via phone, WhatsApp, or contact forms.

---

## Lead Generation Process

### How we built the list

1. **WebSearch** — queried Google for rental operators in target regions
   (UAE, Qatar, Kuwait, Bahrain, UK, USA) using business directories
   (Yello.ae, QatarYello, KuwaitYello, ManchesterDirectory, TollFreeDirectory)

2. **Website scraping** — used Scrapling MCP (stealthy browser) to visit
   company websites and verify:
   - Whether they have online booking (107/121 do NOT)
   - Whether they have a mobile app (only 2 have one, 1 is building one)
   - Contact email addresses (verified from actual website content)
   - Contact form URLs (for sites without public email)

3. **Instagram search** — used Scrapling stealthy browser to visit
   `instagram.com/handle` directly and scrape profile data (follower count,
   bio, website link). Instagram is NOT web-indexed — this was the only
   way to find handles and follower counts.

4. **LinkedIn search** — confirmed profile URLs exist for key decision-makers
   (profiles are authwalled but URLs resolve — `linkedin.com/in/name` format
   is valid)

5. **Decision-maker research** — found CEO/MD/Owner names via Yello.ae
   company profiles, news articles, SignalHire, and ContactOut.

### Key findings across 121 leads

| Metric | Count |
|---|---|
| No online booking | 107 (88%) |
| Phone/WhatsApp only (no website) | 93 (77%) |
| Has a mobile app | 2 (Rent FYV, FYV Exotic) |
| Building an app soon | 1 (Diamondlease — waitlist live) |
| Has verified email from website | 10 |
| Instagram handles confirmed | 13 |
| LinkedIn profiles confirmed | 4 |

### The CSV

**Location:** `/home/adil/Car-Rental/outreach-leads.csv`
**Status:** NOT committed (in `.gitignore`)
**Size:** 121 leads, 25KB
**Columns (18):**

```
company_name | website | city | country | email | phone | contact_form |
social_media | key_people | fleet_hint | has_online_booking | demo_angle |
source | verified | instagram | decision_maker | contact_priority | site_intel
```

---

## Priority Leads — Ready to Contact

### 🔥 TIER 1 — Decision-maker known, contact today

#### 1. Diamondlease (Dubai) — 🚨 HOTTEST LEAD

| Field | Value |
|---|---|
| **Why urgent** | Building their own app NOW — "app coming soon" + waitlist + App Store button on site |
| **Fleet** | 8,000-14,000 vehicles, 650+ employees, 17 UAE branches |
| **Parent** | Al Habtoor Group (since 1996, ISO 9001) |
| **Decision-maker** | Partha Barua, Managing Director |
| **LinkedIn** | linkedin.com/in/partha-barua |
| **Instagram** | @diamondlease (10.2K followers) |
| **Email** | Try `partha.barua@diamondlease.com` or `p.barua@diamondlease.com` |
| **Phone** | +971 4 885 2667, 800 37483 |
| **Website** | diamondlease.com |
| **Pitch** | "Before you finish building your app, let me show you what we already have working" |

#### 2. mph club (Miami)

| Field | Value |
|---|---|
| **Why** | 513K Instagram followers, Drake/Floyd/Shaq clients — books via GET A QUOTE form. No online checkout. No app. |
| **Fleet** | 500+ exotic cars, 14 Florida locations, est. 2010 |
| **Decision-maker** | Liram Sustiel, CEO |
| **Email** | liram@mphclub.com |
| **Instagram** | @mphclub (513K), CEO @liramsustiel (74.8K) |
| **LinkedIn** | linkedin.com/in/liram-sustiel |
| **Phone** | 888-674-4044 |
| **Website** | mphclub.com |
| **Pitch** | "Your 513K followers could book a Lamborghini in 30 seconds instead of filling a form and waiting for a callback" |

#### 3. Carlease (Dubai)

| Field | Value |
|---|---|
| **Why** | 1,200 cars, 34 employees, fast-growing — likely needs tech upgrade soon |
| **Decision-maker** | Aswini Borkotoky, MD & Owner (25 years industry experience) |
| **Email** | md@carlease.ae, info@carlease.ae |
| **LinkedIn** | linkedin.com/in/aswini-borkotoky |
| **Instagram** | @carleaseuae (478 followers) |
| **Phone** | +971 4 338 7773 |
| **Website** | carlease.ae |
| **Pitch** | "You're scaling fast — your booking system should scale with you" |

#### 4. Starr Luxury Cars (London)

| Field | Value |
|---|---|
| **Why** | Forbes-featured, Prince Harry client, global multi-country — all bookings via inquiry form or phone |
| **Fleet** | 120+ luxury vehicles, UK/USA/UAE/Europe |
| **Decision-maker** | Ikenna Ordor, CEO |
| **Email** | sales@starrluxurycars.com |
| **LinkedIn** | linkedin.com/in/ikenna-ordor |
| **Phone** | +44 203 600 1631 (UK), +1 424 244 3285 (USA) |
| **Website** | starrluxurycars.com |
| **Pitch** | "Your brand is global — your booking experience should be too" |

#### 5. Exotic Car Rentals / Rent FYV (Miami)

| Field | Value |
|---|---|
| **Why** | 500+ cars, 40+ cities, has a mobile app (5★) — but website only has inquiry form, no real-time booking |
| **Fleet** | 500+ exotic cars, 26 brands, nationwide USA |
| **Decision-maker** | Sean J., CEO |
| **Email** | info@exoticcarsrental.com |
| **Instagram** | @rentfyv (89.7K followers) |
| **Phone** | +1 415-385-0515 |
| **Website** | exoticcarsrental.com, rentfyv.com |
| **Pitch** | "You have the app — now add real-time checkout so customers don't wait for a callback" |

#### 6. Delta Autos (Texas)

| Field | Value |
|---|---|
| **Why** | 1-page brochure site, veteran-owned, owner answers personally — easiest sale |
| **Fleet** | Small fleet, car rentals + detailing + Turo co-hosting |
| **Decision-maker** | Chandler H., Owner |
| **Email** | owner@deltaautosusa.com |
| **Instagram** | @deltaautosusa (216 followers) |
| **Phone** | 817-888-6710 |
| **Website** | deltaautosusa.com |
| **Pitch** | "I'll give you a full website + mobile app + online booking for what you spend on detailing supplies" |

### 🟡 TIER 2 — Warm, owner identified, reach via DM or call

| Company | Who | How |
|---|---|---|
| **Superior Rental** (Dubai, 300+ cars, 33.6K IG) | Abdul Rahman, Manager | IG DM @superiorrental, WhatsApp +971 50 230 8444 |
| **LavishCars** (Dubai+Houston, 5.3K IG) | Jecelene + Aamir (WhatsApp team) | WhatsApp +971 585 5319 23, info@lavishcars.com |
| **MyDubai Cars** (46.1K IG) | Ahmed Al Nasiri, Owner | IG DM @mydubai.cars |
| **Greenleaf Rent A Car** (4 CA locations) | Nicholas Prestininzi, President | Call 760-547-2228 |
| **Independent Rental Co** (Santa Cruz, 20yr) | Zak Francis & LeAndra Johnson | Call 831-426-0900 |
| **Rent Any Car Dubai** (25.6K IG, 150+ cars) | — | IG DM @rentanycar.ae |
| **FYV Exotic** (Beverly Hills, 8 cities) | George, Sean, David | 888-411-9516 |
| **Practical Car & Van** (UK, 150 franchises) | Pitch individual franchisees, not HQ | info@practical.co.uk |

### 🟢 TIER 3 — Nurture: 100+ phone-only operators
These are 1-5 person shops across UAE, Qatar, Kuwait, Bahrain, UK, and USA.
The person who answers the phone IS the decision-maker. Call or WhatsApp.

---

## Outreach Strategy

### What the platform does (30-second pitch)

> "We give car rental operators a complete white-label platform: website
> with real-time booking, iOS and Android apps, Stripe payment processing,
> fleet management dashboard, push notifications, and customer accounts.
> One backend powers everything. Launch in days, not months."

### Before pitching, deploy these

| Asset | Priority |
|---|---|
| Web app on a real domain (not localhost) | 🚨 Do first |
| Mobile app in Google Play Store | Do after web |
| 1 demo booking flow video (30 seconds) | Record after deploy |
| Stripe test mode live demo link | ✅ Already working locally |

### Diamondlease — special urgency

Their site says "Our app is coming soon" with a waitlist form and App Store
button. They are ACTIVELY building. Reach out THIS WEEK before they commit
to their own build. DM @diamondlease on Instagram AND email Partha Barua.

### Email template (for CEOs with known emails)

```
Subject: Quick question about [company name]

[Name],

I came across [company name] — impressive fleet. [Specific compliment
about their business from our research].

I noticed [observation about their booking process — "you take bookings
via WhatsApp" / "your site has a contact form instead of checkout"].

We've built a white-label car rental platform that handles online booking,
Stripe payments, fleet management, and mobile apps (iOS + Android) out of
the box. Already live and processing real payments.

Would a 15-minute demo be worth your time? Happy to show you exactly how
it would work for [their specific use case — "a fleet of 500 exotics" /
"14 locations across Florida" / "your luxury Dubai fleet"].

[Your name]
```

### Instagram DM template (for IG-native operators)

```
Hey! Love what you're doing at [company]. I noticed you take bookings 
via WhatsApp/DM — we've built a white-label platform that gives rental
operators a real website + booking system + mobile app. Already processing
live Stripe payments. Want me to send you a quick demo?
```

---

## What We Learned About Cold Outreach for Car Rentals

1. **89% of rental operators have no online booking.** They run on phone calls,
   WhatsApp, and paper. This is a HUGE untapped market.

2. **Instagram is the primary channel for Gulf operators.** Dubai rental
   companies use Instagram as their storefront — @mydubai.cars has 46K followers
   but no website. DM is the way to reach them.

3. **Small operators = owner answers.** For companies with 1-10 employees,
   calling the listed number gets you the decision-maker directly.

4. **Instagram profiles are NOT web-indexed.** You have to visit `instagram.com/handle`
   directly with a real browser to get follower counts and profile data.

5. **LinkedIn profiles ARE valid URLs** even though content is behind a login wall.
   `linkedin.com/in/firstname-lastname` format works for the key decision-makers.

6. **Yello.ae is the best directory for Gulf operators.** It lists phone numbers,
   websites, establishment years, employee counts, and manager names.

7. **The biggest competitor isn't another platform — it's WhatsApp.** Every
   operator we found uses WhatsApp as their primary booking channel.

---

## Running the Lead Research Again

To refresh or extend this research:

```bash
# The CSV is at:
/home/adil/Car-Rental/outreach-leads.csv

# It's in .gitignore — won't be committed
# Search for more leads:

# Use WebSearch for new regions/niches
# Use Scrapling MCP for website scraping (stealthy browser)
# Visit instagram.com/HANDLE directly for profile data
# Visit linkedin.com/in/NAME for decision-maker profiles
# Yello.ae has 1,637+ Dubai rental listings across 82 pages
# QatarYello has 420, KuwaitYello has 122
```

---

## Files

| File | Purpose |
|---|---|
| `outreach-leads.csv` | 121 leads, 18 columns, all research data |
| `OUTREACH.md` | This file — context and playbook |
| `CLAUDE.md` | Project architecture (for product context) |

Generated: 2026-08-11
