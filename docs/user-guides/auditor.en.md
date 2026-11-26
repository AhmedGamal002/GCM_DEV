# User Guide — Auditor

> Arabic version: [`auditor.ar.md`](auditor.ar.md) · Last updated: FRD V01.14 review

The Auditor is a **read-only** role over every page in the system: view and export, no creating, editing, or status changes. The one exception: changing their **own password** from the profile page.

---

## What you see in the sidebar

Dashboard, then an **Accounts** section (Users · Drivers), a **Fleet & Assets** section (Vehicles · Assets, with Capacities), an **Operations** section (Facilities · Services · Contractors — the lists only, no "Add") and a **Clients & Projects** section (Client Companies). Any other link is hidden — manual access returns "You don't have permission to view this data."

## 1. Sign in and profile

- `/login` with email + password.
- **My Profile**: change your profile photo, **and your password too** (the "Security" tab — you enter your current password first). You can also upload your own **signature image and operational stamp image** (optional, PNG/JPG, max 2 MB). Name and email stay read-only.

## 2. Users — `/app/user/list`

- **View** the list of GCM staff accounts + **export** (Excel / PDF).
- **View details** of any account — client accounts (company, projects, signature/stamp images) and contractor users (their contractor) included.
- **Not allowed:** the "Add User" button won't open a working form, and there are no edit or status-change controls.

## 3. Drivers — `/app/driver/list`

- **View** the full list + stat cards + filters + **export**.
- **View details** of any driver (their data, documents, permits).
- **Not allowed:** any create or edit.

## 4. Vehicles — `/app/vehicle/list`

- **View** the full list + stat cards + all filters (category / status / affiliation) + search.
- **View details** of any vehicle (its data, photos, documents, permits).
- **Export** the list (Excel / PDF) — with the applied filters.
- **Not allowed:** the "Add Vehicle" button isn't present, and there are no edit or status-change buttons.

## 5. Assets — `/app/asset/list`

- **View** the list + stat cards (containers and tanks) + filters + **export**.
- **View details** of any asset (including "in a project" + the project name when it is in one).
- **Assets capacities** (`/app/asset-category/list`): view and export only — no create, no edit. The eye icon opens a **details page** (client add-on) with a table of the assets carrying that capacity.
- **Not allowed:** any create, edit, or status change.

## 6. Intermediate facilities — `/app/facility/list`

- **View** the list (with each facility's **ID**) + stat cards + search + filters + **export**, and the **details** of any facility (including the contract attachment download).
- **Not allowed:** the "Create new facility" button, the edit icon and the status card aren't shown, and any create / edit / status change returns 403.

## 7. Services — `/app/main-service/list` and `/app/sub-service/list`

- **View** the main services list (search + type and status filters) + **export**, the sub-services accordion page, and the details of any main service or sub-service.
- **Not allowed:** creating, editing or deactivating — the create buttons, edit icons and the Edit button are not shown, and any attempt through the API returns 403.

## 8. Contractors — `/app/contractor/list`

- **View** the list (with the four statistic cards) + search + filter + **export**, and the **details** of any contractor (and download its attachments).
- The driver, vehicle and asset lists and details show the contractor's name in the affiliation column, and filter by it.
- **Not allowed:** creating, editing or deactivating (the "Add" link is not shown in the sidebar, and the edit icon / button are hidden).

## 9. Client companies — `/app/company/list`

- **View** the list + filter + **export**, and the **details** of any company (and download its attachments).
- **Not allowed:** creating, editing or deactivating (the "Add" link is not shown in the sidebar).

## 10. Client projects — `/app/project/list`

- **View** the list (search + company and status filters) + **export**, and the **details** of any project (and its assets table, with export).
- **Not allowed:** creating, editing or deactivating; the "Add" link, the edit icon and the "Insert asset into project" button are not shown.

## 11. Purchase orders — `/app/po/list`

- **View** the list (cards, search, company / project / time-range / items-status / account-status filters), the **details** of any purchase order (its services and the bars of what is left of each part) and **export** (Excel/PDF), and download its attachment. The company and project pages also show a **Contracts** section.
- **Not allowed:** creating, editing, deactivating; the "Add" link and the edit icon are not shown.

## 12. Trips — `/app/trip/list`

Sidebar: **Trips** → List.

- **List:** five counters (awaiting client approval / approved / rejected / completed / cancelled), a server-side table (newest first) with the trip number, date, driver and vehicle with their affiliation, client, project, sub-service, status and PO link; filters (time range, status, client → project, driver, vehicle, affiliations, main → sub-service), search, and **Excel / PDF export** that matches the screen.
- **Details:** the full trip data and its history, with **no action buttons** (view only).
- **Trip documents:** a completed trip shows its Delivery Note, Waste Manifest (not for supply trips) and the combined PDF.

## 13. Notifications — `/app/notifications`

The **bell** in the top bar shows a dot when something is unread and lists the **latest five** notifications (type, sentence, date and time), each linking to the trip (or contract); "Mark all as read" and "View all notifications" are in its menu. The **Notifications page** has a table of all your own notifications (newest first) with a filter by type, a time range and a search box (trip code, contract number, type). They are ordinary notifications, not instant ones — they appear when the page loads or the bell opens. You only ever see your own, and never the person who caused the change.

The Auditor is not a recipient of notices (the FRD names none), but the bell and the page are there and show an empty state.

## 14. Reports — `/app/reports`

Sidebar: **Reports**. Nine filters — time range (today, this week, this month, this quarter, this year, or a custom range), client company, project, sub-service, trip approval status, vehicle, driver, subcontractor, recycling facility — drive everything on the page together: the figures (trips, waste in tons and m³, vehicles used, drivers), the charts (trips over the period, trips by status, sub-services by trips and tons) and the **Trips Summary** table with its **Excel** export (No., trip No., client company, project, date, time, delivery note No., waste manifest No., sub-service, container capacity, trip status). The delivery note / manifest numbers are the generated documents' numbers, shown as soon as the trip is completed. A cancelled trip is a row of the summary and counts in the status chart only.

## 15. What you cannot do (returns 403)

- Create, edit, or deactivate any user / driver / vehicle / asset / facility / service / company / project / category, or insert an asset into a project.
- Change your name or email on the profile page.
- Manage roles & permissions / tenants.

## 16. Features available now

Full view + export across every built module (Users, Drivers, Vehicles, Assets, Intermediate facilities, Main & sub-services, Contractors, Client companies, Client projects, Purchase orders, and asset capacities) — was limited to vehicles and assets only before the FRD V01.14 upgrade. Self-service password change works from the profile page.
