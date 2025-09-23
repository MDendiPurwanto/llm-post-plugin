# Panduan Kontribusi

Terima kasih ingin berkontribusi ke proyek plugin WordPress ini! Panduan singkat ini membantu Anda memulai dengan cepat dan menjaga kualitas kontribusi tetap konsisten.

## Cara Memulai
- Fork repositori ini ke akun Anda, lalu clone fork tersebut.
- Siapkan lingkungan WordPress lokal (mis. menggunakan Local, DevKinsta, Docker, atau XAMPP).
- Tempatkan folder proyek di `wp-content/plugins/llm-post-plugin` (atau sesuai nama yang Anda inginkan).
- Aktifkan plugin melalui WP Admin → Plugins.

## Alur Kerja Branch
- Buat branch dari `main` untuk setiap perubahan:
  - Fitur: `feat/nama-fitur-singkat`
  - Perbaikan bug: `fix/bug-yang-diperbaiki`
  - Dokumentasi: `docs/perubahan-docs`

## Format Commit (Disarankan)
Gunakan gaya Conventional Commits agar riwayat lebih rapi:
- `feat: ...` untuk fitur baru
- `fix: ...` untuk perbaikan bug
- `docs: ...` untuk dokumentasi
- `refactor: ...` untuk refactor tanpa perubahan fitur
- `chore: ...` untuk tugas pendukung (CI, tooling, dsb.)
Contoh: `feat: add chat edit mode (insert before/after anchor)`

## Gaya Kode
- Ikuti praktik WordPress (nonces, capability checks, sanitization/escaping).
- Hindari dependensi berat; plugin ini menggunakan PHP native + WordPress API.
- Nama fungsi gunakan prefix `llmwp_` untuk menghindari konflik.
- Jaga perubahan tetap fokus dan minimal.

## Menjalankan Pengecekan Dasar
- Pastikan file PHP bebas dari syntax error: `php -l llm-plugin.php`.
- Jika menambah file PHP, cek masing-masing file dengan `php -l`.
- CI akan menjalankan lint dasar di pull request.

## Membuat Pull Request
- Pastikan PR Anda kecil, fokus, dan jelas scope-nya.
- Jelaskan konteks: tujuan, perubahan utama, dampak, dan cara uji.
- Sertakan tangkapan layar untuk perubahan UI (jika ada).
- Tautkan ke issue terkait (jika ada), gunakan kata kunci auto-close seperti `Closes #123`.
- Checklist sebelum kirim PR:
  - [ ] Lint PHP (syntax) lulus
  - [ ] Nonce/capability/sanitization untuk input/admin ada
  - [ ] Tidak mengubah perilaku yang tidak terkait
  - [ ] Dokumentasi/README diperbarui (jika perlu)

## Melaporkan Bug & Usulan Fitur
- Gunakan template issue yang tersedia (Bug Report / Feature Request).
- Sertakan langkah reproduksi yang jelas, lingkungan, dan log (jika ada).

## Lisensi
Dengan mengirim kontribusi, Anda setuju bahwa kontribusi Anda dirilis di bawah lisensi MIT dari repositori ini.

