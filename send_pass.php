<?php

// ...existing code for sending pass (email/SMS/etc.)...

// After sending is successful:
echo "<div class='alert alert-success text-center mt-4'>Sent successfully!</div>";
header("refresh:2;url=view_pass.php");
exit;