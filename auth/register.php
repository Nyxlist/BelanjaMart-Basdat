<?php
session_start();
require_once __DIR__ . "/../config/config.php";

$role = $_GET['role'] ?? '';

// kalau role kosong → tampil pilihan
if ($role !== 'buyer' && $role !== 'seller') {
    echo "<h2>Pilih Role</h2>";
    echo "<a href='register.php?role=buyer'>Daftar sebagai Pembeli</a><br><br>";
    echo "<a href='register.php?role=seller'>Daftar sebagai Penjual</a>";
    exit;
}

$error = "";

if (isset($_POST['register'])) {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password_raw = trim($_POST['password']);

    if (empty($name) || empty($email) || empty($password_raw)) {
        $error = "Semua field wajib diisi";
    } else {

        $email = mysqli_real_escape_string($conn, $email);
        $password = password_hash($password_raw, PASSWORD_DEFAULT);

        if ($role == 'buyer') {

            $check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
            if (mysqli_num_rows($check) > 0) {
                $error = "Email sudah terdaftar";
            } else {
                mysqli_query($conn, "
                    INSERT INTO users (name, email, password)
                    VALUES ('$name', '$email', '$password')
                ");
                header("Location: login.php?role=buyer");
                exit;
            }

        } else {

            $check = mysqli_query($conn, "SELECT * FROM sellers WHERE email='$email'");
            if (mysqli_num_rows($check) > 0) {
                $error = "Email sudah terdaftar";
            } else {
                mysqli_query($conn, "
                    INSERT INTO sellers (name, email, password)
                    VALUES ('$name', '$email', '$password')
                ");
                header("Location: login.php?role=seller");
                exit;
            }
        }
    }
}
?>

<h2><?php echo $role == 'buyer' ? 'Register Pembeli' : 'Register Penjual'; ?></h2>

<?php if (!empty($error)) { ?>
    <div style="color:red;"><?php echo $error; ?></div>
<?php } ?>

<form method="POST">
    Nama: <input type="text" name="name" required><br><br>
    Email: <input type="email" name="email" required><br><br>
    Password: <input type="password" name="password" required><br><br>

    <button type="submit" name="register">Daftar</button>
</form>

<br>

Sudah punya akun?
<a href="login.php?role=<?php echo $role; ?>">Login</a>