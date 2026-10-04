<?php
// GET ?period=this-month | last-month | last-3-months  ->  { kpis: {...}, staff: [...] }
require __DIR__ . '/../staff-common.php';
staff_api_start();
if (!can('staff_reports')) staff_forbidden('see performance reports');

$period = $_GET['period'] ?? 'this-month';
if (!in_array($period, ['this-month', 'last-month', 'last-3-months'], true)) $period = 'this-month';
json_response(staff_report($period));
