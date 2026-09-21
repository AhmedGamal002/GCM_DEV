# User Guide — System Admin

> Arabic version: [`system-admin.ar.md`](system-admin.ar.md) · Last updated: end of Week 3

The System Admin has full control **within a single company** (Tenant). They cannot manage the product itself or other companies (that is the separate Super Admin role).

**Hard rule:** there is **exactly one System Admin per company**. The system refuses to create a second one, and always refuses to deactivate a System Admin account (even by themselves).

---

## 1. Sign in

- URL: `/login`
- Email + password.
- There is no password change from the Profile for GCM staff and drivers (per the FRD) — if you forget yours use **Forgot Password** on the login page. The email is **immutable** after creation.

## 2. Dashboard

- Shown right after login; displays your name, role, and company name clearly ("System Admin — GCM").
- **Currently a shell only** — detailed stats arrive in Week 8.

## 3. Profile

- Account menu (top bar) → **My Profile**.
- **Change your profile photo only.** Name and email are read-only, and there is no "Security" page for changing your password (that is for client/contractor users, Week 4-5).

## 4. User management — `/app/user/list`

The only screen exclusive to the System Admin among tenant roles.

### Users list
- 7-column table: code / user / affiliation / entity / role / status / actions.
- **Pagination, sorting, and search are all server-side** — performs well even with thousands of users.
- Search matches by name, email, or code.
- Filters: role (data_entry / auditor / driver) + status (active / on vacation / deactivated).
- **Export** button (Excel / PDF).

### "Add User" button — dropdown
- **GCM Staff (Data Entry / Auditor)** → standard user-creation form.
- **Driver** → sends you to the dedicated driver-creation page (`/app/driver/add`) — a different form.
- *(Client and Contractor options land in Week 4–5.)*

### Create GCM user
- Fields: name, email, phone, photo (optional), password, status, role (data_entry or auditor only — not system_admin or driver), additional data (optional).
- **You cannot create a second System Admin** — the option isn't offered, and a direct request is rejected.

### View / edit user
- Separate details and edit pages.
- **New password (optional):** on the edit page you can set a new password + confirmation for any user or driver — leave it blank to keep the current one. This is the only way to change a GCM staff member's or driver's password.
- If the user is a driver, the edit page shows a notice pointing you to the driver-edit page (residence/license/insurance details are edited there).
- **Empty "Additional Data" does not render** as an empty heading on the details page.

### Change user status
- Active / on vacation / deactivated — from the details page.
- **Deactivating a System Admin is always blocked.**

## 5. Vehicle management — `/app/vehicle/list`

The System Admin has **full access**: view, create, edit, export, and all status changes (including deactivate and reactivate-from-deactivated).

### List
- Stat cards: available / on a trip (0 for now — arrives with the Trip module) / on maintenance / deactivated, plus per-category cards (5 categories).
- Full server-side table: a vehicle's identity is its **plate** (no `code`). Search finds it even if you type the full plate with a space (`AAA 1234`).
- Filters: category / operational status / affiliation. Export button (Excel / PDF).
- **Add Vehicle** button.

### Create / edit vehicle
- Basics: plate (letters + digits, digits only), category, affiliation, whether it has an embedded container (if yes: its type + capacity — the list filters by what is available in the Asset Pool).
- Photos (front/back) + documents (registration card / fitness / inspection certificate / insurance) with numbers and expiry dates.
- Repeatable entry permits (area / number / expiry / attachment).
- **All document and permit numbers are digits only.**

### Vehicle status
- **System Admin**: "on maintenance" + **deactivate** + **reactivate from deactivated**.

## 6. Driver management — `/app/driver/list`

System Admin only. **Drivers are created and edited exclusively here** — not from the user form.

### List
- 4 stat cards: available / on trips (0 for now) / on vacation / deactivated.
- 5-column table: code / driver name / affiliation / driver availability / details.
- Filters: affiliation (GCM / contractor) + driver availability. Export button.
- **Add new driver user** button.

### Create driver (single page, creates everything at once)
1. **Account details:** name, email, phone, photo, password, status, additional data.
2. **Default vehicle:** pick the vehicle type(s) the driver is qualified to drive (from 5 categories) + the default vehicle itself (the list filters by the selected types; the vehicle must belong to one of them).
   - **One vehicle per one driver** as a default — vehicles already taken don't appear in the list at all.
3. **Residence / driving license / operational license / insurance:** number + expiry + attachment for each.
4. **Truck entry permits:** repeatable (area / number / expiry / attachment) — via "Add Permit".

> **Driver documents (residence/licenses/insurance/permits) are stored privately** — served only through a protected link, no public URL.

### View / edit driver
- Details page with a card per document + "Default Vehicle".
- Edit page with the same sections.

## 7. Asset management — `/app/asset/list`

System Admin: view, create, edit (full data), export, and all status changes.

### List
- **Separate stat cards for containers and tanks**: available / in projects (0 for now) / on maintenance / deactivated.
- Server-side table. Filters: type (container / tank) + status + affiliation. Export button.
- Two items under "Assets" in the sidebar: **List** + **Categories**.

### Create asset
- Name, type (container / tank), capacity category (filtered by type), compatible vehicle categories (checkboxes, per-asset), status, affiliation, purchase date, additional data.

### Edit asset
- **Name only** is editable — capacity and category are locked after creation (per FRD: they affect contracts and trips).

### Asset capacity categories — `/app/asset-category/list`
- Create and edit. After creation, **name only** is editable (capacity and kind locked). No delete, no deactivate.

## 8. Change asset status
- **System Admin**: "on maintenance" + **deactivate** + **reactivate from deactivated**.

## 9. Features available now (end of Week 3)

| Module | Available | Deferred |
|---|---|---|
| Users | Full CRUD + status + export | "Client"/"Contractor" options in the Add button (Week 4–5) |
| Vehicles | Full CRUD + status + export + embedded container | "on a trip" column/counter + vehicle trip log (Week 7) |
| Drivers | Full CRUD + default vehicle + documents + permits | "on a trip" status (Week 7), mobile app |
| Assets | Full CRUD + categories + status + export | "Add asset to a project" + "in projects" counter (Week 4) |
| Dashboard | Shell screen | Real stats (Week 8) |
