# Migration Standard

Migration dosyalari su formatla isimlendirilmelidir:

- `YYYYMMDDHHMMSS_create_<table>_table.sql`
- `YYYYMMDDHHMMSS_add_<column>_to_<table>_table.sql`

Ornek:

- `20260517120000_create_users_table.sql`
- `20260517120500_create_jobs_table.sql`

Temel tablolar:

- `users`
- `settings`
- `jobs`
- `job_logs`
- `audit_logs`
- `sites`
- `domains`

