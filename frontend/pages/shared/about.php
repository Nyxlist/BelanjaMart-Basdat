<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
layout('header', ['title' => 'About']);
?>
<div class="container-narrow">
    <div class="card">
        <h2>About BelanjaMart</h2>
        <p class="text-muted">A marketplace project showcasing modern PHP, MySQL and a layered architecture.</p>
        <h3 class="mt-2">Stack</h3>
        <ul>
            <li>PHP 8 + MySQL/MariaDB</li>
            <li>Custom mini-framework (no external dependencies)</li>
            <li>Vanilla JavaScript with CSS variables for dark/light theme</li>
            <li>JWT for the REST API + sessions for HTML pages</li>
        </ul>
        <h3 class="mt-2">Highlights</h3>
        <ul>
            <li>Order tracking with simulated escrow payments</li>
            <li>Real-time-ish chat using short polling</li>
            <li>Seller reputation, badges and recommendations</li>
            <li>Multi-currency catalog with regional tax / shipping</li>
            <li>Smart pricing tool with profit estimation</li>
        </ul>
        <p class="text-muted fs-13 mt-2">A personal project — feel free to explore and extend.</p>
    </div>
</div>
<?php layout('footer'); ?>
