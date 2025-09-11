<?php
// Test tanımlayan sorunu
require_once('../../../wp-config.php');

global $wpdb;
$actions_table = $wpdb->prefix . 'bkm_actions';

// Get table names
$users_table = $wpdb->users;
$usermeta_table = $wpdb->usermeta;

// Test query
$test_query = "SELECT a.*, 
              CASE 
                  WHEN TRIM(CONCAT(um1.meta_value, ' ', um2.meta_value)) != ''
                  THEN TRIM(CONCAT(um1.meta_value, ' ', um2.meta_value))
                  ELSE u.display_name
              END as tanımlayan_name,
              u.display_name as original_display_name,
              um1.meta_value as first_name,
              um2.meta_value as last_name
       FROM $actions_table a
       LEFT JOIN $users_table u ON a.tanımlayan_id = u.ID
       LEFT JOIN $usermeta_table um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
       LEFT JOIN $usermeta_table um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
       LIMIT 5";

$results = $wpdb->get_results($test_query);

echo "<h2>Test Results for Tanımlayan Field:</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>tanımlayan_id</th><th>tanımlayan_name</th><th>original_display_name</th><th>first_name</th><th>last_name</th></tr>";

foreach($results as $row) {
    echo "<tr>";
    echo "<td>" . $row->id . "</td>";
    echo "<td>" . $row->tanımlayan_id . "</td>";
    echo "<td>" . ($row->tanımlayan_name ?? 'NULL') . "</td>";
    echo "<td>" . ($row->original_display_name ?? 'NULL') . "</td>";
    echo "<td>" . ($row->first_name ?? 'NULL') . "</td>";
    echo "<td>" . ($row->last_name ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Users table check
echo "<h2>Users Table Check:</h2>";
$users_check = $wpdb->get_results("SELECT ID, user_login, display_name FROM " . $wpdb->users . " LIMIT 5");
echo "<table border='1'>";
echo "<tr><th>ID</th><th>user_login</th><th>display_name</th></tr>";
foreach($users_check as $user) {
    echo "<tr><td>" . $user->ID . "</td><td>" . $user->user_login . "</td><td>" . $user->display_name . "</td></tr>";
}
echo "</table>";

// Actions table check
echo "<h2>Actions Table Check:</h2>";
$actions_check = $wpdb->get_results("SELECT id, tanımlayan_id FROM $actions_table LIMIT 5");
echo "<table border='1'>";
echo "<tr><th>ID</th><th>tanımlayan_id</th></tr>";
foreach($actions_check as $action) {
    echo "<tr><td>" . $action->id . "</td><td>" . $action->tanımlayan_id . "</td></tr>";
}
echo "</table>";
?>
