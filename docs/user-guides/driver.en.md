# User Guide — Driver

> Arabic version: [`driver.ar.md`](driver.ar.md) · Last updated: end of Week 3

A driver account is created by the System Admin (from the Drivers page) and includes residence, licenses, insurance, entry permits, and a default vehicle.

---

## Current state on the web

The driver **does not use the admin panel** — their mobile app comes in a later phase. If they sign in at `/login` today:

- **Sidebar shows Dashboard and Trips.** (Users / Vehicles / Drivers / Assets and the whole template scaffold are **hidden**.)
- If they try to open any other page manually (e.g. `/app/user/list`) it returns 403 and the table shows "You don't have permission to view this data."
- They **can use the Profile** (My Profile): change their profile photo **and their own password** (the "Security" tab). Name and email are read-only and are changed by the System Admin or Data Entry.
- They can read the roles list (reference data) — with no practical effect for them.

## Trips — `/app/driver-trip/list`

Sidebar: **Trips** → List + Add.

- **My trips:** only your own trips, newest first, with a status filter and search. The details page has no costs, PO number or history.
- **Create a trip (`/app/driver-trip/add`):** the form takes a **live location and a live photo only** — no gallery and no typed location. The *Create* button stays disabled until both are captured (the browser asks for camera and location permission; HTTPS is required). Your default vehicle is picked for you; the lists offer only what fits it. The trip starts as *awaiting client approval*, with a number like `ALN-T0001`, and the PO quota is deducted automatically.
- **Recycle receipt photo:** add or replace it on the trip details until the trip is completed.
- You can't edit a trip after creating it, and there is no export.

## Notifications — `/app/notifications`

The **bell** in the top bar shows a dot when something is unread and lists the **latest five** notifications (type, sentence, date and time), each linking to the trip (or contract); "Mark all as read" and "View all notifications" are in its menu. The **Notifications page** has a table of all your own notifications (newest first) with a filter by type, a time range and a search box (trip code, contract number, type). They are ordinary notifications, not instant ones — they appear when the page loads or the bell opens. You only ever see your own, and never the person who caused the change.

The driver is not a recipient of notices; the bell and the page show an empty state.

## What they cannot do

- Any access to vehicle, driver, asset, or user data.
- Edit their own driver data (residence/licenses/insurance/default vehicle) — that is edited by the System Admin only, from the driver-edit page.

## Features available now (Week 7)

| Available | Deferred |
|---|---|
| Sign in + shell dashboard + Profile (photo + password) + Trips (my trips, create with live location + photo, recycle receipt photo) | A native mobile app (the web pages are mobile-first) |
