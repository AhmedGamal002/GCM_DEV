# User Guide — Driver

> Arabic version: [`driver.ar.md`](driver.ar.md) · Last updated: end of Week 3

A driver account is created by the System Admin (from the Drivers page) and includes residence, licenses, insurance, entry permits, and a default vehicle.

---

## Current state on the web

The driver **does not use the admin panel** — their mobile app comes in a later phase. If they sign in at `/login` today:

- **Sidebar shows a single item — Dashboard.** (Users / Vehicles / Drivers / Assets and the whole template scaffold are **hidden**.)
- If they try to open any other page manually (e.g. `/app/user/list`) it returns 403 and the table shows "You don't have permission to view this data."
- They **can use the Profile** (My Profile): change their profile photo **and their own password** (the "Security" tab). Name and email are read-only and are changed by the System Admin or Data Entry.
- They can read the roles list (reference data) — with no practical effect for them.

## What they cannot do

- Any access to vehicle, driver, asset, or user data.
- Edit their own driver data (residence/licenses/insurance/default vehicle) — that is edited by the System Admin only, from the driver-edit page.

## Features available now (end of Week 3)

| Available | Deferred |
|---|---|
| Sign in + shell dashboard + Profile (photo + password) | The full mobile app (creating trips, uploading container photos, ...) — Week 7+ |
