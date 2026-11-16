// theme.js
// Dimuat di <head> (tanpa defer) supaya tema terpasang sebelum halaman digambar,
// jadi gak ada kedip putih/gelap saat pindah halaman.
(function () {
    var KEY = "bl-theme";

    function tersimpan() {
        try { return localStorage.getItem(KEY); } catch (e) { return null; }
    }

    function simpan(tema) {
        try { localStorage.setItem(KEY, tema); } catch (e) { /* abaikan */ }
    }

    function sistemGelap() {
        return !!(window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches);
    }

    function terapkan(tema) {
        document.documentElement.setAttribute("data-theme", tema);
    }

    var awal = tersimpan();
    if (awal !== "light" && awal !== "dark") {
        awal = sistemGelap() ? "dark" : "light";
    }
    terapkan(awal);

    document.addEventListener("DOMContentLoaded", function () {
        var tombol = document.querySelectorAll(".theme-toggle");
        function labeli() {
            var gelap = document.documentElement.getAttribute("data-theme") === "dark";
            tombol.forEach(function (b) {
                b.setAttribute("aria-label", gelap ? "Ganti ke tema terang" : "Ganti ke tema gelap");
                b.setAttribute("title", gelap ? "Tema terang" : "Tema gelap");
            });
        }
        labeli();
        tombol.forEach(function (b) {
            b.addEventListener("click", function () {
                var gelap = document.documentElement.getAttribute("data-theme") === "dark";
                var baru = gelap ? "light" : "dark";
                terapkan(baru);
                simpan(baru);
                labeli();
            });
        });
    });
})();
