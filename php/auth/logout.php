<?php
session_start();

// Clear sessions
unset($_SESSION['admin'], $_SESSION['user']);
session_destroy();

// Clear frontend auth flag and redirect
echo "<script>
    localStorage.removeItem('auth.loggedIn');
    window.location.href = '/project/Capstone-Car-Service-Draft4/website/home/home.html';
</script>";

// Fallback server-side redirect
header('Location: /project/Capstone-Car-Service-Draft4/website/home/home.html');
exit();
