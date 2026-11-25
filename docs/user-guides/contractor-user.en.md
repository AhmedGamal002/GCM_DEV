# User Guide — Contractor User

> Arabic version: [`contractor-user.ar.md`](contractor-user.ar.md) · Last updated: FRD V01.14 §6 (contractor user pages)

A Contractor User is an account of a **contractor company** (a subcontractor that supplies GCM with drivers, vehicles and containers). The role **only views and exports** — and only the records of **its own contractor**. It never creates, edits or changes the status of anything.

---

## What you see in the sidebar

Dashboard, **Users**, **Drivers**, **Vehicles**, **Assets** — each with its **List** only. There is no "Add", no Categories / Capacities, and nothing about other contractors, client companies, projects, facilities or services.

## 1. Sign in and profile

- `/login` with email + password.
- **My Profile**: change your profile photo and your password (current password first). No signature or stamp images for this role.

## 2. Users — `/app/user/list`

- **View** your contractor's **drivers** and your contractor's **other users** (your own account is not listed).
- Filter by role (Contractor User / Driver), by status, and search by name, email or ID.
- **Export** the list (Excel / PDF) — exactly the rows you see.
- Open an account's details; opening an account outside your contractor is refused.

## 3. Drivers — `/app/driver/list`

- **View** your contractor's drivers with the stat cards (available / on vacation / deactivated) and the **Documents Status** column.
- Open a driver's details: their data, documents (residence, licence, operating licence, insurance) and entry permits, with the time-left bars.
- **Export** the list (Excel / PDF).

## 4. Vehicles — `/app/vehicle/list`

- **View** your contractor's vehicles with the status and category cards, filters (category / status / documents status) and search.
- Open a vehicle's details: photos, documents, entry permits.
- **Export** the list (Excel / PDF).

## 5. Assets — `/app/asset/list`

- **View** your contractor's assets (containers and tanks) with the cards, filters (type / availability) and search.
- Open an asset's details (including the project it is in, when it is placed in one).
- **Export** the list (Excel / PDF).

---

## 6. Trips — `/app/contractor-trip/list`

Sidebar: **Trips** → List. **View and export only.**

- **Whose trips:** the trips whose **driver or vehicle** belongs to your contractor (the containers don't count). Another contractor's trip is a 404.
- **List:** five counters, a table with the trip number, date, driver and vehicle with their affiliation, client, project, sub-service, status and PO link (*linked / not linked* — no number), filters (time range, status, affiliations), search and **Excel / PDF export** of what's on screen.
- **Details:** the trip data as it is — **no costs, PO number, history or photos** — and, once the trip is completed, the **Delivery Note** only (no waste manifest or combined PDF).

## 7. Notifications — `/app/notifications`

The **bell** in the top bar shows a dot when something is unread and lists the **latest five** notifications (type, sentence, date and time), each linking to the trip (or contract); "Mark all as read" and "View all notifications" are in its menu. The **Notifications page** has a table of all your own notifications (newest first) with a filter by type, a time range and a search box (trip code, contract number, type). They are ordinary notifications, not instant ones — they appear when the page loads or the bell opens. You only ever see your own, and never the person who caused the change.

The notices you get — for trips whose driver or vehicle is your contractor's: a new trip awaiting the client's approval, a trip finally approved, and a cancelled trip. A notice opens the trip's page.

## What you cannot do

- Create, edit or change the status of any user, driver, vehicle or asset (there are no buttons, and the server refuses the request).
- See another contractor's records, GCM's own drivers / vehicles / assets, or any client account.
- Open settings, contractors, companies, projects, services or facilities.

## Features available now

| Feature | Available |
|---|:---:|
| Sign in + profile (photo, password) | ✅ |
| Users: your contractor's drivers + users (view / export) | ✅ |
| Drivers: view + documents + export | ✅ |
| Vehicles: view + documents + export | ✅ |
| Assets (containers / tanks): view + export | ✅ |
| Trips: table + trip data + delivery note (view / export) | ✅ |
| Notifications: bell + page for your contractor's trips | ✅ |
