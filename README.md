# WordSec Hide Admin

Plugin WordPress siap instal untuk:

- Menyembunyikan endpoint login default (`wp-login.php`).
- Mengganti URL login menjadi slug kustom (mis. `/inibukanlogin`).
- Mengatur redirect ketika ada akses langsung ke `/wp-admin` oleh user yang belum login.

## Instalasi
1. Kompres folder plugin ini menjadi ZIP (atau clone repo ini).
2. Di dashboard WordPress buka **Plugins > Add New > Upload Plugin**.
3. Upload ZIP lalu **Activate**.

## Penggunaan
1. Buka menu **Settings > WordSec Hide Admin**.
2. Atur:
   - **Aktifkan Fitur Hide Admin** (checkbox ON/OFF).
   - **Login Slug Baru** (contoh `inibukanlogin`).
   - **Mode Redirect wp-admin**:
     - Arahkan ke `/404`.
     - Atau arahkan ke URL custom.
3. Klik **Simpan Pengaturan**.

Setelah disimpan, endpoint login menjadi:

`https://domainanda.com/{login-slug}`

Contoh:

`https://domainanda.com/inibukanlogin`

## Catatan
- Simpan URL login baru Anda agar tidak kehilangan akses.
- Jika fitur dimatikan, login kembali menggunakan URL default WordPress.
- Endpoint `wp-admin/admin-ajax.php` dan `wp-admin/admin-post.php` tetap diizinkan untuk menjaga kompatibilitas fitur front-end.
- Plugin ini fokus fase awal: hide login + redirect proteksi `wp-admin`.
