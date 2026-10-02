<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once "config/database.php";


$admins = [

    [
        "Technical Admin",
        "technical@uniflow.local",
        "123456",
        "technical"
    ],

    [
        "Administrative Admin",
        "administrative@uniflow.local",
        "123456",
        "administrative"
    ],

    [
        "Proctorial Admin",
        "proctorial@uniflow.local",
        "123456",
        "proctorial"
    ],

];


foreach ($admins as $admin) {

    $name = $admin[0];
    $email = $admin[1];
    $plain_password = $admin[2];
    $role = $admin[3];


    $check = $conn->prepare(
        "SELECT admin_id
         FROM admins
         WHERE email = ?"
    );

    $check->bind_param(
        "s",
        $email
    );

    $check->execute();

    $result = $check->get_result();


    $password_hash =
        password_hash(
            $plain_password,
            PASSWORD_DEFAULT
        );


    if ($result->num_rows > 0) {

        $stmt = $conn->prepare(
            "UPDATE admins
             SET name = ?,
                 password = ?,
                 role = ?
             WHERE email = ?"
        );

        $stmt->bind_param(
            "ssss",
            $name,
            $password_hash,
            $role,
            $email
        );

        $stmt->execute();

        echo
        "Updated: " .
        $email .
        "<br>";

    } else {

        $stmt = $conn->prepare(
            "INSERT INTO admins
            (name, email, password, role)
            VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssss",
            $name,
            $email,
            $password_hash,
            $role
        );

        $stmt->execute();

        echo
        "Created: " .
        $email .
        "<br>";
    }
}

?>

<hr>

<h2>UniFlow Admin Setup Completed</h2>

<p>
You can now login using these accounts.
</p>

<table border="1" cellpadding="10">

<tr>
<th>Department</th>
<th>Email</th>
<th>Password</th>
</tr>

<tr>
<td>Technical</td>
<td>technical@uniflow.local</td>
<td>123456</td>
</tr>

<tr>
<td>Administrative</td>
<td>administrative@uniflow.local</td>
<td>123456</td>
</tr>

<tr>
<td>Proctorial</td>
<td>proctorial@uniflow.local</td>
<td>123456</td>
</tr>

</table>

<p>
For security, delete setup_admins.php after setup.
</p>