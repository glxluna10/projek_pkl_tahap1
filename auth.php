<?php
// auth.php
// Panggil file ini di paling atas tiap halaman yang wajib login.
session_start();

if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit;
}