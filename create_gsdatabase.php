<?php
// Include your database configuration file
require_once 'conf.php';

// --- Configuration ---
$sourceTable = 'stationq1'; // The table to copy the structure from
$newTableName = 'GSDatabase'; // The name for the new table

echo "Attempting to create table '$newTableName' based on '$sourceTable'...<br>";

try {
    // Connect to the database using PDO
    $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Get the SQL statement that defines the structure of the source table
    $stmt = $pdo->query("SHOW CREATE TABLE `$sourceTable`");
    $tableStructureResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tableStructureResult) {
        throw new Exception("Could not retrieve the structure for table '$sourceTable'. Please ensure it exists.");
    }

    $createStatement = $tableStructureResult['Create Table'];

    // 2. Modify the statement to create the new table with the new name
    // This replaces the old table name with the new one in the "CREATE TABLE" command
    $newCreateStatement = preg_replace(
        "/CREATE TABLE `$sourceTable`/", 
        "CREATE TABLE `$newTableName`", 
        $createStatement
    );

    // 3. Execute the modified SQL statement to create the new table
    $pdo->exec($newCreateStatement);

    echo "<p style='color:green; font-weight:bold;'>Success! Table '$newTableName' was created with the same structure as '$sourceTable'.</p>";

} catch (PDOException $e) {
    // Handle any database errors
    if ($e->errorInfo[1] == 1050) {
        // Error code 1050 means "Table already exists"
        echo "<p style='color:orange; font-weight:bold;'>Warning: Table '$newTableName' already exists. No action was taken.</p>";
    } else {
        echo "<p style='color:red; font-weight:bold;'>Database Error: " . $e->getMessage() . "</p>";
    }
} catch (Exception $e) {
    // Handle other general errors
    echo "<p style='color:red; font-weight:bold;'>An error occurred: " . $e->getMessage() . "</p>";
}
?>
