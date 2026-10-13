PickDash patch files
====================

These are replacement files for the existing PickDash repository, not a full copy of the repository.

Files:
- Dockerfile
- lib/Database.php
- public/data.php
- public/api.php
- sql/schema.sql

Install by copying each file to the same path in your existing repository, then inspect the diff and commit/push. Do not overwrite your whole repository with this patch folder.

Render setup:
- Create a Render PostgreSQL database.
- Set DATABASE_URL on the PickDash Web Service to the database's Internal Database URL.
- Set DASH_API_KEY to a strong random secret on the Web Service and on the bot(s) that send data.
- Do not use the old default key.
- Runtime schema setup creates missing tables and adds missing columns; it does not drop existing tables/data.

Important:
- Keep a persistent Render PostgreSQL database; SQLite in a container filesystem is not durable across rebuilds/redeploys unless backed by a persistent disk.
- Check Render logs after deploy.
