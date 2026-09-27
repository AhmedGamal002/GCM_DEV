# User Guide — Super Admin (product administration)

> Arabic version: [`super-admin.ar.md`](super-admin.ar.md) · Last updated: end of Week 3

The Super Admin administers **the product itself** (not a single company). Their account has a **completely separate sign-in** and is stored in its own table — not the same as company users.

**Important:** the Super Admin session and a company-user session are fully independent in the browser — signing into one does not sign you out of the other.

---

## 1. Sign in

- URL: **`/platform/login`** (not the regular `/login`).
- All Super Admin pages live under `/platform/*` and are English-only (not bilingual like the company pages).

## 2. Product dashboard — `/platform/dashboard`

- A shell screen confirming the platform session is wired correctly.

## 3. Companies (Tenants) — `/platform/tenants`

- **List** of all registered companies.
- **Create a new company** (`/platform/tenants/create`): name + slug + status.
- **Edit** a company's data.
- **Change status** of a company (active / deactivated) — deactivating a company blocks all its users from signing in.
- **No hard delete** — companies are only deactivated.

## 4. Roles & Permissions — `/platform/roles` + `/platform/permissions`

- **This is the only place roles and permissions are managed** — even a company's System Admin cannot edit them; they only assign an existing role to a user.
- **Roles** (`/platform/roles`): create / edit / delete.
  - **Delete is restricted**: you cannot delete a role still attached to any user — a clear rejection is returned (422).
- **Permissions** (`/platform/permissions`): create / edit / delete.
  - Permissions are seeded as `{resource}.{action}` (e.g. `users.create`). They currently appear in the role-edit screen, but **wiring them to actual Gates is a future detail** — effective permission is currently determined by the role itself.

## 5. What you cannot do from here

- Any access to a specific company's data (its users, vehicles, ...) — the Super Admin manages companies as **entities**, not their contents.
- Opening any tenant `/app/*` page — it redirects to login because you have no `web`/tenant session.

## 6. Features available now (end of Week 3)

| Available | Deferred |
|---|---|
| Full CRUD for companies + their status · CRUD for roles (restricted delete) and permissions · dedicated sidebar (Dashboard / Tenants / Roles & Permissions) | Wiring permissions to actual Gates · product stats · a real dashboard |
