<?php
include '../includes/functions.php';
if(isset($_POST['addStaff']))
{
    $name=$_POST['officerName'];
    $email=$_POST['officerEmail'];
    $phone=$_POST['officerPhone'];
    $password=$_POST['officerPassword'];
    $area=$_POST['areaId'];
    $role=$_POST['roleId'];
   //echo $name,$email,$phone,$password,$area,$role;
    if(add_user($name, $email, $password, $role, $area,$phone))
    {
        header("Location: mail.php?email=$email");
    }
    else{
        ?>
        <script>
alert("Staff not added.")
            </script>
        <?php
    }
}
?>
