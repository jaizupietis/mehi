<?php
require_once 'config.php';

// Iznīcināt sesiju
session_unset();
session_destroy();

// Novirzīt uz pieslēgšanās lapu
redirect('login.php');
?>