<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . "/../config/config.php";

$role = $_GET['role'] ?? '';

// validasi role
if ($role !== 'buyer' && $role !== 'seller') {
    header("Location: role.php");
    exit;
}

// ambil error & old input dari session
$error = $_SESSION['error'] ?? '';
$old_email = $_SESSION['old_email'] ?? '';

unset($_SESSION['error']);
unset($_SESSION['old_email']);

if (isset($_POST['submit'])) {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // simpan email biar ga hilang
    $_SESSION['old_email'] = $email;

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Email dan password wajib diisi";
        header("Location: login.php?role=$role");
        exit;
    }

    $email = mysqli_real_escape_string($conn, $email);

    // ================= BUYER =================
    if ($role == 'buyer') {

        $query = mysqli_query($conn, "
            SELECT * FROM users WHERE email='$email' LIMIT 1
        ");

        if (!$query || mysqli_num_rows($query) == 0) {
            $_SESSION['error'] = "User belum terdaftar";
            header("Location: login.php?role=$role");
            exit;
        }

        $user = mysqli_fetch_assoc($query);

        if (!password_verify($password, $user['password'])) {
            $_SESSION['error'] = "Wrong password";
            header("Location: login.php?role=$role");
            exit;
        }

        // sukses login
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = 'buyer';
    }

    // ================= SELLER =================
    else {

        $query = mysqli_query($conn, "
            SELECT * FROM sellers WHERE email='$email' LIMIT 1
        ");

        if (!$query || mysqli_num_rows($query) == 0) {
            $_SESSION['error'] = "Seller belum terdaftar";
            header("Location: login.php?role=$role");
            exit;
        }

        $seller = mysqli_fetch_assoc($query);

        if (!password_verify($password, $seller['password'])) {
            $_SESSION['error'] = "Wrong password";
            header("Location: login.php?role=$role");
            exit;
        }

        // sukses login
        $_SESSION['user_id'] = $seller['seller_id'];
        $_SESSION['user_name'] = $seller['name'];
        $_SESSION['role'] = 'seller';
    }

    if ($_SESSION['role'] == 'seller') {
        header("Location: ../pages/seller_dashboard.php");
    } else {
        header("Location: ../pages/index.php");
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
<a href="role.php" style="text-decoration:none;">
    <button>⬅ Ganti Role</button>
</a>
<h2><?php echo $role == 'buyer' ? 'Login Pembeli' : 'Login Penjual'; ?></h2>

<!-- ERROR MESSAGE -->
<?php if (!empty($error)) { ?>
    <div style="color:red; background:#ffe0e0; padding:10px; margin-bottom:10px;">
        <?php echo $error; ?>
    </div>
<?php } ?>

<form method="POST">

    Email:
    <input type="email" name="email"
    value="<?php echo htmlspecialchars($old_email); ?>"
    required><br><br>

    Password:
    <input type="password" name="password" required><br><br>

    <button type="submit" name="submit">Masuk</button>

</form>

<br>
Belum punya akun?
<a href="register.php?role=<?php echo $role; ?>">Daftar</a>

</body>
</html>