# Agent instructions

The working rules for this repo live in **[CLAUDE.md](CLAUDE.md)** — they are
tool-agnostic. Read that file before making changes.

The short version: this is a single-repo, multi-tenant clinic platform. A new
clinic is a new `.env` and a new database, never a new branch. Nothing
install-specific (clinic name, domain, logo, template names, CORS origins) may
be hardcoded — it belongs in `config/tenant.php` or the environment.

`GEMINI.md` describes the original WhatsApp platform this was forked from and
is background, not current guidance.
