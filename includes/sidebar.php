<?php

include "../includes/header.php";
session_start();
$user = isset($_SESSION['Username']) ? $_SESSION['Username'] : 'Not set'; // Check if 'user_id'
$varadmin = isset($_SESSION['admin']) ? $_SESSION['admin'] : 'Not set';

$isAdmin = ($varadmin === 1);
$userRole = $isAdmin ? "Admin" : "User";



?>
<!-- Menubar -->
<div class="menubar">
    <div class="user-id">
        <!-- <h2 style="text-align: center; color: white;">E leave portal </h2> -->
    </div>
    <div class="user-id">
        <?= htmlspecialchars($userRole); ?> : <?= htmlspecialchars($user); ?>
    </div>
    <div class="user-id">
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<!-- Sidebar Toggle Button for Mobile (Positioned Outside Sidebar) -->
<div class="menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</div>

<!-- Sidebar Container -->
<div class="sidebar" id="sidebar">
      <!-- Sidebar Header with Logo -->
      <div class="sidebar-header">
        <img src="../images/logo.png" alt="Company Logo" class="sidebar-logo">
        <h2>E-Leave Portal</h2>
        <span class="close-btn" onclick="toggleSidebar()">&times;</span>
    </div>

    <ul class="sidebar-menu">
        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="apply_leave.php"><i class="fas fa-paper-plane"></i> Apply Leave</a></li>
        <li><a href="leave_history.php"><i class="fas fa-history"></i> Leave History</a></li>
        <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>



        <?php if ($userRole === "Admin"): ?>
            <li><a href="process_leave.php"><i class="fas fa-check-circle"></i> Approve/Reject Requests</a></li>
            <li><a href="leave_type.php"><i class="fas fa-list"></i> Leave Types</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>