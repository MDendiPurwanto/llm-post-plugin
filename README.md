# WordPress Plugin: LLM Posts

Generate SEO-friendly WordPress posts via an LLM using native PHP and the WordPress HTTP API. Provides an admin UI for configuring API key/model and a simple form to generate posts directly.

## Features
- Simple, native PHP plugin (no Composer/JS). 
- Settings page for API key, model, temperature, max tokens, and default post status.
- Generate page to create a full post from a title/topic/outline.
- Bulk Generate page to create multiple posts from a list of keywords/topics (one per line).
- Uses WordPress HTTP API (`wp_remote_post`) to call OpenAI-compatible chat completions.
- Supports OpenRouter API (default) so you can use free models.
- Chat Edit page to modify draft posts:
  - Edit Selection: rewrite an exact fragment via chat.
  - Insert Near Anchor: generate a new section and insert before/after an anchor.

## Installation
1. Copy this folder into `wp-content/plugins/` (e.g., `wp-content/plugins/llm-posts`).
2. In WP Admin → Plugins, activate “LLM Posts (Native PHP)”.

## Configuration
1. Go to WP Admin → LLM Posts → Settings.
2. Enter your API key (OpenRouter or OpenAI) and preferred model.
3. Adjust temperature, max tokens, and default post status as needed.

## Usage
1. Go to WP Admin → LLM Posts → Generate.
2. Enter a Title (optional), Topic/Keywords, pick Language, and optionally add an Outline.
3. Click “Generate & Create Post”. The plugin will create a post (default Draft) and link to edit it.

### Chat Edit (Draft)
1. Go to WP Admin → LLM Posts → Chat Edit.
2. Pilih draft post.
3. Mode “Edit Selection”: paste potongan teks/HTML yang persis ada di konten dan berikan instruksi (mis. perbaiki grammar, membuat lebih ringkas, ubah jadi bullet list). Plugin akan mengganti fragment tersebut dengan hasil LLM.
4. Mode “Insert Near Anchor”: masukkan teks jangkar (mis. judul H2) dan pilih before/after, lalu beri instruksi (mis. tambahkan subbagian 2 paragraf + daftar). Plugin akan menyisipkan hasil LLM di lokasi tersebut atau di akhir jika jangkar tidak ditemukan.

## Notes
- API Base URL defaults to OpenRouter: `https://openrouter.ai/api/v1`.
- Set a free model on OpenRouter, for example:
  - `meta-llama/llama-3.1-8b-instruct:free`
  - `google/gemma-2-9b-it:free`
- For OpenRouter, you can set optional headers in Settings:
  - `HTTP-Referer`: your site URL (recommended by OpenRouter)
  - `X-Title`: your app name for their dashboard
- If using OpenAI, change API Base to `https://api.openai.com/v1` and set a compatible model (e.g., `gpt-4o-mini`).
- The plugin stores settings in the WordPress options table.

## Development
- Main plugin code lives in `llm-plugin.php`.
- Keep changes minimal and within WordPress best practices (nonces, capability checks, sanitization).

## Contributing
- Lihat panduan kontribusi: `CONTRIBUTING.md`
- Ikuti Kode Etik: `CODE_OF_CONDUCT.md`
- PR baru akan menjalankan lint dasar (PHP syntax) via GitHub Actions.

## License
MIT
### Bulk Generate
1. Go to WP Admin → LLM Posts → Bulk Generate.
2. Masukkan daftar topik/keyword (satu per baris), pilih bahasa, dan (opsional) outline yang diterapkan ke semua.
3. Klik “Generate Bulk”. Plugin akan membuat beberapa post (default Draft) dan menampilkan tautan Edit.
