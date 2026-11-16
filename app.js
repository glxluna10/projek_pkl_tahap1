// app.js
// Toggle show/hide untuk semua field password di halaman login & register.

document.querySelectorAll(".password-toggle").forEach(function (btn) {
    btn.addEventListener("click", function () {
        var targetId = btn.getAttribute("data-target");
        var input = document.getElementById(targetId);
        var iconOpen = btn.querySelector(".icon-eye-open");
        var iconOff = btn.querySelector(".icon-eye-off");

        if (input.type === "password") {
            input.type = "text";
            iconOpen.style.display = "none";
            iconOff.style.display = "block";
            btn.setAttribute("aria-label", "Sembunyikan password");
        } else {
            input.type = "password";
            iconOpen.style.display = "block";
            iconOff.style.display = "none";
            btn.setAttribute("aria-label", "Tampilkan password");
        }
    });
});