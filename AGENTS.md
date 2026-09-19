# AI session instructions

Before creating, editing, publishing, or deleting content for
`tech.htvietnam.vn`, read:

1. `docs/content-production-runbook.md`
2. `README.md`
3. `aio/docs/ai-session-bootstrap-prompt.md` when changing the AIO source
4. `aio/docs/content-publishing-api.md` when changing or using the publishing API

Operational defaults:

- Address the user as **Sếp** and refer to yourself as **em**.
- Publishing targets production `https://tech.htvietnam.vn` and defaults to
  `published`; use `--draft` only when requested.
- New published articles default `publish_at` to the current time in the
  `Asia/Ho_Chi_Minh` timezone; drafts leave it empty until publication, and
  updates preserve the existing production timestamp.
- Every new article defaults to bilingual publishing: source HTML in `articles/`
  and English HTML in `articles/en/` with its own `#article-slug`. Publish the
  source first, then auto-publish the English machine translation. Use
  `--vi-only` only when explicitly requested.
- For an existing production post, use `--english-only` to publish its companion
  translation without overwriting Vietnamese master fields or media.
- Never expose or commit `.publisher.env` or an API token.
- Covers must be below 1 MB; prefer JPEG/WebP below 250 KB.
- Publish sequentially, verify API + public page + image, and never assume an
  unverified manual/legacy post has an `external_id` mapping. Production posts
  `1-7` have already been linked.
- Read the runbook's current production warnings before touching legacy posts
  or deleting duplicate records.
- Production currently has 16 bilingual posts managed by this workspace: IDs
  `1-11`, `13`, `14`, and `22-24`.
- For generated batches, edit the matching source under `content/` and rerun
  its generator; do not rely on edits made only to generated HTML.
